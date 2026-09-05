<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Secure AJAX API for generated-post reactions. */
class AIPS_Post_Feedback_Controller {
	const NONCE_ACTION = 'aips_post_feedback';
	const MAX_BULK = 100;
	private $service;

	public function __construct($service = null) {
		$this->service = $service ?: new AIPS_Post_Feedback_Service();
		add_action('wp_ajax_aips_post_feedback_set', array($this, 'set_feedback'));
		add_action('wp_ajax_aips_post_feedback_clear', array($this, 'clear_feedback'));
		add_action('wp_ajax_aips_post_feedback_get', array($this, 'get_feedback'));
		add_action('wp_ajax_aips_post_feedback_bulk', array($this, 'bulk_feedback'));
	}

	private function authorize() {
		if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'), 'invalid_nonce', 403);
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		if (!AIPS_Config::get_instance()->get_option('aips_post_feedback_enabled')) {
			AIPS_Ajax_Response::error(__('Generated post feedback is disabled in Settings.', 'ai-post-scheduler'), 'feedback_disabled', 403);
		}
	}

	public function set_feedback() {
		$this->authorize();
		$result = $this->service->record(
			absint($_POST['post_id'] ?? 0),
			sanitize_key(wp_unslash($_POST['reaction'] ?? '')),
			!empty($_POST['reason_category']) ? sanitize_key(wp_unslash($_POST['reason_category'])) : null,
			isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : null,
			get_current_user_id()
		);
		$this->respond($result);
	}

	public function clear_feedback() {
		$this->authorize();
		$this->respond($this->service->clear(absint($_POST['post_id'] ?? 0), get_current_user_id()));
	}

	public function get_feedback() {
		$this->authorize();
		AIPS_Ajax_Response::success(array('feedback' => $this->format($this->service->get_current(absint($_POST['post_id'] ?? 0)))));
	}

	public function bulk_feedback() {
		$this->authorize();
		$post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_values(array_unique(array_map('absint', $_POST['post_ids']))) : array();
		if (empty($post_ids) || count($post_ids) > self::MAX_BULK) {
			AIPS_Ajax_Response::invalid_request(sprintf(
				/* translators: %d: maximum bulk feedback batch size */
				__('Select between 1 and %d posts.', 'ai-post-scheduler'),
				self::MAX_BULK
			));
		}
		$reaction = sanitize_key(wp_unslash($_POST['reaction'] ?? ''));
		$succeeded = array();
		$failed = array();
		foreach ($post_ids as $post_id) {
			$result = 'cleared' === $reaction
				? $this->service->clear($post_id, get_current_user_id())
				: $this->service->record($post_id, $reaction, !empty($_POST['reason_category']) ? sanitize_key(wp_unslash($_POST['reason_category'])) : null, isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : null, get_current_user_id());
			if (is_wp_error($result)) {
				$failed[$post_id] = $result->get_error_message();
			} else {
				$succeeded[] = $post_id;
			}
		}
		AIPS_Ajax_Response::success(array('succeeded' => $succeeded, 'failed' => $failed));
	}

	private function respond($result) {
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code(), 400);
		}
		$result['feedback'] = $this->format($result['feedback'] ?? null);
		AIPS_Ajax_Response::success($result);
	}

	private function format($feedback) {
		if (!$feedback) {
			return null;
		}
		return array(
			'post_id' => (int) $feedback->post_id,
			'reaction' => sanitize_key($feedback->reaction),
			'reason_category' => $feedback->reason_category ? sanitize_key($feedback->reason_category) : null,
			'comment' => $feedback->comment ? sanitize_textarea_field($feedback->comment) : null,
			'updated_by' => (int) $feedback->user_id,
			'updated_at' => AIPS_DateTime::formatRelativeOrAbsolute($feedback->created_at),
		);
	}
}
