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
		$this->bindings[$id] = $factory;
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
		$this->singleton_bindings[$id] = $factory;
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
		// Check if it's a singleton binding
		if (isset($this->singleton_bindings[$id])) {
			// Return cached instance if already resolved
			if (isset($this->singletons[$id])) {
				return $this->singletons[$id];
			}

			// Resolve and cache the instance
			$instance = $this->singleton_bindings[$id]($this);
			$this->singletons[$id] = $instance;
			return $instance;
		}

		// Check if it's a transient binding
		if (isset($this->bindings[$id])) {
			// Always create a new instance for transient bindings
			return $this->bindings[$id]($this);
		}

		// Binding not found
		throw new RuntimeException("Binding not found for: {$id}");
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
}
