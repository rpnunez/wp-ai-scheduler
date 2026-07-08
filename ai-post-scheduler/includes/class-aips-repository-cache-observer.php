<?php
/**
 * Repository cache observability helper.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Repository_Cache_Observer
 *
 * Captures repository-level cache reads, writes, invalidations, and bypasses
 * without coupling repositories to a concrete cache backend. Events are routed
 * through the existing logger and telemetry collector, and all failures are
 * swallowed so observability can never block cache operations.
 */
class AIPS_Repository_Cache_Observer {

	/**
	 * Logger used for structured diagnostics.
	 *
	 * @var AIPS_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Logger_Interface|null $logger Optional logger dependency.
	 */
	public function __construct(AIPS_Logger_Interface $logger = null) {
		$this->logger = $logger;
	}

	/**
	 * Record a repository cache read event.
	 *
	 * @param array $event Event metadata.
	 * @return void
	 */
	public function record_read(array $event) {
		$this->record('read', $event);
	}

	/**
	 * Record a repository cache write event.
	 *
	 * @param array $event Event metadata.
	 * @return void
	 */
	public function record_write(array $event) {
		$this->record('write', $event);
	}

	/**
	 * Record a repository cache invalidation event.
	 *
	 * @param array $event Event metadata.
	 * @return void
	 */
	public function record_invalidation(array $event) {
		$this->record('invalidation', $event);
	}

	/**
	 * Record a repository cache bypass event.
	 *
	 * @param array $event Event metadata.
	 * @return void
	 */
	public function record_bypass(array $event) {
		$event['bypass'] = true;
		$this->record('bypass', $event);
	}

	/**
	 * Record a repository cache warning event.
	 *
	 * @param array $event Event metadata.
	 * @return void
	 */
	public function record_warning(array $event) {
		$this->record('warning', $event);
	}

	/**
	 * Normalize and emit an observability event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $event      Event metadata.
	 * @return void
	 */
	private function record($event_type, array $event) {
		try {
			$context = $this->normalize_event($event_type, $event);
			$this->record_telemetry($context);
			$this->record_log($context);
		} catch (Throwable $e) {
			// Repository cache observability must never block cache reads/writes.
		}
	}

	/**
	 * Normalize supported event metadata and exclude raw cache keys.
	 *
	 * @param string $event_type Event type.
	 * @param array  $event      Raw event metadata.
	 * @return array<string, mixed>
	 */
	private function normalize_event($event_type, array $event) {
		$operation_id = $this->first_string($event, array('operation_id', 'cache_operation_id', 'operation', 'id'));
		$key_hash     = $this->first_string($event, array('key_hash', 'cache_key_hash'));

		if ('' === $key_hash && isset($event['key'])) {
			$key_hash = hash('sha256', (string) $event['key']);
		}

		$context = array(
			'type'                  => 'repository_cache_' . sanitize_key((string) $event_type),
			'event_type'            => sanitize_key((string) $event_type),
			'repository'            => sanitize_text_field($this->first_string($event, array('repository', 'repository_class', 'class'))),
			'cache_operation_id'    => sanitize_text_field($operation_id),
			'cache_group'           => sanitize_key($this->first_string($event, array('cache_group', 'group'))),
			'key_hash'              => sanitize_text_field($key_hash),
			'tags'                  => $this->sanitize_tags(isset($event['tags']) ? $event['tags'] : array()),
			'tier'                  => sanitize_key($this->first_string($event, array('tier', 'cache_tier'))),
			'hit'                   => $this->optional_bool($event, 'hit'),
			'miss'                  => $this->optional_bool($event, 'miss'),
			'stale'                 => $this->optional_bool($event, 'stale'),
			'bypass'                => $this->optional_bool($event, 'bypass'),
			'elapsed_ms'            => $this->optional_float($event, 'elapsed_ms'),
			'invalidation_reason'   => sanitize_text_field($this->first_string($event, array('invalidation_reason', 'reason'))),
			'correlation_id'        => sanitize_text_field($this->resolve_correlation_id($event)),
		);

		return $this->remove_empty_values($context);
	}

	/**
	 * Emit the normalized event through AIPS_Telemetry when enabled.
	 *
	 * @param array $context Normalized event context.
	 * @return void
	 */
	private function record_telemetry(array $context) {
		try {
			if (!class_exists('AIPS_Telemetry') || !AIPS_Telemetry::is_subsystem_enabled('cache')) {
				return;
			}

			AIPS_Telemetry::instance()->add_event('cache', $context);
		} catch (Throwable $e) {
			// Telemetry failures should not affect repository cache behavior.
		}
	}

	/**
	 * Emit the normalized event through the configured logger.
	 *
	 * @param array $context Normalized event context.
	 * @return void
	 */
	private function record_log(array $context) {
		try {
			$logger = $this->get_logger();
			if (!$logger) {
				return;
			}

			$level = 'warning' === $context['event_type'] ? 'warning' : 'debug';
			$logger->log('Repository cache ' . $context['event_type'], $level, $context);
		} catch (Throwable $e) {
			// Logging failures should not affect repository cache behavior.
		}
	}

	/**
	 * Lazily resolve the logger from the container when one was not injected.
	 *
	 * @return AIPS_Logger_Interface|null
	 */
	private function get_logger() {
		if ($this->logger instanceof AIPS_Logger_Interface) {
			return $this->logger;
		}

		try {
			if (class_exists('AIPS_Container')) {
				$logger = AIPS_Container::get_instance()->make(AIPS_Logger_Interface::class);
				if ($logger instanceof AIPS_Logger_Interface) {
					$this->logger = $logger;
					return $this->logger;
				}
			}
		} catch (Throwable $e) {
			// Fall back below when the container is unavailable or unbound.
		}

		try {
			if (class_exists('AIPS_Logger')) {
				$logger = AIPS_Logger::instance();
				if ($logger instanceof AIPS_Logger_Interface) {
					$this->logger = $logger;
					return $this->logger;
				}
			}
		} catch (Throwable $e) {
			return null;
		}

		return null;
	}

	/**
	 * Resolve the active correlation ID from the event or global helper.
	 *
	 * @param array $event Raw event metadata.
	 * @return string
	 */
	private function resolve_correlation_id(array $event) {
		$correlation_id = $this->first_string($event, array('correlation_id'));
		if ('' !== $correlation_id) {
			return $correlation_id;
		}

		try {
			if (class_exists('AIPS_Correlation_ID')) {
				$correlation_id = AIPS_Correlation_ID::get();
				return $correlation_id ? (string) $correlation_id : '';
			}
		} catch (Throwable $e) {
			return '';
		}

		return '';
	}

	/**
	 * Return the first scalar string value present in an event array.
	 *
	 * @param array $event Raw event metadata.
	 * @param array $keys  Candidate keys.
	 * @return string
	 */
	private function first_string(array $event, array $keys) {
		foreach ($keys as $key) {
			if (isset($event[$key]) && is_scalar($event[$key])) {
				return (string) $event[$key];
			}
		}

		return '';
	}

	/**
	 * Normalize tags into a de-duplicated list of safe strings.
	 *
	 * @param mixed $tags Raw tags.
	 * @return array<int, string>
	 */
	private function sanitize_tags($tags) {
		if (is_string($tags)) {
			$tags = explode(',', $tags);
		}

		if (!is_array($tags)) {
			return array();
		}

		$clean = array();
		foreach ($tags as $tag) {
			if (!is_scalar($tag)) {
				continue;
			}
			$tag = sanitize_key((string) $tag);
			if ('' !== $tag) {
				$clean[] = $tag;
			}
		}

		return array_values(array_unique($clean));
	}

	/**
	 * Resolve an optional boolean value.
	 *
	 * @param array  $event Raw event metadata.
	 * @param string $key   Event key.
	 * @return bool|null
	 */
	private function optional_bool(array $event, $key) {
		if (!array_key_exists($key, $event)) {
			return null;
		}

		return (bool) $event[$key];
	}

	/**
	 * Resolve an optional float value.
	 *
	 * @param array  $event Raw event metadata.
	 * @param string $key   Event key.
	 * @return float|null
	 */
	private function optional_float(array $event, $key) {
		if (!isset($event[$key]) || !is_numeric($event[$key])) {
			return null;
		}

		return round((float) $event[$key], 3);
	}

	/**
	 * Remove unset optional values while preserving false booleans and zeroes.
	 *
	 * @param array $context Normalized event context.
	 * @return array<string, mixed>
	 */
	private function remove_empty_values(array $context) {
		foreach ($context as $key => $value) {
			if (null === $value || '' === $value || (is_array($value) && empty($value))) {
				unset($context[$key]);
			}
		}

		return $context;
	}
}
