<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared repository cache mechanics for explicit operation IDs.
 */
trait AIPS_Cacheable_Repository {

	/**
	 * Scoped repository-cache bypass depth.
	 *
	 * When greater than zero, repository cache reads are bypassed for the
	 * lifetime of the current without_repository_cache() scope.
	 *
	 * @var int
	 */
	private $repository_cache_bypass_depth = 0;

	/**
	 * Memoized policy map from the first call to repository_cache_policies().
	 *
	 * @var array|null
	 */
	private $repository_cache_policies_memo = null;

	/**
	 * Memoized observer instance from the first call to repository_cache_observer().
	 *
	 * @var AIPS_Repository_Cache_Observer|null
	 */
	private $repository_cache_observer_memo = null;

	/**
	 * Memoized cache-instance list for tag-version invalidations.
	 *
	 * @var array|null
	 */
	private $repository_cache_invalidation_caches_memo = null;

	/**
	 * Read through the repository cache using an explicit operation ID.
	 *
	 * @param string   $operation_id Explicit repository operation identifier.
	 * @param array    $args Operation arguments used for key generation.
	 * @param callable $callback Callback that rebuilds the value on a miss.
	 * @param array    $options Per-call cache options.
	 * @return mixed
	 */
	protected function cache_read( string $operation_id, array $args, callable $callback, array $options = array() ) {
		$policy = $this->repository_cache_policy_for( $operation_id );
		if (empty( $policy )) {
			return $this->run_repository_cache_bypass( $operation_id, $args, $callback, $policy, 'uncached_policy' );
		}

		$policy = $this->normalize_repository_cache_policy( $policy );
		$bypass_options = $options;
		if ( ! empty( $bypass_options['force_refresh'] ) ) {
			unset( $bypass_options['force_refresh'] );
		}

		if ( $this->repository_cache_should_bypass( $policy, $bypass_options ) ) {
			return $this->run_repository_cache_bypass(
				$operation_id,
				$args,
				$callback,
				$policy,
				$this->resolve_bypass_reason( $policy, $options )
			);
		}

		$cache = AIPS_Repository_Cache_Config::resolve_cache_instance( $this->repository_cache_group(), $policy );
		if (!$cache) {
			return $this->run_repository_cache_bypass( $operation_id, $args, $callback, $policy, $this->resolve_bypass_reason( $policy, $options ) );
		}

		$tags         = $this->repository_cache_tags( $operation_id, $policy, $args );
		$tag_versions = $cache->get_tag_versions( $tags, $this->repository_cache_group() );
		$key          = AIPS_Repository_Cache_Key_Builder::build_key( $operation_id, $args, $tag_versions );
		$key_hash     = hash( 'sha256', $key );
		$observer     = $this->cached_repository_cache_observer();
		$tier         = isset( $policy['tier'] ) ? (string) $policy['tier'] : AIPS_Repository_Cache_Config::TIER_NONE;
		$allow_stale  = !empty( $policy['allow_stale_reads'] );

		if (empty( $options['force_refresh'] )) {
			$start   = microtime( true );
			$payload = $cache->get( $key, $this->repository_cache_group() );
			if ($this->is_repository_cache_payload( $payload )) {
				$this->record_repository_cache_read(
					$observer,
					array(
						'operation_id' => $operation_id,
						'key_hash'     => $key_hash,
						'tier'         => $tier,
						'tags'         => $tags,
						'hit'          => true,
						'miss'         => false,
						'stale'        => false,
						'elapsed_ms'   => $this->elapsed_ms( $start ),
					)
				);
				return $payload['value'];
			}
		}

		if ( ! empty( $options['force_refresh'] ) ) {
			$this->record_repository_cache_bypass(
				$observer,
				array(
					'operation_id'          => $operation_id,
					'key_hash'              => $key_hash,
					'tier'                  => $tier,
					'tags'                  => $tags,
					'invalidation_reason'   => 'force_refresh',
				)
			);
		}

		$rebuild_start = microtime( true );
		$value         = $callback();
		$rebuild_ms    = $this->elapsed_ms( $rebuild_start );

		$this->record_repository_cache_read(
			$observer,
			array(
				'operation_id' => $operation_id,
				'key_hash'     => $key_hash,
				'tier'         => $tier,
				'tags'         => $tags,
				'hit'          => false,
				'miss'         => true,
				'stale'        => false,
				'elapsed_ms'   => $rebuild_ms,
			)
		);

		if (null === $value && empty( $policy['cache_null'] )) {
			return $value;
		}

		$cache->set(
			$key,
			$this->wrap_repository_cache_payload( $value ),
			AIPS_Repository_Cache_Config::resolve_ttl( $policy ),
			$this->repository_cache_group()
		);

		$this->record_repository_cache_write(
			$observer,
			array(
				'operation_id' => $operation_id,
				'key_hash'     => $key_hash,
				'tier'         => $tier,
				'tags'         => $tags,
				'stale'        => $allow_stale ? false : null,
				'elapsed_ms'   => $rebuild_ms,
			)
		);

		return $value;
	}

	/**
	 * Explicitly bypass the repository cache for a read.
	 *
	 * @param string   $operation_id Explicit repository operation identifier.
	 * @param array    $args Operation arguments used for observability.
	 * @param callable $callback Callback that returns the uncached value.
	 * @param array    $options Per-call cache options.
	 * @return mixed
	 */
	protected function cache_bypass_read( string $operation_id, array $args, callable $callback, array $options = array() ) {
		$options['bypass_cache'] = true;

		return $this->run_repository_cache_bypass(
			$operation_id,
			$args,
			$callback,
			$this->normalize_repository_cache_policy( $this->repository_cache_policy_for( $operation_id ) ),
			'explicit_bypass'
		);
	}

	/**
	 * Invalidate a higher-level repository cache domain.
	 *
	 * @param string $domain Domain name.
	 * @param array  $context Optional domain context.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	protected function invalidate_cache_domain( string $domain, array $context = array(), string $reason = '' ) {
		$tags = array( sanitize_key( $domain ) );

		if (class_exists( 'AIPS_Repository_Cache_Dependencies' ) && method_exists( 'AIPS_Repository_Cache_Dependencies', 'tags_for_invalidation' )) {
			$resolved_tags = AIPS_Repository_Cache_Dependencies::tags_for_invalidation( $domain, $context );
			if (is_array( $resolved_tags ) && !empty( $resolved_tags )) {
				$tags = $resolved_tags;
			}
		}

		$this->invalidate_cache_tags( $tags, $reason );
	}

	/**
	 * Invalidate one or more repository cache tags by bumping their versions.
	 *
	 * @param array  $tags Tags to invalidate.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	protected function invalidate_cache_tags( array $tags, string $reason = '' ) {
		$tags = $this->sanitize_repository_cache_tags( $tags );
		if (empty( $tags )) {
			return;
		}

		foreach ( $this->get_memoized_invalidation_caches() as $cache ) {
			$cache->bump_tag_versions( $tags, $this->repository_cache_group() );
		}

		$this->record_repository_cache_invalidation(
			$this->cached_repository_cache_observer(),
			array(
				'operation_id'        => 'repository.invalidate',
				'tags'                => $tags,
				'invalidation_reason' => $reason ? $reason : 'cache_invalidation',
			)
		);
	}

	/**
	 * Return the repository cache group used by this repository.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'default';
	}

	/**
	 * Return the explicit repository cache policy map for this repository.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array();
	}

	/**
	 * Resolve the observer used for repository cache telemetry and logging.
	 *
	 * Repositories may override this to inject a shared observer. The default
	 * implementation is memoized per instance — override only when a fresh
	 * observer is intentionally required on each call.
	 *
	 * @return AIPS_Repository_Cache_Observer
	 */
	protected function repository_cache_observer() {
		return new AIPS_Repository_Cache_Observer();
	}

	/**
	 * Return a memoized observer instance for internal use.
	 *
	 * Caches the result of repository_cache_observer() so a single object is
	 * reused across all sub-operations within one cache_read() call.
	 *
	 * @return AIPS_Repository_Cache_Observer
	 */
	private function cached_repository_cache_observer(): AIPS_Repository_Cache_Observer {
		if ($this->repository_cache_observer_memo === null) {
			$this->repository_cache_observer_memo = $this->repository_cache_observer();
		}
		return $this->repository_cache_observer_memo;
	}

	/**
	 * Determine whether the current request should bypass this cache policy.
	 *
	 * @param array $policy Repository cache policy.
	 * @param array $options Per-call options.
	 * @return bool
	 */
	protected function repository_cache_should_bypass( array $policy, array $options = array() ): bool {
		if ($this->repository_cache_bypass_depth > 0) {
			return true;
		}

		if ( ! empty( $options['bypass_cache'] ) ) {
			return true;
		}

		if ( ! empty( $options['queue_sensitive'] ) || ! empty( $options['lock_sensitive'] ) ) {
			return true;
		}

		if ($this->is_doing_ajax() && !empty( $policy['bypass_ajax'] )) {
			return true;
		}

		if (function_exists( 'wp_doing_cron' ) && wp_doing_cron() && !empty( $policy['bypass_on_cron'] )) {
			return true;
		}

		return AIPS_Repository_Cache_Config::TIER_NONE === ( isset( $policy['tier'] ) ? $policy['tier'] : AIPS_Repository_Cache_Config::TIER_NONE );
	}

	/**
	 * Execute a callback while forcing repository-cache bypass within this scope.
	 *
	 * @param callable $callback Callback to execute.
	 * @return mixed
	 */
	protected function without_repository_cache( callable $callback ) {
		$this->repository_cache_bypass_depth++;

		try {
			return $callback();
		} finally {
			$this->repository_cache_bypass_depth = max( 0, $this->repository_cache_bypass_depth - 1 );
		}
	}

	/**
	 * Run the callback while recording a repository cache bypass event.
	 *
	 * @param string   $operation_id Explicit repository operation identifier.
	 * @param array    $args Operation arguments.
	 * @param callable $callback Callback to execute.
	 * @param array    $policy Resolved policy.
	 * @param string   $reason Bypass reason.
	 * @return mixed
	 */
	private function run_repository_cache_bypass( string $operation_id, array $args, callable $callback, array $policy, string $reason ) {
		$start    = microtime( true );
		$value    = $callback();
		$observer = $this->cached_repository_cache_observer();
		$tags     = $this->repository_cache_tags( $operation_id, $policy, $args );

		$this->record_repository_cache_bypass(
			$observer,
			array(
				'operation_id'        => $operation_id,
				'tier'                => isset( $policy['tier'] ) ? (string) $policy['tier'] : AIPS_Repository_Cache_Config::TIER_NONE,
				'tags'                => $tags,
				'elapsed_ms'          => $this->elapsed_ms( $start ),
				'invalidation_reason' => $reason,
			)
		);

		return $value;
	}

	/**
	 * Resolve the explicit policy for a repository operation.
	 *
	 * @param string $operation_id Explicit operation identifier.
	 * @return array
	 */
	private function repository_cache_policy_for( string $operation_id ): array {
		if ($this->repository_cache_policies_memo === null) {
			$this->repository_cache_policies_memo = $this->repository_cache_policies();
		}

		$policies = $this->repository_cache_policies_memo;

		return isset( $policies[ $operation_id ] ) && is_array( $policies[ $operation_id ] ) ? $policies[ $operation_id ] : array();
	}

	/**
	 * Normalize supported policy aliases before using config resolution.
	 *
	 * @param array $policy Raw policy.
	 * @return array
	 */
	private function normalize_repository_cache_policy( array $policy ): array {
		if (isset( $policy['bypass_cron'] ) && !isset( $policy['bypass_on_cron'] )) {
			$policy['bypass_on_cron'] = (bool) $policy['bypass_cron'];
		}

		if (isset( $policy['allow_stale'] ) && !isset( $policy['allow_stale_reads'] )) {
			$policy['allow_stale_reads'] = (bool) $policy['allow_stale'];
		}

		return $policy;
	}

	/**
	 * Resolve and sanitize read tags from the policy.
	 *
	 * @param array $policy Repository cache policy.
	 * @param array $args Read arguments.
	 * @return array<int, string>
	 */
	private function repository_cache_tags( string $operation_id, array $policy, array $args ): array {
		$tags = array();

		if (class_exists( 'AIPS_Repository_Cache_Dependencies' ) && method_exists( 'AIPS_Repository_Cache_Dependencies', 'tags_for_read' )) {
			$dependency_tags = AIPS_Repository_Cache_Dependencies::tags_for_read( $operation_id, $args );
			if (is_array( $dependency_tags ) && !empty( $dependency_tags )) {
				$tags = array_merge( $tags, $dependency_tags );
			}
		}

		if (!empty( $policy['tags'] ) && is_array( $policy['tags'] )) {
			$tags = array_merge( $tags, $this->resolve_repository_cache_tag_templates( $policy['tags'], $args ) );
		}

		return $this->sanitize_repository_cache_tags( $tags );
	}

	/**
	 * Resolve placeholder-based tag templates from the supplied read arguments.
	 *
	 * Unknown placeholders emit an observer warning and the affected tag is skipped.
	 *
	 * @param array $tags Raw policy tags.
	 * @param array $args Read arguments.
	 * @return array<int, string>
	 */
	private function resolve_repository_cache_tag_templates( array $tags, array $args ): array {
		$resolved = array();

		foreach ( $tags as $tag ) {
			if (!is_scalar( $tag )) {
				continue;
			}

			$tag = (string) $tag;
			if (!preg_match_all( '/\{([a-zA-Z0-9_]+)\}/', $tag, $matches )) {
				$resolved[] = $tag;
				continue;
			}

			$skip_tag = false;
			foreach ( $matches[1] as $placeholder ) {
				if (!array_key_exists( $placeholder, $args ) || is_array( $args[ $placeholder ] ) || is_object( $args[ $placeholder ] )) {
					$this->record_repository_cache_warning(
						$this->cached_repository_cache_observer(),
						array(
							'operation_id'        => 'repository.policy.placeholder',
							'tags'                => array( $tag ),
							'invalidation_reason' => 'unknown_placeholder:' . $placeholder,
						)
					);
					$skip_tag = true;
					break;
				}

				$tag = str_replace( '{' . $placeholder . '}', (string) $args[ $placeholder ], $tag );
			}

			if (!$skip_tag) {
				$resolved[] = $tag;
			}
		}

		return $resolved;
	}

	/**
	 * Normalize raw cache tag values.
	 *
	 * @param array $tags Raw tags.
	 * @return array<int, string>
	 */
	private function sanitize_repository_cache_tags( array $tags ): array {
		$clean = array();

		foreach ( $tags as $tag ) {
			if (!is_scalar( $tag )) {
				continue;
			}

			$tag = strtolower( trim( (string) $tag ) );
			$tag = preg_replace( '/[^a-z0-9_-]+/', '_', $tag );
			$tag = trim( (string) $tag, '_' );
			if ('' !== $tag && !in_array( $tag, $clean, true )) {
				$clean[] = $tag;
			}
		}

		return $clean;
	}

	/**
	 * Wrap a repository cache payload so null values remain cacheable.
	 *
	 * @param mixed $value Raw callback result.
	 * @return array
	 */
	private function wrap_repository_cache_payload( $value ): array {
		return array(
			'__aips_repository_cache_payload' => 1,
			'value'                           => $value,
		);
	}

	/**
	 * Determine whether a cached payload matches the repository wrapper shape.
	 *
	 * @param mixed $payload Cached payload.
	 * @return bool
	 */
	private function is_repository_cache_payload( $payload ): bool {
		return is_array( $payload ) && !empty( $payload['__aips_repository_cache_payload'] ) && array_key_exists( 'value', $payload );
	}

	/**
	 * Record a normalized repository cache read event.
	 *
	 * @param AIPS_Repository_Cache_Observer $observer Observer instance.
	 * @param array                          $event Event payload.
	 * @return void
	 */
	private function record_repository_cache_read( AIPS_Repository_Cache_Observer $observer, array $event ) {
		$observer->record_read( $this->repository_cache_event_context( $event ) );
	}

	/**
	 * Record a normalized repository cache write event.
	 *
	 * @param AIPS_Repository_Cache_Observer $observer Observer instance.
	 * @param array                          $event Event payload.
	 * @return void
	 */
	private function record_repository_cache_write( AIPS_Repository_Cache_Observer $observer, array $event ) {
		$observer->record_write( $this->repository_cache_event_context( $event ) );
	}

	/**
	 * Record a normalized repository cache bypass event.
	 *
	 * @param AIPS_Repository_Cache_Observer $observer Observer instance.
	 * @param array                          $event Event payload.
	 * @return void
	 */
	private function record_repository_cache_bypass( AIPS_Repository_Cache_Observer $observer, array $event ) {
		$observer->record_bypass( $this->repository_cache_event_context( $event ) );
	}

	/**
	 * Record a normalized repository cache invalidation event.
	 *
	 * @param AIPS_Repository_Cache_Observer $observer Observer instance.
	 * @param array                          $event Event payload.
	 * @return void
	 */
	private function record_repository_cache_invalidation( AIPS_Repository_Cache_Observer $observer, array $event ) {
		$observer->record_invalidation( $this->repository_cache_event_context( $event ) );
	}

	/**
	 * Record a normalized repository cache warning event.
	 *
	 * @param AIPS_Repository_Cache_Observer $observer Observer instance.
	 * @param array                          $event Event payload.
	 * @return void
	 */
	private function record_repository_cache_warning( AIPS_Repository_Cache_Observer $observer, array $event ) {
		$observer->record_warning( $this->repository_cache_event_context( $event ) );
	}

	/**
	 * Build common observer context for repository cache events.
	 *
	 * @param array $event Event-specific payload.
	 * @return array
	 */
	private function repository_cache_event_context( array $event ): array {
		return array_merge(
			array(
				'repository'  => get_class( $this ),
				'cache_group' => $this->repository_cache_group(),
			),
			$event
		);
	}

	/**
	 * Resolve an event bypass reason from policy and per-call options.
	 *
	 * @param array $policy Repository cache policy.
	 * @param array $options Per-call options.
	 * @return string
	 */
	private function resolve_bypass_reason( array $policy, array $options ): string {
		if ( ! empty( $options['bypass_cache'] ) ) {
			return 'bypass_cache';
		}

		if ($this->repository_cache_bypass_depth > 0) {
			return 'scoped_bypass';
		}

		if ( ! empty( $options['queue_sensitive'] ) ) {
			return 'queue_sensitive';
		}

		if ( ! empty( $options['lock_sensitive'] ) ) {
			return 'lock_sensitive';
		}

		if (function_exists( 'wp_doing_cron' ) && wp_doing_cron() && !empty( $policy['bypass_on_cron'] )) {
			return 'cron_bypass';
		}

		if ($this->is_doing_ajax() && !empty( $policy['bypass_ajax'] )) {
			return 'ajax_bypass';
		}

		if (AIPS_Repository_Cache_Config::TIER_NONE === ( isset( $policy['tier'] ) ? $policy['tier'] : AIPS_Repository_Cache_Config::TIER_NONE )) {
			return 'tier_none';
		}

		return 'cache_unavailable';
	}

	/**
	 * Return a memoized list of cache instances for tag-version invalidations.
	 *
	 * @return array<int, AIPS_Cache>
	 */
	private function get_memoized_invalidation_caches(): array {
		if ($this->repository_cache_invalidation_caches_memo === null) {
			$this->repository_cache_invalidation_caches_memo = $this->repository_cache_invalidation_caches();
		}
		return $this->repository_cache_invalidation_caches_memo;
	}

	/**
	 * Resolve distinct cache instances that must receive tag-version invalidations.
	 *
	 * @return array<int, AIPS_Cache>
	 */
	private function repository_cache_invalidation_caches(): array {
		$caches = array();
		$seen   = array();

		if ($this->repository_cache_policies_memo === null) {
			$this->repository_cache_policies_memo = $this->repository_cache_policies();
		}

		foreach ( $this->repository_cache_policies_memo as $policy ) {
			if (!is_array( $policy )) {
				continue;
			}

			$policy = $this->normalize_repository_cache_policy( $policy );
			$tier   = isset( $policy['tier'] ) ? (string) $policy['tier'] : AIPS_Repository_Cache_Config::TIER_NONE;
			if (AIPS_Repository_Cache_Config::TIER_NONE === $tier) {
				continue;
			}

			$policy['bypass_on_cron'] = false;
			$cache                    = AIPS_Repository_Cache_Config::resolve_cache_instance( $this->repository_cache_group(), $policy );
			if (!$cache) {
				continue;
			}

			$cache_id = spl_object_hash( $cache );
			if (isset( $seen[ $cache_id ] )) {
				continue;
			}

			$seen[ $cache_id ] = true;
			$caches[]          = $cache;
		}

		if (empty( $caches )) {
			$caches[] = AIPS_Cache_Factory::instance();
		}

		return $caches;
	}

	/**
	 * Determine whether the current request should bypass this cache policy.
	 *
	 * @param array $policy Repository cache policy.
	 * @param array $options Per-call options.
	 * @return bool
	 */
	private function is_doing_ajax(): bool {
		if (function_exists( 'wp_doing_ajax' )) {
			return wp_doing_ajax();
		}

		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Convert a microtime start into rounded milliseconds.
	 *
	 * @param float $start Start timestamp from microtime( true ).
	 * @return float
	 */
	private function elapsed_ms( $start ): float {
		return round( ( microtime( true ) - (float) $start ) * 1000, 3 );
	}
}
