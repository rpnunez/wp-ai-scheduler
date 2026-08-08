<?php
namespace AIPS\Cache\Drivers;

if (!defined('ABSPATH')) {
	exit;
}

use AIPS\Cache\CacheDriverInterface;
use AIPS\Cache\CacheMonitorableDriverInterface;

/**
 * Class ArrayDriver
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
class ArrayDriver implements CacheDriverInterface, CacheMonitorableDriverInterface {

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
	// CacheMonitorableDriverInterface implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get_monitor_capabilities(): array {
		return array(
			'list_keys'     => true,
			'inspect_entry' => true,
			'delete_key'    => true,
			'delete_group'  => true,
			'flush_plugin'  => true,
			'size_bytes'    => true,
			'ttl_remaining' => true,
			'tag_versions'  => false,
			'live_metrics'  => false,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_entries( array $filters = array(), int $limit = 100, int $offset = 0 ): array {
		$now    = time();
		$result = array();

		foreach ($this->store as $composite_key => $value) {
			$parts = explode( ':', $composite_key, 2 );
			$group = $parts[0] ?? 'default';
			$key   = $parts[1] ?? $composite_key;

			if (!empty( $filters['group'] ) && $group !== sanitize_key( $filters['group'] )) {
				continue;
			}

			$expires = $this->expiries[ $composite_key ] ?? 0;

			if (!empty( $filters['ttl_state'] )) {
				switch ($filters['ttl_state']) {
					case 'expired':
						if (!($expires > 0 && $expires < $now)) {
							continue 2;
						}
						break;
					case 'active':
						if ($expires > 0 && $expires < $now) {
							continue 2;
						}
						break;
					case 'no_expiration':
						if ($expires !== 0) {
							continue 2;
						}
						break;
				}
			}

			$key_hash = hash( 'sha256', $composite_key );

			if (!empty( $filters['key_hash'] ) && strpos( $key_hash, $filters['key_hash'] ) !== 0) {
				continue;
			}

			$ttl_remaining = $expires > 0 ? max( 0, $expires - $now ) : null;

			$result[] = array(
				'cache_key'     => $key,
				'key_hash'      => $key_hash,
				'cache_group'   => $group,
				'expires_at'    => $expires,
				'value_size'    => strlen( maybe_serialize( $value ) ),
				'ttl_remaining' => $ttl_remaining,
				'driver'        => 'array',
			);
		}

		return array_slice( $result, (int) $offset, max( 1, min( 500, (int) $limit ) ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function count_entries( array $filters = array() ): int {
		return count( $this->list_entries( $filters, PHP_INT_MAX, 0 ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_entry_metadata( string $key, string $group = 'default' ): array {
		$k       = $this->make_key( $key, $group );
		$now     = time();
		$expires = $this->expiries[ $k ] ?? 0;

		if (!array_key_exists( $k, $this->store )) {
			return array();
		}

		return array(
			'cache_key'     => $key,
			'key_hash'      => hash( 'sha256', $k ),
			'cache_group'   => $group,
			'expires_at'    => $expires,
			'value_size'    => strlen( maybe_serialize( $this->store[ $k ] ) ),
			'ttl_remaining' => $expires > 0 ? max( 0, $expires - $now ) : null,
			'driver'        => 'array',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_entry( string $key, string $group = 'default' ): bool {
		return $this->delete( $key, $group );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_group( string $group ): bool {
		$prefix = $group . ':';
		foreach (array_keys( $this->store ) as $k) {
			if (strpos( $k, $prefix ) === 0) {
				unset( $this->store[ $k ], $this->expiries[ $k ] );
			}
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function estimate_size( array $filters = array() ): array {
		$total_bytes   = 0;
		$expired_bytes = 0;
		$expired_count = 0;
		$row_count     = 0;
		$now           = time();

		foreach ($this->store as $k => $value) {
			$size          = strlen( maybe_serialize( $value ) );
			$expires       = $this->expiries[ $k ] ?? 0;
			$total_bytes  += $size;
			$row_count++;

			if ($expires > 0 && $expires < $now) {
				$expired_bytes += $size;
				$expired_count++;
			}
		}

		return array(
			'total_bytes'   => $total_bytes,
			'row_count'     => $row_count,
			'expired_bytes' => $expired_bytes,
			'expired_count' => $expired_count,
			'available'     => true,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_driver_info(): array {
		$size = $this->estimate_size();

		return array(
			'driver'      => 'array',
			'label'       => __( 'In-Memory Array (Request-Local)', 'ai-post-scheduler' ),
			'row_count'   => $size['row_count'],
			'total_bytes' => $size['total_bytes'],
			'limitations' => array(
				__( 'Data is request-scoped and does not persist across page loads.', 'ai-post-scheduler' ),
				__( 'Entries listed here are only visible for the current PHP request.', 'ai-post-scheduler' ),
			),
		);
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
