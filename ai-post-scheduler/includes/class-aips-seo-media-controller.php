<?php
/**
 * SEO Media Controller
 *
 * AJAX endpoints for optimizing WordPress media attachments,
 * batch processing, and media SEO library audit scanning.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Media_Controller {

	/**
	 * @var AIPS_SEO_Media_Manager
	 */
	private $media_manager;

	/**
	 * @param AIPS_SEO_Media_Manager|null $media_manager Optional media manager.
	 */
	public function __construct($media_manager = null) {
		$this->media_manager = $media_manager ?: new AIPS_SEO_Media_Manager();

		add_action('wp_ajax_aips_seo_optimize_attachment', array($this, 'ajax_optimize_attachment'));
		add_action('wp_ajax_aips_seo_bulk_optimize_attachments', array($this, 'ajax_bulk_optimize_attachments'));
		add_action('wp_ajax_aips_seo_audit_media_library', array($this, 'ajax_audit_media_library'));
	}

	/**
	 * Optimize a single media attachment via AJAX.
	 *
	 * @return void
	 */
	public function ajax_optimize_attachment() {
		if (!$this->authorize()) {
			return;
		}

		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
		if (!$attachment_id) {
			AIPS_Ajax_Response::invalid_request(__('Attachment ID is required.', 'ai-post-scheduler'));
			return;
		}

		$options = array(
			'mode'          => isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'text',
			'focus_keyword' => isset($_POST['focus_keyword']) ? sanitize_text_field(wp_unslash($_POST['focus_keyword'])) : '',
			'custom_prompt' => isset($_POST['custom_prompt']) ? sanitize_text_field(wp_unslash($_POST['custom_prompt'])) : '',
		);

		$result = $this->media_manager->optimize_attachment($attachment_id, $options);

		if (!empty($result['success'])) {
			AIPS_Ajax_Response::success($result['data'], __('Image SEO metadata generated and saved successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error($result['error'] ?: __('Failed to optimize media attachment.', 'ai-post-scheduler'));
		}
	}

	/**
	 * Bulk optimize multiple media attachments via AJAX.
	 *
	 * @return void
	 */
	public function ajax_bulk_optimize_attachments() {
		if (!$this->authorize()) {
			return;
		}

		$raw_ids = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : array();
		$attachment_ids = is_array($raw_ids) ? array_map('absint', $raw_ids) : array_filter(array_map('absint', explode(',', (string) $raw_ids)));

		if (empty($attachment_ids)) {
			AIPS_Ajax_Response::invalid_request(__('No attachment IDs provided.', 'ai-post-scheduler'));
			return;
		}

		$options = array(
			'mode' => isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'text',
		);

		$processed = 0;
		$failed = 0;
		$results = array();

		foreach ($attachment_ids as $att_id) {
			$res = $this->media_manager->optimize_attachment($att_id, $options);
			$results[$att_id] = $res;
			if (!empty($res['success'])) {
				$processed++;
			} else {
				$failed++;
			}
		}

		AIPS_Ajax_Response::success(
			array(
				'processed' => $processed,
				'failed'    => $failed,
				'total'     => count($attachment_ids),
				'results'   => $results,
			),
			sprintf(
				/* translators: 1: processed count, 2: total count */
				__('Optimized %1$d of %2$d media attachments.', 'ai-post-scheduler'),
				$processed,
				count($attachment_ids)
			)
		);
	}

	/**
	 * Audit media library attachments for missing alt text / SEO data.
	 *
	 * @return void
	 */
	public function ajax_audit_media_library() {
		if (!$this->authorize()) {
			return;
		}

		$limit  = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
		$offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
		$filter = isset($_POST['filter']) ? sanitize_key($_POST['filter']) : 'all';

		$data = $this->media_manager->audit_media_library(array(
			'limit'  => $limit,
			'offset' => $offset,
			'filter' => $filter,
		));

		AIPS_Ajax_Response::success($data);
	}

	private function authorize() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
			return false;
		}

		if (!current_user_can('upload_files') || !current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
			return false;
		}

		return true;
	}
}
