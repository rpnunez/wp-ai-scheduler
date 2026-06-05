<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Array_Driver
 *
 * In-memory, request-scoped cache driver.
 *
 * Values are stored in a plain PHP array for the lifetime of the current
 * request. No data survives a page reload. This driver is always available
 * (no dependencies) and is used as the hard fallback when other drivers
 * cannot initialise.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class AIPS_Cache_Array_Driver implements AIPS_Cache_Driver, AIPS_Cache_Monitorable_Driver {

	/**
	 * In-memory store.
	 *
	 * @var array<string, mixed>
	 */
	private $store = array();

	/**
	 * Per-entry expiry timestamps (0 = no expiry).
	 *
	 * @var array<string, int>
	 */
	private $expiries = array();

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		$k = $this->make_key( $key, $group );

		if (!array_key_exists( $k, $this->store )) {
			return null;
		}

		if (isset( $this->expiries[ $k ] ) && $this->expiries[ $k ] !== 0 && $this->expiries[ $k ] < time()) {
			unset( $this->store[ $k ], $this->expiries[ $k ] );
			return null;
		}

		return $this->store[ $k ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		$k = $this->make_key( $key, $group );
		$this->store[ $k ]    = $value;
		$this->expiries[ $k ] = ( $ttl > 0 ) ? ( time() + (int) $ttl ) : 0;
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		$k = $this->make_key( $key, $group );
		unset( $this->store[ $k ], $this->expiries[ $k ] );
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function flush() {
		$this->store    = array();
		$this->expiries = array();
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		return $this->get( $key, $group ) !== null;
	}


	// -----------------------------------------------------------------------
	// Cache Monitor introspection
	// -----------------------------------------------------------------------

	public function get_monitor_capabilities() {
		return array(
			'list_keys' => true,
			'inspect_entry' => true,
			'delete_key' => true,
			'delete_group' => true,
			'flush_plugin' => true,
			'size_bytes' => true,
			'ttl_remaining' => true,
			'tag_versions' => false,
			'live_metrics' => false,
		);
	}

	public function list_entries( array $filters = array(), $limit = 100, $offset = 0 ) {
		$entries = array();
		foreach ($this->store as $composite => $value) {
			$parts = explode( ':', $composite, 2 );
			$group = isset( $parts[0] ) ? $parts[0] : 'default';
			$key = isset( $parts[1] ) ? $parts[1] : $composite;
			$expires = isset( $this->expiries[ $composite ] ) ? (int) $this->expiries[ $composite ] : 0;
			$entries[] = array(
				'cache_key' => $key,
				'key_hash' => hash( 'sha256', $key ),
				'cache_group' => $group,
				'driver' => 'array',
				'expires_at' => $expires,
				'ttl_remaining' => $expires > 0 ? max( 0, $expires - time() ) : null,
				'estimated_size' => strlen( maybe_serialize( $value ) ),
				'value_type' => is_object( $value ) ? 'object:' . get_class( $value ) : gettype( $value ),
			);
		}
		return array_slice( $entries, max( 0, (int) $offset ), max( 1, (int) $limit ) );
	}

	public function count_entries( array $filters = array() ) {
		return count( $this->store );
	}

	public function get_entry_metadata( $key, $group = 'default' ) {
		$k = $this->make_key( $key, $group );
		if (!array_key_exists( $k, $this->store )) {
			return array();
		}
		return array(
			'cache_key' => (string) $key,
			'key_hash' => hash( 'sha256', (string) $key ),
			'cache_group' => (string) $group,
			'value' => $this->get( $key, $group ),
			'expires_at' => isset( $this->expiries[ $k ] ) ? (int) $this->expiries[ $k ] : 0,
		);
	}

	public function delete_entry( $key, $group = 'default' ) {
		return $this->delete( $key, $group );
	}

	public function delete_group( $group ) {
		$prefix = (string) $group . ':';
		foreach (array_keys( $this->store ) as $k) {
			if (str_starts_with( $k, $prefix )) {
				unset( $this->store[ $k ], $this->expiries[ $k ] );
			}
		}
		return true;
	}

	public function estimate_size( array $filters = array() ) {
		$total = 0;
		foreach ($this->store as $value) {
			$total += strlen( maybe_serialize( $value ) );
		}
		return array( 'bytes' => $total, 'entries' => count( $this->store ) );
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the internal composite key from key + group.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string Composite storage key.
	 */
	private function make_key( $key, $group ) {
		return $group . ':' . $key;
	}
}
