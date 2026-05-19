<?php
/**
 * Dependency Injection Container
 *
 * Minimal service container for managing dependencies and their lifecycles.
 * Supports transient (new instance per resolution) and singleton (shared instance) scopes.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Container
 *
 * Simple dependency injection container with singleton and transient support.
 * Provides explicit registration and resolution of class dependencies.
 */
class AIPS_Container {

	/**
	 * @var self|null Singleton instance of the container itself.
	 */
	private static $instance = null;

	/**
	 * @var array<string, Closure> Transient bindings (factory closures).
	 */
	private $bindings = array();

	/**
	 * @var array<string, Closure> Singleton bindings (factory closures).
	 */
	private $singleton_bindings = array();

	/**
	 * @var array<string, mixed> Resolved singleton instances.
	 */
	private $singletons = array();

	/**
	 * @var bool Whether duplicate registrations should throw exceptions.
	 */
	private $strict_duplicates = false;

	/**
	 * @var array<int, array<string, string>> Non-fatal container warnings.
	 */
	private $warnings = array();

	/**
	 * @var array<string, mixed> Resolution diagnostics.
	 */
	private $resolution_stats = array(
		'attempts' => array(),
		'sources' => array(),
	);

	/**
	 * Get the global container instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		// Container is empty until bindings are registered
		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'  => 'class_initialized',
				'class' => 'AIPS_Container',
			) );
		}
	}

	/**
	 * Register a transient binding.
	 *
	 * Transient bindings create a new instance each time make() is called.
	 *
	 * @param string  $id      Class name or abstract identifier.
	 * @param Closure $factory Factory closure that returns an instance.
	 * @return void
	 */
	public function bind($id, Closure $factory) {
		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'bind',
				'class'  => $id,
			) );
		}
		$this->register_binding($id, $factory, 'transient');
	}

	/**
	 * Register a singleton binding.
	 *
	 * Singleton bindings create an instance once and return the same instance on subsequent calls.
	 *
	 * @param string  $id      Class name or abstract identifier.
	 * @param Closure $factory Factory closure that returns an instance.
	 * @return void
	 */
	public function singleton($id, Closure $factory) {
		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'singleton',
				'class'  => $id,
			) );
		}
		$this->register_binding($id, $factory, 'singleton');
	}

	/**
	 * Register a transient alias to another binding or factory.
	 *
	 * @param string          $abstract           Alias identifier.
	 * @param string|Closure  $concrete_or_factory Concrete binding id or factory.
	 * @return void
	 */
	public function bind_alias($abstract, $concrete_or_factory) {
		if ($concrete_or_factory instanceof Closure) {
			$this->bind($abstract, $concrete_or_factory);
			return;
		}

		if (is_string($concrete_or_factory)) {
			$this->bind($abstract, function($container) use ($concrete_or_factory) {
				return $container->make($concrete_or_factory);
			});
			return;
		}

		throw new RuntimeException('Invalid alias target for bind_alias: ' . $abstract);
	}

	/**
	 * Register a singleton alias to another binding or factory.
	 *
	 * @param string          $abstract            Alias identifier.
	 * @param string|Closure  $concrete_or_factory Concrete binding id or factory.
	 * @return void
	 */
	public function singleton_alias($abstract, $concrete_or_factory) {
		if ($concrete_or_factory instanceof Closure) {
			$this->singleton($abstract, $concrete_or_factory);
			return;
		}

		if (is_string($concrete_or_factory)) {
			$this->singleton($abstract, function($container) use ($concrete_or_factory) {
				return $container->make($concrete_or_factory);
			});
			return;
		}

		throw new RuntimeException('Invalid alias target for singleton_alias: ' . $abstract);
	}

	/**
	 * Resolve a binding and return an instance.
	 *
	 * For singletons, the factory is called once and the result is cached.
	 * For transients, the factory is called on every resolution.
	 *
	 * @param string $id Class name or abstract identifier.
	 * @return mixed The resolved instance.
	 * @throws RuntimeException If the binding is not registered.
	 */
	public function make($id) {
		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'make',
				'class'  => $id,
			) );
		}

		// Check if it's a singleton binding
		if (isset($this->singleton_bindings[$id])) {
			// Return cached instance if already resolved
			if (isset($this->singletons[$id])) {
				$this->record_resolution($id, 'singleton_cache');
				return $this->singletons[$id];
			}

			// Resolve and cache the instance
			if (AIPS_Telemetry::is_enabled()) {
				AIPS_Telemetry::instance()->add_event( 'classes', array(
					'type'  => 'class_initialized',
					'class' => $id,
				) );
			}
			$instance = $this->singleton_bindings[$id]($this);
			$this->singletons[$id] = $instance;
			$this->record_resolution($id, 'singleton_factory');
			return $instance;
		}

		// Check if it's a transient binding
		if (isset($this->bindings[$id])) {
			// Always create a new instance for transient bindings
			if (AIPS_Telemetry::is_enabled()) {
				AIPS_Telemetry::instance()->add_event( 'classes', array(
					'type'  => 'class_initialized',
					'class' => $id,
				) );
			}
			$this->record_resolution($id, 'transient_factory');
			return $this->bindings[$id]($this);
		}

		// Binding not found
		$this->record_resolution($id, 'missing');

		$counts = $this->get_binding_counts();
		$similar = $this->find_similar_bindings($id);
		$message = 'Binding not found for: ' . $id;
		$message .= '. Registered bindings: ' . $counts['total'] . ' (singleton: ' . $counts['singleton'] . ', transient: ' . $counts['transient'] . ')';

		if (!empty($similar)) {
			$message .= '. Similar bindings: ' . implode(', ', $similar);
		}

		throw new RuntimeException($message);
	}

	/**
	 * Resolve a binding when it exists, otherwise return a fallback value.
	 *
	 * This is useful for gradual container adoption in classes that still need
	 * backward-compatible defaults.
	 *
	 * @param string $id       Class name or abstract identifier.
	 * @param mixed  $fallback Optional fallback when binding is not registered.
	 *                         Supported forms:
	 *                         - Closure: called with container and return value used.
	 *                         - class-string: instantiated when class exists.
	 *                         - any other value: returned as-is.
	 * @return mixed
	 */
	public function makeIfExists($id, $fallback = null) {
		if ($this->has($id)) {
			return $this->make($id);
		}

		if ($fallback instanceof Closure) {
			$this->record_resolution($id, 'fallback_closure');
			return $fallback($this);
		}

		if (is_string($fallback) && class_exists($fallback)) {
			$this->record_resolution($id, 'fallback_class');
			return new $fallback();
		}

		$this->record_resolution($id, 'fallback_value');

		return $fallback;
	}

	/**
	 * Enable or disable strict duplicate-binding mode.
	 *
	 * @param bool $enabled Whether duplicate ids should throw.
	 * @return void
	 */
	public function set_strict_duplicates($enabled) {
		$this->strict_duplicates = (bool) $enabled;
	}

	/**
	 * Check if strict duplicate-binding mode is enabled.
	 *
	 * @return bool
	 */
	public function is_strict_duplicates() {
		return $this->strict_duplicates;
	}

	/**
	 * Check if a binding exists for the given identifier.
	 *
	 * @param string $id Class name or abstract identifier.
	 * @return bool True if a binding exists.
	 */
	public function has($id) {
		return isset($this->bindings[$id]) || isset($this->singleton_bindings[$id]);
	}

	/**
	 * Clear all bindings and resolved singletons.
	 *
	 * Useful for testing or resetting the container state.
	 *
	 * @return void
	 */
	public function clear() {
		$this->bindings = array();
		$this->singleton_bindings = array();
		$this->singletons = array();
		$this->strict_duplicates = false;
		$this->warnings = array();
		$this->reset_resolution_stats();
	}

	/**
	 * Get container warnings accumulated during registration.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Clear accumulated warnings.
	 *
	 * @return void
	 */
	public function clear_warnings() {
		$this->warnings = array();
	}

	/**
	 * Return resolution diagnostics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_resolution_stats() {
		return $this->resolution_stats;
	}

	/**
	 * Reset resolution diagnostics.
	 *
	 * @return void
	 */
	public function reset_resolution_stats() {
		$this->resolution_stats = array(
			'attempts' => array(),
			'sources' => array(),
		);
	}

	/**
	 * Get the count of registered bindings.
	 *
	 * @return array<string, int> Array with 'transient', 'singleton', and 'total' counts.
	 */
	public function get_binding_counts() {
		$transient_count = count($this->bindings);
		$singleton_count = count($this->singleton_bindings);

		return array(
			'transient' => $transient_count,
			'singleton' => $singleton_count,
			'total' => $transient_count + $singleton_count,
		);
	}

	/**
	 * Get all registered binding identifiers.
	 *
	 * @return array<string, string> Associative array of id => scope ('transient' or 'singleton').
	 */
	public function get_registered_bindings() {
		$registered = array();

		foreach ($this->bindings as $id => $factory) {
			$registered[$id] = 'transient';
		}

		foreach ($this->singleton_bindings as $id => $factory) {
			$registered[$id] = 'singleton';
		}

		return $registered;
	}

	/**
	 * Register a binding with duplicate handling and scope normalization.
	 *
	 * @param string  $id      Binding identifier.
	 * @param Closure $factory Factory callback.
	 * @param string  $scope   Binding scope: transient or singleton.
	 * @return void
	 */
	private function register_binding($id, Closure $factory, $scope) {
		$existing_scope = $this->get_binding_scope($id);

		if ($existing_scope !== null) {
			$this->handle_duplicate_binding($id, $existing_scope, $scope);
		}

		if ($scope === 'singleton') {
			if ($existing_scope === 'transient') {
				unset($this->bindings[$id]);
			}

			$this->singleton_bindings[$id] = $factory;
			return;
		}

		if ($existing_scope === 'singleton') {
			unset($this->singleton_bindings[$id]);
			unset($this->singletons[$id]);
		}

		$this->bindings[$id] = $factory;
	}

	/**
	 * Get current scope for a binding id.
	 *
	 * @param string $id Binding identifier.
	 * @return string|null
	 */
	private function get_binding_scope($id) {
		if (isset($this->singleton_bindings[$id])) {
			return 'singleton';
		}

		if (isset($this->bindings[$id])) {
			return 'transient';
		}

		return null;
	}

	/**
	 * Handle duplicate bindings according to strict mode.
	 *
	 * @param string $id             Binding identifier.
	 * @param string $existing_scope Existing scope.
	 * @param string $new_scope      New scope.
	 * @return void
	 */
	private function handle_duplicate_binding($id, $existing_scope, $new_scope) {
		$message = 'Duplicate binding registration for: ' . $id . ' (existing: ' . $existing_scope . ', new: ' . $new_scope . ')';

		if ($this->strict_duplicates) {
			throw new RuntimeException($message);
		}

		$this->warnings[] = array(
			'type' => 'duplicate_binding',
			'id' => $id,
			'existing_scope' => $existing_scope,
			'new_scope' => $new_scope,
			'message' => $message,
		);
	}

	/**
	 * Record resolution diagnostics for an id and source.
	 *
	 * @param string $id     Binding identifier.
	 * @param string $source Resolution source label.
	 * @return void
	 */
	private function record_resolution($id, $source) {
		if (!isset($this->resolution_stats['attempts'][$id])) {
			$this->resolution_stats['attempts'][$id] = 0;
		}
		$this->resolution_stats['attempts'][$id]++;

		if (!isset($this->resolution_stats['sources'][$source])) {
			$this->resolution_stats['sources'][$source] = 0;
		}
		$this->resolution_stats['sources'][$source]++;
	}

	/**
	 * Find similar binding ids for a missing binding message.
	 *
	 * @param string $id Requested binding id.
	 * @return array<int, string>
	 */
	private function find_similar_bindings($id) {
		$matches = array();
		$all_ids = array_keys($this->get_registered_bindings());

		foreach ($all_ids as $registered_id) {
			$distance = levenshtein((string) $id, (string) $registered_id);
			$max_distance = max(3, (int) floor(strlen((string) $id) / 3));

			if (stripos((string) $registered_id, (string) $id) !== false
				|| stripos((string) $id, (string) $registered_id) !== false
				|| $distance <= $max_distance) {
				$matches[] = $registered_id;
			}
		}

		return array_slice(array_values(array_unique($matches)), 0, 5);
	}
}
