<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Repository cache tier configuration and cache-instance resolution.
 */
class AIPS_Repository_Cache_Config {

	const TIER_REQUEST = 'request';
	const TIER_SHORT = 'short';
	const TIER_MEDIUM = 'medium';
	const TIER_LONG = 'long';
	const TIER_NONE = 'none';

	/**
	 * Return the resolved configuration for a repository cache tier.
	 *
	 * @param string $tier Tier name.
	 * @return array<string, mixed>
	 */
	public static function get_tier_config( string $tier ): array {
		$tier              = self::normalize_tier( $tier );
		$persistent_driver = self::get_persistent_driver_name();

		$configs = array(
			self::TIER_REQUEST => array(
				'tier'               => self::TIER_REQUEST,
				'driver_name'        => 'array',
				'default_ttl'        => 0,
				'persistent_allowed' => false,
				'bypass_on_cron'     => false,
				'allow_stale_reads'  => false,
			),
			self::TIER_SHORT   => array(
				'tier'               => self::TIER_SHORT,
				'driver_name'        => $persistent_driver,
				'default_ttl'        => 5 * MINUTE_IN_SECONDS,
				'persistent_allowed' => true,
				'bypass_on_cron'     => true,
				'allow_stale_reads'  => false,
			),
			self::TIER_MEDIUM  => array(
				'tier'               => self::TIER_MEDIUM,
				'driver_name'        => $persistent_driver,
				'default_ttl'        => HOUR_IN_SECONDS,
				'persistent_allowed' => true,
				'bypass_on_cron'     => false,
				'allow_stale_reads'  => true,
			),
			self::TIER_LONG    => array(
				'tier'               => self::TIER_LONG,
				'driver_name'        => $persistent_driver,
				'default_ttl'        => DAY_IN_SECONDS,
				'persistent_allowed' => true,
				'bypass_on_cron'     => false,
				'allow_stale_reads'  => true,
			),
			self::TIER_NONE    => array(
				'tier'               => self::TIER_NONE,
				'driver_name'        => 'none',
				'default_ttl'        => 0,
				'persistent_allowed' => false,
				'bypass_on_cron'     => true,
				'allow_stale_reads'  => false,
			),
		);

		return $configs[ $tier ];
	}

	/**
	 * Resolve the effective TTL for a repository cache policy.
	 *
	 * @param array $policy Repository cache policy.
	 * @return int
	 */
	public static function resolve_ttl( array $policy ): int {
		if (isset( $policy['ttl'] ) && is_numeric( $policy['ttl'] )) {
			return max( 0, (int) $policy['ttl'] );
		}

		$config = self::get_tier_config( self::policy_tier( $policy ) );

		return max( 0, (int) $config['default_ttl'] );
	}

	/**
	 * Resolve the cache instance for a repository cache group and policy.
	 *
	 * Returns null when the policy disables caching or when the tier should
	 * bypass cache reads/writes during cron execution.
	 *
	 * @param string $group Repository cache group.
	 * @param array  $policy Repository cache policy.
	 * @return AIPS_Cache|null
	 */
	public static function resolve_cache_instance( string $group, array $policy ): ?AIPS_Cache {
		$config = self::get_tier_config( self::policy_tier( $policy ) );

		if (self::TIER_NONE === $config['tier']) {
			return null;
		}

		$bypass_on_cron = array_key_exists( 'bypass_on_cron', $policy ) ? (bool) $policy['bypass_on_cron'] : (bool) $config['bypass_on_cron'];
		if ($bypass_on_cron && self::is_doing_cron()) {
			return null;
		}

		$cache_name = self::build_cache_name( $group, (string) $config['tier'] );
		if (!empty( $config['persistent_allowed'] )) {
			return AIPS_Cache_Factory::named( $cache_name );
		}

		return AIPS_Cache_Factory::named( $cache_name, 'array' );
	}

	/**
	 * Normalize a requested tier.
	 *
	 * @param string $tier Tier name.
	 * @return string
	 */
	private static function normalize_tier( string $tier ): string {
		$tier = strtolower( trim( $tier ) );

		if (in_array( $tier, array( self::TIER_REQUEST, self::TIER_SHORT, self::TIER_MEDIUM, self::TIER_LONG, self::TIER_NONE ), true )) {
			return $tier;
		}

		return self::TIER_NONE;
	}

	/**
	 * Resolve the persistent cache driver from plugin settings.
	 *
	 * Uses direct option reads to avoid unnecessary config bootstrapping.
	 *
	 * @return string
	 */
	private static function get_persistent_driver_name(): string {
		$driver_name = get_option( 'aips_cache_driver', 'array' );
		$driver_name = is_scalar( $driver_name ) ? strtolower( trim( (string) $driver_name ) ) : 'array';

		if ('' === $driver_name) {
			return 'array';
		}

		switch ( $driver_name ) {
			case 'db':
			case 'wp_object_cache':
			case 'array':
				return $driver_name;


			default:
				return 'array';
		}
	}

	/**
	 * Resolve the policy tier with a safe default.
	 *
	 * @param array $policy Repository cache policy.
	 * @return string
	 */
	private static function policy_tier( array $policy ): string {
		if (isset( $policy['tier'] ) && is_scalar( $policy['tier'] )) {
			return self::normalize_tier( (string) $policy['tier'] );
		}

		return self::TIER_NONE;
	}

	/**
	 * Build a stable named-cache identifier for a tier/group pair.
	 *
	 * @param string $group Repository cache group.
	 * @param string $tier Cache tier.
	 * @return string
	 */
	private static function build_cache_name( string $group, string $tier ): string {
		$group = sanitize_key( $group );

		return 'aips_repository_cache_' . sanitize_key( $tier ) . '_' . ( $group ? $group : 'default' );
	}

	/**
	 * Determine whether the current request is running in cron context.
	 *
	 * @return bool
	 */
	private static function is_doing_cron(): bool {
		if (function_exists( 'wp_doing_cron' )) {
			return wp_doing_cron();
		}

		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}