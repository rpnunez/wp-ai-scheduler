<?php
/**
 * SEO Profiles Controller
 *
 * AJAX endpoints for managing reusable SEO profiles.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Profiles_Controller {

	/**
	 * @var AIPS_SEO_Profiles_Repository
	 */
	private $repo;

	/**
	 * @param AIPS_SEO_Profiles_Repository|null $repo Optional repository.
	 */
	public function __construct($repo = null) {
		$this->repo = $repo ?: AIPS_SEO_Profiles_Repository::instance();

		add_action('wp_ajax_aips_get_seo_profiles', array($this, 'ajax_get_seo_profiles'));
		add_action('wp_ajax_aips_get_seo_profile', array($this, 'ajax_get_seo_profile'));
		add_action('wp_ajax_aips_save_seo_profile', array($this, 'ajax_save_seo_profile'));
		add_action('wp_ajax_aips_delete_seo_profile', array($this, 'ajax_delete_seo_profile'));
		add_action('wp_ajax_aips_toggle_seo_profile', array($this, 'ajax_toggle_seo_profile'));
	}

	/**
	 * List all SEO profiles.
	 *
	 * @return void
	 */
	public function ajax_get_seo_profiles() {
		if (!$this->authorize()) {
			return;
		}

		$active_only = !empty($_POST['active_only']) && filter_var(wp_unslash($_POST['active_only']), FILTER_VALIDATE_BOOLEAN);
		$profiles = $this->repo->get_all($active_only);

		AIPS_Ajax_Response::success(array('profiles' => $profiles));
	}

	/**
	 * Get a single SEO profile.
	 *
	 * @return void
	 */
	public function ajax_get_seo_profile() {
		if (!$this->authorize()) {
			return;
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::invalid_request(__('Profile ID is required.', 'ai-post-scheduler'));
			return;
		}

		$profile = $this->repo->get_by_id($id);
		if (!$profile) {
			AIPS_Ajax_Response::error(__('SEO profile not found.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array('profile' => $profile));
	}

	/**
	 * Save (create or update) an SEO profile.
	 *
	 * @return void
	 */
	public function ajax_save_seo_profile() {
		if (!$this->authorize()) {
			return;
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

		if (empty($name)) {
			AIPS_Ajax_Response::invalid_request(__('Profile name is required.', 'ai-post-scheduler'));
			return;
		}

		$raw_fields = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : array();
		$fields = is_string($raw_fields) ? json_decode($raw_fields, true) : $raw_fields;

		$raw_prompts = isset($_POST['field_prompts']) ? wp_unslash($_POST['field_prompts']) : array();
		$field_prompts = is_string($raw_prompts) ? json_decode($raw_prompts, true) : $raw_prompts;

		$data = array(
			'name'                => $name,
			'description'         => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
			'provider_id'         => isset($_POST['provider_id']) ? sanitize_key(wp_unslash($_POST['provider_id'])) : 'auto',
			'fields'              => is_array($fields) ? $fields : array('focus_keyword', 'seo_title', 'meta_description'),
			'field_prompts'       => is_array($field_prompts) ? $field_prompts : array(),
			'custom_instructions'=> isset($_POST['custom_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['custom_instructions'])) : '',
			'is_active'           => isset($_POST['is_active']) ? filter_var(wp_unslash($_POST['is_active']), FILTER_VALIDATE_BOOLEAN) : true,
		);

		if ($id > 0) {
			$success = $this->repo->update($id, $data);
			$profile_id = $id;
		} else {
			$profile_id = $this->repo->create($data);
			$success = (bool) $profile_id;
		}

		if (!$success) {
			AIPS_Ajax_Response::error(__('Failed to save SEO profile.', 'ai-post-scheduler'));
			return;
		}

		$saved = $this->repo->get_by_id($profile_id);
		AIPS_Ajax_Response::success(array('profile' => $saved), __('SEO profile saved successfully.', 'ai-post-scheduler'));
	}

	/**
	 * Delete an SEO profile.
	 *
	 * @return void
	 */
	public function ajax_delete_seo_profile() {
		if (!$this->authorize()) {
			return;
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::invalid_request(__('Profile ID is required.', 'ai-post-scheduler'));
			return;
		}

		if (!$this->repo->delete($id)) {
			AIPS_Ajax_Response::error(__('Failed to delete SEO profile.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array(), __('SEO profile deleted.', 'ai-post-scheduler'));
	}

	/**
	 * Toggle SEO profile active status.
	 *
	 * @return void
	 */
	public function ajax_toggle_seo_profile() {
		if (!$this->authorize()) {
			return;
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$is_active = isset($_POST['is_active']) ? filter_var(wp_unslash($_POST['is_active']), FILTER_VALIDATE_BOOLEAN) : false;

		if (!$id) {
			AIPS_Ajax_Response::invalid_request(__('Profile ID is required.', 'ai-post-scheduler'));
			return;
		}

		if (!$this->repo->set_active($id, $is_active)) {
			AIPS_Ajax_Response::error(__('Failed to update profile status.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array('is_active' => $is_active), __('Profile status updated.', 'ai-post-scheduler'));
	}

	private function authorize() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
			return false;
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
			return false;
		}

		return true;
	}
}
