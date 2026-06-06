<?php
/**
 * Existing Post Improvement Controller.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Existing_Post_Improvement_Controller {

	/** @var AIPS_Existing_Post_Improvement_Repository */
	private $repository;

	/** @var AIPS_Existing_Post_Improvement_Service */
	private $service;

	public function __construct(?AIPS_Existing_Post_Improvement_Repository $repository = null, ?AIPS_Existing_Post_Improvement_Service $service = null) {
		$this->repository = $repository ?: new AIPS_Existing_Post_Improvement_Repository();
		$this->service    = $service ?: new AIPS_Existing_Post_Improvement_Service($this->repository);

		add_action('wp_ajax_aips_existing_posts_get_suggestions', array($this, 'ajax_get_suggestions'));
		add_action('wp_ajax_aips_existing_posts_get_suggestion_detail', array($this, 'ajax_get_suggestion_detail'));
		add_action('wp_ajax_aips_existing_posts_apply_suggestions', array($this, 'ajax_apply_suggestions'));
		add_action('wp_ajax_aips_existing_posts_dismiss_suggestions', array($this, 'ajax_dismiss_suggestions'));
		add_action('wp_ajax_aips_existing_posts_reopen_suggestion', array($this, 'ajax_reopen_suggestion'));
		add_action('wp_ajax_aips_existing_posts_run_scan_now', array($this, 'ajax_run_scan_now'));
		add_action('wp_ajax_aips_existing_posts_save_schedule', array($this, 'ajax_save_schedule'));
		add_action('wp_ajax_aips_existing_posts_delete_schedule', array($this, 'ajax_delete_schedule'));
	}

	public function get_pending_suggestions($args = array()) {
		return $this->repository->get_pending_suggestions($args);
	}

	public function ajax_get_suggestions() {
		$this->assert_nonce_and_capability('aips_existing_posts_fetch', 'nonce_fetch');
		$page   = isset($_POST['page']) ? absint($_POST['page']) : 1;
		$search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

		$result = $this->repository->get_pending_suggestions(
			array(
				'page'   => $page,
				'search' => $search,
			)
		);

		AIPS_Ajax_Response::success($result);
	}

	public function ajax_get_suggestion_detail() {
		$this->assert_nonce_and_capability('aips_existing_posts_review', 'nonce_review');
		$suggestion_id = isset($_POST['suggestion_id']) ? absint($_POST['suggestion_id']) : 0;
		$detail        = $this->repository->get_suggestion_detail($suggestion_id);

		if (!$detail) {
			AIPS_Ajax_Response::error(__('Suggestion not found.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success($detail);
	}

	public function ajax_apply_suggestions() {
		$this->assert_nonce_and_capability('aips_existing_posts_apply', 'nonce_apply');
		$suggestion_id = isset($_POST['suggestion_id']) ? absint($_POST['suggestion_id']) : 0;
		$item_ids      = isset($_POST['item_ids']) ? (array) wp_unslash($_POST['item_ids']) : array();
		$item_ids      = $this->normalize_item_ids_for_suggestion($suggestion_id, $item_ids);
		$result        = $this->service->apply_items($suggestion_id, $item_ids, get_current_user_id());

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
		}

		AIPS_Ajax_Response::success(
			array(
				'message' => __('Suggestions applied successfully.', 'ai-post-scheduler'),
				'applied' => isset($result['applied']) ? (int) $result['applied'] : 0,
			)
		);
	}

	public function ajax_dismiss_suggestions() {
		$this->assert_nonce_and_capability('aips_existing_posts_dismiss', 'nonce_dismiss');
		$suggestion_id = isset($_POST['suggestion_id']) ? absint($_POST['suggestion_id']) : 0;
		$item_ids      = isset($_POST['item_ids']) ? (array) wp_unslash($_POST['item_ids']) : array();
		$item_ids      = $this->normalize_item_ids_for_suggestion($suggestion_id, $item_ids);
		$count         = $this->service->dismiss_items($suggestion_id, $item_ids, get_current_user_id());

		AIPS_Ajax_Response::success(
			array(
				'message'   => __('Suggestions dismissed.', 'ai-post-scheduler'),
				'dismissed' => (int) $count,
			)
		);
	}

	public function ajax_reopen_suggestion() {
		$this->assert_nonce_and_capability('aips_existing_posts_review', 'nonce_review');
		$suggestion_id = isset($_POST['suggestion_id']) ? absint($_POST['suggestion_id']) : 0;

		if (!$this->repository->mark_suggestion_as($suggestion_id, 'pending')) {
			AIPS_Ajax_Response::error(__('Failed to reopen suggestion.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array('message' => __('Suggestion reopened.', 'ai-post-scheduler')));
	}

	public function ajax_run_scan_now() {
		$this->assert_nonce_and_capability('aips_existing_posts_schedule', 'nonce_schedule');
		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
		$result      = $this->service->run_schedule($schedule_id, 'manual');

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
		}

		AIPS_Ajax_Response::success(
			array(
				'message' => __('Existing-post scan started.', 'ai-post-scheduler'),
				'run_id'  => (int) $result,
			)
		);
	}

	public function ajax_save_schedule() {
		$this->assert_nonce_and_capability('aips_existing_posts_schedule', 'nonce_schedule');
		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
		$data        = array(
			'title'                   => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : __('Scan Existing Posts', 'ai-post-scheduler'),
			'description'             => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
			'frequency'               => isset($_POST['frequency']) ? sanitize_key(wp_unslash($_POST['frequency'])) : 'daily',
			'category_filters'        => isset($_POST['category_filters']) ? array_map('absint', (array) $_POST['category_filters']) : array(),
			'include_generated_posts' => !empty($_POST['include_generated_posts']) ? 1 : 0,
			'status'                  => !empty($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'active',
			'next_run'                => isset($_POST['next_run']) ? absint($_POST['next_run']) : time() + HOUR_IN_SECONDS,
		);

		if ($schedule_id > 0) {
			$ok = $this->repository->update_schedule($schedule_id, $data);
			if (!$ok) {
				AIPS_Ajax_Response::error(__('Failed to update scan schedule.', 'ai-post-scheduler'));
			}

			AIPS_Ajax_Response::success(
				array(
					'message'     => __('Scan schedule updated.', 'ai-post-scheduler'),
					'schedule_id' => $schedule_id,
				)
			);
		}

		$new_id = $this->repository->create_schedule($data);
		if (!$new_id) {
			AIPS_Ajax_Response::error(__('Failed to create scan schedule.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(
			array(
				'message'     => __('Scan schedule created.', 'ai-post-scheduler'),
				'schedule_id' => $new_id,
			)
		);
	}

	public function ajax_delete_schedule() {
		$this->assert_nonce_and_capability('aips_existing_posts_schedule', 'nonce_schedule');
		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

		if (!$this->repository->delete_schedule($schedule_id)) {
			AIPS_Ajax_Response::error(__('Failed to delete scan schedule.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array('message' => __('Scan schedule deleted.', 'ai-post-scheduler')));
	}

	private function assert_nonce_and_capability($nonce_action, $nonce_key) {
		$nonce = isset($_POST[$nonce_key]) ? sanitize_text_field(wp_unslash($_POST[$nonce_key])) : '';

		if (!wp_verify_nonce($nonce, $nonce_action)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	private function normalize_item_ids_for_suggestion($suggestion_id, $item_ids) {
		if (in_array('all', array_map('strval', (array) $item_ids), true)) {
			$detail = $this->repository->get_suggestion_detail($suggestion_id);
			if (!$detail || empty($detail['items']) || !is_array($detail['items'])) {
				return array();
			}

			$pending_ids = array();
			foreach ($detail['items'] as $item) {
				if (isset($item->status) && 'pending' === $item->status) {
					$pending_ids[] = (int) $item->id;
				}
			}

			return array_values(array_filter($pending_ids));
		}

		return array_values(array_filter(array_map('absint', (array) $item_ids)));
	}
}
