<?php
/**
 * Local Vector Provider
 *
 * Uses in-process embeddings similarity for nearest-neighbor search.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Vector_Provider_Local implements AIPS_Vector_Provider {

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @param AIPS_Embeddings_Service|null $embeddings_service Optional embeddings service.
	 * @param AIPS_Logger|null             $logger Optional logger.
	 */
	public function __construct($embeddings_service = null, $logger = null) {
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->logger = $logger ?: new AIPS_Logger();
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'local';
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @param string $namespace Namespace (unused for local provider).
	 * @param array  $vectors   Vector records (unused for local provider).
	 * @return bool
	 */
	public function upsert($namespace, $vectors) {
		return true;
	}

	/**
	 * @param string $namespace Namespace (unused for local provider).
	 * @param array  $vector    Query vector.
	 * @param array  $options   Requires candidates for local KNN.
	 * @return array|WP_Error
	 */
	public function query($namespace, $vector, $options = array()) {
		if (!is_array($vector) || empty($vector)) {
			return new WP_Error('invalid_vector', __('Invalid vector for local query.', 'ai-post-scheduler'));
		}

		$candidates = isset($options['candidates']) && is_array($options['candidates']) ? $options['candidates'] : array();
		if (empty($candidates)) {
			return array();
		}

		$top_k = isset($options['top_k']) ? absint($options['top_k']) : 5;
		if ($top_k < 1) {
			$top_k = 5;
		}

		$neighbors = $this->embeddings_service->find_nearest_neighbors($vector, $candidates, $top_k);
		$results = array();

		foreach ($neighbors as $neighbor) {
			$metadata = array();
			if (isset($neighbor['metadata']) && is_array($neighbor['metadata'])) {
				$metadata = $neighbor['metadata'];
			} elseif (isset($neighbor['data']) && is_array($neighbor['data'])) {
				$metadata = $neighbor['data'];
			}

			$results[] = array(
				'id' => isset($neighbor['id']) ? (string) $neighbor['id'] : '',
				'score' => isset($neighbor['similarity']) ? (float) $neighbor['similarity'] : 0.0,
				'metadata' => $metadata,
			);
		}

		return $results;
	}
}
