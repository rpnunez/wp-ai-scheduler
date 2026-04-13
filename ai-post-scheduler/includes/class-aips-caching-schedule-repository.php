<?php
/**
 * Caching Schedule Repository Decorator
 *
 * Wraps an AIPS_Schedule_Repository_Interface implementation and adds a
 * persistent object-cache layer using the WordPress Object Cache API
 * (wp_cache_get / wp_cache_set).
 *
 * This decorator is only registered in the container when a persistent
 * object-cache drop-in is available (wp_using_ext_object_cache() === true).
 * On sites without a drop-in the concrete repository is used directly,
 * which already has an in-request identity-map cache.
 *
 * TTL: 5 minutes. Schedules carry frequently-updated metadata (next_run,
 * last_run, batch progress) so a shorter TTL is used to keep cached data
 * reasonably fresh while still reducing DB round-trips across requests.
 *
 * Cache invalidation: all cached entries are invalidated after any write
 * (create / update / delete / set_active / update_last_run, etc.).
 * The invalidation is scoped to this driver instance and does not affect
 * other plugin or WordPress cache data.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Caching_Schedule_Repository implements AIPS_Schedule_Repository_Interface {

	/**
	 * Conservative TTL for schedule cache entries: 5 minutes.
	 */
	const TTL = 300;

	/**
	 * WP object-cache group for schedule entries.
	 */
	const GROUP = 'aips_schedules';

	/**
	 * Inner repository that owns the actual DB queries.
	 *
	 * @var AIPS_Schedule_Repository_Interface
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
	 * @param AIPS_Schedule_Repository_Interface $inner Inner repository.
	 * @param AIPS_Cache|null                    $cache Optional cache instance.
	 *                                                   Defaults to a new AIPS_Cache
	 *                                                   using the wp_object_cache driver.
	 */
	public function __construct(
		AIPS_Schedule_Repository_Interface $inner,
		AIPS_Cache $cache = null
	) {
		$this->inner = $inner;
		$this->cache = $cache ?: new AIPS_Cache( new AIPS_Cache_Wp_Object_Cache_Driver( 'aips_schedules' ) );
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
	 * Due-schedule results depend on wall-clock time and are not cached
	 * here; the inner repository's in-request cache handles request-level
	 * deduplication.
	 */
	public function get_due_schedules($current_time = null, $limit = 5) {
		return $this->inner->get_due_schedules( $current_time, $limit );
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
	public function update_last_run($id, $timestamp = null) {
		$result = $this->inner->update_last_run( $id, $timestamp );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_active($id, $is_active) {
		$result = $this->inner->set_active( $id, $is_active );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_batch_progress($id, $completed, $total, $last_index, $post_ids = array()) {
		$result = $this->inner->update_batch_progress( $id, $completed, $total, $last_index, $post_ids );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear_batch_progress($id) {
		$result = $this->inner->clear_batch_progress( $id );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_run_state($id, array $state) {
		$result = $this->inner->update_run_state( $id, $state );
		if ( $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_bulk(array $ids) {
		$result = $this->inner->delete_bulk( $ids );
		if ( false !== $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_active_bulk(array $ids, $is_active) {
		$result = $this->inner->set_active_bulk( $ids, $is_active );
		if ( false !== $result ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Post-count aggregation is not cached here; it is an infrequent
	 * read that varies by the supplied schedule IDs.
	 */
	public function get_post_count_for_schedules(array $ids) {
		return $this->inner->get_post_count_for_schedules( $ids );
	}
}
