<?php
/**
 * Tests for AIPS_Container
 *
 * Verifies the dependency injection container implementation:
 *   - Singleton pattern for the container itself
 *   - Transient bindings (new instance per make())
 *   - Singleton bindings (shared instance across make() calls)
 *   - Exception handling for unregistered bindings
 *   - Container introspection methods
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */
class Test_AIPS_Container extends WP_UnitTestCase {

	/**
	 * @var AIPS_Container
	 */
	private $container;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->container = AIPS_Container::get_instance();
		$this->container->clear();
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		$this->container->clear();
		parent::tearDown();
	}

	/**
	 * Test that AIPS_Container::get_instance() returns a singleton.
	 */
	public function test_container_is_singleton() {
		$container_a = AIPS_Container::get_instance();
		$container_b = AIPS_Container::get_instance();

		$this->assertSame($container_a, $container_b, 'Container should return the same instance');
	}

	/**
	 * Test that bind() registers a transient binding.
	 */
	public function test_bind_registers_transient_binding() {
		$this->container->bind('test_class', function() {
			return new stdClass();
		});

		$this->assertTrue($this->container->has('test_class'));
	}

	/**
	 * Test that singleton() registers a singleton binding.
	 */
	public function test_singleton_registers_singleton_binding() {
		$this->container->singleton('test_class', function() {
			return new stdClass();
		});

		$this->assertTrue($this->container->has('test_class'));
	}

	/**
	 * Test that transient bindings create a new instance each time.
	 */
	public function test_transient_binding_creates_new_instance() {
		$this->container->bind('test_class', function() {
			return new stdClass();
		});

		$instance_a = $this->container->make('test_class');
		$instance_b = $this->container->make('test_class');

		$this->assertNotSame($instance_a, $instance_b, 'Transient bindings should create new instances');
	}

	/**
	 * Test that singleton bindings return the same instance.
	 */
	public function test_singleton_binding_returns_same_instance() {
		$this->container->singleton('test_class', function() {
			return new stdClass();
		});

		$instance_a = $this->container->make('test_class');
		$instance_b = $this->container->make('test_class');

		$this->assertSame($instance_a, $instance_b, 'Singleton bindings should return the same instance');
	}

	/**
	 * Test that make() throws exception for unregistered binding.
	 */
	public function test_make_throws_exception_for_unregistered_binding() {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Binding not found for: unregistered_class');

		$this->container->make('unregistered_class');
	}

	/**
	 * Test that makeIfExists returns bound service when present.
	 */
	public function test_make_if_exists_returns_bound_service() {
		$this->container->singleton('test_service', function() {
			$obj = new stdClass();
			$obj->source = 'binding';
			return $obj;
		});

		$result = $this->container->makeIfExists('test_service', function() {
			$obj = new stdClass();
			$obj->source = 'fallback';
			return $obj;
		});

		$this->assertInstanceOf(stdClass::class, $result);
		$this->assertEquals('binding', $result->source);
	}

	/**
	 * Test that makeIfExists executes closure fallback when binding is missing.
	 */
	public function test_make_if_exists_uses_closure_fallback_when_missing() {
		$result = $this->container->makeIfExists('missing_service', function() {
			$obj = new stdClass();
			$obj->source = 'fallback';
			return $obj;
		});

		$this->assertInstanceOf(stdClass::class, $result);
		$this->assertEquals('fallback', $result->source);
	}

	/**
	 * Test that makeIfExists instantiates class-string fallback when missing.
	 */
	public function test_make_if_exists_instantiates_class_string_fallback() {
		$result = $this->container->makeIfExists('missing_service', stdClass::class);

		$this->assertInstanceOf(stdClass::class, $result);
	}

	/**
	 * Test that has() returns false for unregistered binding.
	 */
	public function test_has_returns_false_for_unregistered_binding() {
		$this->assertFalse($this->container->has('unregistered_class'));
	}

	/**
	 * Test that clear() removes all bindings.
	 */
	public function test_clear_removes_all_bindings() {
		$this->container->bind('transient', function() {
			return new stdClass();
		});
		$this->container->singleton('singleton', function() {
			return new stdClass();
		});

		$this->container->clear();

		$this->assertFalse($this->container->has('transient'));
		$this->assertFalse($this->container->has('singleton'));
	}

	/**
	 * Test that clear() removes resolved singleton instances.
	 */
	public function test_clear_removes_resolved_singletons() {
		$this->container->singleton('test_class', function() {
			$obj = new stdClass();
			$obj->value = uniqid();
			return $obj;
		});

		$instance_a = $this->container->make('test_class');
		$value_a = $instance_a->value;

		$this->container->clear();

		// Re-register the same singleton binding
		$this->container->singleton('test_class', function() {
			$obj = new stdClass();
			$obj->value = uniqid();
			return $obj;
		});

		$instance_b = $this->container->make('test_class');
		$value_b = $instance_b->value;

		$this->assertNotSame($value_a, $value_b, 'Clear should remove cached singleton instances');
	}

	/**
	 * Test binding counts.
	 */
	public function test_get_binding_counts() {
		$this->container->bind('transient_a', function() { return new stdClass(); });
		$this->container->bind('transient_b', function() { return new stdClass(); });
		$this->container->singleton('singleton_a', function() { return new stdClass(); });

		$counts = $this->container->get_binding_counts();

		$this->assertEquals(2, $counts['transient']);
		$this->assertEquals(1, $counts['singleton']);
		$this->assertEquals(3, $counts['total']);
	}

	/**
	 * Test get_registered_bindings returns all bindings with their scopes.
	 */
	public function test_get_registered_bindings() {
		$this->container->bind('transient_class', function() { return new stdClass(); });
		$this->container->singleton('singleton_class', function() { return new stdClass(); });

		$registered = $this->container->get_registered_bindings();

		$this->assertArrayHasKey('transient_class', $registered);
		$this->assertArrayHasKey('singleton_class', $registered);
		$this->assertEquals('transient', $registered['transient_class']);
		$this->assertEquals('singleton', $registered['singleton_class']);
	}

	/**
	 * Test that factory closure receives container instance.
	 */
	public function test_factory_receives_container_instance() {
		$container_passed = null;

		$this->container->bind('test_class', function($c) use (&$container_passed) {
			$container_passed = $c;
			return new stdClass();
		});

		$this->container->make('test_class');

		$this->assertInstanceOf(AIPS_Container::class, $container_passed);
		$this->assertSame($this->container, $container_passed);
	}

	/**
	 * Test registering existing singletons with their instance() methods.
	 */
	public function test_register_existing_singleton_classes() {
		// Register AIPS_History_Repository singleton
		$this->container->singleton(AIPS_History_Repository::class, function() {
			return AIPS_History_Repository::instance();
		});

		// Register AIPS_History_Service singleton
		$this->container->singleton(AIPS_History_Service::class, function() {
			return AIPS_History_Service::instance();
		});

		// Register AIPS_Config singleton
		$this->container->singleton(AIPS_Config::class, function() {
			return AIPS_Config::get_instance();
		});

		// Verify all are registered
		$this->assertTrue($this->container->has(AIPS_History_Repository::class));
		$this->assertTrue($this->container->has(AIPS_History_Service::class));
		$this->assertTrue($this->container->has(AIPS_Config::class));

		// Verify singleton behavior
		$repo_a = $this->container->make(AIPS_History_Repository::class);
		$repo_b = $this->container->make(AIPS_History_Repository::class);
		$this->assertSame($repo_a, $repo_b);

		// Verify they match the existing singleton instances
		$this->assertSame(AIPS_History_Repository::instance(), $repo_a);
		$this->assertSame(AIPS_Config::get_instance(), $this->container->make(AIPS_Config::class));
	}

	/**
	 * Test dependency injection through container.
	 */
	public function test_dependency_injection_through_container() {
		// Register a dependency
		$this->container->singleton('dependency', function() {
			$obj = new stdClass();
			$obj->name = 'test_dependency';
			return $obj;
		});

		// Register a class that depends on it
		$this->container->bind('dependent_class', function($c) {
			$obj = new stdClass();
			$obj->dependency = $c->make('dependency');
			return $obj;
		});

		$instance_a = $this->container->make('dependent_class');
		$instance_b = $this->container->make('dependent_class');

		// Instances should be different (transient)
		$this->assertNotSame($instance_a, $instance_b);

		// But they should share the same dependency (singleton)
		$this->assertSame($instance_a->dependency, $instance_b->dependency);
		$this->assertEquals('test_dependency', $instance_a->dependency->name);
	}

	/**
	 * Test registering AIPS_Notifications_Repository.
	 */
	public function test_register_notifications_repository() {
		// Since AIPS_Notifications_Repository doesn't have a singleton method,
		// we can register it as a singleton with new instance creation
		$this->container->singleton(AIPS_Notifications_Repository::class, function() {
			return new AIPS_Notifications_Repository();
		});

		$instance_a = $this->container->make(AIPS_Notifications_Repository::class);
		$instance_b = $this->container->make(AIPS_Notifications_Repository::class);

		$this->assertInstanceOf(AIPS_Notifications_Repository::class, $instance_a);
		$this->assertSame($instance_a, $instance_b, 'Should return same instance for singleton binding');
	}

	/**
	 * Test overwriting a binding.
	 */
	public function test_overwriting_binding() {
		$this->container->bind('test_class', function() {
			$obj = new stdClass();
			$obj->version = 1;
			return $obj;
		});

		$instance_v1 = $this->container->make('test_class');
		$this->assertEquals(1, $instance_v1->version);

		// Overwrite the binding
		$this->container->bind('test_class', function() {
			$obj = new stdClass();
			$obj->version = 2;
			return $obj;
		});

		$instance_v2 = $this->container->make('test_class');
		$this->assertEquals(2, $instance_v2->version);
	}

	/**
	 * Test that singleton can be overwritten.
	 */
	public function test_overwriting_singleton_binding() {
		$this->container->singleton('test_class', function() {
			$obj = new stdClass();
			$obj->version = 1;
			return $obj;
		});

		$instance_v1 = $this->container->make('test_class');
		$this->assertEquals(1, $instance_v1->version);

		// Overwrite the singleton binding (note: will keep old cached instance until cleared)
		$this->container->singleton('test_class', function() {
			$obj = new stdClass();
			$obj->version = 2;
			return $obj;
		});

		// First make should still return cached v1
		$instance_still_v1 = $this->container->make('test_class');
		$this->assertEquals(1, $instance_still_v1->version);

		// Clear and re-register to get v2
		$this->container->clear();
		$this->container->singleton('test_class', function() {
			$obj = new stdClass();
			$obj->version = 2;
			return $obj;
		});

		$instance_v2 = $this->container->make('test_class');
		$this->assertEquals(2, $instance_v2->version);
	}

	/**
	 * Test empty container state.
	 */
	public function test_empty_container_state() {
		$counts = $this->container->get_binding_counts();
		$this->assertEquals(0, $counts['total']);

		$registered = $this->container->get_registered_bindings();
		$this->assertEmpty($registered);
	}

	/**
	 * Test autowiring a concrete class without explicit bindings.
	 */
	public function test_autowire_concrete_class() {
		$instance = $this->container->make(AIPS_Test_Autowire_Simple::class);
		$this->assertInstanceOf(AIPS_Test_Autowire_Simple::class, $instance);
	}

	/**
	 * Test autowiring class with typed dependencies.
	 */
	public function test_autowire_class_with_dependencies() {
		$instance = $this->container->make(AIPS_Test_Autowire_Dependent::class);
		$this->assertInstanceOf(AIPS_Test_Autowire_Dependent::class, $instance);
		$this->assertInstanceOf(AIPS_Test_Autowire_Simple::class, $instance->dependency);
	}

	/**
	 * Test make() with parameter overrides.
	 */
	public function test_make_with_parameter_overrides() {
		$override = new AIPS_Test_Autowire_Simple();
		$override->custom_val = 'overridden';

		$instance = $this->container->make(AIPS_Test_Autowire_Dependent::class, array(
			'dependency' => $override,
		));

		$this->assertSame($override, $instance->dependency);
		$this->assertEquals('overridden', $instance->dependency->custom_val);
	}

	/**
	 * Test instance() directly binds an instantiated object.
	 */
	public function test_instance_binding() {
		$obj = new stdClass();
		$obj->key = 'value';

		$this->container->instance('my_instance', $obj);
		$this->assertTrue($this->container->has('my_instance'));
		$this->assertSame($obj, $this->container->make('my_instance'));
		$this->assertSame($obj, $this->container->get('my_instance'));
	}

	/**
	 * Test string-based class/interface aliasing.
	 */
	public function test_interface_alias_binding() {
		$this->container->singleton('Test_Alias_Interface', AIPS_Test_Autowire_Simple::class);

		$instance_a = $this->container->make('Test_Alias_Interface');
		$instance_b = $this->container->make('Test_Alias_Interface');

		$this->assertInstanceOf(AIPS_Test_Autowire_Simple::class, $instance_a);
		$this->assertSame($instance_a, $instance_b);
	}

	/**
	 * Test circular dependency throws RuntimeException.
	 */
	public function test_circular_dependency_detection() {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Circular dependency detected');

		$this->container->make(AIPS_Test_Circular_A::class);
	}
}

class AIPS_Test_Autowire_Simple {
	public $custom_val = 'default';
}

class AIPS_Test_Autowire_Dependent {
	public $dependency;

	public function __construct(AIPS_Test_Autowire_Simple $dependency) {
		$this->dependency = $dependency;
	}
}

class AIPS_Test_Circular_A {
	public function __construct(AIPS_Test_Circular_B $b) {}
}

class AIPS_Test_Circular_B {
	public function __construct(AIPS_Test_Circular_A $a) {}
}

