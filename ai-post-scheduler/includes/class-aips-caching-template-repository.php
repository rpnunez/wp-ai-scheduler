<?php
/**
 * Caching Template Repository Decorator
 *
 * Wraps an AIPS_Template_Repository_Interface implementation and adds a
 * persistent object-cache layer using the WordPress Object Cache API
 * (wp_cache_get / wp_cache_set).
 *
 * This decorator is only registered in the container when a persistent
 * object-cache drop-in is available (wp_using_ext_object_cache() === true).
 * On sites without a drop-in the concrete repository is registered directly,
 * which already has an in-request identity-map cache.
 *
 * TTL: 30 minutes. Templates are relatively static; 30 minutes is a
 * conservative TTL that reduces DB round-trips across requests without
 * serving noticeably stale data.
 *
 * Cache invalidation: all cached entries are invalidated after any write
 * (create / update / delete). The invalidation is prefix-scoped so it
 * does not touch unrelated plugin or WordPress cache data.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Caching_Template_Repository implements AIPS_Template_Repository_Interface {

	/**
	 * Conservative TTL for template cache entries: 30 minutes.
	 */
	const TTL = 1800;

	/**
	 * WP object-cache group for template entries.
	 */
	const GROUP = 'aips_templates';

	/**
	 * Inner repository that owns the actual DB queries.
	 *
	 * @var AIPS_Template_Repository_Interface
	 */
	private $inner;

	/**
	 * AIPS_Cache instance backed by the WP Object Cache driver.
	 *
	 * @var AIPS_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Template_Repository_Interface $inner Inner repository.
	 * @param AIPS_Cache|null                    $cache Optional cache instance.
	 *                                                   Defaults to a new AIPS_Cache
	 *                                                   using the wp_object_cache driver.
	 */
	public function __construct(
		AIPS_Template_Repository_Interface $inner,
		AIPS_Cache $cache = null
	) {
		$this->inner = $inner;
		$this->cache = $cache ?: new AIPS_Cache( new AIPS_Cache_Wp_Object_Cache_Driver( 'aips_templates' ) );
	}

	// -----------------------------------------------------------------------
	// Read methods – served from persistent cache when available
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get_all($active_only = false) {
		$key = 'all:' . ( $active_only ? '1' : '0' );
		return $this->cache->remember( $key, self::TTL, function() use ( $active_only ) {
			return $this->inner->get_all( $active_only );
		}, self::GROUP );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_by_id($id) {
		$key = 'id:' . (int) $id;
		if ( $this->cache->has( $key, self::GROUP ) ) {
			return $this->cache->get( $key, self::GROUP );
		}
		$result = $this->inner->get_by_id( $id );
		if ( $result !== null ) {
			$this->cache->set( $key, $result, self::TTL, self::GROUP );
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Search results are not cached because the query varies by search term.
	 */
	public function search($search_term) {
		return $this->inner->search( $search_term );
	}

	// -----------------------------------------------------------------------
	// Write methods – delegate to inner repo and invalidate cache
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function create($data) {
		$result = $this->inner->create( $data );
		if ( false !== $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($id, $data) {
		$result = $this->inner->update( $id, $data );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($id) {
		$result = $this->inner->delete( $id );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_active($id, $is_active) {
		// Delegates to update() on the inner repo, which already calls
		// flush() internally. We call it here for the same reason –
		// so the outer persistent cache is also cleared.
		$result = $this->inner->set_active( $id, $is_active );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	// -----------------------------------------------------------------------
	// Ancillary read methods – pass-through (not worth caching individually)
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function count_by_status() {
		return $this->inner->count_by_status();
	}

	/**
	 * {@inheritdoc}
	 */
	public function name_exists($name, $exclude_id = 0) {
		return $this->inner->name_exists( $name, $exclude_id );
	}
}
