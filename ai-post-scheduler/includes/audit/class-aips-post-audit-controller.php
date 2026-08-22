<?php
/**
 * Post Audit AJAX Controller
 *
 * Handles AJAX endpoints for scanning stale posts and reviewing/approving
 * staging revisions.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Audit_Controller {

	/**
	 * @var AIPS_Post_Audit_Service
	 */
	private $audit_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Post_Audit_Service|null $audit_service Optional audit service.
	 */
	public function __construct(?AIPS_Post_Audit_Service $audit_service = null) {
		$this->audit_service = $audit_service ?: new AIPS_Post_Audit_Service();

		add_action('wp_ajax_aips_run_audit_scan', array($this, 'ajax_run_audit_scan'));
		add_action('wp_ajax_aips_get_pending_revisions', array($this, 'ajax_get_pending_revisions'));
		add_action('wp_ajax_aips_approve_revision', array($this, 'ajax_approve_revision'));
		add_action('wp_ajax_aips_reject_revision', array($this, 'ajax_reject_revision'));
	}

	/**
	 * Run an audit scan on older posts.
	 */
	public function ajax_run_audit_scan(): void {
		$this->verify_permissions();

		$days = isset($_POST['days_old']) ? absint($_POST['days_old']) : 180;
		$limit = isset($_POST['limit']) ? min(absint($_POST['limit']), 20) : 5;

		$stale_posts = $this->audit_service->find_stale_posts($days, $limit);
		$created_count = 0;
		$errors = array();

		foreach ($stale_posts as $post) {
			$result = $this->audit_service->create_staging_revision($post->ID);
			if (is_wp_error($result)) {
				$errors[] = sprintf('Post #%d: %s', $post->ID, $result->get_error_message());
			} else {
				$created_count++;
			}
		}

		AIPS_Ajax_Response::success(array(
			'scanned'       => count($stale_posts),
			'created_count' => $created_count,
			'errors'        => $errors,
		), sprintf(__('Audit scan completed: %d staging revision(s) created.', 'ai-post-scheduler'), $created_count));
	}

	/**
	 * Get list of pending staging revisions.
	 */
	public function ajax_get_pending_revisions(): void {
		$this->verify_permissions();

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'posts_per_page' => 50,
			'meta_key'       => '_aips_is_staging_revision',
			'meta_value'     => '1',
		);

		$revisions = get_posts($args);
		$data = array();

		foreach ($revisions as $rev) {
			$target_id = (int) get_post_meta($rev->ID, '_aips_target_post_id', true);
			$target = get_post($target_id);

			$data[] = array(
				'revision_id'     => $rev->ID,
				'target_id'       => $target_id,
				'target_title'    => $target ? $target->post_title : __('(Deleted Post)', 'ai-post-scheduler'),
				'revision_title'  => $rev->post_title,
				'proposed_at'     => get_post_meta($rev->ID, '_aips_revision_proposed_at', true),
				'target_content'  => $target ? $target->post_content : '',
				'revision_content'=> $rev->post_content,
			);
		}

		AIPS_Ajax_Response::success(array(
			'revisions' => $data,
		));
	}

	/**
	 * Approve a staging revision and merge it to live.
	 */
	public function ajax_approve_revision(): void {
		$this->verify_permissions();

		$revision_id = isset($_POST['revision_id']) ? absint($_POST['revision_id']) : 0;
		if (!$revision_id) {
			AIPS_Ajax_Response::error(__('Missing revision ID.', 'ai-post-scheduler'), 400);
			return;
		}

		$result = $this->audit_service->approve_revision($revision_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
			return;
		}

		AIPS_Ajax_Response::success(array(
			'revision_id' => $revision_id,
		), __('Revision successfully applied to live post.', 'ai-post-scheduler'));
	}

	/**
	 * Reject and discard a staging revision.
	 */
	public function ajax_reject_revision(): void {
		$this->verify_permissions();

		$revision_id = isset($_POST['revision_id']) ? absint($_POST['revision_id']) : 0;
		if (!$revision_id) {
			AIPS_Ajax_Response::error(__('Missing revision ID.', 'ai-post-scheduler'), 400);
			return;
		}

		$this->audit_service->reject_revision($revision_id);

		AIPS_Ajax_Response::success(array(
			'revision_id' => $revision_id,
		), __('Staging revision dismissed.', 'ai-post-scheduler'));
	}

	/**
	 * Verify permissions and nonce.
	 */
	private function verify_permissions(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false) && !check_ajax_referer('aips_admin_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed.', 'ai-post-scheduler'), 403);
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::error(__('Unauthorized.', 'ai-post-scheduler'), 403);
		}
	}
}
