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

		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());

		add_action('wp_ajax_aips_save_settings', array($this, 'ajax_save_settings'));
		add_action('wp_ajax_aips_test_connection', array($this, 'ajax_test_connection'));
		add_action('wp_ajax_aips_get_ai_model_catalog', array($this, 'ajax_get_ai_model_catalog'));
	}

	/**
	 * Return cached model metadata for the Admin model picker.
	 *
	 * @return void
	 */
	public function ajax_get_ai_model_catalog() {
		$this->verify_request();

		$capability = isset($_POST['capability']) ? sanitize_key(wp_unslash($_POST['capability'])) : 'text';
		$refresh = !empty($_POST['refresh']);
		AIPS_Ajax_Response::success(array(
			'capability' => $capability === 'image' ? 'image' : 'text',
			'models' => AIPS_AI_Model_Catalog::get($capability, $refresh),
		));
	}

	/**
	 * Save one settings payload via AJAX without reloading the page.
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		$this->verify_request();

		$settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();

		if (!is_array($settings)) {
			AIPS_Ajax_Response::invalid_request(__('Invalid settings payload.', 'ai-post-scheduler'));
		}

		AIPS_Settings::register_setting_schema(new AIPS_Settings_UI());
		$registered_settings = AIPS_Settings::get_registered_settings_args();
		$updated = array();

		foreach ($settings as $option_name => $raw_value) {
			$option_name = is_string($option_name) ? sanitize_key($option_name) : '';

			if (empty($option_name) || !isset($registered_settings[$option_name])) {
				continue;
			}

			$array_settings = array(
				'aips_notification_preferences',
				'aips_wp_ai_connector_ids',
			);

			if (is_array($raw_value) && !in_array($option_name, $array_settings, true)) {
				continue;
			}

			$sanitized_value = sanitize_option($option_name, $raw_value);
			update_option($option_name, $sanitized_value);
			$updated[] = $option_name;
		}

		if (empty($updated)) {
			AIPS_Ajax_Response::invalid_request(__('No valid settings were provided.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Settings saved successfully.', 'ai-post-scheduler'),
			'updated' => array_values($updated),
		));
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
			'max_tokens' => 20,
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
