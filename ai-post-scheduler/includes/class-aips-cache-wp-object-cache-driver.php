<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Wp_Object_Cache_Driver
 *
 * Cache driver that delegates to the WordPress Object Cache API.
 *
 * Uses the public WordPress functions `wp_cache_get`, `wp_cache_set`,
 * `wp_cache_delete`, and `wp_cache_flush` — never the internal
 * WP_Object_Cache class directly — so that any persistent object-cache
 * drop-in (Redis, Memcached, etc.) installed on the site is automatically
 * used as the backend.
 *
 * Without a persistent drop-in, the WordPress object cache is request-scoped
 * only. In that scenario this driver behaves similarly to ArrayDriver.
 *
 * Group names are prefixed with 'aips' (or the configured default group) to
 * avoid collisions with other plugins using the same WP object cache.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class AIPS_Cache_Wp_Object_Cache_Driver implements AIPS_Cache_Driver {

	/**
	 * Base group name used as the prefix for all groups.
	 *
	 * @var string
	 */
	private $base_group;

	/**
	 * Constructor.
	 *
	 * @param string $base_group Base group name. Default 'aips'.
	 */
	public function __construct( $base_group = 'aips' ) {
		$this->base_group = !empty( $base_group ) ? (string) $base_group : 'aips';
	}

	// -----------------------------------------------------------------------
	// AIPS_Cache_Driver implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		$found = false;
		$value = wp_cache_get( $key, $this->resolve_group( $group ), false, $found );

		return $found ? $value : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		return wp_cache_set( $key, $value, $this->resolve_group( $group ), (int) $ttl );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		return wp_cache_delete( $key, $this->resolve_group( $group ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Calls wp_cache_flush() which flushes the entire WP object cache —
	 * not just entries written by this plugin. Use with caution.
	 */
	public function flush() {
		return wp_cache_flush();
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		$found = false;
		wp_cache_get( $key, $this->resolve_group( $group ), false, $found );
		return $found;
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the WP object cache group name.
	 *
	 * Groups that are already 'default' or empty are mapped to the base
	 * group; otherwise the group is appended as `{base}_{group}`.
	 *
	 * @param string $group Logical group name.
	 * @return string WP object cache group name.
	 */
	private function resolve_group( $group ) {
		if (empty( $group ) || $group === 'default') {
			return $this->base_group;
		}

		return $this->base_group . '_' . $group;
	}
}
