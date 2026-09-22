<?php
/**
 * Dependency Injection Container
 *
 * Service container for managing dependencies and their lifecycles.
 * Supports transient and singleton scopes, reflection-based autowiring,
 * parameter overrides, and circular dependency detection.
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
 * Dependency injection container with singleton, transient, and autowiring support.
 */
class AIPS_Container {

	/**
	 * @var self|null Singleton instance of the container itself.
	 */
	private static $instance = null;

	/**
	 * @var array<string, Closure|string> Transient bindings.
	 */
	private $bindings = array();

	/**
	 * @var array<string, Closure|string> Singleton bindings.
	 */
	private $singleton_bindings = array();

	/**
	 * @var array<string, mixed> Resolved singleton instances.
	 */
	private $singletons = array();

	/**
	 * @var array<string, ReflectionClass> Cached reflection instances.
	 */
	private $reflection_cache = array();

	/**
	 * @var array<string> Stack of classes currently being resolved (circular dependency detection).
	 */
	private $resolving = array();

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
	 * @param string              $id       Class name or abstract identifier.
	 * @param Closure|string|null $concrete Factory closure, concrete class name, or null to bind to self.
	 * @return void
	 */
	public function bind($id, $concrete = null) {
		if ($concrete === null) {
			$concrete = $id;
		}

		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'bind',
				'class'  => $id,
			) );
		}
		$this->bindings[$id] = $concrete;
	}

	/**
	 * Register a singleton binding.
	 *
	 * @param string              $id       Class name or abstract identifier.
	 * @param Closure|string|null $concrete Factory closure, concrete class name, or null to bind to self.
	 * @return void
	 */
	public function singleton($id, $concrete = null) {
		if ($concrete === null) {
			$concrete = $id;
		}

		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'singleton',
				'class'  => $id,
			) );
		}
		$this->singleton_bindings[$id] = $concrete;
	}

	/**
	 * Bind an existing instance into the container as a singleton.
	 *
	 * @param string $id       Class name or abstract identifier.
	 * @param mixed  $instance The pre-instantiated object.
	 * @return void
	 */
	public function instance($id, $instance) {
		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'instance',
				'class'  => $id,
			) );
		}
		$this->singletons[$id] = $instance;
		$this->singleton_bindings[$id] = true;
	}

	/**
	 * Resolve an entry from the container (PSR-11 compatibility alias).
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed
	 */
	public function get($id) {
		return $this->make($id);
	}

	/**
	 * Resolve a binding or autowire a class, returning an instance.
	 *
	 * @param string $id         Class name or abstract identifier.
	 * @param array  $parameters Optional runtime parameter overrides.
	 * @return mixed|WP_Error The resolved instance or WP_Error on failure.
	 */
	public function make($id, array $parameters = array()) {
		if (AIPS_Telemetry::is_enabled()) {
			AIPS_Telemetry::instance()->add_event( 'classes', array(
				'type'   => 'class_referenced',
				'method' => 'make',
				'class'  => $id,
			) );
		}

		// Return cached singleton if available and no parameter overrides
		if (empty($parameters) && isset($this->singletons[$id])) {
			return $this->singletons[$id];
		}

		// Check if it's a singleton binding
		if (isset($this->singleton_bindings[$id])) {
			$concrete = $this->singleton_bindings[$id];

			if ($concrete instanceof Closure) {
				$instance = $concrete($this, $parameters);
			} elseif (is_string($concrete) && $concrete !== $id) {
				$instance = $this->make($concrete, $parameters);
			} else {
				$instance = $this->build($id, $parameters);
			}

			if (empty($parameters) && !is_wp_error($instance)) {
				$this->singletons[$id] = $instance;
			}

			return $instance;
		}

		// Check if it's a transient binding
		if (isset($this->bindings[$id])) {
			$concrete = $this->bindings[$id];

			if ($concrete instanceof Closure) {
				return $concrete($this, $parameters);
			}

			if (is_string($concrete) && $concrete !== $id) {
				return $this->make($concrete, $parameters);
			}

			return $this->build($id, $parameters);
		}

		// Autowire class if it exists
		if (class_exists($id)) {
			return $this->build($id, $parameters);
		}

		// Binding not found
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("AIPS_Container: Binding not found for [{$id}].");
		}
		return new WP_Error('aips_binding_not_found', "Binding not found for: {$id}");
	}

	/**
	 * Build a concrete class instance via Reflection and autowire dependencies.
	 *
	 * @param string $class_name Concrete class name to build.
	 * @param array  $parameters Optional runtime parameter overrides.
	 * @return object|WP_Error
	 */
	public function build($class_name, array $parameters = array()) {
		if (in_array($class_name, $this->resolving, true)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("AIPS_Container: Circular dependency detected while resolving [{$class_name}].");
			}
			return new WP_Error('aips_circular_dependency', "Circular dependency detected while resolving: {$class_name}");
		}

		$this->resolving[] = $class_name;

		try {
			if (!isset($this->reflection_cache[$class_name])) {
				$this->reflection_cache[$class_name] = new ReflectionClass($class_name);
			}

			$reflector = $this->reflection_cache[$class_name];

			if (!$reflector->isInstantiable()) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("AIPS_Container: Target [{$class_name}] is not instantiable.");
				}
				return new WP_Error('aips_not_instantiable', "Target [{$class_name}] is not instantiable.");
			}

			$constructor = $reflector->getConstructor();

			if ($constructor === null) {
				if (AIPS_Telemetry::is_enabled()) {
					AIPS_Telemetry::instance()->add_event( 'classes', array(
						'type'  => 'class_initialized',
						'class' => $class_name,
					) );
				}
				return new $class_name();
			}

			$dependencies = $this->resolve_dependencies($constructor->getParameters(), $parameters, $class_name);

			if (is_wp_error($dependencies)) {
				return $dependencies;
			}

			if (AIPS_Telemetry::is_enabled()) {
				AIPS_Telemetry::instance()->add_event( 'classes', array(
					'type'  => 'class_initialized',
					'class' => $class_name,
				) );
			}

			return $reflector->newInstanceArgs($dependencies);
		} finally {
			array_pop($this->resolving);
		}
	}

	/**
	 * Resolve constructor parameter dependencies.
	 *
	 * @param ReflectionParameter[] $params      Constructor parameters.
	 * @param array                 $parameters  Explicit parameter overrides.
	 * @param string                $class_name  Class name for error messages.
	 * @return array|WP_Error
	 */
	private function resolve_dependencies(array $params, array $parameters, $class_name) {
		$results = array();

		foreach ($params as $index => $param) {
			$name = $param->getName();

			// 1. Check for named or positional override
			if (array_key_exists($name, $parameters)) {
				$results[] = $parameters[$name];
				continue;
			}
			if (array_key_exists($index, $parameters)) {
				$results[] = $parameters[$index];
				continue;
			}

			// 2. Check parameter type
			$type = $param->getType();

			if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				$dependency_class = $type->getName();
				$resolved = $this->make($dependency_class);

				if (!is_wp_error($resolved)) {
					$results[] = $resolved;
					continue;
				}

				if ($param->isDefaultValueAvailable()) {
					$results[] = $param->getDefaultValue();
					continue;
				}
				if ($param->allowsNull()) {
					$results[] = null;
					continue;
				}

				return $resolved;
			}

			// 3. Check for default value
			if ($param->isDefaultValueAvailable()) {
				$results[] = $param->getDefaultValue();
				continue;
			}

			// 4. Check if nullable
			if ($param->allowsNull()) {
				$results[] = null;
				continue;
			}

			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("AIPS_Container: Unresolvable dependency [{$name}] in class [{$class_name}].");
			}
			return new WP_Error('aips_unresolvable_dependency', "Unresolvable dependency [{$name}] in class [{$class_name}].");
		}

		return $results;
	}

	/**
	 * Resolve a binding when it exists, otherwise return a fallback value.
	 *
	 * @param string $id       Class name or abstract identifier.
	 * @param mixed  $fallback Optional fallback when binding is not registered.
	 * @return mixed
	 */
	public function makeIfExists($id, $fallback = null) {
		if ($this->has($id)) {
			$result = $this->make($id);
			if (!is_wp_error($result)) {
				return $result;
			}
		}

		if ($fallback instanceof Closure) {
			return $fallback($this);
		}

		if (is_string($fallback) && class_exists($fallback)) {
			return $this->make($fallback);
		}

		return $fallback;
	}

	/**
	 * Check if a binding or class exists for the given identifier.
	 *
	 * @param string $id Class name or abstract identifier.
	 * @return bool True if a binding or class exists.
	 */
	public function has($id) {
		return isset($this->bindings[$id])
			|| isset($this->singleton_bindings[$id])
			|| isset($this->singletons[$id])
			|| class_exists($id);
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
		$this->reflection_cache = array();
		$this->resolving = array();
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
			'total'     => $transient_count + $singleton_count,
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

