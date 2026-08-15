<?php
/**
 * Integrations Controller
 *
 * AJAX endpoints for the third-party plugin bridge admin UI: list available
 * integrations, introspect a target plugin's schema (e.g. ACF field groups),
 * and manage per-Template field mappings. All persistence lives in
 * AIPS_Integration_Mappings_Repository; schema introspection is delegated to
 * the resolved AIPS_Integration_Interface adapter.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integrations_Controller {

	/**
	 * @var AIPS_Integration_Mappings_Repository
	 */
	private $repo;

	/**
	 * @param AIPS_Integration_Mappings_Repository|null $repo Optional repository (injectable for tests).
	 */
	public function __construct($repo = null) {
		$this->repo = $repo ?: new AIPS_Integration_Mappings_Repository();

		add_action('wp_ajax_aips_get_available_integrations', array($this, 'ajax_get_available_integrations'));
		add_action('wp_ajax_aips_get_integration_schema', array($this, 'ajax_get_integration_schema'));
		add_action('wp_ajax_aips_get_field_mappings', array($this, 'ajax_get_field_mappings'));
		add_action('wp_ajax_aips_save_field_mappings', array($this, 'ajax_save_field_mappings'));
		add_action('wp_ajax_aips_delete_field_mapping', array($this, 'ajax_delete_field_mapping'));
	}

	/**
	 * List integrations whose target plugin is active on this site.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_available_integrations() {
		if (!$this->authorize()) {
			return;
		}

		$integrations = array();
		foreach (AIPS_Integration_Registry::get_available() as $integration_id => $adapter) {
			$integrations[] = array(
				'id'                        => $integration_id,
				'label'                     => $adapter->get_label(),
				'supports_custom_field_keys' => $adapter->supports_custom_field_keys(),
			);
		}

		AIPS_Ajax_Response::success(array('integrations' => $integrations));
	}

	/**
	 * Return the field-group/field schema an integration exposes for a post type.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_integration_schema() {
		if (!$this->authorize()) {
			return;
		}

		$integration_id = isset($_POST['integration_id']) ? sanitize_key(wp_unslash($_POST['integration_id'])) : '';
		$post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : null;
		$group_id = isset($_POST['group_id']) ? sanitize_text_field(wp_unslash($_POST['group_id'])) : '';
		$include_protected = !empty($_POST['include_protected']);

		$adapter = AIPS_Integration_Registry::get($integration_id);

		if (!$adapter instanceof AIPS_Integration_Interface || !$adapter->is_available()) {
			AIPS_Ajax_Response::error(__('That integration is not available on this site.', 'ai-post-scheduler'), 'integration_unavailable');
			return;
		}

		if ($group_id !== '') {
			AIPS_Ajax_Response::success(array('fields' => $adapter->get_fields($group_id, array('include_protected' => $include_protected))));
			return;
		}

		AIPS_Ajax_Response::success(array('field_groups' => $adapter->get_field_groups($post_type)));
	}

	/**
	 * Return the saved field mappings for a Template.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_field_mappings() {
		if (!$this->authorize()) {
			return;
		}

		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

		if (!$template_id) {
			AIPS_Ajax_Response::invalid_request(__('A template_id is required.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array('mappings' => $this->repo->get_by_template($template_id, false)));
	}

	/**
	 * Bulk save (upsert) field mappings for a Template.
	 *
	 * Expects $_POST['mappings'] as a JSON-encoded array of mapping rows.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_save_field_mappings() {
		if (!$this->authorize()) {
			return;
		}

		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		$raw_mappings = isset($_POST['mappings']) ? wp_unslash($_POST['mappings']) : '';

		if (!$template_id) {
			AIPS_Ajax_Response::invalid_request(__('A template_id is required.', 'ai-post-scheduler'));
			return;
		}

		$mappings = is_string($raw_mappings) ? json_decode($raw_mappings, true) : $raw_mappings;

		if (!is_array($mappings)) {
			AIPS_Ajax_Response::invalid_request(__('Invalid mappings payload.', 'ai-post-scheduler'));
			return;
		}

		// Validate every submitted field_key against its OWN integration's
		// adapter before saving anything, so a bad key (e.g. a reserved meta
		// key) fails the whole request instead of silently persisting. A save
		// from the UI normally carries one integration, but a crafted payload
		// could mix them — so an adapter is resolved (and memoized) per row's
		// integration_id rather than assuming they all match row 0.
		$adapters = array();

		foreach ($mappings as $mapping) {
			if (empty($mapping['integration_id']) || empty($mapping['field_key'])) {
				continue;
			}

			$integration_id = $mapping['integration_id'];

			if (!array_key_exists($integration_id, $adapters)) {
				$resolved = AIPS_Integration_Registry::get($integration_id);
				$adapters[$integration_id] = $resolved instanceof AIPS_Integration_Interface ? $resolved : null;
			}

			$adapter = $adapters[$integration_id];

			if (!$adapter instanceof AIPS_Integration_Interface) {
				AIPS_Ajax_Response::error(
					sprintf(
						/* translators: %s: integration identifier. */
						__('Unknown or unavailable integration: %s', 'ai-post-scheduler'),
						sanitize_key($integration_id)
					),
					'integration_unavailable'
				);
				return;
			}

			$valid = $adapter->validate_field_key($mapping['field_key']);

			if (is_wp_error($valid)) {
				AIPS_Ajax_Response::error($valid->get_error_message(), $valid->get_error_code());
				return;
			}
		}

		// A template's mappings for one integration should reflect exactly one
		// selected group at a time. Retire mappings left over from a
		// previously-selected group, once per distinct (integration, group)
		// present in this save, so switching groups doesn't leave both active.
		$pruned = array();

		foreach ($mappings as $mapping) {
			if (empty($mapping['integration_id']) || !isset($mapping['source_key'])) {
				continue;
			}

			$prune_key = $mapping['integration_id'] . '|' . $mapping['source_key'];

			if (isset($pruned[$prune_key])) {
				continue;
			}

			$pruned[$prune_key] = true;
			$this->repo->delete_stale_group_mappings($template_id, $mapping['integration_id'], $mapping['source_key']);
		}

		foreach ($mappings as $mapping) {
			if (empty($mapping['integration_id']) || empty($mapping['field_key'])) {
				continue;
			}

			$this->repo->save_mapping(array(
				'template_id'    => $template_id,
				'integration_id' => $mapping['integration_id'],
				'source_key'     => isset($mapping['source_key']) ? $mapping['source_key'] : '',
				'field_key'      => $mapping['field_key'],
				'field_label'    => isset($mapping['field_label']) ? $mapping['field_label'] : '',
				'field_type'     => isset($mapping['field_type']) ? $mapping['field_type'] : '',
				'custom_prompt'  => isset($mapping['custom_prompt']) ? $mapping['custom_prompt'] : '',
				'is_active'      => !empty($mapping['is_active']),
			));
		}

		AIPS_Ajax_Response::success(array('mappings' => $this->repo->get_by_template($template_id, false)), __('Field mappings saved.', 'ai-post-scheduler'));
	}

	/**
	 * Delete a single field mapping.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_delete_field_mapping() {
		if (!$this->authorize()) {
			return;
		}

		$mapping_id = isset($_POST['mapping_id']) ? absint($_POST['mapping_id']) : 0;

		if (!$mapping_id) {
			AIPS_Ajax_Response::invalid_request(__('A mapping_id is required.', 'ai-post-scheduler'));
			return;
		}

		if (!$this->repo->delete_mapping($mapping_id)) {
			AIPS_Ajax_Response::error(__('Failed to delete field mapping.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array(), __('Field mapping deleted.', 'ai-post-scheduler'));
	}

	/**
	 * Shared nonce + capability guard for every action on this controller.
	 *
	 * @return bool True if the request is authorized; false after sending an
	 *              error response otherwise.
	 */
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
