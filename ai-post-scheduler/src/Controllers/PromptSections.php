<?php
if (!defined('ABSPATH')) {
	exit;
}

namespace AIPS\Controllers;

use AIPS\Repository\PromptSection as PromptSectionRepository;

/**
 * Controller for managing prompt sections via AJAX in the WordPress admin.
 *
 * @package AI_Post_Scheduler
 * @subpackage Controllers
 * @since 1.0.0
 */
class PromptSections {

	/**
	 * Repository used to manage prompt section records.
	 *
	 * @var PromptSectionRepository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * Initializes the controller with a prompt section repository and
	 * registers the AJAX actions used to manage prompt sections.
	 *
	 * @param PromptSectionRepository|null $repo Optional repository instance for dependency injection.
	 *
	 * @return void
	 */
	public function __construct($repo = null) {
		$this->repo = $repo ?: new PromptSectionRepository();

		add_action('wp_ajax_aips_get_prompt_sections', array($this, 'ajax_get_sections'));
		add_action('wp_ajax_aips_get_prompt_section', array($this, 'ajax_get_section'));
		add_action('wp_ajax_aips_save_prompt_section', array($this, 'ajax_save_section'));
		add_action('wp_ajax_aips_delete_prompt_section', array($this, 'ajax_delete_section'));
		add_action('wp_ajax_aips_toggle_prompt_section_active', array($this, 'ajax_toggle_section_active'));
	}

	public function ajax_get_sections() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$sections = $this->repo->get_all(false);
		wp_send_json_success(array('sections' => $sections));
	}

	public function ajax_get_section() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid section ID.', 'ai-post-scheduler')));
		}

		$section = $this->repo->get_by_id($id);
		if (!$section) {
			wp_send_json_error(array('message' => __('Section not found.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('section' => $section));
	}

	public function ajax_save_section() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
		$section_key = isset($_POST['section_key']) ? sanitize_key($_POST['section_key']) : '';
		$content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
		$is_active = isset($_POST['is_active']) ? 1 : 0;

		if (empty($name) || empty($section_key) || empty($content)) {
			wp_send_json_error(array('message' => __('Name, key, and content are required.', 'ai-post-scheduler')));
		}

		if ($this->repo->key_exists($section_key, $id)) {
			wp_send_json_error(array('message' => __('Section key already exists.', 'ai-post-scheduler')));
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
				wp_send_json_error(array('message' => __('Failed to update prompt section.', 'ai-post-scheduler')));
			}

			wp_send_json_success(array('message' => __('Section updated.', 'ai-post-scheduler'), 'section_id' => $id));
		}

		$new_id = $this->repo->create($data);
		if (!$new_id) {
			wp_send_json_error(array('message' => __('Failed to create prompt section.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Section created.', 'ai-post-scheduler'), 'section_id' => $new_id));
	}

	public function ajax_delete_section() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid section ID.', 'ai-post-scheduler')));
		}

		$result = $this->repo->delete($id);
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to delete prompt section.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Section deleted.', 'ai-post-scheduler')));
	}

	public function ajax_toggle_section_active() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
		$is_active = isset($_POST['is_active']) ? 1 : 0;

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid section ID.', 'ai-post-scheduler')));
		}

		$result = $this->repo->set_active($id, $is_active);
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to update active status.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Section status updated.', 'ai-post-scheduler')));
	}
}
