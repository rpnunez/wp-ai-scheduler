<?php
/**
 * Story Budget Controller
 *
 * Handles admin interactions for the editorial planning layer.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Story_Budget_Controller {

	/**
	 * @var AIPS_Story_Budget_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Trending_Topics_Repository
	 */
	private $research_repository;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $author_topics_repository;

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repository;

	public function __construct() {
		$this->repository = new AIPS_Story_Budget_Repository();
		$this->research_repository = new AIPS_Trending_Topics_Repository();
		$this->author_topics_repository = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();

		static $hooks_registered = false;
		if (!$hooks_registered) {
			add_action('admin_post_aips_save_story_budget', array($this, 'handle_save'));
			add_action('admin_post_aips_delete_story_budget', array($this, 'handle_delete'));
			$hooks_registered = true;
		}
	}

	public function render_page() {
		$filters = $this->get_filters_from_request();
		$items = $this->repository->get_all($filters);
		$total_items = $this->repository->count_all($filters);
		$stats = $this->repository->get_stats();
		$beats = $this->repository->get_beats();
		$users = get_users(array(
			'orderby' => 'display_name',
			'order' => 'ASC',
			'fields' => array('ID', 'display_name'),
		));

		$approved_topics = $this->author_topics_repository->get_all_approved_for_queue();
		$research_entries = $this->research_repository->get_top_topics(15, 30);
		$form_defaults = $this->get_form_defaults();
		$editing_item = $this->get_editing_item();

		include AIPS_PLUGIN_DIR . 'templates/admin/story-budget.php';
	}

	public function handle_save() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied.', 'ai-post-scheduler'));
		}

		check_admin_referer('aips_save_story_budget');

		$item_id = isset($_POST['story_budget_id']) ? absint($_POST['story_budget_id']) : 0;
		$data = array(
			'title' => isset($_POST['title']) ? wp_unslash($_POST['title']) : '',
			'beat' => isset($_POST['beat']) ? wp_unslash($_POST['beat']) : '',
			'desk' => isset($_POST['desk']) ? wp_unslash($_POST['desk']) : '',
			'story_type' => isset($_POST['story_type']) ? wp_unslash($_POST['story_type']) : 'feature',
			'priority' => isset($_POST['priority']) ? wp_unslash($_POST['priority']) : 'medium',
			'assigned_editor_user_id' => isset($_POST['assigned_editor_user_id']) ? absint($_POST['assigned_editor_user_id']) : 0,
			'assigned_writer_user_id' => isset($_POST['assigned_writer_user_id']) ? absint($_POST['assigned_writer_user_id']) : 0,
			'due_at' => isset($_POST['due_at']) ? wp_unslash($_POST['due_at']) : '',
			'publish_window_start' => isset($_POST['publish_window_start']) ? wp_unslash($_POST['publish_window_start']) : '',
			'publish_window_end' => isset($_POST['publish_window_end']) ? wp_unslash($_POST['publish_window_end']) : '',
			'source_topic_id' => isset($_POST['source_topic_id']) ? absint($_POST['source_topic_id']) : 0,
			'source_research_id' => isset($_POST['source_research_id']) ? absint($_POST['source_research_id']) : 0,
			'source_type' => isset($_POST['source_type']) ? wp_unslash($_POST['source_type']) : 'manual',
			'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'pitched',
			'notes' => isset($_POST['notes']) ? wp_unslash($_POST['notes']) : '',
		);

		if (empty(trim($data['title']))) {
			$this->redirect_with_notice('error', __('A title is required for each story budget item.', 'ai-post-scheduler'));
		}

		$result = $item_id > 0 ? $this->repository->update($item_id, $data) : $this->repository->create($data);

		if (false === $result) {
			$this->redirect_with_notice('error', __('Unable to save the story budget item.', 'ai-post-scheduler'));
		}

		$message = $item_id > 0 ? __('Story budget item updated.', 'ai-post-scheduler') : __('Story budget item created.', 'ai-post-scheduler');
		$this->redirect_with_notice('success', $message);
	}

	public function handle_delete() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied.', 'ai-post-scheduler'));
		}

		check_admin_referer('aips_delete_story_budget');
		$item_id = isset($_POST['story_budget_id']) ? absint($_POST['story_budget_id']) : 0;

		if (!$item_id) {
			$this->redirect_with_notice('error', __('Invalid story budget item.', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($item_id);
		if (false === $result) {
			$this->redirect_with_notice('error', __('Unable to delete the story budget item.', 'ai-post-scheduler'));
		}

		$this->redirect_with_notice('success', __('Story budget item deleted.', 'ai-post-scheduler'));
	}

	private function get_filters_from_request() {
		$publish_start = isset($_GET['publish_window_start']) ? sanitize_text_field(wp_unslash($_GET['publish_window_start'])) : '';
		$publish_end = isset($_GET['publish_window_end']) ? sanitize_text_field(wp_unslash($_GET['publish_window_end'])) : '';

		if (!empty($publish_start) && strlen($publish_start) === 10) {
			$publish_start .= ' 00:00:00';
		}
		if (!empty($publish_end) && strlen($publish_end) === 10) {
			$publish_end .= ' 23:59:59';
		}

		return array(
			'beat' => isset($_GET['beat']) ? sanitize_text_field(wp_unslash($_GET['beat'])) : '',
			'assignee' => isset($_GET['assignee']) ? absint($_GET['assignee']) : 0,
			'priority' => isset($_GET['priority']) ? sanitize_key(wp_unslash($_GET['priority'])) : '',
			'status' => isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '',
			'publish_window_start' => $publish_start,
			'publish_window_end' => $publish_end,
			'limit' => 100,
			'offset' => 0,
		);
	}

	private function get_form_defaults() {
		$defaults = (object) array(
			'id' => 0,
			'title' => '',
			'beat' => '',
			'desk' => '',
			'story_type' => 'feature',
			'priority' => 'medium',
			'assigned_editor_user_id' => 0,
			'assigned_writer_user_id' => 0,
			'due_at' => '',
			'publish_window_start' => '',
			'publish_window_end' => '',
			'source_topic_id' => 0,
			'source_research_id' => 0,
			'source_type' => 'manual',
			'status' => 'pitched',
			'notes' => '',
		);

		$source_research_id = isset($_GET['source_research_id']) ? absint($_GET['source_research_id']) : 0;
		$source_topic_id = isset($_GET['source_topic_id']) ? absint($_GET['source_topic_id']) : 0;

		if ($source_research_id) {
			$entry = $this->research_repository->get_by_id($source_research_id);
			if (!empty($entry)) {
				$defaults->title = $entry['topic'];
				$defaults->beat = !empty($entry['niche']) ? $entry['niche'] : '';
				$defaults->desk = !empty($entry['niche']) ? $entry['niche'] : '';
				$defaults->story_type = 'analysis';
				$defaults->priority = ((int) $entry['score'] >= 90) ? 'urgent' : (((int) $entry['score'] >= 75) ? 'high' : 'medium');
				$defaults->source_research_id = $source_research_id;
				$defaults->source_type = 'research';
				$defaults->notes = !empty($entry['reason']) ? $entry['reason'] : '';
			}
		}

		if ($source_topic_id) {
			$topic = $this->author_topics_repository->get_by_id($source_topic_id);
			if (!empty($topic) && isset($topic->status) && 'approved' === $topic->status) {
				$author = $this->authors_repository->get_by_id($topic->author_id);
				$defaults->title = $topic->topic_title;
				$defaults->beat = !empty($author->field_niche) ? $author->field_niche : '';
				$defaults->desk = !empty($author->field_niche) ? $author->field_niche : '';
				$defaults->story_type = 'feature';
				$defaults->priority = ((int) $topic->score >= 85) ? 'high' : 'medium';
				$defaults->assigned_writer_user_id = !empty($author->post_author) ? absint($author->post_author) : 0;
				$defaults->source_topic_id = $source_topic_id;
				$defaults->source_type = 'author_topic';
				$defaults->notes = !empty($author->description) ? $author->description : '';
				$defaults->status = 'assigned';
			}
		}

		return $defaults;
	}

	private function get_editing_item() {
		$item_id = isset($_GET['edit_story_budget']) ? absint($_GET['edit_story_budget']) : 0;
		if (!$item_id) {
			return null;
		}

		return $this->repository->get_by_id($item_id);
	}

	private function redirect_with_notice($notice_type, $message) {
		$url = AIPS_Admin_Menu_Helper::get_page_url('story_budget', array(
			'notice_type' => sanitize_key($notice_type),
			'notice_message' => $message,
		));

		wp_safe_redirect($url);
		exit;
	}
}
