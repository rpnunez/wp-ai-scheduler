<?php
/**
 * Consolidation Controller
 *
 * AJAX endpoints for consolidating two overlapping posts from the
 * Cannibalization Shield: preview, AI-merged draft, consolidate, undo and
 * recent history.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Consolidation_Controller
 */
class AIPS_Consolidation_Controller {

	/**
	 * @var AIPS_Consolidation_Service
	 */
	private $service;

	/**
	 * @var AIPS_Duplicate_Group_Service
	 */
	private $groups;

	/**
	 * @param AIPS_Consolidation_Service|null   $service Consolidation service.
	 * @param AIPS_Duplicate_Group_Service|null $groups  Duplicate group service.
	 */
	public function __construct(?AIPS_Consolidation_Service $service = null, ?AIPS_Duplicate_Group_Service $groups = null) {
		$this->service = $service ?: new AIPS_Consolidation_Service();
		$this->groups  = $groups ?: new AIPS_Duplicate_Group_Service();

		add_action('wp_ajax_aips_consolidation_preview', array($this, 'ajax_preview'));
		add_action('wp_ajax_aips_consolidation_merge', array($this, 'ajax_merge'));
		add_action('wp_ajax_aips_consolidation_run', array($this, 'ajax_run'));
		add_action('wp_ajax_aips_consolidation_undo', array($this, 'ajax_undo'));
		add_action('wp_ajax_aips_consolidation_history', array($this, 'ajax_history'));
		add_action('wp_ajax_aips_duplicate_groups', array($this, 'ajax_duplicate_groups'));
		add_action('wp_ajax_aips_duplicate_dismiss', array($this, 'ajax_duplicate_dismiss'));
		add_action('wp_ajax_aips_duplicate_reset_dismissed', array($this, 'ajax_duplicate_reset_dismissed'));
	}

	/**
	 * AJAX: the duplicate review queue, as small groups with a recommended keeper.
	 *
	 * Optional `threshold` (0.70-0.99) overrides the saved Settings value for this scan.
	 *
	 * @return void
	 */
	public function ajax_duplicate_groups() {
		$this->verify_request();

		$threshold = isset($_POST['threshold']) && $_POST['threshold'] !== '' ? (float) $_POST['threshold'] : null;

		AIPS_Ajax_Response::success($this->groups->get_review_groups($threshold));
	}

	/**
	 * AJAX: mark a group's posts as "not duplicates" so it stops being listed.
	 *
	 * @return void
	 */
	public function ajax_duplicate_dismiss() {
		$this->verify_request();

		$ids = isset($_POST['post_ids']) ? array_map('absint', (array) wp_unslash($_POST['post_ids'])) : array();
		$ids = array_values(array_unique(array_filter($ids)));
		if (count($ids) < 2) {
			AIPS_Ajax_Response::error(__('Choose at least two posts.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(
			'dismissed' => $this->groups->dismiss_group($ids),
			'message'   => __('Marked as not duplicates. They will no longer be listed.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: forget every "not a duplicate" decision.
	 *
	 * @return void
	 */
	public function ajax_duplicate_reset_dismissed() {
		$this->verify_request();

		$this->groups->reset_dismissed();

		AIPS_Ajax_Response::success(array('message' => __('Dismissed groups will be listed again.', 'ai-post-scheduler')));
	}

	/**
	 * Data for the initial render of the Recent Consolidations card.
	 *
	 * @return array{consolidations:array[]}
	 */
	public function get_view_data(): array {
		return array('consolidations' => $this->service->get_history());
	}

	/**
	 * AJAX: what consolidating the pair would do.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		$this->verify_request();
		list($keep_id, $retire_id) = $this->read_pair();

		$result = $this->service->preview($keep_id, $retire_id);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success($result);
	}

	/**
	 * AJAX: generate the AI-merged draft (nothing is saved).
	 *
	 * @return void
	 */
	public function ajax_merge() {
		$this->verify_request();
		list($keep_id, $retire_id) = $this->read_pair();

		$instructions = isset($_POST['instructions']) ? sanitize_textarea_field(wp_unslash($_POST['instructions'])) : '';
		$result       = $this->service->generate_merge($keep_id, $retire_id, $instructions);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array('content' => $result));
	}

	/**
	 * AJAX: consolidate the pair.
	 *
	 * @return void
	 */
	public function ajax_run() {
		$this->verify_request();
		list($keep_id, $retire_id) = $this->read_pair();

		$result = $this->service->consolidate($keep_id, $retire_id, array(
			'content_mode' => isset($_POST['content_mode']) ? sanitize_key(wp_unslash($_POST['content_mode'])) : AIPS_Consolidation_Service::CONTENT_NONE,
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized with wp_kses_post().
			'content'      => isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '',
		));
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$message = sprintf(
			/* translators: 1: retired post title, 2: kept post title, 3: links re-pointed */
			__('"%1$s" was moved to draft and now redirects to "%2$s". %3$d internal link(s) re-pointed.', 'ai-post-scheduler'),
			$result['retire_title'],
			$result['keep_title'],
			$result['links_repointed']
		);
		if ($result['content_mode'] === AIPS_Consolidation_Service::CONTENT_REVISION) {
			$message .= ' ' . __('The merged draft was saved as a revision of the kept post; review and restore it from the post\'s revisions.', 'ai-post-scheduler');
		} elseif ($result['content_mode'] === AIPS_Consolidation_Service::CONTENT_REWRITE) {
			$message .= ' ' . __('The kept post now has the merged content.', 'ai-post-scheduler');
		}
		if ($result['link_failures'] > 0) {
			/* translators: %d: number of links */
			$message .= ' ' . sprintf(__('%d link(s) could not be re-pointed because their post is being edited.', 'ai-post-scheduler'), $result['link_failures']);
		}

		AIPS_Ajax_Response::success(array(
			'message'        => $message,
			'consolidations' => $this->service->get_history(),
		));
	}

	/**
	 * AJAX: undo a consolidation.
	 *
	 * @return void
	 */
	public function ajax_undo() {
		$this->verify_request();

		$id     = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
		$result = $this->service->undo($id);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$message = __('Consolidation undone.', 'ai-post-scheduler');
		if (!empty($result['warnings'])) {
			$message .= ' ' . implode(' ', $result['warnings']);
		}

		AIPS_Ajax_Response::success(array(
			'message'        => $message,
			'warnings'       => $result['warnings'],
			'consolidations' => $this->service->get_history(),
		));
	}

	/**
	 * AJAX: recent consolidations.
	 *
	 * @return void
	 */
	public function ajax_history() {
		$this->verify_request();

		AIPS_Ajax_Response::success(array('consolidations' => $this->service->get_history()));
	}

	/**
	 * Keep and retire IDs from the request.
	 *
	 * @return int[]
	 */
	private function read_pair(): array {
		return array(
			isset($_POST['keep_id']) ? absint($_POST['keep_id']) : 0,
			isset($_POST['retire_id']) ? absint($_POST['retire_id']) : 0,
		);
	}

	/**
	 * Nonce and capability gate shared by every endpoint.
	 *
	 * @return void
	 */
	private function verify_request() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed. Please refresh the page.', 'ai-post-scheduler'), 'invalid_nonce');
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
