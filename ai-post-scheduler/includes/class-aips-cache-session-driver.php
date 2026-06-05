<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Session_Driver
 *
 * Cache driver that persists values in the PHP session ($_SESSION).
 *
 * Entries survive across page loads for the lifetime of the user's browser
 * session, making this driver ideal for short-lived, user-specific
 * cross-request caching — for example, caching notification counts for a
 * few minutes without hitting the database on every page.
 *
 * Features:
 * - Full TTL support: expiry timestamps are stored alongside values and
 *   checked on every read.
 * - Namespace isolation: all keys are stored under a configurable namespace
 *   prefix inside $_SESSION so they never collide with other plugins or
 *   application code that also uses the session.
 * - flush() only removes keys belonging to this driver's namespace.
 * - Graceful no-op when a session cannot be started (e.g. headers already
 *   sent). Methods return false/null instead of throwing.
 *
 * Limitations:
 * - Data is user-scoped (tied to the current browser session), not
 *   shared across users.
 * - Requires a running PHP session. If used early in the WordPress boot
 *   (before headers are sent) or in contexts where sessions are not
 *   available (REST API, CLI), consider a different driver.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */
class AIPS_Cache_Session_Driver implements AIPS_Cache_Driver, AIPS_Cache_Monitorable_Driver {

	/**
	 * Namespace prefix used for every key stored in $_SESSION.
	 *
	 * All cache entries managed by this driver will be stored under keys
	 * of the form "{namespace}::{group}:{key}" inside $_SESSION.
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * Whether the PHP session is currently available.
	 *
	 * Set to true when ensure_session() successfully starts or resumes the
	 * session; false when the session could not be started.
	 *
	 * @var bool
	 */
	private $session_available = false;

	/**
	 * Constructor.
	 *
	 * Attempts to start or resume the PHP session.
	 *
	 * @param string $namespace Session key namespace / prefix. Default 'aips_cache'.
	 */
	public function __construct( $namespace = 'aips_cache' ) {
		$this->namespace         = !empty( $namespace ) ? (string) $namespace : 'aips_cache';
		$this->session_available = $this->ensure_session();
	}

	// -----------------------------------------------------------------------
	// AIPS_Cache_Driver implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		if (!$this->session_available) {
			return null;
		}

		$k = $this->make_key( $key, $group );

		if (!isset( $_SESSION[ $k ] )) {
			return null;
		}

		$entry = $_SESSION[ $k ];

		if (!is_array( $entry ) || !array_key_exists( 'value', $entry )) {
			return null;
		}

		// Expire stale entries on read.
		if (!empty( $entry['expires'] ) && $entry['expires'] < time()) {
			unset( $_SESSION[ $k ] );
			return null;
		}

		return maybe_unserialize( $entry['value'] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		if (!$this->session_available) {
			return false;
		}

		$k            = $this->make_key( $key, $group );
		$_SESSION[ $k ] = array(
			'value'   => maybe_serialize( $value ),
			'expires' => ( $ttl > 0 ) ? ( time() + (int) $ttl ) : 0,
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		if (!$this->session_available) {
			return false;
		}

		$k = $this->make_key( $key, $group );
		unset( $_SESSION[ $k ] );
		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Only removes entries belonging to this driver's namespace — other
	 * session data is left untouched.
	 */
	public function flush() {
		if (!$this->session_available) {
			return false;
		}

		$prefix = $this->namespace . '::';

		foreach (array_keys( $_SESSION ) as $k) {
			if (str_starts_with( $k, $prefix )) {
				unset( $_SESSION[ $k ] );
			}
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		return $this->get( $key, $group ) !== null;
	}

	// -----------------------------------------------------------------------
	// Public status helper
	// -----------------------------------------------------------------------

	/**
	 * Return whether the PHP session is currently available.
	 *
	 * @return bool
	 */
	public function is_session_available() {
		return $this->session_available;
	}


	// -----------------------------------------------------------------------
	// Cache Monitor introspection
	// -----------------------------------------------------------------------

	public function get_monitor_capabilities() {
		return array( 'list_keys' => true, 'inspect_entry' => true, 'delete_key' => true, 'delete_group' => true, 'flush_plugin' => true, 'size_bytes' => true, 'ttl_remaining' => true, 'tag_versions' => false, 'live_metrics' => false );
	}

	public function list_entries( array $filters = array(), $limit = 100, $offset = 0 ) {
		if (!$this->session_available) { return array(); }
		$prefix = $this->namespace . '::';
		$entries = array();
		foreach ($_SESSION as $session_key => $entry) {
			if (!str_starts_with( $session_key, $prefix ) || !is_array( $entry )) { continue; }
			$parts = explode( ':', substr( $session_key, strlen( $prefix ) ), 2 );
			$key = isset( $parts[1] ) ? $parts[1] : '';
			$group = isset( $parts[0] ) ? $parts[0] : 'default';
			$value = isset( $entry['value'] ) ? $entry['value'] : '';
			$expires = isset( $entry['expires'] ) ? (int) $entry['expires'] : 0;
			$entries[] = array( 'cache_key' => $key, 'key_hash' => hash( 'sha256', $key ), 'cache_group' => $group, 'driver' => 'session', 'expires_at' => $expires, 'ttl_remaining' => $expires > 0 ? max( 0, $expires - time() ) : null, 'estimated_size' => strlen( (string) $value ), 'value_type' => 'serialized' );
		}
		return array_slice( $entries, max( 0, (int) $offset ), max( 1, (int) $limit ) );
	}

	public function count_entries( array $filters = array() ) {
		return count( $this->list_entries( $filters, 10000, 0 ) );
	}

	public function get_entry_metadata( $key, $group = 'default' ) {
		return array( 'cache_key' => (string) $key, 'key_hash' => hash( 'sha256', (string) $key ), 'cache_group' => (string) $group, 'value' => $this->get( $key, $group ) );
	}

	public function delete_entry( $key, $group = 'default' ) {
		return $this->delete( $key, $group );
	}

	public function delete_group( $group ) {
		if (!$this->session_available) { return false; }
		$prefix = $this->namespace . '::' . (string) $group . ':';
		foreach (array_keys( $_SESSION ) as $key) { if (str_starts_with( $key, $prefix )) { unset( $_SESSION[ $key ] ); } }
		return true;
	}

	public function estimate_size( array $filters = array() ) {
		$total = 0; $count = 0;
		foreach ($this->list_entries( $filters, 10000, 0 ) as $entry) { $total += (int) $entry['estimated_size']; $count++; }
		return array( 'bytes' => $total, 'entries' => $count );
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the fully-qualified session key from key + group.
	 *
	 * Format: "{namespace}::{group}:{key}"
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string Qualified session key.
	 */
	private function make_key( $key, $group ) {
		return $this->namespace . '::' . $group . ':' . $key;
	}

	/**
	 * Ensure the PHP session is started.
	 *
	 * Does nothing if the session is already active. Returns false — without
	 * raising an error — when the session is disabled or cannot be started.
	 *
	 * The error-suppression operator (@) is intentional: `session_start()`
	 * emits an E_WARNING ("headers already sent") that would be converted to
	 * an exception in test environments with `convertWarningsToExceptions`
	 * enabled. We handle failure gracefully via the boolean return value so
	 * driver methods become no-ops rather than crashing the request.
	 *
	 * @return bool True when the session is (or becomes) available.
	 */
	private function ensure_session() {
		if (PHP_SESSION_ACTIVE === session_status()) {
			return true;
		}

		if (PHP_SESSION_DISABLED === session_status()) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencingOperator.Discouraged
		return (bool) @session_start();
	}
}
