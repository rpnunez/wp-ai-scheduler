<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Invalidation_Bus
 *
 * High-level cache invalidation entrypoint to keep invalidation calls
 * centralized and policy-driven.
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */
class AIPS_Cache_Invalidation_Bus {

	/**
	 * Cache instance.
	 *
	 * @var AIPS_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Cache|null $cache Optional injected cache instance.
	 */
	public function __construct( AIPS_Cache $cache = null ) {
		$this->cache = $cache ? $cache : AIPS_Cache_Factory::instance();
	}

	/**
	 * Emergency full cache reset.
	 *
	 * @return bool
	 */
	public function flush_all() {
		return $this->cache->flush();
	}

	/**
	 * Force rebuild by namespace bump.
	 *
	 * @return int New namespace version.
	 */
	public function force_rebuild() {
		return AIPS_Cache_Factory::force_rebuild();
	}

	/**
	 * Flush all entries in a cache group.
	 *
	 * @param string $group Group name.
	 * @return bool
	 */
	public function flush_group( $group ) {
		return $this->cache->flush_group( $group );
	}

	/**
	 * Flush entries by key prefix within a group.
	 *
	 * @param string $prefix Prefix.
	 * @param string $group  Group name.
	 * @return bool
	 */
	public function flush_prefix( $prefix, $group = 'default' ) {
		return $this->cache->flush_prefix( $prefix, $group );
	}

	/**
	 * Flush a named subsystem using policy rules.
	 *
	 * @param string $subsystem Subsystem identifier.
	 * @return bool
	 */
	public function flush_subsystem( $subsystem ) {
		$rule = AIPS_Cache_Policy::get_rule( (string) $subsystem );
		if (!$rule) {
			return false;
		}

		$group  = isset( $rule['group'] ) ? (string) $rule['group'] : 'default';
		$prefix = isset( $rule['prefix'] ) ? (string) $rule['prefix'] : '';

		if ($prefix !== '') {
			return $this->flush_prefix( $prefix, $group );
		}

		return $this->flush_group( $group );
	}
}
