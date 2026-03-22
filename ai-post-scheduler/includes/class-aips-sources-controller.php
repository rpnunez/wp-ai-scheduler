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
		// Source Group (taxonomy) endpoints.
		add_action('wp_ajax_aips_get_source_groups', array($this, 'ajax_get_source_groups'));
		add_action('wp_ajax_aips_save_source_group', array($this, 'ajax_save_source_group'));
		add_action('wp_ajax_aips_delete_source_group', array($this, 'ajax_delete_source_group'));
		add_action('wp_ajax_aips_get_source_dossiers', array($this, 'ajax_get_source_dossiers'));
		add_action('wp_ajax_aips_save_source_dossier', array($this, 'ajax_save_source_dossier'));
		add_action('wp_ajax_aips_delete_source_dossier', array($this, 'ajax_delete_source_dossier'));
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
		wp_send_json_success(array('sources' => $sources));
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
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id          = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$url         = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$label       = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$is_active   = isset($_POST['is_active']) ? 1 : 0;
		$term_ids    = isset($_POST['term_ids']) && is_array($_POST['term_ids'])
			? array_map('absint', $_POST['term_ids'])
			: array();

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

			$this->repo->set_source_terms($id, $term_ids);

			$source          = $this->repo->get_by_id($id);
			$source->term_ids = $this->repo->get_source_term_ids($id);
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

			$this->repo->set_source_terms($new_id, $term_ids);

			$source          = $this->repo->get_by_id($new_id);
			$source->term_ids = $this->repo->get_source_term_ids($new_id);
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

		// Clean up group term assignments first.
		$this->repo->delete_source_terms($id);

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

	/**
	 * Return all source group taxonomy terms.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_source_groups() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$terms = get_terms(array(
			'taxonomy'   => 'aips_source_group',
			'hide_empty' => false,
		));

		if (is_wp_error($terms)) {
			wp_send_json_error(array('message' => $terms->get_error_message()));
		}

		wp_send_json_success(array('groups' => $terms));
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
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$term_id     = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		$name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

		if (empty($name)) {
			wp_send_json_error(array('message' => __('A group name is required.', 'ai-post-scheduler')));
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
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		$saved_id = $term_id ?: $result['term_id'];
		$term     = get_term($saved_id, 'aips_source_group');

		wp_send_json_success(array(
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
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		if (!$term_id) {
			wp_send_json_error(array('message' => __('Invalid group ID.', 'ai-post-scheduler')));
		}

		$result = wp_delete_term($term_id, 'aips_source_group');

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to delete source group.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Source group deleted.', 'ai-post-scheduler')));
	}

	/**
	 * Return dossier records for the editorial workflow UI.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_source_dossiers() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$dossiers = $this->repo->get_dossiers(array('limit' => 250));

		wp_send_json_success(array('dossiers' => $dossiers));
	}

	/**
	 * Create or update an editorial dossier record.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_save_source_dossier() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$dossier_id          = isset($_POST['dossier_id']) ? absint($_POST['dossier_id']) : 0;
		$source_id           = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$relation_type       = isset($_POST['relation_type']) ? sanitize_key(wp_unslash($_POST['relation_type'])) : 'author_topic';
		$relation_id         = isset($_POST['relation_id']) ? absint($_POST['relation_id']) : 0;
		$source_url          = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
		$source_type         = isset($_POST['source_type']) ? sanitize_text_field(wp_unslash($_POST['source_type'])) : '';
		$quote_summary       = isset($_POST['quote_summary']) ? sanitize_textarea_field(wp_unslash($_POST['quote_summary'])) : '';
		$trust_rating        = isset($_POST['trust_rating']) ? absint($_POST['trust_rating']) : 3;
		$citation_required   = isset($_POST['citation_required']) ? 1 : 0;
		$verification_status = isset($_POST['verification_status']) ? sanitize_key(wp_unslash($_POST['verification_status'])) : 'pending';
		$editor_notes        = isset($_POST['editor_notes']) ? sanitize_textarea_field(wp_unslash($_POST['editor_notes'])) : '';

		if (empty($relation_id)) {
			wp_send_json_error(array('message' => __('A related editorial record ID is required.', 'ai-post-scheduler')));
		}

		if (empty($source_url) || !filter_var($source_url, FILTER_VALIDATE_URL)) {
			wp_send_json_error(array('message' => __('Please enter a valid source URL.', 'ai-post-scheduler')));
		}

		$data = array(
			'source_id'           => $source_id,
			'relation_type'       => $relation_type,
			'relation_id'         => $relation_id,
			'source_url'          => $source_url,
			'source_type'         => $source_type,
			'quote_summary'       => $quote_summary,
			'trust_rating'        => $trust_rating,
			'citation_required'   => $citation_required,
			'verification_status' => $verification_status,
			'editor_notes'        => $editor_notes,
		);

		if ($dossier_id > 0) {
			$result = $this->repo->update_dossier($dossier_id, $data);
			if (!$result) {
				wp_send_json_error(array('message' => __('Failed to update dossier record.', 'ai-post-scheduler')));
			}

			wp_send_json_success(
				array(
					'message' => __('Dossier record updated.', 'ai-post-scheduler'),
					'dossier' => $this->repo->get_dossier_by_id($dossier_id),
				)
			);
		}

		$new_id = $this->repo->create_dossier($data);
		if (!$new_id) {
			wp_send_json_error(array('message' => __('Failed to create dossier record.', 'ai-post-scheduler')));
		}

		wp_send_json_success(
			array(
				'message' => __('Dossier record created.', 'ai-post-scheduler'),
				'dossier' => $this->repo->get_dossier_by_id($new_id),
			)
		);
	}

	/**
	 * Delete an editorial dossier record.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_delete_source_dossier() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$dossier_id = isset($_POST['dossier_id']) ? absint($_POST['dossier_id']) : 0;
		if (!$dossier_id) {
			wp_send_json_error(array('message' => __('Invalid dossier ID.', 'ai-post-scheduler')));
		}

		if (!$this->repo->delete_dossier($dossier_id)) {
			wp_send_json_error(array('message' => __('Failed to delete dossier record.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Dossier record deleted.', 'ai-post-scheduler')));
	}
}
