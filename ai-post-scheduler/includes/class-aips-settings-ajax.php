<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings_AJAX
 *
 * Handles the AJAX endpoints for the settings page.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings_AJAX {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * Initialize the AJAX handler.
	 *
	 * Hooks into wp_ajax.
	 *
	 * @param AIPS_AI_Service_Interface|null      $ai_service AI service dependency.
	 * @param AIPS_History_Service_Interface|null $history_service History service dependency.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null, ?AIPS_History_Service_Interface $history_service = null) {
		$container = AIPS_Container::get_instance();

		$this->ai_service = $ai_service ?: $container->makeIfExists(AIPS_AI_Service_Interface::class, AIPS_AI_Service::class);
		$this->history_service = $history_service ?: $container->makeIfExists(AIPS_History_Service_Interface::class, AIPS_History_Service::class);

		add_action('wp_ajax_aips_test_connection', array($this, 'ajax_test_connection'));
		add_action('wp_ajax_aips_notifications_data_hygiene', array($this, 'ajax_notifications_data_hygiene'));
	}

	/**
	 * Handle AJAX request to test AI connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		$this->verify_request();

		$prompt  = 'Say "Hello World" in 2 words.';
		$options = array(
			'maxTokens' => 20,
		);
		$history = $this->history_service->create(
			'settings_connection_test',
			array(
				'user_id' => get_current_user_id(),
			)
		);

		$history->record(
			'activity',
			__('Testing AI connection from the settings screen.', 'ai-post-scheduler'),
			array(
				'source' => 'settings_ui',
			)
		);
		$history->record(
			'ai_request',
			__('Settings connection test prompt sent to AI.', 'ai-post-scheduler'),
			array(
				'prompt'  => $prompt,
				'options' => $options,
			)
		);

		$result = $this->ai_service->generate_text($prompt, $options);

		if (is_wp_error($result)) {
			$history->record_error(
				__('AI settings connection test failed.', 'ai-post-scheduler'),
				array(
					'error_code' => $result->get_error_code(),
				),
				$result
			);
			$history->complete_failure(
				$result->get_error_message(),
				array(
					'error_code' => $result->get_error_code(),
				)
			);

			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		$history->record(
			'ai_response',
			__('Settings connection test response received.', 'ai-post-scheduler'),
			null,
			$result
		);
		$history->complete_success(array('status' => 'success'));

		// SECURITY: Escape the AI response before sending it to the browser to prevent XSS.
		// Even though the prompt is hardcoded ("Say Hello World"), the AI response should be treated as untrusted.
		AIPS_Ajax_Response::success(array('message' => __('Connection successful! AI response: ', 'ai-post-scheduler') . esc_html($result)));
	}

	/**
	 * Run one-time notifications hygiene actions from System Status.
	 *
	 * @return void
	 */
	public function ajax_notifications_data_hygiene() {
		$this->verify_request();

		$history = $this->history_service->create(
			'settings_notifications_hygiene',
			array(
				'user_id' => get_current_user_id(),
			)
		);
		$history->record(
			'activity',
			__('Running notifications hygiene from the settings screen.', 'ai-post-scheduler'),
			array(
				'source' => 'settings_ui',
			)
		);

		$removed_options = 0;
		if (AIPS_Config::get_instance()->has_option('aips_review_notifications_enabled')) {
			delete_option('aips_review_notifications_enabled');
			$removed_options++;
		}

		$unscheduled_events = 0;
		$legacy_hook = 'aips_send_review_notifications';
		$next_run = wp_next_scheduled($legacy_hook);
		while ($next_run) {
			wp_unschedule_event($next_run, $legacy_hook);
			$unscheduled_events++;
			$next_run = wp_next_scheduled($legacy_hook);
		}

		$rollup_scheduled = (bool) wp_next_scheduled('aips_notification_rollups');
		if (!$rollup_scheduled) {
			wp_schedule_event(AIPS_DateTime::now()->timestamp(), 'daily', 'aips_notification_rollups');
			$rollup_scheduled = (bool) wp_next_scheduled('aips_notification_rollups');

			if (!$rollup_scheduled) {
				$history->record(
					'warning',
					__('Notification rollup remained unscheduled after hygiene attempted to recreate it.', 'ai-post-scheduler'),
					array(
						'hook' => 'aips_notification_rollups',
					)
				);
			}
		}

		$registry = AIPS_Notifications::get_notification_type_registry();
		$allowed_modes = array_keys(AIPS_Notifications::get_channel_mode_options());
		$current_preferences = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
		$current_preferences = is_array($current_preferences) ? $current_preferences : array();
		$config_defaults = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
		$config_defaults = is_array($config_defaults) ? $config_defaults : array();

		$cleaned_preferences = array();
		foreach ($registry as $type => $meta) {
			$fallback = isset($config_defaults[$type]) ? $config_defaults[$type] : (isset($meta['default_mode']) ? $meta['default_mode'] : AIPS_Notifications::MODE_BOTH);
			$mode = isset($current_preferences[$type]) ? sanitize_key($current_preferences[$type]) : $fallback;
			if (!in_array($mode, $allowed_modes, true)) {
				$mode = $fallback;
			}
			$cleaned_preferences[$type] = $mode;
		}

		$preferences_changed = ($cleaned_preferences !== $current_preferences);
		if ($preferences_changed) {
			update_option('aips_notification_preferences', $cleaned_preferences, false);
		}

		$details = array(
			'removed_options'     => $removed_options,
			'unscheduled_events'  => $unscheduled_events,
			'rollup_scheduled'    => $rollup_scheduled ? 1 : 0,
			'preferences_changed' => $preferences_changed ? 1 : 0,
		);

		$history->record(
			'activity',
			__('Notifications hygiene completed.', 'ai-post-scheduler'),
			null,
			$details
		);
		$history->complete_success($details);

		AIPS_Ajax_Response::success(array(
			'message' => __('Notifications hygiene completed successfully.', 'ai-post-scheduler'),
			'details' => $details,
		));
	}

	/**
	 * Validate AJAX nonce and permissions.
	 *
	 * @return void
	 */
	private function verify_request() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
