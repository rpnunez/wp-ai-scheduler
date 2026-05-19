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
		$this->expectExceptionMessage('Registered bindings:');

		$this->container->make('unregistered_class');
	}

	/**
	 * Test that missing binding message includes similar ids when available.
	 */
	public function test_make_error_message_includes_similar_bindings() {
		$this->container->bind('logger_service', function() {
			return new stdClass();
		});

		try {
			$this->container->make('logger_servic');
			$this->fail('Expected RuntimeException was not thrown');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('Similar bindings: logger_service', $e->getMessage());
		}
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
	 * Test that bind_alias maps abstract ids to existing bindings.
	 */
	public function test_bind_alias_maps_to_existing_binding() {
		$this->container->bind('concrete_service', function() {
			$obj = new stdClass();
			$obj->kind = 'concrete';
			$obj->id = uniqid('svc_', true);
			return $obj;
		});

		$this->container->bind_alias('abstract_service', 'concrete_service');

		$instance_a = $this->container->make('abstract_service');
		$instance_b = $this->container->make('abstract_service');

		$this->assertEquals('concrete', $instance_a->kind);
		$this->assertNotSame($instance_a, $instance_b);
	}

	/**
	 * Test that singleton_alias returns shared instance via alias id.
	 */
	public function test_singleton_alias_maps_to_existing_binding() {
		$this->container->singleton('concrete_service', function() {
			$obj = new stdClass();
			$obj->id = uniqid('singleton_', true);
			return $obj;
		});

		$this->container->singleton_alias('abstract_service', 'concrete_service');

		$instance_a = $this->container->make('abstract_service');
		$instance_b = $this->container->make('abstract_service');

		$this->assertSame($instance_a, $instance_b);
	}

	/**
	 * Test alias helpers reject invalid alias targets.
	 */
	public function test_alias_helpers_reject_invalid_targets() {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Invalid alias target for bind_alias: invalid_alias');

		$this->container->bind_alias('invalid_alias', array('not-valid'));
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

		$warnings = $this->container->get_warnings();
		$this->assertCount(1, $warnings);
		$this->assertEquals('duplicate_binding', $warnings[0]['type']);
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

		$warnings = $this->container->get_warnings();
		$this->assertGreaterThanOrEqual(1, count($warnings));

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
	 * Test strict duplicate mode throws on duplicate registrations.
	 */
	public function test_strict_duplicates_throw_exception() {
		$this->container->set_strict_duplicates(true);
		$this->container->bind('strict_class', function() {
			return new stdClass();
		});

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate binding registration for: strict_class');

		$this->container->bind('strict_class', function() {
			return new stdClass();
		});
	}

	/**
	 * Test strict mode flag methods.
	 */
	public function test_strict_mode_flag_methods() {
		$this->assertFalse($this->container->is_strict_duplicates());

		$this->container->set_strict_duplicates(true);
		$this->assertTrue($this->container->is_strict_duplicates());

		$this->container->set_strict_duplicates(false);
		$this->assertFalse($this->container->is_strict_duplicates());
	}

	/**
	 * Test resolution diagnostics capture hit sources.
	 */
	public function test_resolution_stats_track_sources() {
		$this->container->bind('transient_service', function() {
			return new stdClass();
		});
		$this->container->singleton('singleton_service', function() {
			return new stdClass();
		});

		$this->container->make('transient_service');
		$this->container->make('transient_service');
		$this->container->make('singleton_service');
		$this->container->make('singleton_service');
		$this->container->makeIfExists('missing_closure', function() {
			return new stdClass();
		});
		$this->container->makeIfExists('missing_value', 'fallback');

		$stats = $this->container->get_resolution_stats();

		$this->assertEquals(2, $stats['sources']['transient_factory']);
		$this->assertEquals(1, $stats['sources']['singleton_factory']);
		$this->assertEquals(1, $stats['sources']['singleton_cache']);
		$this->assertEquals(1, $stats['sources']['fallback_closure']);
		$this->assertEquals(1, $stats['sources']['fallback_value']);
		$this->assertEquals(2, $stats['attempts']['transient_service']);
	}

	/**
	 * Test reset_resolution_stats clears tracked diagnostics.
	 */
	public function test_reset_resolution_stats_clears_diagnostics() {
		$this->container->bind('tracked_service', function() {
			return new stdClass();
		});

		$this->container->make('tracked_service');
		$stats_before = $this->container->get_resolution_stats();
		$this->assertNotEmpty($stats_before['attempts']);

		$this->container->reset_resolution_stats();
		$stats_after = $this->container->get_resolution_stats();

		$this->assertEmpty($stats_after['attempts']);
		$this->assertEmpty($stats_after['sources']);
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
}
