<?php
/**
 * Background Processes Controller
 *
 * AJAX endpoints for listing, estimating and controlling background processes.
 * The Diagnostics "Background Processes" tab, the admin bar and the Internal
 * Links page all use these endpoints.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Background_Processes_Controller {

	/**
	 * Nonce action shared by the endpoints below.
	 */
	const NONCE_ACTION = 'aips_bg_nonce';

	/**
	 * @var AIPS_Background_Process_Manager
	 */
	private $manager;

	/**
	 * @param AIPS_Background_Process_Manager|null $manager Process manager.
	 */
	public function __construct(?AIPS_Background_Process_Manager $manager = null) {
		$this->manager = $manager ?: AIPS_Container::get_instance()->make(AIPS_Background_Process_Manager::class);

		add_action('wp_ajax_aips_bg_list', array($this, 'ajax_list'));
		add_action('wp_ajax_aips_bg_estimate', array($this, 'ajax_estimate'));
		add_action('wp_ajax_aips_bg_start', array($this, 'ajax_start'));
		add_action('wp_ajax_aips_bg_pause', array($this, 'ajax_pause'));
		add_action('wp_ajax_aips_bg_resume', array($this, 'ajax_resume'));
		add_action('wp_ajax_aips_bg_cancel', array($this, 'ajax_cancel'));
		add_action('wp_ajax_aips_bg_pause_all', array($this, 'ajax_pause_all'));
	}

	/**
	 * AJAX: Snapshots of every process.
	 *
	 * @return void
	 */
	public function ajax_list() {
		$this->verify_request();

		AIPS_Ajax_Response::success(array(
			'processes' => $this->manager->get_snapshots(),
		));
	}

	/**
	 * AJAX: Cost and duration estimate for starting a process now.
	 *
	 * @return void
	 */
	public function ajax_estimate() {
		$this->verify_request();

		$process = $this->get_process_from_request();

		AIPS_Ajax_Response::success(array(
			'estimate' => $process->get_estimate(),
			'uses_ai'  => $process->uses_ai(),
		));
	}

	/**
	 * AJAX: Start a process.
	 *
	 * @return void
	 */
	public function ajax_start() {
		$this->verify_request();

		$process = $this->get_process_from_request();
		$options = array();

		if (isset($_POST['ai_budget'])) {
			$options['ai_budget'] = absint(wp_unslash($_POST['ai_budget']));
		}

		$this->respond($this->manager->control($process->get_key(), AIPS_Background_Process_Manager::ACTION_START, $options), __('Started. It runs in the background.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: Pause a process.
	 *
	 * @return void
	 */
	public function ajax_pause() {
		$this->verify_request();

		$process = $this->get_process_from_request();

		$this->respond($this->manager->control($process->get_key(), AIPS_Background_Process_Manager::ACTION_PAUSE), __('Paused.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: Resume a paused process.
	 *
	 * @return void
	 */
	public function ajax_resume() {
		$this->verify_request();

		$process = $this->get_process_from_request();

		$this->respond($this->manager->control($process->get_key(), AIPS_Background_Process_Manager::ACTION_RESUME), __('Resumed.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: Stop a process.
	 *
	 * @return void
	 */
	public function ajax_cancel() {
		$this->verify_request();

		$process = $this->get_process_from_request();

		$this->respond($this->manager->control($process->get_key(), AIPS_Background_Process_Manager::ACTION_CANCEL), __('Stopped.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: Pause every running process.
	 *
	 * @return void
	 */
	public function ajax_pause_all() {
		$this->verify_request();

		$paused = $this->manager->pause_all();

		AIPS_Ajax_Response::success(
			array(
				'paused'    => $paused,
				'processes' => $this->manager->get_snapshots(),
			),
			sprintf(
				/* translators: %d: number of processes paused. */
				_n('Paused %d process.', 'Paused %d processes.', count($paused), 'ai-post-scheduler'),
				count($paused)
			)
		);
	}

	/**
	 * Send the result of a control action.
	 *
	 * @param array|WP_Error $result  Snapshot or error.
	 * @param string         $message Success message.
	 * @return void
	 */
	private function respond($result, string $message): void {
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array('process' => $result), $message);
	}

	/**
	 * Resolve the process named by the request, or end the request.
	 *
	 * @return AIPS_Background_Process_Interface
	 */
	private function get_process_from_request(): AIPS_Background_Process_Interface {
		$key     = isset($_POST['process']) ? sanitize_key(wp_unslash($_POST['process'])) : '';
		$process = $key !== '' ? $this->manager->get($key) : null;

		if (!$process) {
			AIPS_Ajax_Response::error(__('Unknown background process.', 'ai-post-scheduler'), 'aips_bg_unknown_process');
		}

		return $process;
	}

	/**
	 * Check nonce and capability, or end the request.
	 *
	 * @return void
	 */
	private function verify_request(): void {
		if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
