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
	 * @param array $data Arbitrary key/value pairs describing the event.
	 *                    A '_ts' key (microseconds since request start) is added automatically.
	 * @return void
	 */
	public function add_event(array $data) {
		if (!empty($data)) {
			$data['_ts'] = (int) round((microtime(true) - $this->start_time) * 1000000);
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
		if (!AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			return;
		}

		global $wpdb;

		$elapsed_ms     = round((microtime(true) - $this->start_time) * 1000, 2);
		$num_queries    = isset($wpdb) ? (int) $wpdb->num_queries : 0;
		$peak_memory    = memory_get_peak_usage(true);
		$user_id        = function_exists('is_user_logged_in') && is_user_logged_in() ? (int) get_current_user_id() : 0;
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		$request_uri    = isset($_SERVER['REQUEST_URI'])    ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))             : '';
		$page           = $this->resolve_page();

		$payload = array(
			'events'         => $this->events,
			'num_queries'    => $num_queries,
			'peak_memory_mb' => round($peak_memory / 1048576, 3),
			'elapsed_ms'     => $elapsed_ms,
			'request_uri'    => $request_uri,
		);

		$inserted = $this->repository->insert(array(
			'page'              => $page,
			'user_id'           => $user_id,
			'request_method'    => $request_method,
			'num_queries'       => $num_queries,
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
		if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
			$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'unknown';
			return 'ajax:' . $action;
		}
		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return 'cron';
		}
		if (function_exists('is_admin') && is_admin()) {
			$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'admin';
			return 'admin:' . $page;
		}
		return 'frontend';
	}
}
