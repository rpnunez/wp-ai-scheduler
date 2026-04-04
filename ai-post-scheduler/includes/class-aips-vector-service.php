<?php
/**
 * Vector Service
 *
 * Coordinates vector provider operations with fail-open fallback behavior.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Vector_Service {

	/**
	 * Option key for vector diagnostics counters.
	 */
	const DIAGNOSTICS_OPTION_KEY = 'aips_vector_diagnostics_stats';

	/**
	 * Rolling diagnostics window in seconds.
	 */
	const DIAGNOSTICS_WINDOW_SECONDS = DAY_IN_SECONDS;

	/**
	 * @var AIPS_Vector_Provider
	 */
	private $provider;

	/**
	 * @var AIPS_Vector_Provider
	 */
	private $fallback_provider;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @param AIPS_Vector_Provider|null $provider Optional provider override.
	 * @param AIPS_Vector_Provider|null $fallback_provider Optional fallback override.
	 * @param AIPS_Logger|null          $logger Optional logger.
	 */
	public function __construct($provider = null, $fallback_provider = null, $logger = null) {
		$this->logger = $logger ?: new AIPS_Logger();
		$this->fallback_provider = $fallback_provider ?: new AIPS_Vector_Provider_Local(null, $this->logger);
		$this->provider = $provider ?: $this->build_provider_from_settings();

		if (!$this->provider->is_available()) {
			$this->provider = $this->fallback_provider;
		}
	}

	/**
	 * @return string
	 */
	public function get_active_provider_name() {
		return $this->provider->get_name();
	}

	/**
	 * @return bool
	 */
	public function is_remote_provider_active() {
		return $this->provider->get_name() !== 'local';
	}

	/**
	 * @param string $namespace Namespace.
	 * @param array  $vectors   Vector records.
	 * @return bool|WP_Error
	 */
	public function upsert_vectors($namespace, $vectors) {
		if (empty($vectors) || !is_array($vectors)) {
			return true;
		}

		$result = $this->provider->upsert($namespace, $vectors);
		if (!is_wp_error($result)) {
			$this->record_diagnostic('upsert_success');
			return true;
		}

		$this->record_diagnostic('upsert_error', $result->get_error_message());

		$this->logger->log('Vector upsert failed: ' . $result->get_error_message(), 'warning', array(
			'provider' => $this->provider->get_name(),
			'namespace' => $namespace,
		));

		if ($this->is_fail_open_enabled()) {
			return true;
		}

		return $result;
	}

	/**
	 * @param string $namespace Namespace.
	 * @param array  $vector    Query vector.
	 * @param array  $options   Query options.
	 * @return array|WP_Error
	 */
	public function query_neighbors($namespace, $vector, $options = array()) {
		$result = $this->provider->query($namespace, $vector, $options);
		if (!is_wp_error($result)) {
			$this->record_diagnostic('query_success');
			return $result;
		}

		$this->record_diagnostic('query_error', $result->get_error_message());

		$this->logger->log('Vector query failed: ' . $result->get_error_message(), 'warning', array(
			'provider' => $this->provider->get_name(),
			'namespace' => $namespace,
		));

		if ($this->provider->get_name() !== 'local' && !empty($options['candidates'])) {
			$fallback_result = $this->fallback_provider->query($namespace, $vector, $options);
			if (!is_wp_error($fallback_result)) {
				$this->record_diagnostic('query_success');
				return $fallback_result;
			}
		}

		if ($this->is_fail_open_enabled()) {
			return array();
		}

		return $result;
	}

	/**
	 * @return AIPS_Vector_Provider
	 */
	private function build_provider_from_settings() {
		$provider_name = sanitize_key((string) get_option('aips_vector_provider', 'local'));
		if ($provider_name === 'pinecone') {
			return new AIPS_Vector_Provider_Pinecone($this->logger);
		}

		return new AIPS_Vector_Provider_Local(null, $this->logger);
	}

	/**
	 * @return bool
	 */
	private function is_fail_open_enabled() {
		return (int) get_option('aips_vector_fail_open', 1) === 1;
	}

	/**
	 * Get a snapshot of recent vector diagnostics counters.
	 *
	 * @param int $window_seconds Window length in seconds.
	 * @return array
	 */
	public static function get_diagnostics_snapshot($window_seconds = self::DIAGNOSTICS_WINDOW_SECONDS) {
		$window_seconds = absint($window_seconds);
		if ($window_seconds < 60) {
			$window_seconds = self::DIAGNOSTICS_WINDOW_SECONDS;
		}

		$stats = get_option(self::DIAGNOSTICS_OPTION_KEY, array());
		$defaults = array(
			'window_started_at' => time(),
			'upsert_success' => 0,
			'upsert_error' => 0,
			'query_success' => 0,
			'query_error' => 0,
			'last_error_message' => '',
			'last_provider' => '',
			'last_event_at' => 0,
		);

		if (!is_array($stats)) {
			$stats = array();
		}

		$stats = wp_parse_args($stats, $defaults);
		$window_started_at = absint($stats['window_started_at']);

		if ($window_started_at <= 0 || (time() - $window_started_at) > $window_seconds) {
			$stats = $defaults;
			$stats['window_started_at'] = time();
			update_option(self::DIAGNOSTICS_OPTION_KEY, $stats, false);
		}

		return $stats;
	}

	/**
	 * Record a vector diagnostics event.
	 *
	 * @param string $counter_key Counter key to increment.
	 * @param string $error_message Optional last error message.
	 * @return void
	 */
	private function record_diagnostic($counter_key, $error_message = '') {
		$stats = self::get_diagnostics_snapshot();

		if (isset($stats[$counter_key])) {
			$stats[$counter_key] = absint($stats[$counter_key]) + 1;
		}

		$stats['last_provider'] = $this->provider->get_name();
		$stats['last_event_at'] = time();

		if ($error_message !== '') {
			$stats['last_error_message'] = sanitize_text_field($error_message);
		}

		update_option(self::DIAGNOSTICS_OPTION_KEY, $stats, false);
	}
}
