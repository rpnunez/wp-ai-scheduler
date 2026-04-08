<?php
/**
 * Notifications Event Handler
 *
 * Handles WordPress hook bindings and transformation into notification payloads.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notifications_Event_Handler
 */
class AIPS_Notifications_Event_Handler {

	/**
 * @var AIPS_Notifications
 */
	private $notifications;

	/**
 * @var AIPS_Notifications_Repository
 */
	private $repository;

	/**
 * Tracks whether the WordPress action hooks have been registered by any
 * instance so that multiple instantiations do not register duplicate handlers.
 *
 * @var bool
 */
	private static $hooks_registered = false;

	/**
 * Constructor.
 *
 * @param AIPS_Notifications $notifications The dispatcher.
 * @param AIPS_Notifications_Repository|null $repository DB notifications repository.
 */
	public function __construct($notifications, $repository = null) {
	$this->notifications = $notifications;
	$this->repository = $repository instanceof AIPS_Notifications_Repository ? $repository : new AIPS_Notifications_Repository();
	$this->register_hooks();
	}

	public static function get_hook_bindings() {
		$bindings = array(
			array(
				'hook'          => 'aips_generation_failed',
				'method'        => 'handle_generation_failed_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_quota_alert',
				'method'        => 'handle_quota_alert_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_integration_error',
				'method'        => 'handle_integration_error_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_scheduler_error',
				'method'        => 'handle_scheduler_error_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_system_error',
				'method'        => 'handle_system_error_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_schedule_execution_completed',
				'method'        => 'handle_template_generated_notification',
				'priority'      => 10,
				'accepted_args' => 3,
			),
			array(
				'hook'          => 'aips_post_generated',
				'method'        => 'handle_post_generated_notification',
				'priority'      => 10,
				'accepted_args' => 4,
			),
			array(
				'hook'          => 'aips_post_review_deleted',
				'method'        => 'handle_post_rejected_notification',
				'priority'      => 10,
				'accepted_args' => 2,
			),
			array(
				'hook'          => 'aips_post_generation_incomplete',
				'method'        => 'handle_partial_generation_completed_notification',
				'priority'      => 10,
				'accepted_args' => 4,
			),
			array(
				'hook'          => 'aips_notification_rollups',
				'method'        => 'handle_summary_rollups_cron',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_export_cleanup_completed',
				'method'        => 'handle_history_cleanup_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_seeder_completed',
				'method'        => 'handle_seeder_complete_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_template_changed',
				'method'        => 'handle_template_change_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_author_suggestions_generated',
				'method'        => 'handle_author_suggestions_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_circuit_breaker_opened',
				'method'        => 'handle_circuit_breaker_opened_notification',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_rate_limit_reached',
				'method'        => 'handle_rate_limit_reached_notification',
				'priority'      => 10,
				'accepted_args' => 1,
      ),
      array(
				'hook'          => 'aips_scheduled_research_completed',
				'method'        => 'handle_research_topics_notification',
				'priority'      => 10,
				'accepted_args' => 3,
			),
		);

		/**
		 * Filter: aips_notification_hook_bindings
		 *
		 * Modify the list of WordPress action hooks that AIPS_Notifications
		 * registers automatically.  Each item is an associative array with
		 * 'hook', 'method', optional 'priority', and optional 'accepted_args'.
		 *
		 * Example — add a binding for a custom notification hook:
		 *
		 *   add_filter( 'aips_notification_hook_bindings', function( $bindings ) {
		 *       $bindings[] = array(
		 *           'hook'          => 'my_plugin_event',
		 *           'method'        => 'handle_my_event',
		 *           'priority'      => 10,
		 *           'accepted_args' => 2,
		 *       );
		 *       return $bindings;
		 *   } );
		 *
		 * @since 1.9.0
		 * @param array $bindings Current list of hook binding maps.
		 * @return array Modified list.
		 */
		return apply_filters('aips_notification_hook_bindings', $bindings);
	}

	/**
	 * Register WordPress action hooks for all declared notification bindings.
	 *
	 * Uses a static flag so that multiple instantiations (e.g. one in the main
	 * plugin bootstrap and another inside a scheduler) do not register duplicate
	 * hook callbacks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		if (self::$hooks_registered) {
			return;
		}
		self::$hooks_registered = true;

		foreach (self::get_hook_bindings() as $binding) {
			if (empty($binding['hook']) || empty($binding['method'])) {
				continue;
			}

			if (!method_exists($this, $binding['method'])) {
				// Log a warning to help debug misconfigured bindings from the filter.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error(
					sprintf(
						'%s: hook binding skipped — method "%s" does not exist on %s (hook: %s)',
						__CLASS__,
						$binding['method'],
						__CLASS__,
						$binding['hook']
					),
					E_USER_WARNING
				);
				continue;
			}

			$priority      = isset($binding['priority'])      ? (int) $binding['priority']      : 10;
			$accepted_args = isset($binding['accepted_args']) ? (int) $binding['accepted_args'] : 1;

			add_action($binding['hook'], array($this, $binding['method']), $priority, $accepted_args);
		}
	}

	/**
	 * Hook handler for generation failures.
	 *
	 * @param array $payload Failure payload.
	 * @return void
	 */
	public function handle_generation_failed_notification($payload) {
		if (is_array($payload)) {
			$this->notifications->generation_failed($payload);
		}
	}

	/**
	 * Hook handler for quota alerts.
	 *
	 * @param array $payload Alert payload.
	 * @return void
	 */
	public function handle_quota_alert_notification($payload) {
		if (is_array($payload)) {
			$this->notifications->quota_alert($payload);
		}
	}

	/**
	 * Hook handler for integration errors.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function handle_integration_error_notification($payload) {
		if (is_array($payload)) {
			$this->notifications->integration_error($payload);
		}
	}

	/**
	 * Hook handler for scheduler errors.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function handle_scheduler_error_notification($payload) {
		if (is_array($payload)) {
			$this->notifications->scheduler_error($payload);
		}
	}

	/**
	 * Hook handler for system errors.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function handle_system_error_notification($payload) {
		if (is_array($payload)) {
			$this->notifications->system_error($payload);
		}
	}

	/**
	 * Hook handler for template-generated notifications.
	 *
	 * @param int         $schedule_id Schedule ID.
	 * @param int|int[]   $result      Post ID(s).
	 * @param object|null $schedule    Optional schedule model.
	 * @return void
	 */
	public function handle_template_generated_notification($schedule_id, $result, $schedule = null) {
		$schedule_id = absint($schedule_id);
		if (!$schedule_id) {
			return;
		}

		$post_ids = is_array($result) ? array_values(array_filter(array_map('absint', $result))) : array(absint($result));
		$post_ids = array_values(array_filter($post_ids));

		if (empty($post_ids)) {
			return;
		}

		if (!is_object($schedule)) {
			$schedule_repository = new AIPS_Schedule_Repository();
			$schedule = $schedule_repository->get_by_id($schedule_id);
		}

		$template_name = '';
		$template_id = 0;

		if (is_object($schedule)) {
			$template_name = !empty($schedule->name) ? $schedule->name : '';
			$template_id = !empty($schedule->template_id) ? absint($schedule->template_id) : 0;
		}

		if ('' === $template_name && $template_id) {
			$template_repository = new AIPS_Template_Repository();
			$template = $template_repository->get_by_id($template_id);
			$template_name = ($template && !empty($template->name)) ? $template->name : '';
		}

		$this->notifications->template_generated(array(
			'schedule_id'    => $schedule_id,
			'template_id'    => $template_id,
			'template_name'  => $template_name ? $template_name : __('Template', 'ai-post-scheduler'),
			'post_ids'       => $post_ids,
			'url'            => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
			'dedupe_key'     => 'template_generated_' . $schedule_id . '_' . md5(implode('-', $post_ids)),
			'dedupe_window'  => 60,
		));
	}

	/**
	 * Hook handler for generated-post notifications.
	 *
	 * @param int         $post_id             Post ID.
	 * @param mixed       $template_or_context Legacy payload (template or context).
	 * @param int         $history_id          History/session ID.
	 * @param mixed|null  $context             Optional generation context.
	 * @return void
	 */
	public function handle_post_generated_notification($post_id, $template_or_context, $history_id = 0, $context = null) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		if (null === $context) {
			$context = $template_or_context;
		}

		$creation_method = $this->extract_creation_method($context);
		$post = get_post($post_id);
		$post_status = ($post && !empty($post->post_status)) ? $post->post_status : '';

		if ('manual' === $creation_method) {
			$this->notifications->manual_generation_completed(array(
				'post_id'       => $post_id,
				'history_id'    => absint($history_id),
				'creation_method'=> 'manual',
				'post_status'   => $post_status,
				'dedupe_key'    => 'manual_generation_completed_' . $post_id,
				'dedupe_window' => 60,
			));
		}

		if (in_array($post_status, array('draft', 'pending'), true)) {
			$this->notifications->post_ready_for_review(array(
				'post_id'        => $post_id,
				'history_id'     => absint($history_id),
				'creation_method'=> $creation_method,
				'post_status'    => $post_status,
				'dedupe_key'     => 'post_ready_for_review_' . $post_id,
				'dedupe_window'  => 60,
			));
		}
	}

	/**
	 * Hook handler for post rejection notifications.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Optional metadata.
	 * @return void
	 */
	public function handle_post_rejected_notification($post_id, $meta = array()) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$post_title = '';
		if (is_array($meta) && !empty($meta['post_title'])) {
			$post_title = sanitize_text_field($meta['post_title']);
		}

		$this->notifications->post_rejected(array(
			'post_id'       => $post_id,
			'post_title'    => $post_title,
			'url'           => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
			'dedupe_key'    => 'post_rejected_' . $post_id,
			'dedupe_window' => 120,
		));
	}

	/**
	 * Hook handler for partial-generation-completed notifications.
	 *
	 * @param int                     $post_id            Post ID.
	 * @param array                   $component_statuses Component status map.
	 * @param AIPS_Generation_Context $context            Generation context.
	 * @param int                     $history_id         History/session ID.
	 * @return void
	 */
	public function handle_partial_generation_completed_notification($post_id, $component_statuses, $context, $history_id = 0) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$missing_components = $this->get_missing_components(is_array($component_statuses) ? $component_statuses : array());
		$this->notifications->partial_generation_completed(array(
			'post_id'            => $post_id,
			'history_id'         => absint($history_id),
			'source'             => $this->get_source_label($context),
			'missing_components' => $missing_components,
			'url'                => admin_url('admin.php?page=aips-generated-posts#aips-partial-generations'),
			'dedupe_key'         => 'partial_generation_completed_' . $post_id,
			'dedupe_window'      => 60,
		));
	}

	/**
	 * Hook handler for daily/weekly/monthly summary rollups.
	 *
	 * @return void
	 */
	public function handle_summary_rollups_cron() {
		$config        = AIPS_Config::get_instance();
		$today_key     = gmdate('Y-m-d', current_time('timestamp', true));
		$daily_sent_key = $config->get_option('aips_notif_daily_digest_last_sent');

		if ($daily_sent_key !== $today_key) {
			$this->notifications->daily_digest($this->build_rollup_payload(86400, 'daily_digest_' . $today_key));
			$config->set_option('aips_notif_daily_digest_last_sent', $today_key);
		}

		$current_timestamp = current_time('timestamp', true);

		// Weekly summary: send once per ISO week when the week key changes.
		$weekly_key       = gmdate('o-W', $current_timestamp);
		$weekly_last_sent = $config->get_option('aips_notif_weekly_summary_last_sent');

		if ($weekly_last_sent !== $weekly_key) {
			$this->notifications->weekly_summary($this->build_rollup_payload(7 * DAY_IN_SECONDS, 'weekly_summary_' . $weekly_key));
			$config->set_option('aips_notif_weekly_summary_last_sent', $weekly_key);
		}

		// Monthly report: send once per calendar month when the month key changes.
		$monthly_key       = gmdate('Y-m', $current_timestamp);
		$monthly_last_sent = $config->get_option('aips_notif_monthly_report_last_sent');

		if ($monthly_last_sent !== $monthly_key) {
			$this->notifications->monthly_report($this->build_rollup_payload(30 * DAY_IN_SECONDS, 'monthly_report_' . $monthly_key));
			$config->set_option('aips_notif_monthly_report_last_sent', $monthly_key);
		}
	}

	/**
	 * Hook handler for cleanup-completed notifications.
	 *
	 * @param array $payload Cleanup payload.
	 * @return void
	 */
	public function handle_history_cleanup_notification($payload) {
		if (!is_array($payload)) {
			return;
		}

		$this->notifications->history_cleanup($payload);
	}

	/**
	 * Hook handler for seeder completion notifications.
	 *
	 * @param array $payload Seeder payload.
	 * @return void
	 */
	public function handle_seeder_complete_notification($payload) {
		if (!is_array($payload)) {
			return;
		}

		$this->notifications->seeder_complete($payload);
	}

	/**
	 * Hook handler for template-change notifications.
	 *
	 * @param array $payload Template payload.
	 * @return void
	 */
	public function handle_template_change_notification($payload) {
		if (!is_array($payload)) {
			return;
		}

		$this->notifications->template_change($payload);
	}

	/**
	 * Hook handler for author suggestion notifications.
	 *
	 * @param array $payload Suggestions payload.
	 * @return void
	 */
	public function handle_author_suggestions_notification($payload) {
		if (!is_array($payload)) {
			return;
		}

		$this->notifications->author_suggestions($payload);
	}

	/**
	 * Hook handler for circuit-breaker-opened notifications.
	 *
	 * @param array $payload Event payload.
	 * @return void
	 */
	public function handle_circuit_breaker_opened_notification($payload) {
		if (!is_array($payload)) {
			return;
		}

		$this->notifications->circuit_breaker_opened($payload);
	}

	/**
	 * Hook handler for rate-limit-reached notifications.
	 *
	 * @param array $payload Event payload.
	 * @return void
	 */
	public function handle_rate_limit_reached_notification($payload) {
		if (!is_array($payload)) {
			return;
		}

		$this->notifications->rate_limit_reached($payload);
  }
  
  /*
	 * Hook handler for scheduled research completion notifications.
	 *
	 * Fires when the cron-driven research run saves new trending topics.
	 *
	 * @param string $niche       Niche that was researched.
	 * @param int    $saved_count Number of topics saved.
	 * @param array  $topics      Raw topic data returned by the research service.
	 * @return void
	 */
	public function handle_research_topics_notification($niche, $saved_count, $topics) {
		$saved_count = absint($saved_count);
		if ($saved_count < 1) {
			return;
		}

		$this->notifications->research_topics_ready(array(
			'niche' => sanitize_text_field((string) $niche),
			'count' => $saved_count,
			'topics' => is_array($topics) ? $topics : array(),
		));
	}

	/**
	 * Extract creation method from a generation context-like object.
	 *
	 * @param mixed $context Context object.
	 * @return string 'manual', 'scheduled', or empty string when unknown.
	 */
	private function extract_creation_method($context) {
		if (is_object($context) && method_exists($context, 'get_creation_method')) {
			$method = sanitize_key((string) $context->get_creation_method());
			if (in_array($method, array('manual', 'scheduled'), true)) {
				return $method;
			}
		}

		return '';
	}

	/**
	 * Build summary payload for digest/report notifications.
	 *
	 * @param int    $window_seconds Time window in seconds.
	 * @param string $dedupe_key     Dedupe key.
	 * @return array<string, mixed>
	 */
	private function build_rollup_payload($window_seconds, $dedupe_key) {
		$window_seconds = absint($window_seconds);
		if ($window_seconds < 1) {
			$window_seconds = DAY_IN_SECONDS;
		}

		$counts = $this->repository->get_type_counts_for_window($window_seconds, array(
			'template_generated',
			'manual_generation_completed',
			'post_ready_for_review',
			'generation_failed',
			'scheduler_error',
			'integration_error',
			'quota_alert',
			'system_error',
		));

		$generated = (isset($counts['template_generated']) ? (int) $counts['template_generated'] : 0)
			+ (isset($counts['manual_generation_completed']) ? (int) $counts['manual_generation_completed'] : 0);

		$errors = (isset($counts['generation_failed']) ? (int) $counts['generation_failed'] : 0)
			+ (isset($counts['scheduler_error']) ? (int) $counts['scheduler_error'] : 0)
			+ (isset($counts['integration_error']) ? (int) $counts['integration_error'] : 0)
			+ (isset($counts['quota_alert']) ? (int) $counts['quota_alert'] : 0)
			+ (isset($counts['system_error']) ? (int) $counts['system_error'] : 0);

		$review_ready = isset($counts['post_ready_for_review']) ? (int) $counts['post_ready_for_review'] : 0;

		return array(
			'generated'    => $generated,
			'review_ready' => $review_ready,
			'errors'       => $errors,
			'window'       => $window_seconds,
			'dedupe_key'   => sanitize_key($dedupe_key),
			'types'        => $counts,
		);
	}

	/**
	 * Derive a human-readable source label from a generation context object.
	 *
	 * @param AIPS_Generation_Context|mixed $context Generation context.
	 * @return string
	 */
	private function get_source_label($context) {
		if (!is_object($context) || !method_exists($context, 'get_type')) {
			return __('Unknown', 'ai-post-scheduler');
		}

		if ($context instanceof AIPS_Template_Context) {
			$template = $context->get_template();
			if ($template && !empty($template->name)) {
				return sprintf(__('Template: %s', 'ai-post-scheduler'), $template->name);
			}
			return __('Template', 'ai-post-scheduler');
		}

		if ($context instanceof AIPS_Topic_Context) {
			$topic = $context->get_topic();
			if (!empty($topic)) {
				return sprintf(__('Author Topic: %s', 'ai-post-scheduler'), $topic);
			}
			return __('Author Topic', 'ai-post-scheduler');
		}

		return __('Unknown', 'ai-post-scheduler');
	}

	/**
	 * Return the list of missing component labels from a status map.
	 *
	 * @param array $component_statuses Per-component boolean status map.
	 * @return array Array of translated label strings.
	 */
	private function get_missing_components(array $component_statuses) {
		$labels = array(
			'post_title'     => __('Title', 'ai-post-scheduler'),
			'post_excerpt'   => __('Excerpt', 'ai-post-scheduler'),
			'post_content'   => __('Content', 'ai-post-scheduler'),
			'featured_image' => __('Featured Image', 'ai-post-scheduler'),
		);

		$missing = array();
		foreach ($labels as $key => $label) {
			if (array_key_exists($key, $component_statuses) && !$component_statuses[$key]) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

}
