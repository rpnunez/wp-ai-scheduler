<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Controller for managing prompt sections via AJAX in the WordPress admin.
 *
 * @package AI_Post_Scheduler
 * @subpackage Controllers
 * @since 1.0.0
 */
class AIPS_Prompt_Sections_Controller {

	/**
	 * Repository used to manage prompt section records.
	 *
	 * @var AIPS_Prompt_Section_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * Initializes the controller with a prompt section repository and
	 * registers the AJAX actions used to manage prompt sections.
	 *
	 * @param AIPS_Prompt_Section_Repository|null $repo Optional repository instance for dependency injection.
	 *
	 * @return void
	 */
	public function __construct($repo = null) {
		$this->repo = $repo ?: new AIPS_Prompt_Section_Repository();

		add_action('wp_ajax_aips_get_prompt_sections', array($this, 'ajax_get_sections'));
		add_action('wp_ajax_aips_get_prompt_section', array($this, 'ajax_get_section'));
		add_action('wp_ajax_aips_save_prompt_section', array($this, 'ajax_save_section'));
		add_action('wp_ajax_aips_delete_prompt_section', array($this, 'ajax_delete_section'));
		add_action('wp_ajax_aips_toggle_prompt_section_active', array($this, 'ajax_toggle_section_active'));
	}

	public function ajax_get_sections() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$sections = $this->repo->get_all(false);
		AIPS_Ajax_Response::success(array('sections' => $sections));
	}

	public function ajax_get_section() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid section ID.', 'ai-post-scheduler'));
		}

		$section = $this->repo->get_by_id($id);
		if (!$section) {
			AIPS_Ajax_Response::error(__('Section not found.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array('section' => $section));
	}

	public function ajax_save_section() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$section_key = isset($_POST['section_key']) ? sanitize_key(wp_unslash($_POST['section_key'])) : '';
		$content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
		$is_active = isset($_POST['is_active']) ? 1 : 0;

		if (empty($name) || empty($section_key) || empty($content)) {
			AIPS_Ajax_Response::error(__('Name, key, and content are required.', 'ai-post-scheduler'));
		}

		if ($this->repo->key_exists($section_key, $id)) {
			AIPS_Ajax_Response::error(__('Section key already exists.', 'ai-post-scheduler'));
		}

		$data = array(
			'name' => $name,
			'description' => $description,
			'section_key' => $section_key,
			'content' => $content,
			'is_active' => $is_active,
		);

		if ($id) {
			$result = $this->repo->update($id, $data);
			if (!$result) {
				AIPS_Ajax_Response::error(__('Failed to update prompt section.', 'ai-post-scheduler'));
			}
			$section = $this->repo->get_by_id($id);
			AIPS_Ajax_Response::success(array('message' => __('Section updated.', 'ai-post-scheduler'), 'section_id' => $id, 'section' => $section));
		}

		$new_id = $this->repo->create($data);
		if (!$new_id) {
			AIPS_Ajax_Response::error(__('Failed to create prompt section.', 'ai-post-scheduler'));
		}
		$section = $this->repo->get_by_id($new_id);
		AIPS_Ajax_Response::success(array('message' => __('Section created.', 'ai-post-scheduler'), 'section_id' => $new_id, 'section' => $section));
	}

	public function ajax_delete_section() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid section ID.', 'ai-post-scheduler'));
		}

		$result = $this->repo->delete($id);
		if (!$result) {
			AIPS_Ajax_Response::error(__('Failed to delete prompt section.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Section deleted.', 'ai-post-scheduler'));
	}

	public function ajax_toggle_section_active() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		$is_active = isset($_POST['is_active']) ? 1 : 0;

		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid section ID.', 'ai-post-scheduler'));
		}

		$result = $this->repo->set_active($id, $is_active);
		if (!$result) {
			AIPS_Ajax_Response::error(__('Failed to update active status.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Section status updated.', 'ai-post-scheduler'));
	}
}

