<?php
/**
 * REST controller for fetched source data rows.
 *
 * Routes:
 *   GET    /aips/v1/source-data/{id}   Fetch one row + recent generation usage
 *   PUT    /aips/v1/source-data/{id}   Update one row
 *   DELETE /aips/v1/source-data/{id}   Delete one row
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Source_Data_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Sources_Data_Repository */
	private $data_repo;

	protected $rest_base = 'source-data';

	public function __construct() {
		parent::__construct();
		$this->data_repo = new AIPS_Sources_Data_Repository();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->data_args()),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));
	}

	public function get_item($request) {
		$row = $this->data_repo->get_by_id((int) $request['id']);
		if (!$row) {
			return $this->error_not_found(__('Source data record', 'ai-post-scheduler'));
		}
		return $this->respond(array(
			'source_data' => $row,
			'usage'       => $this->data_repo->get_generation_usage((int) $request['id'], 10),
		));
	}

	public function update_item($request) {
		$id       = (int) $request['id'];
		$existing = $this->data_repo->get_by_id($id);
		if (!$existing) {
			return $this->error_not_found(__('Source data record', 'ai-post-scheduler'));
		}

		$url = $request->get_param('url');
		$url = null === $url ? (string) ($existing->url ?? '') : (string) $url;
		if ('' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
			return $this->error_invalid_request(__('Please enter a valid URL (e.g. https://example.com).', 'ai-post-scheduler'));
		}

		$fetch_status = $request->get_param('fetch_status');
		$fetch_status = null === $fetch_status ? (string) ($existing->fetch_status ?? 'success') : sanitize_key($fetch_status);
		if (!in_array($fetch_status, array('pending', 'success', 'failed'), true)) {
			return $this->error_invalid_request(__('Invalid fetch status.', 'ai-post-scheduler'));
		}

		$raw_html = $request->get_param('raw_html');
		if (null === $raw_html) {
			$raw_html = (string) ($existing->raw_html ?? '');
		}
		if (!current_user_can('unfiltered_html')) {
			$raw_html = wp_kses_post((string) $raw_html);
		}

		$data = array(
			'url'              => $url,
			'page_title'       => $this->fallback_string($request->get_param('page_title'), $existing->page_title ?? ''),
			'meta_description' => $this->fallback_string($request->get_param('meta_description'), $existing->meta_description ?? ''),
			'extracted_text'   => $this->fallback_string($request->get_param('extracted_text'), $existing->extracted_text ?? ''),
			'raw_html'         => $raw_html,
			'fetch_status'     => $fetch_status,
			'http_status'      => absint($request->get_param('http_status') ?? ($existing->http_status ?? 0)),
			'error_message'    => $this->fallback_string($request->get_param('error_message'), $existing->error_message ?? ''),
			'fetched_at'       => absint($request->get_param('fetched_at') ?? ($existing->fetched_at ?? 0)),
		);

		if (!$this->data_repo->update($id, $data)) {
			return $this->error_server(__('Failed to update source data.', 'ai-post-scheduler'));
		}

		return $this->respond(array(
			'source_data' => $this->data_repo->get_by_id($id),
			'message'     => __('Source data updated.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->data_repo->get_by_id($id)) {
			return $this->error_not_found(__('Source data record', 'ai-post-scheduler'));
		}
		if (!$this->data_repo->delete($id)) {
			return $this->error_server(__('Failed to delete source data.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Source data deleted.', 'ai-post-scheduler')));
	}

	private function fallback_string($value, $fallback) {
		return null === $value ? (string) $fallback : (string) $value;
	}

	private function data_args() {
		return array(
			'url'              => array('type' => 'string', 'format' => 'uri', 'sanitize_callback' => 'esc_url_raw'),
			'page_title'       => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
			'meta_description' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
			'extracted_text'   => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
			'raw_html'         => array('type' => 'string'),
			'fetch_status'     => array('type' => 'string', 'enum' => array('pending', 'success', 'failed')),
			'http_status'      => array('type' => 'integer', 'minimum' => 0),
			'error_message'    => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
			'fetched_at'       => array('type' => 'integer', 'minimum' => 0),
		);
	}
}
