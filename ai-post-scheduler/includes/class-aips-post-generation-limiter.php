<?php
/**
 * Post Generation Limiter
 *
 * Enforces safety rate limits and ceiling controls on post generation across
 * hourly, daily, weekly, and monthly time horizons using native WordPress
 * WP_Query date queries against generated post meta, with transient caching.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Generation_Limiter
 */
class AIPS_Post_Generation_Limiter {

	/**
	 * Transient key prefix for count caches.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'aips_pgen_cnt_';

	/**
	 * Transient key for the 4-hour notification cooldown.
	 *
	 * @var string
	 */
	const NOTIF_COOLDOWN_TRANSIENT = 'aips_pgen_notif_cd';

	/**
	 * Meta key marking a generated post in WordPress.
	 *
	 * @var string
	 */
	const META_KEY_GENERATED = '_aips_generated_post';

	/**
	 * In-memory flag for session notification deduplication.
	 *
	 * @var bool
	 */
	private static $session_notified = false;

	/**
	 * Configuration instance.
	 *
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Logger instance.
	 *
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Notifications repository instance.
	 *
	 * @var AIPS_Notifications_Repository_Interface
	 */
	private $notifications;

	/**
	 * In-memory cache for counts during the current request.
	 *
	 * @var array<string, int>
	 */
	private $in_memory_counts = array();

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config|null                            $config        Config instance.
	 * @param AIPS_Logger_Interface|null                  $logger        Logger instance.
	 * @param AIPS_Notifications_Repository_Interface|null $notifications Notifications repository.
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Notifications_Repository_Interface $notifications = null
	) {
		$container           = AIPS_Container::get_instance();
		$this->config        = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->logger        = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : AIPS_Logger::instance());
		$this->notifications = $notifications ?: ($container->has(AIPS_Notifications_Repository_Interface::class) ? $container->make(AIPS_Notifications_Repository_Interface::class) : AIPS_Notifications_Repository::instance());
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter('aips_pre_generate_post', array($this, 'check_pre_generate'), 10, 2);
		add_action('aips_post_generated', array($this, 'clear_cache'), 10, 0);
	}

	/**
	 * Check whether the generation limiter is enabled.
	 *
	 * @return bool True if active, false if disabled by admin.
	 */
	public function is_enabled(): bool {
		return ! (bool) $this->config->get_option('aips_disable_generation_limiter', false);
	}

	/**
	 * Check whether manual generations bypass the ceiling.
	 *
	 * @return bool True if manual triggers can bypass limits.
	 */
	public function is_manual_bypass_enabled(): bool {
		return (bool) $this->config->get_option('aips_generation_limiter_bypass_manual', true);
	}

	/**
	 * Get the post types counted against the limits.
	 *
	 * @return array<string> List of post type slugs.
	 */
	public function get_counted_post_types(): array {
		$selectable = class_exists('AIPS_Utilities') ? array_keys(AIPS_Utilities::get_selectable_post_types()) : array('post', 'page');
		$default    = !empty($selectable) ? $selectable : array('post', 'page');
		$post_types = $this->config->get_option('aips_generation_limiter_post_types', $default);

		if (!is_array($post_types) || empty($post_types)) {
			return $default;
		}

		$sanitized = array();
		foreach ($post_types as $pt) {
			$pt = sanitize_key((string) $pt);
			if (in_array($pt, $selectable, true)) {
				$sanitized[] = $pt;
			}
		}

		return !empty($sanitized) ? array_values(array_unique($sanitized)) : $default;
	}

	/**
	 * Get the post statuses counted against the limits.
	 *
	 * @return array<string> List of post status slugs.
	 */
	public function get_counted_post_statuses(): array {
		$allowed_defaults = array('publish', 'draft', 'pending', 'future', 'private');
		$statuses         = $this->config->get_option('aips_generation_limiter_post_statuses', $allowed_defaults);

		if (!is_array($statuses) || empty($statuses)) {
			return $allowed_defaults;
		}

		$sanitized = array();
		foreach ($statuses as $status) {
			$status = sanitize_key($status);
			if (in_array($status, $allowed_defaults, true)) {
				$sanitized[] = $status;
			}
		}

		return !empty($sanitized) ? array_values(array_unique($sanitized)) : $allowed_defaults;
	}

	/**
	 * Retrieve configured generation limits, optionally resolving for a specific post type.
	 *
	 * @param string|null $post_type Optional post type to resolve specific overrides for.
	 * @return array<string, int> Associative array of period => limit.
	 */
	public function get_limits(?string $post_type = null): array {
		$limits = array(
			'hourly'  => absint($this->config->get_option('aips_generation_limit_hourly', 10)),
			'daily'   => absint($this->config->get_option('aips_generation_limit_daily', 30)),
			'weekly'  => absint($this->config->get_option('aips_generation_limit_weekly', 150)),
			'monthly' => absint($this->config->get_option('aips_generation_limit_monthly', 500)),
		);

		/**
		 * Filter generation limits for a specific post type or overall.
		 *
		 * @param array<string, int> $limits    Base limits array.
		 * @param string|null        $post_type Target post type if specified.
		 */
		return (array) apply_filters('aips_generation_limits_for_post_type', $limits, $post_type);
	}

	/**
	 * Get the date string for the given time period.
	 *
	 * @param string $period Time period ('hourly', 'daily', 'weekly', 'monthly').
	 * @return string Date relative expression for WP_Query date_query.
	 */
	private function get_period_date_after(string $period): string {
		switch ($period) {
			case 'hourly':
				return '1 hour ago';
			case 'daily':
				return '24 hours ago';
			case 'weekly':
				return '7 days ago';
			case 'monthly':
				return '30 days ago';
			default:
				return '24 hours ago';
		}
	}

	/**
	 * Get the transient key for a given period, post types, and post statuses.
	 *
	 * @param string        $period     Period key.
	 * @param array<string> $post_types Post types array.
	 * @param array<string> $statuses   Post statuses array.
	 * @return string Transient cache key.
	 */
	private function get_transient_key(string $period, array $post_types, array $statuses): string {
		sort($post_types);
		sort($statuses);
		return self::TRANSIENT_PREFIX . $period . '_' . md5(implode(',', $post_types) . '|' . implode(',', $statuses));
	}

	/**
	 * Count generated posts for a specific time period.
	 *
	 * Uses native WP_Query with date_query and _aips_generated_post meta query,
	 * backed by in-memory caching and a 60-second WordPress transient.
	 *
	 * @param string      $period    Time period ('hourly', 'daily', 'weekly', 'monthly').
	 * @param string|null $post_type Optional specific post type to count. If null, counts configured post types.
	 * @return int Total generated posts within the period.
	 */
	public function get_count_for_period(string $period, ?string $post_type = null): int {
		$target_types = $post_type ? array($post_type) : $this->get_counted_post_types();
		$statuses     = $this->get_counted_post_statuses();
		$cache_key    = $period . '_' . ($post_type ?: 'all');

		if (isset($this->in_memory_counts[$cache_key])) {
			return $this->in_memory_counts[$cache_key];
		}

		$transient_key = $this->get_transient_key($period, $target_types, $statuses);
		$cached        = get_transient($transient_key);

		if (false !== $cached && is_numeric($cached)) {
			$this->in_memory_counts[$cache_key] = (int) $cached;
			return (int) $cached;
		}

		$query = new WP_Query(
			array(
				'post_type'              => $target_types,
				'post_status'            => $statuses,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'after'     => $this->get_period_date_after($period),
						'inclusive' => true,
					),
				),
				'meta_query'             => array(
					array(
						'key'   => self::META_KEY_GENERATED,
						'value' => '1',
					),
				),
			)
		);

		$count = (int) $query->found_posts;
		set_transient($transient_key, $count, 60);

		$this->in_memory_counts[$cache_key] = $count;
		return $count;
	}

	/**
	 * Get current usage stats against all configured limits.
	 *
	 * @param string|null $post_type Optional post type to resolve usage for.
	 * @return array<string, array{limit: int, count: int, reached: bool}> Usage data per period.
	 */
	public function get_usage(?string $post_type = null): array {
		$limits = $this->get_limits($post_type);
		$usage  = array();

		foreach ($limits as $period => $limit) {
			$count          = $this->get_count_for_period($period, $post_type);
			$usage[$period] = array(
				'limit'   => $limit,
				'count'   => $count,
				'reached' => ($limit > 0 && $count >= $limit),
			);
		}

		return $usage;
	}

	/**
	 * Check if generation can proceed without exceeding any ceiling.
	 *
	 * @param bool        $is_manual Whether this generation was triggered manually by an admin.
	 * @param mixed       $context   Optional generation context.
	 * @param string|null $post_type Optional specific post type being generated.
	 * @return true|WP_Error True if permitted, WP_Error if ceiling reached.
	 */
	public function check_limits(bool $is_manual = false, $context = null, ?string $post_type = null) {
		if (!$this->is_enabled()) {
			return true;
		}

		if ($is_manual && $this->is_manual_bypass_enabled()) {
			return true;
		}

		// Resolve target post type from context if not explicitly passed
		if (null === $post_type) {
			if (is_object($context) && method_exists($context, 'get_post_type')) {
				$post_type = $context->get_post_type() ?: 'post';
			} elseif (is_object($context) && isset($context->post_type)) {
				$post_type = $context->post_type ?: 'post';
			}
		}

		// If a target post type is identified and not in counted post types, exempt it from rate limiting.
		if ($post_type && !in_array($post_type, $this->get_counted_post_types(), true)) {
			return true;
		}

		$limits = $this->get_limits($post_type);
		$order  = array('hourly', 'daily', 'weekly', 'monthly');

		foreach ($order as $period) {
			$limit = isset($limits[$period]) ? $limits[$period] : 0;
			if ($limit <= 0) {
				continue; // 0 = unlimited
			}

			$count = $this->get_count_for_period($period);
			if ($count >= $limit) {
				$this->handle_limit_breach($period, $limit, $count, $context);

				$period_labels = array(
					'hourly'  => __('hour (60 minutes)', 'ai-post-scheduler'),
					'daily'   => __('day (24 hours)', 'ai-post-scheduler'),
					'weekly'  => __('week (7 days)', 'ai-post-scheduler'),
					'monthly' => __('month (30 days)', 'ai-post-scheduler'),
				);
				$label = isset($period_labels[$period]) ? $period_labels[$period] : $period;

				return new WP_Error(
					'aips_generation_rate_limit_exceeded',
					sprintf(
						/* translators: 1: period label, 2: current count, 3: max limit */
						__('Post generation halted by Safety Limiter: Maximum limit for the %1$s reached (%2$d/%3$d posts).', 'ai-post-scheduler'),
						$label,
						$count,
						$limit
					),
					array(
						'period'    => $period,
						'limit'     => $limit,
						'count'     => $count,
						'post_type' => $post_type,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Pre-generation filter callback for `aips_pre_generate_post`.
	 *
	 * @param mixed $pre_result Existing filter value or WP_Error.
	 * @param mixed $context    Generation context.
	 * @return mixed WP_Error if limit exceeded, else incoming $pre_result.
	 */
	public function check_pre_generate($pre_result, $context = null) {
		if (is_wp_error($pre_result)) {
			return $pre_result;
		}

		$is_manual = false;
		$post_type = null;
		if (is_object($context)) {
			if (method_exists($context, 'get_creation_method')) {
				$method = $context->get_creation_method();
				if (class_exists('AIPS_Generation_Trigger')) {
					$is_manual = (AIPS_Generation_Trigger::classify_creation_method($method) === AIPS_Generation_Trigger::METHOD_MANUAL);
				} else {
					$is_manual = ($method === 'manual');
				}
			}
			if (method_exists($context, 'get_post_type')) {
				$post_type = $context->get_post_type() ?: 'post';
			} elseif (isset($context->post_type)) {
				$post_type = $context->post_type ?: 'post';
			}
		}

		$check = $this->check_limits($is_manual, $context, $post_type);
		if (is_wp_error($check)) {
			return $check;
		}

		return $pre_result;
	}

	/**
	 * Handle a limit breach event: fires action, logs warning, and dispatches throttled notification.
	 *
	 * @param string $period  Period key that exceeded ceiling.
	 * @param int    $limit   The maximum limit configured.
	 * @param int    $count   Current count reached.
	 * @param mixed  $context Generation context.
	 * @return void
	 */
	private function handle_limit_breach(string $period, int $limit, int $count, $context = null): void {
		/**
		 * Action fired when a post generation ceiling is breached.
		 *
		 * @param string $period  Period name ('hourly', 'daily', 'weekly', 'monthly').
		 * @param int    $limit   Configured limit ceiling.
		 * @param int    $count   Current generated post count in period.
		 * @param mixed  $context Generation context.
		 */
		do_action('aips_generation_rate_limit_exceeded', $period, $limit, $count, $context);

		$this->logger->log(
			sprintf('Post generation halted by Safety Limiter: %s limit reached (%d/%d posts).', $period, $count, $limit),
			'warning',
			array(
				'period' => $period,
				'limit'  => $limit,
				'count'  => $count,
			)
		);

		$this->maybe_send_notification($period, $limit, $count);
	}

	/**
	 * Send an admin notification if not in cooldown.
	 *
	 * Throttled to maximum once every 4 hours and once per user session.
	 *
	 * @param string $period Period key.
	 * @param int    $limit  Limit ceiling.
	 * @param int    $count  Current count.
	 * @return bool True if notification was sent, false if throttled.
	 */
	private function maybe_send_notification(string $period, int $limit, int $count): bool {
		// Session throttle: do not send more than once in the same PHP process/session.
		if (self::$session_notified) {
			return false;
		}

		// 4-Hour transient throttle.
		if (get_transient(self::NOTIF_COOLDOWN_TRANSIENT)) {
			return false;
		}

		$period_labels = array(
			'hourly'  => __('hourly (1 hour)', 'ai-post-scheduler'),
			'daily'   => __('daily (24 hours)', 'ai-post-scheduler'),
			'weekly'  => __('weekly (7 days)', 'ai-post-scheduler'),
			'monthly' => __('monthly (30 days)', 'ai-post-scheduler'),
		);
		$label = isset($period_labels[$period]) ? $period_labels[$period] : $period;

		$message = sprintf(
			/* translators: 1: period label, 2: max limit, 3: current count */
			__('Post generation has been paused because the %1$s ceiling of %2$d posts has been reached (%3$d posts created). You can adjust this ceiling or disable the limiter in Settings > Resilience & Limits.', 'ai-post-scheduler'),
			$label,
			$limit,
			$count
		);

		if ($this->notifications && method_exists($this->notifications, 'create_notification')) {
			$this->notifications->create_notification(
				array(
					'type'       => 'generation_limit_exceeded',
					'title'      => __('Post Generation Limit Reached', 'ai-post-scheduler'),
					'message'    => $message,
					'url'        => admin_url('admin.php?page=aips-settings&tab=settings-resilience'),
					'level'      => 'warning',
					'dedupe_key' => 'generation_limit_exceeded_' . $period,
				)
			);
		}

		self::$session_notified = true;
		set_transient(self::NOTIF_COOLDOWN_TRANSIENT, 1, 4 * HOUR_IN_SECONDS);

		return true;
	}

	/**
	 * Invalidate all cached counts.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->in_memory_counts = array();
		$post_types             = $this->get_counted_post_types();
		$statuses               = $this->get_counted_post_statuses();
		$periods                = array('hourly', 'daily', 'weekly', 'monthly');

		foreach ($periods as $period) {
			delete_transient($this->get_transient_key($period, $post_types, $statuses));
			foreach ($post_types as $pt) {
				delete_transient($this->get_transient_key($period, array($pt), $statuses));
			}
		}
	}
}
