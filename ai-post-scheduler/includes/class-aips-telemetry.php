<?php
/**
 * Telemetry Collector
 *
 * Accumulates structured events into an in-request buffer and persists
 * a single JSON payload to the aips_telemetry table at PHP shutdown.
 *
 * Usage (anywhere after init):
 *   AIPS_Telemetry::instance()->add_event(array('type' => 'cache_miss', 'key' => 'my_key'));
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Telemetry
 *
 * Singleton telemetry collector.  Call boot() once during plugin
 * initialisation to activate the shutdown flush.  Telemetry is only
 * recorded when the aips_enable_telemetry option is truthy.
 */
class AIPS_Telemetry {

	/**
	 * @var AIPS_Telemetry|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var float Microtime at which this request started.
	 */
	private $start_time;

	/**
	 * @var array In-request event buffer.
	 */
	private $events = array();

	/**
	 * @var array<string, mixed> Aggregated in-request event metrics.
	 */
	private $event_summary = array(
		'total'   => 0,
		'buckets' => array(),
		'types'   => array(),
	);

	/**
	 * @var bool Whether the shutdown handler has been registered.
	 */
	private $shutdown_registered = false;

	/**
	 * @var bool Whether this request has already been flushed.
	 */
	private $flushed = false;

	/**
	 * @var AIPS_Telemetry_Repository
	 */
	private $repository;

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {
		// Use a constant defined as early as possible so the elapsed-time
		// measurement starts before plugins_loaded fires.
		$this->start_time = defined('AIPS_REQUEST_START') ? AIPS_REQUEST_START : microtime(true);
		$this->repository = AIPS_Telemetry_Repository::instance();
	}

	/**
	 * Whether telemetry collection is currently enabled.
	 *
	 * Use this to guard add_event() call-sites so disabled requests incur
	 * zero overhead — no singleton creation, no event-buffer allocation.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		static $is_checking = false;

		// Prevent re-entrant loops when telemetry is checked during option-cache
		// operations that themselves pass through AIPS_Cache instrumentation.
		if ($is_checking) {
			return false;
		}

		$is_checking = true;
		$enabled     = (bool) get_option('aips_enable_telemetry', false);
		$is_checking = false;

		return $enabled;
	}

	/**
	 * Return (and lazily create) the singleton.
	 *
	 * @return AIPS_Telemetry
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add a structured event to the in-request buffer.
	 *
	 * Backward compatible forms:
	 * - add_event( array( 'type' => 'cache_hit' ) )
	 * - add_event( 'cache', array( 'type' => 'cache_hit' ) )
	 *
	 * @param string|array $bucket_or_data Event bucket key or full event data.
	 * @param array        $data           Arbitrary key/value pairs describing the event.
	 *                    A '_ts' key (microseconds since request start) is added automatically.
	 * @return void
	 */
	public function add_event($bucket_or_data, array $data = array()) {
		$bucket = 'general';

		if (is_array($bucket_or_data)) {
			$data = $bucket_or_data;
		} else {
			$bucket = sanitize_key((string) $bucket_or_data);
		}

		if (!empty($data)) {
			if (empty($bucket) && !empty($data['_bucket'])) {
				$bucket = sanitize_key((string) $data['_bucket']);
			}

			if (empty($bucket)) {
				$bucket = $this->infer_bucket($data);
			}

			$data['_bucket'] = $bucket;
			$data['_ts'] = (int) round((microtime(true) - $this->start_time) * 1000000);
			$this->update_event_summary($bucket, $data);
			$this->events[] = $data;
		}
	}

	/**
	 * Register the shutdown handler that will persist the telemetry row.
	 *
	 * Safe to call multiple times; registers only once per request.
	 *
	 * @return void
	 */
	public function boot() {
		if ($this->shutdown_registered) {
			return;
		}
		$this->shutdown_registered = true;
		register_shutdown_function(array($this, 'flush'));
	}

	/**
	 * Persist the accumulated buffer to the aips_telemetry table.
	 *
	 * Called automatically via register_shutdown_function, but may also
	 * be called directly in tests.
	 *
	 * @return void
	 */
	public function flush() {
		if ($this->flushed) {
			return;
		}

		// Double-check the option at flush time in case it was toggled mid-request.
		if (!self::is_enabled()) {
			return;
		}

		global $wpdb;

		$elapsed_ms     = round((microtime(true) - $this->start_time) * 1000, 2);
		$num_queries    = isset($wpdb) ? (int) $wpdb->num_queries : 0;
		$peak_memory    = memory_get_peak_usage(true);
		$user_id        = function_exists('is_user_logged_in') && is_user_logged_in() ? (int) get_current_user_id() : 0;
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		$request_uri    = isset($_SERVER['REQUEST_URI'])    ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))             : '';
		$request_type   = $this->resolve_request_type();
		$page           = $this->resolve_page();
		$query_summary  = $this->build_query_summary(isset($wpdb) ? $wpdb : null);
		$event_summary  = $this->get_event_summary();
		$cache_summary  = $this->build_cache_summary($event_summary);
		$categories     = $this->resolve_event_categories($event_summary, $query_summary, $elapsed_ms);

		if ($elapsed_ms >= (float) AIPS_TELEMETRY_SLOW_REQUEST_MS) {
			$this->add_event('performance', array(
				'type'       => 'slow_request',
				'elapsed_ms' => $elapsed_ms,
			));
			$event_summary = $this->get_event_summary();
			$categories    = $this->resolve_event_categories($event_summary, $query_summary, $elapsed_ms);
		}

		$payload = array(
			'events'         => $this->events,
			'event_summary'  => $event_summary,
			'cache_summary'  => $cache_summary,
			'query_summary'  => $query_summary,
			'num_queries'    => $num_queries,
			'peak_memory_mb' => round($peak_memory / 1048576, 3),
			'elapsed_ms'     => $elapsed_ms,
			'request_uri'    => $request_uri,
			'request_type'   => $request_type,
		);

		$inserted = $this->repository->insert(array(
			'type'              => $request_type,
			'page'              => $page,
			'event_categories'  => implode(',', $categories),
			'user_id'           => $user_id,
			'request_method'    => $request_method,
			'num_queries'       => $num_queries,
			'total_events'      => (int) $event_summary['total'],
			'cache_calls'       => (int) $cache_summary['calls'],
			'cache_hits'        => (int) $cache_summary['hits'],
			'cache_misses'      => (int) $cache_summary['misses'],
			'slow_query_count'  => (int) $query_summary['slow_query_count'],
			'duplicate_query_count' => (int) $query_summary['duplicate_query_count'],
			'peak_memory_bytes' => $peak_memory,
			'elapsed_ms'        => $elapsed_ms,
			'payload'           => wp_json_encode($payload),
			'inserted_at'       => current_time('mysql'),
		));

		if ($inserted !== false) {
			$this->events  = array();
			$this->flushed = true;
		}
	}

	/**
	 * Derive a short, human-readable page slug for the current request.
	 *
	 * @return string
	 */
	private function resolve_page() {
		$request_type = $this->resolve_request_type();

		if ('ajax' === $request_type) {
			$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'unknown';
			return 'ajax:' . $action;
		}
		if ('cron' === $request_type) {
			return 'cron';
		}
		if ('admin' === $request_type) {
			$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'admin';
			return 'admin:' . $page;
		}
		return 'frontend';
	}

	/**
	 * Resolve the current request type.
	 *
	 * @return string
	 */
	private function resolve_request_type() {
		if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
			return 'ajax';
		}

		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return 'cron';
		}

		if (function_exists('is_admin') && is_admin()) {
			return 'admin';
		}

		return 'frontend';
	}

	/**
	 * Infer a fallback bucket when a caller uses the legacy add_event(array()) form.
	 *
	 * @param array $data Event data.
	 * @return string
	 */
	private function infer_bucket(array $data) {
		$type = isset($data['type']) ? sanitize_key((string) $data['type']) : '';

		if (str_starts_with($type, 'cache_')) {
			return 'cache';
		}

		if (false !== strpos($type, 'query') || false !== strpos($type, 'sql')) {
			return 'query';
		}

		return 'general';
	}

	/**
	 * Update aggregated event counters.
	 *
	 * @param string $bucket Event bucket.
	 * @param array  $data   Event data.
	 * @return void
	 */
	private function update_event_summary($bucket, array $data) {
		$this->event_summary['total']++;

		if (!isset($this->event_summary['buckets'][$bucket])) {
			$this->event_summary['buckets'][$bucket] = 0;
		}
		$this->event_summary['buckets'][$bucket]++;

		if (!empty($data['type'])) {
			$type = sanitize_key((string) $data['type']);
			if (!isset($this->event_summary['types'][$type])) {
				$this->event_summary['types'][$type] = 0;
			}
			$this->event_summary['types'][$type]++;
		}
	}

	/**
	 * Return the current event summary in a normalized structure.
	 *
	 * @return array<string, mixed>
	 */
	private function get_event_summary() {
		return array(
			'total'   => (int) $this->event_summary['total'],
			'buckets' => $this->event_summary['buckets'],
			'types'   => $this->event_summary['types'],
		);
	}

	/**
	 * Build a cache-specific summary from the aggregated event counts.
	 *
	 * @param array<string, mixed> $event_summary Event summary array.
	 * @return array<string, int>
	 */
	private function build_cache_summary(array $event_summary) {
		$types   = isset($event_summary['types']) && is_array($event_summary['types']) ? $event_summary['types'] : array();
		$summary = array(
			'calls'   => (int) (isset($event_summary['buckets']['cache']) ? $event_summary['buckets']['cache'] : 0),
			'hits'    => 0,
			'misses'  => 0,
			'sets'    => (int) (isset($types['cache_set']) ? $types['cache_set'] : 0),
			'deletes' => (int) (isset($types['cache_delete']) ? $types['cache_delete'] : 0),
			'flushes' => (int) (isset($types['cache_flush']) ? $types['cache_flush'] : 0),
		);

		foreach ($this->events as $event) {
			if (!is_array($event) || !isset($event['_bucket']) || 'cache' !== $event['_bucket']) {
				continue;
			}

			if (array_key_exists('hit', $event)) {
				if ($event['hit']) {
					$summary['hits']++;
				} else {
					$summary['misses']++;
				}
			}

			if (array_key_exists('present', $event)) {
				if ($event['present']) {
					$summary['hits']++;
				} else {
					$summary['misses']++;
				}
			}
		}

		return $summary;
	}

	/**
	 * Analyse the recorded SQL query log when SAVEQUERIES is enabled.
	 *
	 * @param wpdb|null $wpdb WordPress DB adapter.
	 * @return array<string, mixed>
	 */
	private function build_query_summary($wpdb) {
		$summary = array(
			'savequeries_enabled'   => defined('SAVEQUERIES') && SAVEQUERIES,
			'slow_query_threshold_ms' => (float) AIPS_TELEMETRY_SLOW_QUERY_MS,
			'total_logged_queries'  => 0,
			'slow_query_count'      => 0,
			'duplicate_query_count' => 0,
			'duplicate_query_groups' => 0,
			'slow_queries'          => array(),
			'duplicate_queries'     => array(),
		);

		if (!$summary['savequeries_enabled'] || !isset($wpdb) || !isset($wpdb->queries) || !is_array($wpdb->queries)) {
			return $summary;
		}

		$duplicates = array();
		$slow_queries = array();
		$sample_limit = max(1, (int) AIPS_TELEMETRY_QUERY_SAMPLE_LIMIT);

		foreach ($wpdb->queries as $entry) {
			if (!is_array($entry) || empty($entry[0])) {
				continue;
			}

			$sql      = trim((string) $entry[0]);
			$time_ms  = isset($entry[1]) ? round(((float) $entry[1]) * 1000, 2) : 0.0;
			$caller   = isset($entry[2]) ? sanitize_text_field((string) $entry[2]) : '';
			$fingerprint = preg_replace('/\s+/', ' ', $sql);

			$summary['total_logged_queries']++;

			if (!isset($duplicates[$fingerprint])) {
				$duplicates[$fingerprint] = array(
					'sql'     => $sql,
					'count'   => 0,
					'time_ms' => 0.0,
					'caller'  => $caller,
				);
			}

			$duplicates[$fingerprint]['count']++;
			$duplicates[$fingerprint]['time_ms'] += $time_ms;

			if ($time_ms >= (float) AIPS_TELEMETRY_SLOW_QUERY_MS) {
				$summary['slow_query_count']++;
				$slow_queries[] = array(
					'sql'     => $sql,
					'time_ms' => $time_ms,
					'caller'  => $caller,
				);
			}
		}

		foreach ($duplicates as $fingerprint => $entry) {
			if ($entry['count'] < 2) {
				unset($duplicates[$fingerprint]);
				continue;
			}

			$summary['duplicate_query_groups']++;
			$summary['duplicate_query_count'] += (int) $entry['count'];
		}

		usort($slow_queries, array($this, 'sort_queries_by_time_desc'));
		usort($duplicates, array($this, 'sort_duplicate_queries_desc'));

		$summary['slow_queries'] = array_slice($slow_queries, 0, $sample_limit);
		$summary['duplicate_queries'] = array_slice($duplicates, 0, $sample_limit);

		return $summary;
	}

	/**
	 * Resolve all event categories relevant to the current request.
	 *
	 * @param array<string, mixed> $event_summary Event summary.
	 * @param array<string, mixed> $query_summary Query summary.
	 * @param float                $elapsed_ms    Current request elapsed time.
	 * @return array<int, string>
	 */
	private function resolve_event_categories(array $event_summary, array $query_summary, $elapsed_ms) {
		$categories = array();

		if (!empty($event_summary['buckets']) && is_array($event_summary['buckets'])) {
			$categories = array_keys($event_summary['buckets']);
		}

		if (!empty($query_summary['total_logged_queries'])) {
			$categories[] = 'query';
		}

		if (!empty($query_summary['slow_query_count']) || !empty($query_summary['duplicate_query_count']) || $elapsed_ms >= (float) AIPS_TELEMETRY_SLOW_REQUEST_MS) {
			$categories[] = 'performance';
		}

		$categories = array_values(array_unique(array_filter(array_map('sanitize_key', $categories))));
		sort($categories);

		return $categories;
	}

	/**
	 * Sort slow-query entries by duration descending.
	 *
	 * @param array $left  Left row.
	 * @param array $right Right row.
	 * @return int
	 */
	private function sort_queries_by_time_desc(array $left, array $right) {
		return ($right['time_ms'] <=> $left['time_ms']);
	}

	/**
	 * Sort duplicate-query entries by count and duration descending.
	 *
	 * @param array $left  Left row.
	 * @param array $right Right row.
	 * @return int
	 */
	private function sort_duplicate_queries_desc(array $left, array $right) {
		$compare = ($right['count'] <=> $left['count']);
		if (0 !== $compare) {
			return $compare;
		}

		return ($right['time_ms'] <=> $left['time_ms']);
	}
}
