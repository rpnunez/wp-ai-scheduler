<?php
/**
 * Blueprint Presets Controller
 *
 * Handles AJAX operations for Blueprint Presets.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Blueprint_Presets_Controller
 */
class AIPS_Blueprint_Presets_Controller {

	/**
	 * @var AIPS_Blueprint_Presets_Repository
	 */
	private $repository;

	/**
	 * Initialize and register AJAX hooks.
	 */
	public function __construct() {
		$this->repository = AIPS_Blueprint_Presets_Repository::instance();

		add_action('wp_ajax_aips_save_blueprint_preset', array($this, 'ajax_save'));
		add_action('wp_ajax_aips_get_blueprint_preset', array($this, 'ajax_get'));
		add_action('wp_ajax_aips_delete_blueprint_preset', array($this, 'ajax_delete'));
	}

	/**
	 * AJAX: Save (create or update) a preset.
	 */
	public function ajax_save() {
		check_ajax_referer('aips_blueprint_presets_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized.', 'ai-post-scheduler'), 403);
		}

		$preset_id = isset($_POST['preset_id']) ? absint($_POST['preset_id']) : 0;
		$data = array(
			'name'              => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
			'description'       => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
			'structure_id'      => isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0,
			'voice_id'          => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
			'slice_ids'         => isset($_POST['slice_ids']) ? sanitize_text_field(wp_unslash($_POST['slice_ids'])) : '[]',
			'section_overrides' => isset($_POST['section_overrides']) ? sanitize_text_field(wp_unslash($_POST['section_overrides'])) : null,
			'is_active'         => isset($_POST['is_active']) ? absint($_POST['is_active']) : 1,
			'is_default'        => isset($_POST['is_default']) ? absint($_POST['is_default']) : 0,
		);

		if (empty($data['name'])) {
			wp_send_json_error(__('Preset name is required.', 'ai-post-scheduler'));
		}

		if ($preset_id > 0) {
			$result = $this->repository->update($preset_id, $data);
			if ($result === false) {
				wp_send_json_error(__('Failed to update preset.', 'ai-post-scheduler'));
			}
			wp_send_json_success(array('id' => $preset_id, 'updated' => true));
		} else {
			$new_id = $this->repository->create($data);
			if (!$new_id) {
				wp_send_json_error(__('Failed to create preset.', 'ai-post-scheduler'));
			}
			wp_send_json_success(array('id' => $new_id, 'created' => true));
		}
	}

	/**
	 * AJAX: Get a single preset by ID.
	 */
	public function ajax_get() {
		check_ajax_referer('aips_blueprint_presets_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized.', 'ai-post-scheduler'), 403);
		}

		$preset_id = isset($_POST['preset_id']) ? absint($_POST['preset_id']) : 0;
		if (!$preset_id) {
			wp_send_json_error(__('Invalid preset ID.', 'ai-post-scheduler'));
		}

		$preset = $this->repository->get_by_id($preset_id);
		if (!$preset) {
			wp_send_json_error(__('Preset not found.', 'ai-post-scheduler'));
		}

		wp_send_json_success($preset);
	}

	/**
	 * AJAX: Delete a preset.
	 */
	public function ajax_delete() {
		check_ajax_referer('aips_blueprint_presets_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized.', 'ai-post-scheduler'), 403);
		}

		$preset_id = isset($_POST['preset_id']) ? absint($_POST['preset_id']) : 0;
		if (!$preset_id) {
			wp_send_json_error(__('Invalid preset ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($preset_id);
		if ($result === false) {
			wp_send_json_error(__('Failed to delete preset.', 'ai-post-scheduler'));
		}

		wp_send_json_success(array('deleted' => true));
	}
}
