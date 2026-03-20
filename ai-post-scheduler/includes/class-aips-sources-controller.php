<?php
/**
 * Sources Controller
 *
 * Handles AJAX endpoints for the Trusted Sources admin UI.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_Controller
 *
 * Registers wp_ajax_* actions for listing, saving, deleting, and toggling
 * trusted sources. All SQL lives in AIPS_Sources_Repository.
 */
class AIPS_Sources_Controller {

	/**
	 * @var AIPS_Sources_Repository
	 */
	private $repo;

	/**
	 * Initialize the controller and register AJAX hooks.
	 *
	 * @param AIPS_Sources_Repository|null $repo Optional repository (injectable for tests).
	 */
	public function __construct($repo = null) {
		$this->repo = $repo ?: new AIPS_Sources_Repository();

		add_action('wp_ajax_aips_get_sources', array($this, 'ajax_get_sources'));
		add_action('wp_ajax_aips_save_source', array($this, 'ajax_save_source'));
		add_action('wp_ajax_aips_delete_source', array($this, 'ajax_delete_source'));
		add_action('wp_ajax_aips_toggle_source_active', array($this, 'ajax_toggle_source_active'));
	}

	/**
	 * Return all sources (including inactive) for the admin UI.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_sources() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$sources = $this->repo->get_all(false);
		wp_send_json_success(array('sources' => $sources));
	}

	/**
	 * Create or update a source.
	 *
	 * Expected POST params: source_id (0 = create), url, label, description, is_active.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_save_source() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id          = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$url         = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$label       = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$is_active   = isset($_POST['is_active']) ? 1 : 0;

		if (empty($url)) {
			wp_send_json_error(array('message' => __('A URL is required.', 'ai-post-scheduler')));
		}

		// Basic URL validation.
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			wp_send_json_error(array('message' => __('Please enter a valid URL (e.g. https://example.com).', 'ai-post-scheduler')));
		}

		$data = array(
			'url'         => $url,
			'label'       => $label,
			'description' => $description,
			'is_active'   => $is_active,
		);

		if ($id) {
			if ($this->repo->url_exists($url, $id)) {
				wp_send_json_error(array('message' => __('This URL already exists as another source.', 'ai-post-scheduler')));
			}

			$result = $this->repo->update($id, $data);
			if (!$result) {
				wp_send_json_error(array('message' => __('Failed to update source.', 'ai-post-scheduler')));
			}

			$source = $this->repo->get_by_id($id);
			wp_send_json_success(array(
				'message'   => __('Source updated.', 'ai-post-scheduler'),
				'source_id' => $id,
				'source'    => $source,
			));
		} else {
			if ($this->repo->url_exists($url)) {
				wp_send_json_error(array('message' => __('This URL is already in the sources list.', 'ai-post-scheduler')));
			}

			$new_id = $this->repo->create($data);
			if (!$new_id) {
				wp_send_json_error(array('message' => __('Failed to create source.', 'ai-post-scheduler')));
			}

			$source = $this->repo->get_by_id($new_id);
			wp_send_json_success(array(
				'message'   => __('Source added.', 'ai-post-scheduler'),
				'source_id' => $new_id,
				'source'    => $source,
			));
		}
	}

	/**
	 * Delete a source by ID.
	 *
	 * Expected POST param: source_id.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_delete_source() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid source ID.', 'ai-post-scheduler')));
		}

		$result = $this->repo->delete($id);
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to delete source.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Source deleted.', 'ai-post-scheduler')));
	}

	/**
	 * Toggle the active status of a source.
	 *
	 * Expected POST params: source_id, is_active (1 or 0).
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_toggle_source_active() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id        = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid source ID.', 'ai-post-scheduler')));
		}

		$result = $this->repo->set_active($id, $is_active);
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to update source status.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Source status updated.', 'ai-post-scheduler')));
	}
}
