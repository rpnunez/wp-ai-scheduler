<?php
/**
 * Review Workflow Controller
 *
 * Admin UI + AJAX endpoints for the multi-stage post review workflow.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Review_Workflow_Controller {

	/**
	 * @var AIPS_Review_Workflow_Repository
	 */
	private $repository;

	public function __construct($repository = null) {
		$this->repository = $repository instanceof AIPS_Review_Workflow_Repository ? $repository : new AIPS_Review_Workflow_Repository();

		add_action('wp_ajax_aips_review_workflow_get_item', array($this, 'ajax_get_item'));
		add_action('wp_ajax_aips_review_workflow_update_item_meta', array($this, 'ajax_update_item_meta'));
		add_action('wp_ajax_aips_review_workflow_set_stage', array($this, 'ajax_set_stage'));
		add_action('wp_ajax_aips_review_workflow_approve_stage', array($this, 'ajax_approve_stage'));
		add_action('wp_ajax_aips_review_workflow_request_changes', array($this, 'ajax_request_changes'));
		add_action('wp_ajax_aips_review_workflow_skip_stage', array($this, 'ajax_skip_stage'));
		add_action('wp_ajax_aips_review_workflow_toggle_checklist', array($this, 'ajax_toggle_checklist'));
		add_action('wp_ajax_aips_review_workflow_save_stage_notes', array($this, 'ajax_save_stage_notes'));
		add_action('wp_ajax_aips_review_workflow_add_comment', array($this, 'ajax_add_comment'));
		add_action('wp_ajax_aips_review_workflow_publish_now', array($this, 'ajax_publish_now'));
		add_action('wp_ajax_aips_review_workflow_schedule', array($this, 'ajax_schedule'));
		add_action('wp_ajax_aips_review_workflow_archive', array($this, 'ajax_archive'));
	}

	/**
	 * Render the Review Workflow admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied.', 'ai-post-scheduler'));
		}

		$stage       = isset($_GET['stage']) ? sanitize_key(wp_unslash($_GET['stage'])) : '';
		$closed      = isset($_GET['closed_state']) ? sanitize_key(wp_unslash($_GET['closed_state'])) : 'open';
		$assigned_to = isset($_GET['assigned_to']) ? absint($_GET['assigned_to']) : 0;
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
		$search      = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$page        = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

		if (!in_array($stage, AIPS_Review_Workflow_Repository::get_stages(), true)) {
			$stage = '';
		}

		if (!in_array($closed, array('open','scheduled','published','archived'), true)) {
			$closed = 'open';
		}

		$items = $this->repository->get_items(array(
			'page'         => $page,
			'per_page'     => 20,
			'stage'        => $stage,
			'closed_state' => $closed,
			'assigned_to'  => $assigned_to,
			'template_id'  => $template_id,
			'search'       => $search,
		));

		$template_repository = new AIPS_Template_Repository();
		$templates           = $template_repository->get_all();

		$users = get_users(array(
			'fields' => array('ID','display_name'),
		));

		$counts = $this->repository->get_stage_counts();

		$stages = self::get_stage_definitions();

		include AIPS_PLUGIN_DIR . 'templates/admin/review-workflow.php';
	}

	/**
	 * Stage definitions for UI (labels + checklist items).
	 *
	 * @return array
	 */
	public static function get_stage_definitions() {
		return array(
			'brief' => array(
				'label' => __('Needs Brief Approval', 'ai-post-scheduler'),
				'checklist' => array(
					array('key' => 'audience', 'label' => __('Audience is clear', 'ai-post-scheduler')),
					array('key' => 'angle', 'label' => __('Angle / goal defined', 'ai-post-scheduler')),
					array('key' => 'cta', 'label' => __('CTA specified', 'ai-post-scheduler')),
				),
			),
			'outline' => array(
				'label' => __('Needs Outline Approval', 'ai-post-scheduler'),
				'checklist' => array(
					array('key' => 'structure', 'label' => __('Outline matches structure', 'ai-post-scheduler')),
					array('key' => 'coverage', 'label' => __('Key points covered', 'ai-post-scheduler')),
					array('key' => 'links', 'label' => __('Internal links identified', 'ai-post-scheduler')),
				),
			),
			'fact_check' => array(
				'label' => __('Needs Fact Check', 'ai-post-scheduler'),
				'checklist' => array(
					array('key' => 'claims', 'label' => __('Claims verified', 'ai-post-scheduler')),
					array('key' => 'numbers', 'label' => __('Stats / numbers verified', 'ai-post-scheduler')),
					array('key' => 'sources', 'label' => __('Sources reviewed', 'ai-post-scheduler')),
				),
			),
			'seo' => array(
				'label' => __('Needs SEO Pass', 'ai-post-scheduler'),
				'checklist' => array(
					array('key' => 'title', 'label' => __('Title optimized', 'ai-post-scheduler')),
					array('key' => 'headings', 'label' => __('Headings organized', 'ai-post-scheduler')),
					array('key' => 'meta', 'label' => __('Excerpt / snippet ready', 'ai-post-scheduler')),
				),
			),
			'ready' => array(
				'label' => __('Ready to Schedule', 'ai-post-scheduler'),
				'checklist' => array(
					array('key' => 'final_read', 'label' => __('Final read-through complete', 'ai-post-scheduler')),
					array('key' => 'formatting', 'label' => __('Formatting checked', 'ai-post-scheduler')),
					array('key' => 'image', 'label' => __('Featured image checked', 'ai-post-scheduler')),
				),
			),
		);
	}

	private function check_ajax() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
	}

	public function ajax_get_item() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$post_id        = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		$item = $review_item_id ? $this->repository->get_item_row($review_item_id) : null;
		if (!$item && $post_id) {
			$by_post = $this->repository->get_item_row_by_post_id($post_id);
			$item    = $by_post ? $this->repository->get_item_row((int) $by_post->id) : null;
		}

		if (!$item) {
			wp_send_json_error(array('message' => __('Item not found.', 'ai-post-scheduler')));
		}

		$this->repository->sync_closed_state_from_post_status((int) $item->post_id);

		$stage_rows = $this->repository->get_stage_rows((int) $item->id);
		$comments   = $this->repository->get_comments((int) $item->id);
		$stages     = self::get_stage_definitions();

		$preview = array(
			'title'          => get_the_title((int) $item->post_id),
			'content'        => apply_filters('the_content', (string) $item->post_content),
			'excerpt'        => get_the_excerpt((int) $item->post_id),
			'featured_image' => esc_url_raw(get_the_post_thumbnail_url((int) $item->post_id, 'full')),
			'edit_url'       => esc_url_raw(get_edit_post_link((int) $item->post_id)),
		);

		$stage_payload = array();
		foreach (AIPS_Review_Workflow_Repository::get_stages() as $stage_key) {
			$row = isset($stage_rows[$stage_key]) ? $stage_rows[$stage_key] : null;
			$check_state = array();
			if ($row && !empty($row->checklist_state)) {
				$tmp = json_decode((string) $row->checklist_state, true);
				if (is_array($tmp)) {
					$check_state = $tmp;
				}
			}
			$stage_payload[$stage_key] = array(
				'state'          => $row ? (string) $row->state : 'pending',
				'notes'          => $row ? (string) $row->notes : '',
				'checklist_state'=> $check_state,
				'reviewed_by'    => $row ? (int) $row->reviewed_by : 0,
				'reviewed_at'    => $row ? (string) $row->reviewed_at : '',
			);
		}

		$comment_payload = array();
		foreach ($comments as $c) {
			$user_label = '';
			if (!empty($c->user_id)) {
				$u = get_userdata((int) $c->user_id);
				$user_label = $u ? $u->display_name : '';
			}
			$comment_payload[] = array(
				'id'         => (int) $c->id,
				'user_id'    => (int) $c->user_id,
				'user_label' => $user_label,
				'comment'    => (string) $c->comment,
				'created_at' => (string) $c->created_at,
			);
		}

		wp_send_json_success(array(
			'item' => array(
				'id'           => (int) $item->id,
				'post_id'      => (int) $item->post_id,
				'post_title'   => (string) $item->post_title,
				'post_status'  => (string) $item->post_status,
				'template_id'  => (int) $item->template_id,
				'template_name'=> (string) $item->template_name,
				'stage'        => (string) $item->stage,
				'stage_state'  => (string) $item->stage_state,
				'assigned_to'  => (int) $item->assigned_to,
				'priority'     => (string) $item->priority,
				'due_at'       => (string) $item->due_at,
				'closed_state' => (string) $item->closed_state,
			),
			'preview'  => $preview,
			'stages'   => $stages,
			'stage_data' => $stage_payload,
			'comments' => $comment_payload,
		));
	}

	public function ajax_update_item_meta() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		if (!$review_item_id) {
			wp_send_json_error(array('message' => __('Invalid item.', 'ai-post-scheduler')));
		}

		$assigned_to = isset($_POST['assigned_to']) ? absint($_POST['assigned_to']) : null;
		$priority    = isset($_POST['priority']) ? sanitize_key(wp_unslash($_POST['priority'])) : null;
		$due_at      = isset($_POST['due_at']) ? sanitize_text_field(wp_unslash($_POST['due_at'])) : null;

		$fields = array();
		if (null !== $assigned_to) {
			$fields['assigned_to'] = $assigned_to;
		}
		if (null !== $priority) {
			$fields['priority'] = $priority;
		}
		if (null !== $due_at) {
			$fields['due_at'] = $due_at;
		}

		$ok = $this->repository->update_item_meta($review_item_id, $fields);
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to update item.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Updated.', 'ai-post-scheduler')));
	}

	public function ajax_set_stage() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$stage_key      = isset($_POST['stage_key']) ? sanitize_key(wp_unslash($_POST['stage_key'])) : '';

		if (!$review_item_id || !$stage_key) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$ok = $this->repository->set_stage($review_item_id, $stage_key);
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to update stage.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Stage updated.', 'ai-post-scheduler')));
	}

	private function get_missing_checklist_keys($review_item_id, $stage_key) {
		$review_item_id = absint($review_item_id);
		$stage_key      = sanitize_key($stage_key);

		$defs = self::get_stage_definitions();
		if (!isset($defs[$stage_key])) {
			return array();
		}

		$rows = $this->repository->get_stage_rows($review_item_id);
		$row  = isset($rows[$stage_key]) ? $rows[$stage_key] : null;

		$state = array();
		if ($row && !empty($row->checklist_state)) {
			$tmp = json_decode((string) $row->checklist_state, true);
			if (is_array($tmp)) {
				$state = $tmp;
			}
		}

		$missing = array();
		foreach ($defs[$stage_key]['checklist'] as $check) {
			$key = $check['key'];
			if (empty($state[$key])) {
				$missing[] = $key;
			}
		}

		return $missing;
	}

	public function ajax_approve_stage() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$stage_key      = isset($_POST['stage_key']) ? sanitize_key(wp_unslash($_POST['stage_key'])) : '';
		$notes          = isset($_POST['notes']) ? wp_kses_post(wp_unslash($_POST['notes'])) : '';
		$force          = isset($_POST['force']) ? (bool) absint($_POST['force']) : false;

		if (!$review_item_id || !$stage_key) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$missing = $this->get_missing_checklist_keys($review_item_id, $stage_key);
		if (!empty($missing) && !$force) {
			wp_send_json_error(array(
				'code'    => 'checklist_incomplete',
				'message' => __('Checklist is incomplete for this stage.', 'ai-post-scheduler'),
				'missing' => $missing,
			));
		}

		$ok = $this->repository->set_stage_state($review_item_id, $stage_key, 'approved', $notes, get_current_user_id(), true);
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to approve stage.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Stage approved.', 'ai-post-scheduler')));
	}

	public function ajax_request_changes() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$stage_key      = isset($_POST['stage_key']) ? sanitize_key(wp_unslash($_POST['stage_key'])) : '';
		$notes          = isset($_POST['notes']) ? wp_kses_post(wp_unslash($_POST['notes'])) : '';

		if (!$review_item_id || !$stage_key) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$ok = $this->repository->set_stage_state($review_item_id, $stage_key, 'changes_requested', $notes, get_current_user_id(), false);
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to request changes.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Changes requested.', 'ai-post-scheduler')));
	}

	public function ajax_skip_stage() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$stage_key      = isset($_POST['stage_key']) ? sanitize_key(wp_unslash($_POST['stage_key'])) : '';
		$notes          = isset($_POST['notes']) ? wp_kses_post(wp_unslash($_POST['notes'])) : '';

		if (!$review_item_id || !$stage_key) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$ok = $this->repository->set_stage_state($review_item_id, $stage_key, 'skipped', $notes, get_current_user_id(), true);
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to skip stage.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Stage skipped.', 'ai-post-scheduler')));
	}

	public function ajax_toggle_checklist() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$stage_key      = isset($_POST['stage_key']) ? sanitize_key(wp_unslash($_POST['stage_key'])) : '';
		$check_key      = isset($_POST['check_key']) ? sanitize_key(wp_unslash($_POST['check_key'])) : '';
		$checked        = isset($_POST['checked']) ? (bool) absint($_POST['checked']) : false;

		if (!$review_item_id || !$stage_key || !$check_key) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$updated = $this->repository->toggle_checklist_item($review_item_id, $stage_key, $check_key, $checked);
		wp_send_json_success(array('checklist_state' => $updated));
	}

	public function ajax_save_stage_notes() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$stage_key      = isset($_POST['stage_key']) ? sanitize_key(wp_unslash($_POST['stage_key'])) : '';
		$notes          = isset($_POST['notes']) ? wp_kses_post(wp_unslash($_POST['notes'])) : '';

		if (!$review_item_id || !$stage_key) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$ok = $this->repository->save_stage_notes($review_item_id, $stage_key, $notes);
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to save notes.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Saved.', 'ai-post-scheduler')));
	}

	public function ajax_add_comment() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$comment        = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

		if (!$review_item_id || '' === $comment) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$id = $this->repository->add_comment($review_item_id, get_current_user_id(), $comment);
		if (!$id) {
			wp_send_json_error(array('message' => __('Failed to add comment.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Comment added.', 'ai-post-scheduler')));
	}

	public function ajax_publish_now() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		if (!$review_item_id) {
			wp_send_json_error(array('message' => __('Invalid item.', 'ai-post-scheduler')));
		}

		$item = $this->repository->get_item_row($review_item_id);
		if (!$item) {
			wp_send_json_error(array('message' => __('Item not found.', 'ai-post-scheduler')));
		}

		if (!current_user_can('publish_post', (int) $item->post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to publish this post.', 'ai-post-scheduler')));
		}

		$result = wp_update_post(array(
			'ID'          => (int) $item->post_id,
			'post_status' => 'publish',
		), true);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		$this->repository->close_item($review_item_id, 'published');

		wp_send_json_success(array('message' => __('Post published.', 'ai-post-scheduler')));
	}

	public function ajax_schedule() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		$schedule_at    = isset($_POST['schedule_at']) ? sanitize_text_field(wp_unslash($_POST['schedule_at'])) : '';

		if (!$review_item_id || '' === $schedule_at) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		$item = $this->repository->get_item_row($review_item_id);
		if (!$item) {
			wp_send_json_error(array('message' => __('Item not found.', 'ai-post-scheduler')));
		}

		$schedule_at = str_replace('T', ' ', $schedule_at);

		try {
			$tz = wp_timezone();
			$dt = new DateTimeImmutable($schedule_at, $tz);
		} catch (Exception $e) {
			wp_send_json_error(array('message' => __('Invalid date/time.', 'ai-post-scheduler')));
		}

		$local = $dt->format('Y-m-d H:i:s');
		$gmt   = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

		$result = wp_update_post(array(
			'ID'            => (int) $item->post_id,
			'post_status'   => 'future',
			'post_date'     => $local,
			'post_date_gmt' => $gmt,
		), true);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		$this->repository->close_item($review_item_id, 'scheduled');

		wp_send_json_success(array('message' => __('Post scheduled.', 'ai-post-scheduler')));
	}

	public function ajax_archive() {
		$this->check_ajax();

		$review_item_id = isset($_POST['review_item_id']) ? absint($_POST['review_item_id']) : 0;
		if (!$review_item_id) {
			wp_send_json_error(array('message' => __('Invalid item.', 'ai-post-scheduler')));
		}

		$ok = $this->repository->close_item($review_item_id, 'archived');
		if (!$ok) {
			wp_send_json_error(array('message' => __('Failed to archive.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Archived.', 'ai-post-scheduler')));
	}
}
