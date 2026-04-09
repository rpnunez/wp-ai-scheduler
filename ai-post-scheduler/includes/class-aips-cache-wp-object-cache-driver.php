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
 * flush() uses a generation counter instead of calling wp_cache_flush(), so
 * only entries written by this driver instance become unreachable — all other
 * plugin or WordPress cache data is left untouched.
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
	 * Current flush generation counter.
	 *
	 * Incremented on each flush() call. Included as a suffix in every group
	 * name so "flushed" entries become unreachable without touching unrelated
	 * cache data. Loaded from the object cache at construction time.
	 *
	 * @var int
	 */
	private $generation;

	/**
	 * WP object cache group used to store the generation counter.
	 *
	 * Intentionally separate from all content groups so it is never affected
	 * by a generation bump.
	 *
	 * @var string
	 */
	private $meta_group = 'aips__flush_meta';

	/**
	 * Constructor.
	 *
	 * @param string $base_group Base group name. Default 'aips'.
	 */
	public function __construct( $base_group = 'aips' ) {
		$this->base_group = !empty( $base_group ) ? (string) $base_group : 'aips';
		// Load the persisted generation so persistent-cache drop-ins carry the
		// generation across requests correctly.
		$stored           = wp_cache_get( $this->base_group, $this->meta_group );
		$this->generation = ( false !== $stored && is_int( $stored ) ) ? $stored : 0;
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
	 * Increments the internal generation counter and persists it in the
	 * object cache. All previously stored entries become unreachable because
	 * their group names no longer match the new generation suffix. This avoids
	 * calling wp_cache_flush() which would wipe unrelated plugin/WP data.
	 */
	public function flush() {
		$this->generation++;
		wp_cache_set( $this->base_group, $this->generation, $this->meta_group );
		return true;
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
	 * Build the WP object cache group name, incorporating the current flush
	 * generation so that entries from before the last flush() call are
	 * automatically shadowed by a new (empty) group name.
	 *
	 * Groups that are already 'default' or empty are mapped to the base
	 * group; otherwise the group is appended as `{base}_{group}`.
	 * A non-zero generation appends `_g{N}` to the group name.
	 *
	 * @param string $group Logical group name.
	 * @return string WP object cache group name.
	 */
	private function resolve_group( $group ) {
		$base = ( empty( $group ) || $group === 'default' )
			? $this->base_group
			: $this->base_group . '_' . $group;

		return $this->generation > 0 ? $base . '_g' . $this->generation : $base;
	}
}
