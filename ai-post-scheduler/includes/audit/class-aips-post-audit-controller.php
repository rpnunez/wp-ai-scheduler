<?php
/**
 * Post Audit AJAX Controller
 *
 * Handles AJAX endpoints for scanning stale posts, reviewing/approving staging
 * revisions, and toggling a post's immutable (never-refresh) flag.
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
		if ($audit_service === null) {
			$container = AIPS_Container::get_instance();
			$audit_service = $container->has(AIPS_Post_Audit_Service::class)
				? $container->make(AIPS_Post_Audit_Service::class)
				: new AIPS_Post_Audit_Service();
		}

		$this->audit_service = $audit_service;

		add_action('wp_ajax_aips_run_audit_scan', array($this, 'ajax_run_audit_scan'));
		add_action('wp_ajax_aips_get_pending_revisions', array($this, 'ajax_get_pending_revisions'));
		add_action('wp_ajax_aips_approve_revision', array($this, 'ajax_approve_revision'));
		add_action('wp_ajax_aips_reject_revision', array($this, 'ajax_reject_revision'));
		add_action('wp_ajax_aips_set_post_immutable', array($this, 'ajax_set_post_immutable'));
		add_action('wp_ajax_aips_get_post_audit_log', array($this, 'ajax_get_post_audit_log'));
	}

	/**
	 * Run an audit scan on older posts.
	 *
	 * Both `days_old` and `limit` are optional overrides; when omitted the
	 * configured Settings values are used. `limit` is clamped to the configured
	 * maximum so a crafted request cannot start an unbounded generation run.
	 */
	public function ajax_run_audit_scan(): void {
		$this->verify_permissions();

		$config = $this->audit_service->get_config();

		$days = isset($_POST['days_old']) && $_POST['days_old'] !== ''
			? max(1, min(3650, absint(wp_unslash($_POST['days_old']))))
			: $config['stale_days'];

		$limit = isset($_POST['limit']) && $_POST['limit'] !== ''
			? max(1, min($config['max_batch_limit'], absint(wp_unslash($_POST['limit']))))
			: $config['batch_limit'];

		$scan = $this->audit_service->run_scan($days, $limit);

		AIPS_Ajax_Response::success(
			array(
				'scanned'       => $scan['scanned'],
				'created_count' => $scan['created'],
				'skipped_count' => $scan['skipped'],
				'revision_ids'  => $scan['revision_ids'],
				'days_old'      => $days,
				'limit'         => $limit,
				'errors'        => $scan['errors'],
			),
			sprintf(
				/* translators: %d: number of staging revisions created. */
				_n(
					'Audit scan completed: %d staging revision created.',
					'Audit scan completed: %d staging revisions created.',
					$scan['created'],
					'ai-post-scheduler'
				),
				$scan['created']
			)
		);
	}

	/**
	 * Get list of pending staging revisions.
	 */
	public function ajax_get_pending_revisions(): void {
		$this->verify_permissions();

		$args = array(
			'post_type'           => 'any',
			'post_status'         => 'draft',
			'posts_per_page'      => 50,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				array(
					'key'   => AIPS_Post_Audit_Service::META_IS_STAGING,
					'value' => '1',
				),
			),
		);

		$revisions = get_posts($args);
		$data = array();

		foreach ($revisions as $rev) {
			$target_id = (int) get_post_meta($rev->ID, AIPS_Post_Audit_Service::META_TARGET_POST_ID, true);
			$target = get_post($target_id);

			$data[] = array(
				'revision_id'      => (int) $rev->ID,
				'target_id'        => $target_id,
				'target_title'     => $target ? $target->post_title : __('(Deleted Post)', 'ai-post-scheduler'),
				'target_edit_url'  => $target ? get_edit_post_link($target_id, 'raw') : '',
				'revision_title'   => $rev->post_title,
				'revision_edit_url'=> get_edit_post_link($rev->ID, 'raw'),
				'proposed_at'      => get_post_meta($rev->ID, AIPS_Post_Audit_Service::META_PROPOSED_AT, true),
				'target_content'   => $target ? $target->post_content : '',
				'revision_content' => $rev->post_content,
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

		$revision_id = $this->get_revision_id_from_request();

		$result = $this->audit_service->approve_revision($revision_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
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

		$revision_id = $this->get_revision_id_from_request();

		$result = $this->audit_service->reject_revision($revision_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
			return;
		}

		AIPS_Ajax_Response::success(array(
			'revision_id' => $revision_id,
		), __('Staging revision dismissed.', 'ai-post-scheduler'));
	}

	/**
	 * Toggle a post's immutable flag.
	 *
	 * Requires edit rights on the specific post, not just manage_options, so the
	 * capability check is per-object here.
	 */
	public function ajax_set_post_immutable(): void {
		$this->verify_nonce();

		$post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;

		if (!$post_id || !get_post($post_id)) {
			AIPS_Ajax_Response::invalid_request(__('Missing or invalid post ID.', 'ai-post-scheduler'));
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		$immutable = isset($_POST['immutable']) && in_array((string) wp_unslash($_POST['immutable']), array('1', 'true', 'yes'), true);

		$this->audit_service->set_immutable($post_id, $immutable);

		AIPS_Ajax_Response::success(
			array(
				'post_id'   => $post_id,
				'immutable' => $immutable,
			),
			$immutable
				? __('Post marked immutable. The Post Refresher will skip it.', 'ai-post-scheduler')
				: __('Post is no longer immutable. The Post Refresher may update it.', 'ai-post-scheduler')
		);
	}

	/**
	 * Return the refresher audit log recorded on a post.
	 */
	public function ajax_get_post_audit_log(): void {
		$this->verify_nonce();

		$post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;

		if (!$post_id || !get_post($post_id)) {
			AIPS_Ajax_Response::invalid_request(__('Missing or invalid post ID.', 'ai-post-scheduler'));
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		AIPS_Ajax_Response::success(array(
			'post_id'     => $post_id,
			'immutable'   => $this->audit_service->is_immutable($post_id),
			'last_check'  => get_post_meta($post_id, AIPS_Post_Audit_Service::META_LAST_AUDITED_AT, true),
			'check_count' => (int) get_post_meta($post_id, AIPS_Post_Audit_Service::META_AUDIT_COUNT, true),
			'entries'     => $this->audit_service->get_audit_log($post_id),
		));
	}

	/**
	 * Read and validate the revision ID from the request.
	 *
	 * Sends an error response and exits when the ID is missing.
	 *
	 * @return int
	 */
	private function get_revision_id_from_request(): int {
		$revision_id = isset($_POST['revision_id']) ? absint(wp_unslash($_POST['revision_id'])) : 0;

		if (!$revision_id) {
			AIPS_Ajax_Response::invalid_request(__('Missing revision ID.', 'ai-post-scheduler'));
		}

		return $revision_id;
	}

	/**
	 * Verify the AJAX nonce, sending a 403 response when it fails.
	 *
	 * @return void
	 */
	private function verify_nonce(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed.', 'ai-post-scheduler'), 'invalid_nonce', 403);
		}
	}

	/**
	 * Verify nonce and site-level capability.
	 *
	 * @return void
	 */
	private function verify_permissions(): void {
		$this->verify_nonce();

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
