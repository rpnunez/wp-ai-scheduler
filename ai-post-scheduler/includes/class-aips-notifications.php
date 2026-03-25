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
 *
 * Usage examples
 * --------------
 *
 *   // 1. Convenience methods (recommended for built-in notifications)
 *   $notifs = new AIPS_Notifications();
 *   $notifs->author_topics_generated( 'Jane Doe', 10, 42 );
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
			'template_generated' => array(
				'label'         => __('Template Generated', 'ai-post-scheduler'),
				'description'   => __('A scheduled template run generated one or more posts.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 60,
			),
			'manual_generation_completed' => array(
				'label'         => __('Manual Generation Completed', 'ai-post-scheduler'),
				'description'   => __('A manually triggered generation request completed successfully.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 60,
			),
			'post_ready_for_review' => array(
				'label'         => __('Post Ready For Review', 'ai-post-scheduler'),
				'description'   => __('A generated post is waiting for editorial review.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 60,
			),
			'post_rejected' => array(
				'label'         => __('Post Rejected', 'ai-post-scheduler'),
				'description'   => __('A generated draft was removed from the review queue.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'warning',
				'dedupe_window' => 120,
			),
			'partial_generation_completed' => array(
				'label'         => __('Partial Generation Completed', 'ai-post-scheduler'),
				'description'   => __('A post was saved with missing generated components and needs follow-up.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'warning',
				'dedupe_window' => 60,
			),
			'daily_digest' => array(
				'label'         => __('Daily Digest', 'ai-post-scheduler'),
				'description'   => __('Daily summary of generation and review activity.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_EMAIL_ONLY,
				'level'         => 'info',
				'dedupe_window' => 3600,
			),
			'weekly_summary' => array(
				'label'         => __('Weekly Summary', 'ai-post-scheduler'),
				'description'   => __('Weekly summary of generation performance and workflow activity.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_EMAIL_ONLY,
				'level'         => 'info',
				'dedupe_window' => 3600,
			),
			'monthly_report' => array(
				'label'         => __('Monthly Report', 'ai-post-scheduler'),
				'description'   => __('Monthly generation and operational report.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_EMAIL_ONLY,
				'level'         => 'info',
				'dedupe_window' => 3600,
			),
			'history_cleanup' => array(
				'label'         => __('History Cleanup', 'ai-post-scheduler'),
				'description'   => __('Operational cleanup completed.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
			),
			'seeder_complete' => array(
				'label'         => __('Seeder Completed', 'ai-post-scheduler'),
				'description'   => __('Seeder operation finished successfully.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
			),
			'template_change' => array(
				'label'         => __('Template Changed', 'ai-post-scheduler'),
				'description'   => __('A template was created, updated, cloned, or deleted.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 180,
			),
			'author_suggestions' => array(
				'label'         => __('Author Suggestions Ready', 'ai-post-scheduler'),
				'description'   => __('AI-generated author profile suggestions are available.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
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
				'template_generated',
				'manual_generation_completed',
				'post_ready_for_review',
				'post_rejected',
				'partial_generation_completed',
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

	/**
	 * Send a template-generated notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function template_generated(array $payload) {
		$post_ids = isset($payload['post_ids']) && is_array($payload['post_ids']) ? array_values(array_filter(array_map('absint', $payload['post_ids']))) : array();
		$post_count = count($post_ids);
		$template_name = !empty($payload['template_name']) ? $payload['template_name'] : __('Template', 'ai-post-scheduler');

		$title = sprintf(
			_n('%1$d post generated from "%2$s"', '%1$d posts generated from "%2$s"', $post_count, 'ai-post-scheduler'),
			$post_count,
			$template_name
		);

		$message = sprintf(
			_n('Scheduled run generated %1$d post for template "%2$s".', 'Scheduled run generated %1$d posts for template "%2$s".', $post_count, 'ai-post-scheduler'),
			$post_count,
			$template_name
		);

		$url = !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		$this->dispatch_notification('template_generated', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'info',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Review generated posts', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a manual generation completed notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function manual_generation_completed(array $payload) {
		$post_id = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post = $post_id ? get_post($post_id) : null;
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');

		$title = sprintf(__('Manual generation completed: %s', 'ai-post-scheduler'), $post_title);
		$message = sprintf(__('Manual generation created post "%s".', 'ai-post-scheduler'), $post_title);
		$url = $post_id ? get_edit_post_link($post_id) : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		$this->dispatch_notification('manual_generation_completed', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'info',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Edit generated post', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a post-ready-for-review notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_ready_for_review(array $payload) {
		$post_id = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post = $post_id ? get_post($post_id) : null;
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');

		$title = sprintf(__('Post ready for review: %s', 'ai-post-scheduler'), $post_title);
		$message = sprintf(__('Generated post "%s" is awaiting review.', 'ai-post-scheduler'), $post_title);
		$url = $post_id ? get_edit_post_link($post_id) : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		$this->dispatch_notification('post_ready_for_review', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'info',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Open review queue', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a post-rejected notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_rejected(array $payload) {
		$post_id = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post_label = !empty($payload['post_title']) ? $payload['post_title'] : sprintf(__('Post #%d', 'ai-post-scheduler'), $post_id);

		$title = sprintf(__('Post rejected: %s', 'ai-post-scheduler'), $post_label);
		$message = sprintf(__('Generated draft "%s" was removed from the review queue.', 'ai-post-scheduler'), $post_label);
		$url = !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		$this->dispatch_notification('post_rejected', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'warning',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 120,
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Open generated posts', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a partial-generation-completed notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function partial_generation_completed(array $payload) {
		$post_id = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post = $post_id ? get_post($post_id) : null;
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');

		$title = sprintf(__('Partial generation completed: %s', 'ai-post-scheduler'), $post_title);
		$message = sprintf(__('Post "%s" was saved with missing components and requires review.', 'ai-post-scheduler'), $post_title);
		$url = !empty($payload['url']) ? $payload['url'] : admin_url('admin.php?page=aips-generated-posts#aips-partial-generations');

		$this->dispatch_notification('partial_generation_completed', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'warning',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Open partial generations', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a daily digest summary notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function daily_digest(array $payload) {
		$title = __('Daily generation digest', 'ai-post-scheduler');
		$message = sprintf(
			__('Today: %1$d posts generated, %2$d review-ready, %3$d errors.', 'ai-post-scheduler'),
			isset($payload['generated']) ? (int) $payload['generated'] : 0,
			isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0,
			isset($payload['errors']) ? (int) $payload['errors'] : 0
		);

		$url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		$this->dispatch_notification('daily_digest', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'info',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => 3600,
			'channels'      => array(self::CHANNEL_DB, self::CHANNEL_EMAIL),
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Open generated posts', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a weekly summary notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function weekly_summary(array $payload) {
		$title = __('Weekly generation summary', 'ai-post-scheduler');
		$message = sprintf(
			__('This week: %1$d posts generated, %2$d review-ready, %3$d errors.', 'ai-post-scheduler'),
			isset($payload['generated']) ? (int) $payload['generated'] : 0,
			isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0,
			isset($payload['errors']) ? (int) $payload['errors'] : 0
		);

		$url = AIPS_Admin_Menu_Helper::get_page_url('history');

		$this->dispatch_notification('weekly_summary', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'info',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => 3600,
			'channels'      => array(self::CHANNEL_EMAIL),
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Open history', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a monthly report notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function monthly_report(array $payload) {
		$title = __('Monthly generation report', 'ai-post-scheduler');
		$message = sprintf(
			__('This month: %1$d posts generated, %2$d review-ready, %3$d errors.', 'ai-post-scheduler'),
			isset($payload['generated']) ? (int) $payload['generated'] : 0,
			isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0,
			isset($payload['errors']) ? (int) $payload['errors'] : 0
		);

		$url = AIPS_Admin_Menu_Helper::get_page_url('status');

		$this->dispatch_notification('monthly_report', array(
			'title'         => $title,
			'message'       => $message,
			'url'           => $url,
			'level'         => 'info',
			'meta'          => $payload,
			'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
			'dedupe_window' => 3600,
			'channels'      => array(self::CHANNEL_EMAIL),
			'vars'          => $this->build_standard_notification_vars($title, $message, $payload, $url, __('Open system status', 'ai-post-scheduler')),
		));
	}

	/**
	 * Send a cleanup-completed notification.
	 *
	 * @param array $payload Cleanup payload.
	 * @return void
	 */
	public function history_cleanup(array $payload) {
		$deleted = isset($payload['deleted']) ? (int) $payload['deleted'] : 0;
		$errors = isset($payload['errors']) ? (int) $payload['errors'] : 0;

		$title = __('Cleanup completed', 'ai-post-scheduler');
		$message = sprintf(__('Cleanup finished. Deleted: %1$d. Errors: %2$d.', 'ai-post-scheduler'), $deleted, $errors);

		$this->dispatch_notification('history_cleanup', array(
			'title'   => $title,
			'message' => $message,
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('status'),
			'level'   => $errors > 0 ? 'warning' : 'info',
			'meta'    => $payload,
		));
	}

	/**
	 * Send a seeder-completed notification.
	 *
	 * @param array $payload Seeder payload.
	 * @return void
	 */
	public function seeder_complete(array $payload) {
		$type = !empty($payload['type']) ? sanitize_text_field($payload['type']) : __('unknown', 'ai-post-scheduler');
		$message_raw = !empty($payload['message']) ? sanitize_text_field($payload['message']) : __('Seeder operation completed.', 'ai-post-scheduler');

		$this->dispatch_notification('seeder_complete', array(
			'title'   => sprintf(__('Seeder completed: %s', 'ai-post-scheduler'), $type),
			'message' => $message_raw,
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('seeder'),
			'level'   => 'info',
			'meta'    => $payload,
		));
	}

	/**
	 * Send a template-change notification.
	 *
	 * @param array $payload Template payload.
	 * @return void
	 */
	public function template_change(array $payload) {
		$action = !empty($payload['action']) ? sanitize_key($payload['action']) : 'updated';
		$template_name = !empty($payload['template_name']) ? sanitize_text_field($payload['template_name']) : __('Template', 'ai-post-scheduler');

		$this->dispatch_notification('template_change', array(
			'title'   => sprintf(__('Template %1$s: %2$s', 'ai-post-scheduler'), $action, $template_name),
			'message' => sprintf(__('Template "%1$s" was %2$s.', 'ai-post-scheduler'), $template_name, $action),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('templates'),
			'level'   => 'info',
			'meta'    => $payload,
		));
	}

	/**
	 * Send an author-suggestions-ready notification.
	 *
	 * @param array $payload Suggestions payload.
	 * @return void
	 */
	public function author_suggestions(array $payload) {
		$count = isset($payload['count']) ? (int) $payload['count'] : 0;
		$niche = !empty($payload['site_niche']) ? sanitize_text_field($payload['site_niche']) : __('N/A', 'ai-post-scheduler');

		$this->dispatch_notification('author_suggestions', array(
			'title'   => sprintf(__('Author suggestions ready (%d)', 'ai-post-scheduler'), $count),
			'message' => sprintf(__('Generated %1$d author suggestion(s) for niche "%2$s".', 'ai-post-scheduler'), $count, $niche),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('authors'),
			'level'   => 'info',
			'meta'    => $payload,
		));
	}

	// -----------------------------------------------------------------------
	// Cron / action hook handlers
	// -----------------------------------------------------------------------

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

		$this->template_generated(array(
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
			$this->manual_generation_completed(array(
				'post_id'       => $post_id,
				'history_id'    => absint($history_id),
				'creation_method'=> 'manual',
				'post_status'   => $post_status,
				'dedupe_key'    => 'manual_generation_completed_' . $post_id,
				'dedupe_window' => 60,
			));
		}

		if (in_array($post_status, array('draft', 'pending'), true)) {
			$this->post_ready_for_review(array(
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

		$this->post_rejected(array(
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
		$this->partial_generation_completed(array(
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
		$today_key = gmdate('Y-m-d', current_time('timestamp', true));
		$daily_sent_key = get_option('aips_notif_daily_digest_last_sent', '');

		if ($daily_sent_key !== $today_key) {
			$this->daily_digest($this->build_rollup_payload(86400, 'daily_digest_' . $today_key));
			update_option('aips_notif_daily_digest_last_sent', $today_key, false);
		}

		$current_timestamp = current_time('timestamp', true);

		// Weekly summary: send once per ISO week when the week key changes.
		$weekly_key       = gmdate('o-W', $current_timestamp);
		$weekly_last_sent = get_option('aips_notif_weekly_summary_last_sent', '');

		if ($weekly_last_sent !== $weekly_key) {
			$this->weekly_summary($this->build_rollup_payload(7 * DAY_IN_SECONDS, 'weekly_summary_' . $weekly_key));
			update_option('aips_notif_weekly_summary_last_sent', $weekly_key, false);
		}

		// Monthly report: send once per calendar month when the month key changes.
		$monthly_key       = gmdate('Y-m', $current_timestamp);
		$monthly_last_sent = get_option('aips_notif_monthly_report_last_sent', '');

		if ($monthly_last_sent !== $monthly_key) {
			$this->monthly_report($this->build_rollup_payload(30 * DAY_IN_SECONDS, 'monthly_report_' . $monthly_key));
			update_option('aips_notif_monthly_report_last_sent', $monthly_key, false);
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

		$this->history_cleanup($payload);
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

		$this->seeder_complete($payload);
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

		$this->template_change($payload);
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

		$this->author_suggestions($payload);
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
	 * @return bool True when wp_mail reports success.
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

			return true;
		}

		$history = $this->history_service->create('notification_sent', array());
		$history->record(
			'activity',
			/* translators: %s: recipient email address */
			sprintf(__('Notification email failed for %s', 'ai-post-scheduler'), $to_email),
			array(
				'event_type'   => 'notification_email_sent',
				'event_status' => 'failed',
			),
			null,
			array(
				'notification_type' => $template->get_type(),
				'to_email'          => $to_email,
			)
		);

		return false;
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
				$email_sent = false;
				foreach ($recipients as $recipient) {
					if ($this->send_email_notification($recipient, $template, $vars)) {
						$email_sent = true;
					}
				}
				if ($email_sent) {
					$sent = true;
				}
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

		if (isset($config['default_mode'])) {
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
			$stored = get_option('aips_review_notifications_email', '');
			if (is_string($stored)) {
				$stored = trim($stored);
			}

			if (empty($stored)) {
				$emails = get_option('admin_email');
			} else {
				$emails = $stored;
			}
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
