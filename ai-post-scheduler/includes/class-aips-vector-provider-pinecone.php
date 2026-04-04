<?php
/**
 * Pinecone Vector Provider
 *
 * Implements vector operations backed by Pinecone.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Vector_Provider_Pinecone implements AIPS_Vector_Provider {

	/**
	 * @var string
	 */
	private $api_key;

	/**
	 * @var string
	 */
	private $index_host;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $api_version;

	/**
	 * @param AIPS_Logger|null $logger Optional logger.
	 * @param string|null      $api_key Optional API key override.
	 * @param string|null      $index_host Optional host override.
	 */
	public function __construct($logger = null, $api_key = null, $index_host = null) {
		$this->logger = $logger ?: new AIPS_Logger();
		$this->api_key = $api_key !== null ? trim((string) $api_key) : trim((string) get_option('aips_pinecone_api_key', ''));
		$this->index_host = $index_host !== null ? trim((string) $index_host) : trim((string) get_option('aips_pinecone_index_host', ''));
		$this->api_version = '2024-07';
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'pinecone';
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		return $this->api_key !== '' && $this->index_host !== '';
	}

	/**
	 * @param string $namespace Namespace.
	 * @param array  $vectors   Vector records.
	 * @return bool|WP_Error
	 */
	public function upsert($namespace, $vectors) {
		if (!$this->is_available()) {
			return new WP_Error('pinecone_unavailable', __('Pinecone configuration is incomplete.', 'ai-post-scheduler'));
		}

		if (empty($vectors) || !is_array($vectors)) {
			return true;
		}

		$prepared_vectors = array();
		foreach ($vectors as $vector) {
			if (empty($vector['id']) || empty($vector['values']) || !is_array($vector['values'])) {
				continue;
			}

			$prepared = array(
				'id' => (string) $vector['id'],
				'values' => array_map('floatval', $vector['values']),
			);

			if (!empty($vector['metadata']) && is_array($vector['metadata'])) {
				$prepared['metadata'] = $this->sanitize_metadata($vector['metadata']);
			}

			$prepared_vectors[] = $prepared;
		}

		if (empty($prepared_vectors)) {
			return true;
		}

		$payload = array(
			'namespace' => $this->sanitize_namespace($namespace),
			'vectors' => $prepared_vectors,
		);

		$response = $this->request('vectors/upsert', $payload);
		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	/**
	 * @param string $namespace Namespace.
	 * @param array  $vector    Query vector.
	 * @param array  $options   Query options.
	 * @return array|WP_Error
	 */
	public function query($namespace, $vector, $options = array()) {
		if (!$this->is_available()) {
			return new WP_Error('pinecone_unavailable', __('Pinecone configuration is incomplete.', 'ai-post-scheduler'));
		}

		if (!is_array($vector) || empty($vector)) {
			return new WP_Error('invalid_vector', __('Invalid vector for Pinecone query.', 'ai-post-scheduler'));
		}

		$top_k = isset($options['top_k']) ? absint($options['top_k']) : 5;
		$top_k = max(1, min(100, $top_k));

		$payload = array(
			'namespace' => $this->sanitize_namespace($namespace),
			'vector' => array_map('floatval', $vector),
			'topK' => $top_k,
			'includeMetadata' => true,
			'includeValues' => false,
		);

		if (!empty($options['filter']) && is_array($options['filter'])) {
			$payload['filter'] = $this->sanitize_filter($options['filter']);
		}

		$response = $this->request('query', $payload);
		if (is_wp_error($response)) {
			return $response;
		}

		$matches = isset($response['matches']) && is_array($response['matches']) ? $response['matches'] : array();
		$results = array();

		foreach ($matches as $match) {
			$results[] = array(
				'id' => isset($match['id']) ? (string) $match['id'] : '',
				'score' => isset($match['score']) ? (float) $match['score'] : 0.0,
				'metadata' => isset($match['metadata']) && is_array($match['metadata']) ? $match['metadata'] : array(),
			);
		}

		return $results;
	}

	/**
	 * Test whether the configured Pinecone index is reachable.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if (!$this->is_available()) {
			return new WP_Error('pinecone_unavailable', __('Pinecone configuration is incomplete.', 'ai-post-scheduler'));
		}

		$response = $this->request('describe_index_stats', array());
		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	/**
	 * @param string $path Endpoint path.
	 * @param array  $payload Request payload.
	 * @return array|WP_Error
	 */
	private function request($path, $payload) {
		$endpoint = $this->build_endpoint($path);
		$response = wp_remote_post($endpoint, array(
			'timeout' => 15,
			'headers' => array(
				'Api-Key' => $this->api_key,
				'Content-Type' => 'application/json',
				'X-Pinecone-API-Version' => $this->api_version,
			),
			'body' => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 300) {
			$this->logger->log('Pinecone request failed: ' . $code, 'warning', array('path' => $path));
			return new WP_Error('pinecone_http_error', sprintf(__('Pinecone request failed with status %d.', 'ai-post-scheduler'), $code));
		}

		$decoded = json_decode($body, true);
		if (!is_array($decoded)) {
			return new WP_Error('pinecone_invalid_json', __('Invalid JSON response from Pinecone.', 'ai-post-scheduler'));
		}

		return $decoded;
	}

	/**
	 * @param string $path Endpoint path.
	 * @return string
	 */
	private function build_endpoint($path) {
		$host = trim($this->index_host);
		if (stripos($host, 'http://') !== 0 && stripos($host, 'https://') !== 0) {
			$host = 'https://' . ltrim($host, '/');
		}

		return rtrim($host, '/') . '/' . ltrim($path, '/');
	}

	/**
	 * @param string $namespace Raw namespace.
	 * @return string
	 */
	private function sanitize_namespace($namespace) {
		$sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $namespace);
		return $sanitized !== '' ? $sanitized : 'default';
	}

	/**
	 * @param array $metadata Raw metadata.
	 * @return array
	 */
	private function sanitize_metadata($metadata) {
		$sanitized = array();
		foreach ($metadata as $key => $value) {
			$meta_key = sanitize_key($key);
			if ($meta_key === '') {
				continue;
			}

			if (is_scalar($value) || $value === null) {
				$sanitized[$meta_key] = is_string($value) ? sanitize_text_field($value) : $value;
			}
		}

		return $sanitized;
	}

	/**
	 * @param array $filter Raw filter.
	 * @return array
	 */
	private function sanitize_filter($filter) {
		$sanitized = array();
		foreach ($filter as $key => $value) {
			$filter_key = sanitize_key($key);
			if ($filter_key === '') {
				continue;
			}

			if (is_scalar($value) || $value === null) {
				$sanitized[$filter_key] = is_string($value) ? sanitize_text_field($value) : $value;
			}
		}

		return $sanitized;
	}
}
