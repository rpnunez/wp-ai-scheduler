<?php
/**
 * Tests for AIPS_Lock.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Lock extends WP_UnitTestCase {

	public function tearDown(): void {
		AIPS_Lock::release( 'test_lock' );
		parent::tearDown();
	}

	public function test_second_acquire_fails_until_released() {
		$this->assertTrue( AIPS_Lock::acquire( 'test_lock', 30 ) );
		$this->assertTrue( AIPS_Lock::is_locked( 'test_lock' ) );
		$this->assertFalse( AIPS_Lock::acquire( 'test_lock', 30 ) );

		$this->assertTrue( AIPS_Lock::release( 'test_lock' ) );
		$this->assertFalse( AIPS_Lock::is_locked( 'test_lock' ) );
		$this->assertTrue( AIPS_Lock::acquire( 'test_lock', 30 ) );
	}

	public function test_expired_lock_is_taken_over() {
		global $wpdb;

		$this->assertTrue( AIPS_Lock::acquire( 'test_lock', 30 ) );
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => ( time() - 10 ) . '|stale' ),
			array( 'option_name' => 'aips_lock_test_lock' )
		);

		$this->assertFalse( AIPS_Lock::is_locked( 'test_lock' ), 'An expired lock is not held.' );
		$this->assertTrue( AIPS_Lock::acquire( 'test_lock', 30 ), 'An expired lock can be taken over.' );
		$this->assertTrue( AIPS_Lock::is_locked( 'test_lock' ) );
	}

	public function test_with_lock_runs_callback_and_releases() {
		$result = AIPS_Lock::with_lock( 'test_lock', function () {
			$this->assertTrue( AIPS_Lock::is_locked( 'test_lock' ) );
			return 'done';
		} );

		$this->assertSame( 'done', $result );
		$this->assertFalse( AIPS_Lock::is_locked( 'test_lock' ) );
	}

	public function test_with_lock_returns_error_without_running_when_locked() {
		AIPS_Lock::acquire( 'test_lock', 30 );
		$ran = false;

		$result = AIPS_Lock::with_lock( 'test_lock', function () use ( &$ran ) {
			$ran = true;
		} );

		$this->assertWPError( $result );
		$this->assertSame( 'resource_locked', $result->get_error_code() );
		$this->assertFalse( $ran );
		$this->assertTrue( AIPS_Lock::is_locked( 'test_lock' ), 'A failed caller must not free the holder\'s lock.' );
	}

	public function test_with_lock_releases_when_callback_throws() {
		try {
			AIPS_Lock::with_lock( 'test_lock', function () {
				throw new RuntimeException( 'boom' );
			} );
			$this->fail( 'Exception expected.' );
		} catch ( RuntimeException $e ) {
			$this->assertFalse( AIPS_Lock::is_locked( 'test_lock' ) );
		}
	}

	public function test_release_does_not_free_a_lock_taken_over_by_another_holder() {
		global $wpdb;

		$this->assertTrue( AIPS_Lock::acquire( 'test_lock', 30 ) );
		// Another request took over after this holder's lock expired.
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => ( time() + 60 ) . '|someone-else' ),
			array( 'option_name' => 'aips_lock_test_lock' )
		);

		$this->assertFalse( AIPS_Lock::release( 'test_lock' ) );
		$this->assertTrue( AIPS_Lock::is_locked( 'test_lock' ) );

		$wpdb->delete( $wpdb->options, array( 'option_name' => 'aips_lock_test_lock' ) );
	}
}
