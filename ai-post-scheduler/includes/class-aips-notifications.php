<?php
/**
 * Central Notifications Service
 *
 * AIPS_Notifications is the single entry-point for sending any notification
 * within the plugin, regardless of channel (DB admin-bar notification, email,
 * or both simultaneously).
 *
 * Built-in notification types
 * ---------------------------
 *  - author_topics_generated  — DB notification shown in the admin bar.
 *  - partial_generation       — Email alert when a post is saved with missing components.
 *  - posts_awaiting_review    — Daily email digest of draft posts waiting for review.
 *
 * Usage examples
 * --------------
 *
 *   // 1. Convenience methods (recommended for built-in notifications)
 *   $notifs = new AIPS_Notifications();
 *   $notifs->author_topics_generated( 'Jane Doe', 10, 42 );
 *   $notifs->partial_generation( $post_id, $component_statuses, $context, $history_id );
 *   $notifs->posts_awaiting_review( $draft_posts, $draft_count );
 *
 *   // 2. Low-level send() for custom or third-party notification types
 *   $notifs->send(
 *       'my_type',
 *       array( '{{site_name}}' => get_bloginfo('name'), '{{user}}' => 'Jane' ),
 *       array( AIPS_Notifications::CHANNEL_DB, AIPS_Notifications::CHANNEL_EMAIL ),
 *       'admin@example.com',   // $to_email  (required for CHANNEL_EMAIL)
 *       'https://...',         // $db_url    (optional, for the DB notification link)
 *       'Human-readable msg'   // $db_message (optional, DB notification body)
 *   );
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notifications
 */
class AIPS_Notifications {

	// -----------------------------------------------------------------------
	// Channel constants
	// -----------------------------------------------------------------------

	/** Persist the notification in the DB (visible in the admin-bar dropdown). */
	const CHANNEL_DB = 'db';

	/** Send the notification via email using wp_mail(). */
	const CHANNEL_EMAIL = 'email';

	/** Disable all delivery for a notification type. */
	const MODE_OFF = 'off';

	/** Persist only in the DB. */
	const MODE_DB_ONLY = 'db';

	/** Send only email notifications. */
	const MODE_EMAIL_ONLY = 'email';

	/** Send to both DB and email. */
	const MODE_BOTH = 'both';

	// -----------------------------------------------------------------------
	// Dependencies
	// -----------------------------------------------------------------------

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Notification_Templates
	 */
	private $templates;

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * Tracks whether the WordPress action hooks have been registered by any
	 * instance so that multiple instantiations do not register duplicate handlers.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	// -----------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * All dependencies are optional and default to their concrete implementations,
	 * making the class easy to unit-test by passing mocks.
	 *
	 * @param AIPS_Notifications_Repository|null $repository      DB notifications repository.
	 * @param AIPS_Notification_Templates|null   $templates       Email template registry.
	 * @param AIPS_History_Service|null          $history_service History/audit service.
	 */
	public function __construct(
		$repository = null,
		$templates = null,
		$history_service = null
	) {
		$this->repository      = $repository      instanceof AIPS_Notifications_Repository ? $repository      : new AIPS_Notifications_Repository();
		$this->templates       = $templates       instanceof AIPS_Notification_Templates   ? $templates       : new AIPS_Notification_Templates();
		$this->history_service = $history_service instanceof AIPS_History_Service          ? $history_service : new AIPS_History_Service();

		$this->register_hooks();
	}

	/**
	 * Return the available channel modes for settings.
	 *
	 * @return array<string, string>
	 */
	public static function get_channel_mode_options() {
		return array(
			self::MODE_OFF        => __('Off', 'ai-post-scheduler'),
			self::MODE_DB_ONLY    => __('DB only', 'ai-post-scheduler'),
			self::MODE_EMAIL_ONLY => __('Email only', 'ai-post-scheduler'),
			self::MODE_BOTH       => __('DB + Email', 'ai-post-scheduler'),
		);
	}

	/**
	 * Return the notification type registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_notification_type_registry() {
		return array(
			'author_topics_generated' => array(
				'label'        => __('Author Topics Generated', 'ai-post-scheduler'),
				'description'  => __('New author topics are available for review in the admin area.', 'ai-post-scheduler'),
				'default_mode' => self::MODE_DB_ONLY,
				'level'        => 'info',
			),
			'partial_generation' => array(
				'label'        => __('Partial Generation', 'ai-post-scheduler'),
				'description'  => __('A post was created with one or more missing AI-generated components.', 'ai-post-scheduler'),
				'default_mode' => self::MODE_EMAIL_ONLY,
				'level'        => 'warning',
			),
			'posts_awaiting_review' => array(
				'label'        => __('Posts Awaiting Review', 'ai-post-scheduler'),
				'description'  => __('Daily digest of posts awaiting review.', 'ai-post-scheduler'),
				'default_mode' => self::MODE_EMAIL_ONLY,
				'level'        => 'info',
			),
			'generation_failed' => array(
				'label'         => __('Generation Failed', 'ai-post-scheduler'),
				'description'   => __('A manual or direct post generation request failed.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 900,
			),
			'quota_alert' => array(
				'label'         => __('Quota Alert', 'ai-post-scheduler'),
				'description'   => __('The AI provider is rejecting requests because usage limits or circuit protection were reached.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
			'integration_error' => array(
				'label'         => __('Integration Error', 'ai-post-scheduler'),
				'description'   => __('The AI Engine dependency is unavailable or misconfigured.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
			'scheduler_error' => array(
				'label'         => __('Scheduler Error', 'ai-post-scheduler'),
				'description'   => __('A scheduled automation run failed or could not obtain its execution lock.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 900,
			),
			'system_error' => array(
				'label'         => __('System Error', 'ai-post-scheduler'),
				'description'   => __('A plugin-level operational error occurred during activation, upgrades, or cron execution.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
		);
	}

	/**
	 * Return only the high-priority notification types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_high_priority_notification_types() {
		$registry = self::get_notification_type_registry();

		return array_intersect_key(
			$registry,
			array_flip(array(
				'generation_failed',
				'quota_alert',
				'integration_error',
				'scheduler_error',
				'system_error',
			))
		);
	}

	// -----------------------------------------------------------------------
	// Hook registry
	// -----------------------------------------------------------------------

	/**
	 * Declare the WordPress action hook bindings for this service.
	 *
	 * Returns an array of binding maps.  Each map may contain:
	 *   - 'hook'          (string, required) — WordPress action hook name.
	 *   - 'method'        (string, required) — Public method name on this class.
	 *   - 'priority'      (int,    optional) — Hook priority.  Default 10.
	 *   - 'accepted_args' (int,    optional) — Number of accepted arguments.  Default 1.
	 *
	 * Third-party code can add, modify, or remove bindings via the
	 * `aips_notification_hook_bindings` filter before this service is
	 * first instantiated.
	 *
	 * @return array<int, array{hook: string, method: string, priority?: int, accepted_args?: int}>
	 */
	public static function get_hook_bindings() {
		$bindings = array(
			array(
				'hook'          => 'aips_send_review_notifications',
				'method'        => 'handle_review_notifications_cron',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aips_post_generation_incomplete',
				'method'        => 'handle_partial_generation',
				'priority'      => 10,
				'accepted_args' => 4,
			),
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
						'AIPS_Notifications: hook binding skipped — method "%s" does not exist on %s (hook: %s)',
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

	// -----------------------------------------------------------------------
	// Core send method
	// -----------------------------------------------------------------------

	/**
	 * Send a notification through one or more channels.
	 *
	 * @param string $type       Notification type slug (must match a registered template for CHANNEL_EMAIL).
	 * @param array  $vars       Token-replacement map: `'{{token}}' => 'value'` (or just `'token' => 'value'`).
	 * @param array  $channels   Channels to use.  Defaults to `[CHANNEL_DB]`.  Pass `[CHANNEL_DB, CHANNEL_EMAIL]` for both.
	 * @param string $to_email   Target email address.  Required when CHANNEL_EMAIL is included.
	 * @param string $db_url     Optional URL attached to the DB notification (action link in the admin bar).
	 * @param string $db_message Human-readable message for the DB notification.  Required when CHANNEL_DB is included.
	 * @return void
	 */
	public function send(
		$type,
		array $vars = array(),
		array $channels = array(self::CHANNEL_DB),
		$to_email = '',
		$db_url = '',
		$db_message = '',
		array $options = array()
	) {
		$options['vars'] = $vars;
		$options['channels'] = $channels;
		$options['to_email'] = $to_email;
		$options['url'] = $db_url;
		$options['message'] = $db_message;

		$this->dispatch_notification($type, $options);
	}

	// -----------------------------------------------------------------------
	// Named convenience methods
	// -----------------------------------------------------------------------

	/**
	 * Create a DB notification when topics have been generated for an author.
	 *
	 * @param string $author_name  Author display name.
	 * @param int    $topic_count  Number of topics generated.
	 * @param int    $author_id    Author ID (used to build the action link URL).
	 * @return void
	 */
	public function author_topics_generated($author_name, $topic_count, $author_id) {
		$url = AIPS_Admin_Menu_Helper::get_page_url(
			'author_topics',
			array(
				'author_id' => absint($author_id),
				'status'    => 'pending',
			)
		);

		/* translators: 1: author name, 2: number of topics */
		$message = sprintf(
			__('Author (%1$s) generated %2$d pending topic(s) for review', 'ai-post-scheduler'),
			$author_name,
			(int) $topic_count
		);

		$this->send(
			'author_topics_generated',
			array(),
			array(self::CHANNEL_DB),
			'',
			$url,
			$message
		);
	}

	/**
	 * Send an email notification when a post is saved with missing generated components.
	 *
	 * @param int                     $post_id             WordPress post ID.
	 * @param array                   $component_statuses  Per-component boolean status map.
	 * @param AIPS_Generation_Context $context             Generation context.
	 * @param int                     $history_id          Related history session ID.
	 * @return void
	 */
	public function partial_generation($post_id, array $component_statuses, $context, $history_id = 0) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		if (!$this->has_notification_recipients()) {
			return;
		}

		$missing_components = $this->get_missing_components($component_statuses);
		if (empty($missing_components)) {
			return;
		}

		$post       = get_post($post_id);
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');
		$edit_url   = get_edit_post_link($post_id);
		$partial_url = admin_url('admin.php?page=aips-generated-posts#aips-partial-generations');

		// Build the `<ul>` list for missing components.
		$components_html = '<ul class="component-list">';
		foreach ($missing_components as $label) {
			$components_html .= '<li>' . esc_html($label) . '</li>';
		}
		$components_html .= '</ul>';

		// Optional session ID row.
		$history_id_row = '';
		if (!empty($history_id)) {
			$history_id_row = '<br><strong>' . esc_html__('Session ID:', 'ai-post-scheduler') . '</strong> ' . esc_html($history_id);
		}

		$vars = array(
			'{{site_name}}'          => esc_html(get_bloginfo('name')),
			'{{post_title}}'         => esc_html($post_title),
			'{{source_label}}'       => esc_html($this->get_source_label($context)),
			'{{history_id_row}}'     => $history_id_row,
			'{{missing_components}}' => $components_html,
			'{{edit_url}}'           => esc_url($edit_url),
			'{{partial_url}}'        => esc_url($partial_url),
		);

		$this->send(
			'partial_generation',
			$vars,
			array(self::CHANNEL_EMAIL),
			''
		);
	}

	/**
	 * Send a daily digest email listing the draft posts that await review.
	 *
	 * @param array $draft_posts Repository result set (array with 'items' key).
	 * @param int   $total_count Total number of draft posts.
	 * @return void
	 */
	public function posts_awaiting_review(array $draft_posts, $total_count) {
		if (!$this->has_notification_recipients()) {
			return;
		}

		$review_url  = AIPS_Admin_Menu_Helper::get_page_url('generated_posts') . '#aips-pending-review';
		$stats_label = sprintf(
			_n('%d Post Awaiting Review', '%d Posts Awaiting Review', $total_count, 'ai-post-scheduler'),
			$total_count
		);

		// Build post list HTML.
		$post_list_html = '';
		if (!empty($draft_posts['items'])) {
			$post_list_html .= '<p><strong>' . esc_html__('Recent Draft Posts:', 'ai-post-scheduler') . '</strong></p>';
			$post_list_html .= '<ul class="post-list">';
			foreach ($draft_posts['items'] as $post) {
				$title = $post->post_title ?: ($post->generated_title ?? __('Untitled', 'ai-post-scheduler'));
				$meta  = sprintf(
					__('Template: %s | Created: %s', 'ai-post-scheduler'),
					$post->template_name ?: __('None', 'ai-post-scheduler'),
					date_i18n(get_option('date_format'), strtotime($post->created_at))
				);
				$post_list_html .= '<li class="post-item">';
				$post_list_html .= '<div class="post-title">' . esc_html($title) . '</div>';
				$post_list_html .= '<div class="post-meta">' . esc_html($meta) . '</div>';
				$post_list_html .= '</li>';
			}
			$post_list_html .= '</ul>';
		}

		// "…and N more" note.
		$more_posts_html = '';
		if ($total_count > 10) {
			$more_posts_html = '<p><em>' . esc_html(sprintf(
				__('...and %d more posts', 'ai-post-scheduler'),
				$total_count - 10
			)) . '</em></p>';
		}

		$vars = array(
			'{{site_name}}'   => esc_html(get_bloginfo('name')),
			'{{stats_label}}' => esc_html($stats_label),
			'{{post_list}}'   => $post_list_html,
			'{{more_posts}}'  => $more_posts_html,
			'{{review_url}}'  => esc_url($review_url),
		);

		$this->send(
			'posts_awaiting_review',
			$vars,
			array(self::CHANNEL_EMAIL),
			''
		);
	}

	/**
	 * Send a high-priority generation failure notification.
	 *
	 * @param array $payload Failure payload.
	 * @return void
	 */
	public function generation_failed(array $payload) {
		$resource_label = !empty($payload['resource_label']) ? $payload['resource_label'] : __('AI generation request', 'ai-post-scheduler');
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Unknown error', 'ai-post-scheduler');
		$title = sprintf(__('Generation failed: %s', 'ai-post-scheduler'), $resource_label);
		$message = sprintf(__('Generation failed for %1$s. Error: %2$s', 'ai-post-scheduler'), $resource_label, $error_message);

		$this->dispatch_notification('generation_failed', array(
			'title'        => $title,
			'message'      => $message,
			'url'          => !empty($payload['url']) ? $payload['url'] : '',
			'level'        => 'error',
			'meta'         => $payload,
			'dedupe_key'   => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window'=> !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
			'vars'         => $this->build_standard_notification_vars($title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Open generation history', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a high-priority quota alert notification.
	 *
	 * @param array $payload Alert payload.
	 * @return void
	 */
	public function quota_alert(array $payload) {
		$request_type = !empty($payload['request_type']) ? $payload['request_type'] : __('request', 'ai-post-scheduler');
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Quota threshold reached.', 'ai-post-scheduler');
		$title = sprintf(__('Quota alert: %s', 'ai-post-scheduler'), $request_type);
		$message = sprintf(__('AI requests are being blocked for %1$s operations. Error: %2$s', 'ai-post-scheduler'), $request_type, $error_message);

		$this->dispatch_notification('quota_alert', array(
			'title'        => $title,
			'message'      => $message,
			'url'          => !empty($payload['url']) ? $payload['url'] : '',
			'level'        => 'error',
			'meta'         => $payload,
			'dedupe_key'   => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window'=> !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
			'vars'         => $this->build_standard_notification_vars($title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Review AI settings', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a high-priority integration error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function integration_error(array $payload) {
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('AI integration unavailable.', 'ai-post-scheduler');
		$title = __('AI integration error', 'ai-post-scheduler');
		$message = sprintf(__('The AI integration is unavailable. Error: %s', 'ai-post-scheduler'), $error_message);

		$this->dispatch_notification('integration_error', array(
			'title'        => $title,
			'message'      => $message,
			'url'          => !empty($payload['url']) ? $payload['url'] : '',
			'level'        => 'error',
			'meta'         => $payload,
			'dedupe_key'   => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window'=> !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
			'vars'         => $this->build_standard_notification_vars($title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Check integration status', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a scheduler error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function scheduler_error(array $payload) {
		$schedule_name = !empty($payload['schedule_name']) ? $payload['schedule_name'] : __('Scheduled run', 'ai-post-scheduler');
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Unknown scheduler error', 'ai-post-scheduler');
		$title = sprintf(__('Scheduler error: %s', 'ai-post-scheduler'), $schedule_name);
		$message = sprintf(__('The scheduler could not complete "%1$s". Error: %2$s', 'ai-post-scheduler'), $schedule_name, $error_message);

		$this->dispatch_notification('scheduler_error', array(
			'title'        => $title,
			'message'      => $message,
			'url'          => !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('schedule'),
			'level'        => 'error',
			'meta'         => $payload,
			'dedupe_key'   => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window'=> !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
			'vars'         => $this->build_standard_notification_vars($title, $message, $payload, !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('schedule'), __('Open schedules', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a system error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function system_error(array $payload) {
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Unknown system error', 'ai-post-scheduler');
		$title = !empty($payload['title']) ? $payload['title'] : __('System error', 'ai-post-scheduler');
		$message = sprintf(__('A system-level plugin error occurred. Error: %s', 'ai-post-scheduler'), $error_message);

		$this->dispatch_notification('system_error', array(
			'title'        => $title,
			'message'      => $message,
			'url'          => !empty($payload['url']) ? $payload['url'] : '',
			'level'        => 'error',
			'meta'         => $payload,
			'dedupe_key'   => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window'=> !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
			'vars'         => $this->build_standard_notification_vars($title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Review details', 'ai-post-scheduler')),
		));
	}

	// -----------------------------------------------------------------------
	// Cron / action hook handlers
	// -----------------------------------------------------------------------

	/**
	 * Hook handler: `aips_send_review_notifications` (daily cron).
	 *
	 * Checks plugin settings, fetches draft posts, and delegates to
	 * posts_awaiting_review() which handles the actual sending.
	 *
	 * @return void
	 */
	public function handle_review_notifications_cron() {
		if (!get_option('aips_review_notifications_enabled', 0)) {
			return;
		}

		$repository  = new AIPS_Post_Review_Repository();
		$draft_count = $repository->get_draft_count();

		if ($draft_count === 0) {
			return;
		}

		$draft_posts = $repository->get_draft_posts(array(
			'per_page' => 10,
			'page'     => 1,
		));

		$this->posts_awaiting_review($draft_posts, $draft_count);
	}

	/**
	 * Hook handler: `aips_post_generation_incomplete` (fired by the generator).
	 *
	 * @param int                     $post_id            WordPress post ID.
	 * @param array                   $component_statuses Per-component boolean status map.
	 * @param AIPS_Generation_Context $context            Generation context.
	 * @param int                     $history_id         Related history session ID.
	 * @return void
	 */
	public function handle_partial_generation($post_id, $component_statuses, $context, $history_id = 0) {
		$this->partial_generation($post_id, $component_statuses, $context, $history_id);
	}

	/**
	 * Hook handler for generation failures.
	 *
	 * @param array $payload Failure payload.
	 * @return void
	 */
	public function handle_generation_failed_notification($payload) {
		if (is_array($payload)) {
			$this->generation_failed($payload);
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
			$this->quota_alert($payload);
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
			$this->integration_error($payload);
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
			$this->scheduler_error($payload);
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
			$this->system_error($payload);
		}
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Persist a DB notification via the repository.
	 *
	 * @param string $type    Notification type slug.
	 * @param string $message Human-readable message body.
	 * @param string $url     Optional action-link URL.
	 * @return void
	 */
	private function send_db_notification($type, $message, $url = '', array $options = array()) {
		if (empty($message)) {
			return;
		}

		$this->repository->create_notification(array(
			'type'       => $type,
			'title'      => isset($options['title']) ? $options['title'] : '',
			'message'    => $message,
			'url'        => $url,
			'level'      => isset($options['level']) ? $options['level'] : 'info',
			'meta'       => isset($options['meta']) ? $options['meta'] : null,
			'dedupe_key' => isset($options['dedupe_key']) ? $options['dedupe_key'] : '',
		));
	}

	/**
	 * Render and dispatch an email notification.
	 *
	 * Logs the outcome via AIPS_History_Service.
	 *
	 * @param string                     $to_email Target email address.
	 * @param AIPS_Notification_Template $template Template to render.
	 * @param array                      $vars     Token-replacement map.
	 * @return void
	 */
	private function send_email_notification($to_email, AIPS_Notification_Template $template, array $vars) {
		$subject = $template->render_subject($vars);
		$body    = $template->render_body($vars);
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		$sent = wp_mail($to_email, $subject, $body, $headers);

		if ($sent) {
			$history = $this->history_service->create('notification_sent', array());
			$history->record(
				'activity',
				/* translators: %s: recipient email address */
				sprintf(__('Notification email sent to %s', 'ai-post-scheduler'), $to_email),
				array(
					'event_type'   => 'notification_email_sent',
					'event_status' => 'success',
				),
				null,
				array(
					'notification_type' => $template->get_type(),
					'to_email'          => $to_email,
				)
			);
		}
	}

	/**
	 * Dispatch a notification using the resolved channels.
	 *
	 * @param string $type    Notification type.
	 * @param array  $options Notification options.
	 * @return bool True when at least one channel was used.
	 */
	private function dispatch_notification($type, array $options = array()) {
		$config = $this->get_notification_type_config($type);
		$channels = $this->resolve_channels($type, isset($options['channels']) ? $options['channels'] : array(self::CHANNEL_DB));
		$vars = isset($options['vars']) && is_array($options['vars']) ? $options['vars'] : array();
		$vars['{{site_name}}'] = isset($vars['{{site_name}}']) ? $vars['{{site_name}}'] : esc_html(get_bloginfo('name'));

		$dedupe_key = !empty($options['dedupe_key']) ? sanitize_text_field($options['dedupe_key']) : '';
		$dedupe_window = isset($options['dedupe_window']) && absint($options['dedupe_window']) > 0 ? absint($options['dedupe_window']) : (isset($config['dedupe_window']) ? absint($config['dedupe_window']) : 0);

		if ($this->is_duplicate_notification($dedupe_key, $dedupe_window)) {
			return false;
		}

		$sent = false;
		$title = isset($options['title']) ? $options['title'] : '';
		$message = isset($options['message']) ? $options['message'] : '';
		$url = isset($options['url']) ? $options['url'] : '';
		$level = isset($options['level']) ? $options['level'] : (isset($config['level']) ? $config['level'] : 'info');
		$meta = isset($options['meta']) ? $options['meta'] : null;

		if (in_array(self::CHANNEL_DB, $channels, true)) {
			$this->send_db_notification($type, $message, $url, array(
				'title'      => $title,
				'level'      => $level,
				'meta'       => $meta,
				'dedupe_key' => $dedupe_key,
			));
			$sent = true;
		}

		if (in_array(self::CHANNEL_EMAIL, $channels, true)) {
			$template = $this->templates->get($type);
			$recipients = $this->parse_notification_emails(isset($options['to_email']) ? $options['to_email'] : '');

			if ($template && !empty($recipients)) {
				foreach ($recipients as $recipient) {
					$this->send_email_notification($recipient, $template, $vars);
				}
				$sent = true;
			}
		}

		if ($sent && '' !== $dedupe_key && $dedupe_window > 0) {
			set_transient($this->get_dedupe_transient_key($dedupe_key), 1, $dedupe_window);
		}

		return $sent;
	}

	/**
	 * Resolve effective channels for a notification type.
	 *
	 * @param string $type             Notification type.
	 * @param array  $fallback_channels Fallback channels.
	 * @return array<int, string>
	 */
	private function resolve_channels($type, array $fallback_channels) {
		$config = $this->get_notification_type_config($type);
		$mode = null;

		if (isset(self::get_high_priority_notification_types()[$type])) {
			$preferences = get_option('aips_notification_preferences', array());
			$mode = isset($preferences[$type]) ? $preferences[$type] : (isset($config['default_mode']) ? $config['default_mode'] : self::MODE_BOTH);
		}

		if (null === $mode) {
			return $fallback_channels;
		}

		switch ($mode) {
			case self::MODE_OFF:
				return array();
			case self::MODE_DB_ONLY:
				return array(self::CHANNEL_DB);
			case self::MODE_EMAIL_ONLY:
				return array(self::CHANNEL_EMAIL);
			case self::MODE_BOTH:
			default:
				return array(self::CHANNEL_DB, self::CHANNEL_EMAIL);
		}
	}

	/**
	 * Return notification configuration for a type.
	 *
	 * @param string $type Notification type.
	 * @return array<string, mixed>
	 */
	private function get_notification_type_config($type) {
		$registry = self::get_notification_type_registry();

		return isset($registry[$type]) ? $registry[$type] : array();
	}

	/**
	 * Check whether notification recipients are configured.
	 *
	 * @return bool
	 */
	private function has_notification_recipients() {
		return !empty($this->parse_notification_emails(''));
	}

	/**
	 * Parse a notification email list.
	 *
	 * @param string|array $emails Raw email list.
	 * @return array<int, string>
	 */
	private function parse_notification_emails($emails) {
		if (empty($emails)) {
			$emails = get_option('aips_review_notifications_email', get_option('admin_email'));
		}

		if (is_array($emails)) {
			$candidates = $emails;
		} else {
			$candidates = preg_split('/\s*,\s*/', (string) $emails);
		}

		$candidates = is_array($candidates) ? $candidates : array();
		$valid = array();

		foreach ($candidates as $candidate) {
			$candidate = sanitize_email($candidate);
			if (!empty($candidate) && is_email($candidate)) {
				$valid[] = $candidate;
			}
		}

		return array_values(array_unique($valid));
	}

	/**
	 * Determine whether a notification is a duplicate inside the dedupe window.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @param int    $window     Dedupe window in seconds.
	 * @return bool
	 */
	private function is_duplicate_notification($dedupe_key, $window) {
		if ('' === $dedupe_key || $window < 1) {
			return false;
		}

		if (get_transient($this->get_dedupe_transient_key($dedupe_key))) {
			return true;
		}

		return $this->repository->was_recently_sent($dedupe_key, $window);
	}

	/**
	 * Return the transient key used for dedupe.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @return string
	 */
	private function get_dedupe_transient_key($dedupe_key) {
		return 'aips_notif_' . md5($dedupe_key);
	}

	/**
	 * Build standard notification template variables.
	 *
	 * @param string $title        Notification title.
	 * @param string $message      Notification message.
	 * @param array  $details      Additional detail payload.
	 * @param string $action_url   Optional action URL.
	 * @param string $action_label Optional action label.
	 * @return array<string, string>
	 */
	private function build_standard_notification_vars($title, $message, array $details, $action_url = '', $action_label = '') {
		return array(
			'{{site_name}}'          => esc_html(get_bloginfo('name')),
			'{{notification_title}}' => esc_html($title),
			'{{notification_message}}' => esc_html($message),
			'{{details_html}}'       => $this->build_details_html($details),
			'{{action_url}}'         => esc_url($action_url),
			'{{action_label}}'       => esc_html($action_label),
		);
	}

	/**
	 * Render detail rows for email templates.
	 *
	 * @param array $details Notification details.
	 * @return string
	 */
	private function build_details_html(array $details) {
		$items = array();

		foreach ($details as $key => $value) {
			if (is_array($value) || is_object($value) || '' === (string) $value) {
				continue;
			}

			$label = ucwords(str_replace(array('_', '-'), ' ', (string) $key));
			$items[] = '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html((string) $value) . '</li>';
		}

		if (empty($items)) {
			return '';
		}

		return '<ul class="notification-details">' . implode('', $items) . '</ul>';
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
