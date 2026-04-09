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
class AIPS_Cache_Array_Driver implements AIPS_Cache_Driver {

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
