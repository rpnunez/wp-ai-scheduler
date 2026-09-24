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

	public function endTest( \PHPUnit\Framework\Test $test, float $time ): void {
		if ( class_exists( 'AIPS_Config', false ) ) {
			AIPS_Config::get_instance()->flush_option_cache();
		}
	}
}
