<?php
/** Shared source fetch ownership regression tests. */
class Test_AIPS_Sources_Fetch_Lease extends WP_UnitTestCase {
	public function tearDown(): void {
		delete_option( AIPS_Sources_Fetch_Lease::OPTION );
		parent::tearDown();
	}
	public function test_contention_and_owner_checked_release() {
		$lease = new AIPS_Sources_Fetch_Lease();
		$owner = $lease->acquire();
		$this->assertIsString( $owner );
		$this->assertFalse( $lease->acquire() );
		$lease->release( 'other-worker' );
		$this->assertFalse( $lease->acquire() );
		$lease->release( $owner );
		$this->assertIsString( $lease->acquire() );
	}
	public function test_expired_lease_can_be_taken_over() {
		add_option( AIPS_Sources_Fetch_Lease::OPTION, array( 'owner' => 'crashed', 'expires' => time() - 1 ), '', false );
		$lease = new AIPS_Sources_Fetch_Lease();
		$owner = $lease->acquire();
		$this->assertIsString( $owner );
		$lease->release( 'crashed' );
		$this->assertFalse( $lease->acquire() );
	}
	public function test_nonautoload_lease_preserves_the_sitewide_options_cache() {
		$alloptions = wp_load_alloptions();
		wp_cache_set( 'alloptions', $alloptions, 'options' );
		$lease = new AIPS_Sources_Fetch_Lease();
		$owner = $lease->acquire();
		$this->assertIsString( $owner );
		$this->assertSame( $alloptions, wp_cache_get( 'alloptions', 'options' ) );
		$lease->release( $owner );
		$this->assertSame( $alloptions, wp_cache_get( 'alloptions', 'options' ) );
	}
	public function test_interleaved_first_acquisition_never_overwrites_competing_owner() {
		$lease = new AIPS_Sources_Fetch_Lease();
		$interleaved = false;
		$competing_owner = false;
		$filter = function ( $query ) use ( $lease, &$interleaved, &$competing_owner ) {
			if ( ! $interleaved && 0 === stripos( ltrim( $query ), 'INSERT' ) && false !== strpos( $query, AIPS_Sources_Fetch_Lease::OPTION ) ) {
				$interleaved = true;
				// A second request wins after the first checked absence but before its INSERT.
				$competing_owner = $lease->acquire();
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$owner = $lease->acquire();
		} finally {
			remove_filter( 'query', $filter );
		}
		$this->assertTrue( $interleaved );
		$this->assertIsString( $competing_owner );
		$this->assertFalse( $owner );
		$lease->release( 'not-the-owner' );
		$this->assertFalse( $lease->acquire() );
		$lease->release( $competing_owner );
		$this->assertIsString( $lease->acquire() );
	}
}
