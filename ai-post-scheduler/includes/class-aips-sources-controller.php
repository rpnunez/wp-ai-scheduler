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
 * trusted sources. Also manages Source Group (taxonomy) CRUD endpoints.
 * All SQL lives in AIPS_Sources_Repository.
 */
class AIPS_Sources_Controller {

	/**
	 * @var AIPS_Sources_Repository
	 */
	private $repo;

	/**
	 * @var AIPS_Sources_Data_Repository
	 */
	private $data_repo;

	/**
	 * Initialize the controller and register AJAX hooks.
	 *
	 * @param AIPS_Sources_Repository|null $repo Optional repository (injectable for tests).
	 */
	public function __construct($repo = null) {
		$this->repo      = $repo ?: new AIPS_Sources_Repository();
		$this->data_repo = new AIPS_Sources_Data_Repository();

		add_action('wp_ajax_aips_get_sources', array($this, 'ajax_get_sources'));
		add_action('wp_ajax_aips_save_source', array($this, 'ajax_save_source'));
		add_action('wp_ajax_aips_delete_source', array($this, 'ajax_delete_source'));
		add_action('wp_ajax_aips_toggle_source_active', array($this, 'ajax_toggle_source_active'));
		add_action('wp_ajax_aips_fetch_source_now', array($this, 'ajax_fetch_source_now'));
		// Source Group (taxonomy) endpoints.
		add_action('wp_ajax_aips_get_source_groups', array($this, 'ajax_get_source_groups'));
		add_action('wp_ajax_aips_save_source_group', array($this, 'ajax_save_source_group'));
		add_action('wp_ajax_aips_delete_source_group', array($this, 'ajax_delete_source_group'));
	}

	/**
	 * Return all sources (including inactive) for the admin UI.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_sources() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$sources = $this->repo->get_all(false);

		// Collect source IDs for bulk term lookup.
		$source_ids = array();
		foreach ($sources as $source) {
			if (isset($source->id)) {
				$source_id = (int) $source->id;
				if ($source_id > 0) {
					$source_ids[] = $source_id;
				}
			}
		}

		$source_ids = array_values(array_unique($source_ids));

		// Fetch all term mappings in one repository call to avoid N+1 queries.
		$term_ids_map = array();
		if (!empty($source_ids)) {
			$term_ids_map = $this->repo->get_term_ids_for_sources($source_ids);
			if (!is_array($term_ids_map)) {
				$term_ids_map = array();
			}
		}

		// Attach term IDs to each source from the bulk mapping.
		foreach ($sources as $source) {
			$source_id = isset($source->id) ? (int) $source->id : 0;
			if ($source_id > 0 && isset($term_ids_map[$source_id]) && is_array($term_ids_map[$source_id])) {
				$source->term_ids = $term_ids_map[$source_id];
			} else {
				$source->term_ids = array();
			}
		}
		AIPS_Ajax_Response::success(array('sources' => $sources));
	}

	/**
	 * Create or update a source.
	 *
	 * Expected POST params: source_id (0 = create), url, label, description, is_active, term_ids[].
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_save_source() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id             = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$url            = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$label          = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
		$description    = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$is_active      = isset($_POST['is_active']) ? 1 : 0;
		$fetch_interval = isset($_POST['fetch_interval']) ? sanitize_text_field(wp_unslash($_POST['fetch_interval'])) : '';
		$term_ids       = isset($_POST['term_ids']) && is_array($_POST['term_ids'])
			? array_map('absint', $_POST['term_ids'])
			: array();

		if (empty($url)) {
			AIPS_Ajax_Response::error(__('A URL is required.', 'ai-post-scheduler'));
		}

		// Basic URL validation.
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			AIPS_Ajax_Response::error(array('message' => __('Please enter a valid URL (e.g. https://example.com).', 'ai-post-scheduler')));
		}

		$data = array(
			'url'         => $url,
			'label'       => $label,
			'description' => $description,
			'is_active'   => $is_active,
		);

		if ($id) {
			if ($this->repo->url_exists($url, $id)) {
				AIPS_Ajax_Response::error(__('This URL already exists as another source.', 'ai-post-scheduler'));
			}

			$result = $this->repo->update($id, $data);
			if (!$result) {
				AIPS_Ajax_Response::error(__('Failed to update source.', 'ai-post-scheduler'));
			}

			// Update fetch schedule if supplied.
			$this->repo->set_fetch_schedule($id, $fetch_interval ?: null);

			$this->repo->set_source_terms($id, $term_ids);

			$source           = $this->repo->get_by_id($id);
			$source->term_ids = $this->repo->get_source_term_ids($id);
			AIPS_Ajax_Response::success(array(
				'message'   => __('Source updated.', 'ai-post-scheduler'),
				'source_id' => $id,
				'source'    => $source,
			));
		} else {
			if ($this->repo->url_exists($url)) {
				AIPS_Ajax_Response::error(__('This URL is already in the sources list.', 'ai-post-scheduler'));
			}

			$new_id = $this->repo->create($data);
			if (!$new_id) {
				AIPS_Ajax_Response::error(__('Failed to create source.', 'ai-post-scheduler'));
			}

			// Set fetch schedule if supplied.
			if ($fetch_interval) {
				$this->repo->set_fetch_schedule($new_id, $fetch_interval);
			}

			$this->repo->set_source_terms($new_id, $term_ids);

			$source           = $this->repo->get_by_id($new_id);
			$source->term_ids = $this->repo->get_source_term_ids($new_id);
			AIPS_Ajax_Response::success(array(
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
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid source ID.', 'ai-post-scheduler'));
		}

		// Clean up group term assignments and fetched content first.
		$this->repo->delete_source_terms($id);
		$this->data_repo->delete_by_source_id($id);

		$result = $this->repo->delete($id);
		if (!$result) {
			AIPS_Ajax_Response::error(__('Failed to delete source.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Source deleted.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::permission_denied();
		}

		$id        = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid source ID.', 'ai-post-scheduler'));
		}

		$result = $this->repo->set_active($id, $is_active);
		if (!$result) {
			AIPS_Ajax_Response::error(__('Failed to update source status.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Source status updated.', 'ai-post-scheduler'));
	}

	/**
	 * Return all source group taxonomy terms.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_source_groups() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$terms = get_terms(array(
			'taxonomy'   => 'aips_source_group',
			'hide_empty' => false,
		));

		if (is_wp_error($terms)) {
			AIPS_Ajax_Response::error(array('message' => $terms->get_error_message()));
		}

		AIPS_Ajax_Response::success(array('groups' => $terms));
	}

	/**
	 * Create or update a source group term.
	 *
	 * Expected POST params: term_id (0 = create), name, description.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_save_source_group() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$term_id     = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		$name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

		if (empty($name)) {
			AIPS_Ajax_Response::error(__('A group name is required.', 'ai-post-scheduler'));
		}

		if ($term_id) {
			$result = wp_update_term($term_id, 'aips_source_group', array(
				'name'        => $name,
				'description' => $description,
			));
		} else {
			$result = wp_insert_term($name, 'aips_source_group', array(
				'description' => $description,
			));
		}

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		$saved_id = $term_id ?: $result['term_id'];
		$term     = get_term($saved_id, 'aips_source_group');

		AIPS_Ajax_Response::success(array(
			'message' => $term_id ? __('Source group updated.', 'ai-post-scheduler') : __('Source group created.', 'ai-post-scheduler'),
			'group'   => $term,
		));
	}

	/**
	 * Delete a source group term.
	 *
	 * Expected POST param: term_id.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_delete_source_group() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		if (!$term_id) {
			AIPS_Ajax_Response::error(__('Invalid group ID.', 'ai-post-scheduler'));
		}

		$result = wp_delete_term($term_id, 'aips_source_group');

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		if (!$result) {
			AIPS_Ajax_Response::error(__('Failed to delete source group.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Source group deleted.', 'ai-post-scheduler'));
	}

	/**
	 * Manually trigger a fetch for a single source.
	 *
	 * Expected POST param: source_id.
	 *
	 * @return void Sends JSON response with fetch result and updated source metadata.
	 */
	public function ajax_fetch_source_now() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid source ID.', 'ai-post-scheduler'));
		}

		$source = $this->repo->get_by_id($id);
		if (!$source) {
			AIPS_Ajax_Response::error(__('Source not found.', 'ai-post-scheduler'));
		}

		$fetcher = new AIPS_Sources_Fetcher(
			$this->data_repo,
			$this->repo
		);

		$result = $fetcher->fetch($source);

		// Return updated source row + fetch result so the UI can refresh.
		$updated_source           = $this->repo->get_by_id($id);
		$updated_source->term_ids = $this->repo->get_source_term_ids($id);
		$fetch_data               = $this->data_repo->get_by_source_id($id);

		AIPS_Ajax_Response::success(array(
			'fetch_result' => $result,
			'source'       => $updated_source,
			'fetch_data'   => $fetch_data,
			'message'      => $result['success']
				? sprintf(
					/* translators: %d = number of characters extracted */
					__('Fetched successfully. %d characters extracted.', 'ai-post-scheduler'),
					$result['word_count']
				)
				: sprintf(
					/* translators: %s = error message */
					__('Fetch failed: %s', 'ai-post-scheduler'),
					$result['error']
				),
		));
	}
}
