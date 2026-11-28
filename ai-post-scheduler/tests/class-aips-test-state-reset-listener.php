<?php
/**
 * PHPUnit listener that flushes process-wide singleton caches between tests.
 *
 * PHPUnit runs the whole suite in a single PHP process, but WordPress's core
 * WP_UnitTestCase only rolls back the database after each test — it does not
 * reset PHP-level singletons such as AIPS_Config's in-memory option cache.
 * Without this, a value read/cached by one test class can silently leak into
 * every test that runs afterward in the same process (e.g. a stale
 * `aips_ai_provider` or `aips_cache_driver` reading).
 *
 * @package AI_Post_Scheduler
 */

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

class AIPS_Test_State_Reset_Listener implements TestListener {
	use TestListenerDefaultImplementation;

	/** @var array<string, Closure>|null */
	private $initial_bindings = null;

	/** @var array<string, Closure>|null */
	private $initial_singleton_bindings = null;

	public function startTest( \PHPUnit\Framework\Test $test ): void {
		if ( null === $this->initial_singleton_bindings && class_exists( 'AIPS_Container', false ) ) {
			$container = AIPS_Container::get_instance();
			$ref       = new ReflectionClass( $container );

			$bindings_prop = $ref->getProperty( 'bindings' );
			$bindings_prop->setAccessible( true );
			$this->initial_bindings = $bindings_prop->getValue( $container );

			$singletons_prop = $ref->getProperty( 'singleton_bindings' );
			$singletons_prop->setAccessible( true );
			$this->initial_singleton_bindings = $singletons_prop->getValue( $container );
		}
	}

	public function endTest( \PHPUnit\Framework\Test $test, float $time ): void {
		if ( class_exists( 'AIPS_Config', false ) ) {
			AIPS_Config::get_instance()->flush_option_cache();
		}
		if ( class_exists( 'AIPS_Cache_Factory', false ) ) {
			AIPS_Cache_Factory::reset();
		}
		if ( null !== $this->initial_singleton_bindings && class_exists( 'AIPS_Container', false ) ) {
			$container = AIPS_Container::get_instance();
			$ref       = new ReflectionClass( $container );

			$bindings_prop = $ref->getProperty( 'bindings' );
			$bindings_prop->setAccessible( true );
			$bindings_prop->setValue( $container, $this->initial_bindings );

			$singletons_prop = $ref->getProperty( 'singleton_bindings' );
			$singletons_prop->setAccessible( true );
			$singletons_prop->setValue( $container, $this->initial_singleton_bindings );

			$resolved_prop = $ref->getProperty( 'singletons' );
			$resolved_prop->setAccessible( true );
			$resolved_prop->setValue( $container, array() );

			$resolving_prop = $ref->getProperty( 'resolving' );
			$resolving_prop->setAccessible( true );
			$resolving_prop->setValue( $container, array() );
		}
	}
}
