<?php
/**
 * SEO Controller
 *
 * AJAX endpoints for the SEO Provider & Write-Through subsystem:
 * generate SEO for existing posts, re-sync canonical SEO to target providers,
 * retrieve post SEO status, and bulk process posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Controller {

	/**
	 * @var AIPS_SEO_Manager
	 */
	private $manager;

	/**
	 * @param AIPS_SEO_Manager|null $manager Optional SEO manager.
	 */
	public function __construct($manager = null) {
		$this->manager = $manager ?: new AIPS_SEO_Manager();

		add_action('wp_ajax_aips_seo_generate_for_post', array($this, 'ajax_generate_for_post'));
		add_action('wp_ajax_aips_seo_sync_to_provider', array($this, 'ajax_sync_to_provider'));
		add_action('wp_ajax_aips_seo_get_post_data', array($this, 'ajax_get_post_data'));
		add_action('wp_ajax_aips_seo_bulk_generate', array($this, 'ajax_bulk_generate'));
		add_action('wp_ajax_aips_seo_bulk_sync', array($this, 'ajax_bulk_sync'));
		add_action('wp_ajax_aips_seo_get_available_providers', array($this, 'ajax_get_available_providers'));
	}

	/**
	 * AJAX endpoint to generate SEO metadata for an existing post.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_generate_for_post() {
		if (!$this->authorize()) {
			return;
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$provider_id = isset($_POST['provider_id']) ? sanitize_key(wp_unslash($_POST['provider_id'])) : '';
		$custom_instructions = isset($_POST['custom_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['custom_instructions'])) : '';

		if (!$post_id || !get_post($post_id)) {
			AIPS_Ajax_Response::invalid_request(__('A valid post_id is required.', 'ai-post-scheduler'));
			return;
		}

		$options = array(
			'provider_id'         => $provider_id,
			'custom_instructions' => $custom_instructions,
		);

		$result = $this->manager->generate_for_post($post_id, $options);

		if (empty($result['success'])) {
			AIPS_Ajax_Response::error($result['error'] ? $result['error'] : __('SEO generation failed.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success($result, __('SEO metadata generated and saved successfully.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX endpoint to re-sync existing SEO data to a specified provider.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_sync_to_provider() {
		if (!$this->authorize()) {
			return;
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$provider_id = isset($_POST['provider_id']) ? sanitize_key(wp_unslash($_POST['provider_id'])) : '';

		if (!$post_id || !get_post($post_id)) {
			AIPS_Ajax_Response::invalid_request(__('A valid post_id is required.', 'ai-post-scheduler'));
			return;
		}

		$sync_result = $this->manager->sync_to_provider($post_id, $provider_id);

		if (is_wp_error($sync_result)) {
			AIPS_Ajax_Response::error($sync_result->get_error_message(), $sync_result->get_error_code());
			return;
		}

		$status = $this->manager->get_post_seo_status($post_id);

		AIPS_Ajax_Response::success($status, __('SEO metadata synchronized to provider.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX endpoint to retrieve current SEO metadata status for a post.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_post_data() {
		if (!$this->authorize()) {
			return;
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id || !get_post($post_id)) {
			AIPS_Ajax_Response::invalid_request(__('A valid post_id is required.', 'ai-post-scheduler'));
			return;
		}

		$status = $this->manager->get_post_seo_status($post_id);

		AIPS_Ajax_Response::success($status);
	}

	/**
	 * AJAX endpoint to bulk generate SEO for multiple posts.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_bulk_generate() {
		if (!$this->authorize()) {
			return;
		}

		$post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('absint', $_POST['post_ids']) : array();
		$provider_id = isset($_POST['provider_id']) ? sanitize_key(wp_unslash($_POST['provider_id'])) : '';

		if (empty($post_ids)) {
			AIPS_Ajax_Response::invalid_request(__('No post IDs provided.', 'ai-post-scheduler'));
			return;
		}

		$results = array(
			'total'     => count($post_ids),
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ($post_ids as $post_id) {
			if (!$post_id || !get_post($post_id)) {
				continue;
			}

			$res = $this->manager->generate_for_post($post_id, array('provider_id' => $provider_id));
			$results['processed']++;

			if (!empty($res['success'])) {
				$results['succeeded']++;
			} else {
				$results['failed']++;
				$results['errors'][$post_id] = isset($res['error']) ? $res['error'] : __('Unknown error', 'ai-post-scheduler');
			}
		}

		AIPS_Ajax_Response::success($results, sprintf(
			/* translators: 1: succeeded count, 2: total count */
			__('Processed SEO generation for %1$d of %2$d posts.', 'ai-post-scheduler'),
			$results['succeeded'],
			$results['total']
		));
	}

	/**
	 * AJAX endpoint to bulk sync stored SEO data for multiple posts.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_bulk_sync() {
		if (!$this->authorize()) {
			return;
		}

		$post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('absint', $_POST['post_ids']) : array();
		$provider_id = isset($_POST['provider_id']) ? sanitize_key(wp_unslash($_POST['provider_id'])) : '';

		if (empty($post_ids)) {
			AIPS_Ajax_Response::invalid_request(__('No post IDs provided.', 'ai-post-scheduler'));
			return;
		}

		$results = array(
			'total'     => count($post_ids),
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
		);

		foreach ($post_ids as $post_id) {
			if (!$post_id || !get_post($post_id)) {
				continue;
			}

			$res = $this->manager->sync_to_provider($post_id, $provider_id);
			$results['processed']++;

			if (!is_wp_error($res)) {
				$results['succeeded']++;
			} else {
				$results['failed']++;
			}
		}

		AIPS_Ajax_Response::success($results, sprintf(
			/* translators: 1: succeeded count, 2: total count */
			__('Synchronized SEO for %1$d of %2$d posts.', 'ai-post-scheduler'),
			$results['succeeded'],
			$results['total']
		));
	}

	/**
	 * AJAX endpoint to list available SEO providers.
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_available_providers() {
		if (!$this->authorize()) {
			return;
		}

		$providers = array();
		foreach (AIPS_SEO_Registry::get_available() as $provider_id => $adapter) {
			$providers[] = array(
				'id'               => $provider_id,
				'label'            => $adapter->get_label(),
				'supported_fields' => $adapter->get_supported_fields(),
			);
		}

		$active = AIPS_SEO_Registry::get_active_provider();

		AIPS_Ajax_Response::success(array(
			'providers' => $providers,
			'active'    => $active ? $active->get_id() : 'native',
		));
	}

	/**
	 * Nonce and capability authorization check.
	 *
	 * @return bool True if authorized; false after sending error response.
	 */
	private function authorize() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
			return false;
		}

		if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
			return false;
		}

		return true;
	}
}
