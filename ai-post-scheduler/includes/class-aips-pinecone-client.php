<?php
/**
 * Pinecone Client
 *
 * Thin HTTP wrapper around the Pinecone REST API using WP HTTP API.
 * No external SDK dependencies — uses wp_remote_post / wp_remote_get.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Pinecone_Client
 *
 * Provides upsert, query, delete, and stats operations against a Pinecone index.
 * Credentials are read from WordPress options on construction.
 */
class AIPS_Pinecone_Client {

	/**
	 * @var string Pinecone API key.
	 */
	private $api_key;

	/**
	 * @var string Pinecone index name.
	 */
	private $index_name;

	/**
	 * @var string Pinecone region (e.g. us-east-1).
	 */
	private $region;

	/**
	 * @var string Namespace within the index (default: aips).
	 */
	private $namespace;

	/**
	 * @var string|null Full index host URL (cached after first successful connection test).
	 */
	private $host;

	/**
	 * @var AIPS_Logger Logger instance.
	 */
	private $logger;

	/**
	 * Request timeout in seconds.
	 */
	const TIMEOUT = 30;

	/**
	 * Initialize the client from WordPress options.
	 *
	 * @param AIPS_Logger|null $logger Optional logger instance.
	 */
	public function __construct($logger = null) {
		$this->api_key    = (string) get_option('aips_pinecone_api_key', '');
		$this->index_name = (string) get_option('aips_pinecone_index_name', '');
		$this->region     = (string) get_option('aips_pinecone_region', 'us-east-1');
		$this->namespace  = (string) get_option('aips_pinecone_namespace', 'aips');
		$this->host       = (string) get_option('aips_pinecone_host', '') ?: null;
		$this->logger     = $logger ?: new AIPS_Logger();
	}

	/**
	 * Check whether all required credentials are present.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return !empty($this->api_key) && !empty($this->index_name);
	}

	/**
	 * Upsert one or more vectors into the index.
	 *
	 * @param array $vectors Array of vectors, each with keys: id (string), values (float[]), metadata (array).
	 * @return array|WP_Error Response body on success, WP_Error on failure.
	 */
	public function upsert(array $vectors) {
		$payload = array(
			'vectors'   => $vectors,
			'namespace' => $this->namespace,
		);

		return $this->request('/vectors/upsert', 'POST', $payload);
	}

	/**
	 * Query the index for the nearest neighbours of a given vector.
	 *
	 * @param float[] $vector  Query vector.
	 * @param int     $top_k   Number of results to return.
	 * @param array   $filter  Optional metadata filter object.
	 * @return array|WP_Error Array of matches on success, WP_Error on failure.
	 */
	public function query(array $vector, $top_k = 10, array $filter = array()) {
		$payload = array(
			'vector'          => $vector,
			'topK'            => (int) $top_k,
			'namespace'       => $this->namespace,
			'includeMetadata' => true,
			'includeValues'   => false,
		);

		if (!empty($filter)) {
			$payload['filter'] = $filter;
		}

		$response = $this->request('/query', 'POST', $payload);

		if (is_wp_error($response)) {
			return $response;
		}

		return isset($response['matches']) ? $response['matches'] : array();
	}

	/**
	 * Delete vectors by their IDs.
	 *
	 * @param string[] $ids Vector IDs to delete.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete(array $ids) {
		$payload = array(
			'ids'       => $ids,
			'namespace' => $this->namespace,
		);

		$result = $this->request('/vectors/delete', 'POST', $payload);

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Fetch index statistics (also used to verify credentials and cache the host).
	 *
	 * @return array|WP_Error Stats array on success, WP_Error on failure.
	 */
	public function describe_index_stats() {
		return $this->request('/describe_index_stats', 'GET');
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the base host URL for the configured index.
	 *
	 * If `aips_pinecone_host` is set (cached), that is returned directly.
	 * Otherwise falls back to the legacy host pattern for self-managed indexes.
	 *
	 * @return string|WP_Error Base URL or WP_Error when credentials are missing.
	 */
	private function get_base_url() {
		if (!$this->is_configured()) {
			return new WP_Error('pinecone_not_configured', __('Pinecone API key and index name are required.', 'ai-post-scheduler'));
		}

		if (!empty($this->host)) {
			return rtrim($this->host, '/');
		}

		// Fallback host pattern for legacy / self-managed (serverless uses explicit host)
		return 'https://' . $this->index_name . '.svc.' . $this->region . '.pinecone.io';
	}

	/**
	 * Execute an HTTP request against the Pinecone API.
	 *
	 * @param string $path   Relative API path (e.g. '/vectors/upsert').
	 * @param string $method HTTP method ('GET' or 'POST').
	 * @param array  $body   Request body (will be JSON-encoded).
	 * @return array|WP_Error Decoded response body on success, WP_Error on failure.
	 */
	private function request($path, $method, array $body = array()) {
		$base_url = $this->get_base_url();

		if (is_wp_error($base_url)) {
			return $base_url;
		}

		$url = $base_url . $path;

		$args = array(
			'method'  => strtoupper($method),
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Api-Key'      => $this->api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if (!empty($body) && $method === 'POST') {
			$args['body'] = wp_json_encode($body);
		}

		$this->logger->log('Pinecone request: ' . $method . ' ' . $path, 'debug');

		$response = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			$this->logger->log('Pinecone HTTP error: ' . $response->get_error_message(), 'error');
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$raw_body    = wp_remote_retrieve_body($response);
		$decoded     = json_decode($raw_body, true);

		if ($status_code < 200 || $status_code >= 300) {
			$error_message = isset($decoded['message']) ? $decoded['message'] : $raw_body;
			$this->logger->log('Pinecone API error (' . $status_code . '): ' . $error_message, 'error');

			return new WP_Error(
				'pinecone_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__('Pinecone API error (%1$d): %2$s', 'ai-post-scheduler'),
					(int) $status_code,
					(string) $error_message
				),
				array('status' => $status_code, 'body' => $raw_body)
			);
		}

		// Cache the host on successful describe_index_stats so future requests are fast
		if ($path === '/describe_index_stats' && !empty($decoded) && empty($this->host)) {
			$this->host = $base_url;
			update_option('aips_pinecone_host', $base_url);
		}

		$this->logger->log('Pinecone response (' . $status_code . '): OK', 'debug');

		return is_array($decoded) ? $decoded : array();
	}
}
