<?php
/**
 * Tests for AIPS_Generation_Claims_Repository — atomic, expiring generation claims.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Claims_Repository extends WP_UnitTestCase {

	/** @var AIPS_Generation_Claims_Repository */
	private $claims;

	/** @var string */
	private $table;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->table  = $wpdb->prefix . 'aips_generation_claims';
		$this->claims = new AIPS_Generation_Claims_Repository();

		if ( $this->should_skip() ) {
			return;
		}

		$wpdb->query( "DELETE FROM {$this->table}" );
	}

	public function tearDown(): void {
		global $wpdb;
		if ( ! $this->should_skip() ) {
			$wpdb->query( "DELETE FROM {$this->table}" );
		}
		parent::tearDown();
	}

	/**
	 * Skip when the claims table is not available (mock wpdb / partial env).
	 *
	 * @return bool
	 */
	private function should_skip() {
		global $wpdb;
		if ( property_exists( $wpdb, 'get_results_return_val' ) ) {
			return true;
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
		return $found !== $this->table;
	}

	/**
	 * A resource can be claimed once; a second concurrent claim is denied.
	 */
	public function test_two_workers_cannot_claim_same_author() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$token_a = $this->claims->claim_author_post_generation( 42 );
		$token_b = $this->claims->claim_author_post_generation( 42 );

		$this->assertNotFalse( $token_a, 'First claim should succeed.' );
		$this->assertFalse( $token_b, 'Second concurrent claim should be denied.' );
	}

	/**
	 * Two workers cannot claim the same topic.
	 */
	public function test_two_workers_cannot_claim_same_topic() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$first  = $this->claims->claim_topic_post_generation( 7 );
		$second = $this->claims->claim_topic_post_generation( 7 );

		$this->assertNotFalse( $first );
		$this->assertFalse( $second );
	}

	/**
	 * Different resources / types do not collide.
	 */
	public function test_distinct_resources_do_not_collide() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$this->assertNotFalse( $this->claims->claim_author_post_generation( 1 ) );
		$this->assertNotFalse( $this->claims->claim_author_post_generation( 2 ) );
		$this->assertNotFalse( $this->claims->claim_author_topic_generation( 1 ) );
	}

	/**
	 * Releasing a claim frees the resource for a new claim.
	 */
	public function test_release_allows_reclaim() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$token = $this->claims->claim_author_topic_generation( 100 );
		$this->assertNotFalse( $token );

		$released = $this->claims->release_claim(
			AIPS_Generation_Claims_Repository::TYPE_AUTHOR_TOPIC_GENERATION,
			100,
			$token
		);
		$this->assertTrue( $released );

		$this->assertNotFalse(
			$this->claims->claim_author_topic_generation( 100 ),
			'Resource should be claimable again after release.'
		);
	}

	/**
	 * A non-holder token cannot release a claim.
	 */
	public function test_release_requires_matching_token() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$this->claims->claim_author_post_generation( 55 );

		$released = $this->claims->release_claim(
			AIPS_Generation_Claims_Repository::TYPE_AUTHOR_POST_GENERATION,
			55,
			'not-the-real-token'
		);

		$this->assertFalse( $released, 'Releasing with the wrong token must fail.' );
		$this->assertFalse( $this->claims->claim_author_post_generation( 55 ), 'Claim should still be held.' );
	}

	/**
	 * An expired claim can be reclaimed by another worker (recovery).
	 */
	public function test_expired_claim_can_be_reclaimed() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		global $wpdb;

		// Acquire, then force expiry into the past.
		$token = $this->claims->claim_author_post_generation( 200, 600 );
		$this->assertNotFalse( $token );

		$past = AIPS_DateTime::now()->timestamp() - 10;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->table} SET expires_at = %d WHERE resource_id = 200",
			$past
		) );

		$new_token = $this->claims->claim_author_post_generation( 200 );
		$this->assertNotFalse( $new_token, 'Expired claim should be reclaimable via the atomic write.' );
		$this->assertNotSame( $token, $new_token, 'Reclaim should mint a fresh token.' );
	}

	/**
	 * recover_expired_claims() reaps only expired rows.
	 */
	public function test_recover_expired_claims_removes_only_expired() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		global $wpdb;

		$live = $this->claims->claim_author_post_generation( 300, 600 );
		$this->claims->claim_author_post_generation( 301, 600 );

		// Expire only resource 301.
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->table} SET expires_at = %d WHERE resource_id = 301",
			AIPS_DateTime::now()->timestamp() - 5
		) );

		$removed = $this->claims->recover_expired_claims();
		$this->assertSame( 1, $removed, 'Exactly one expired claim should be recovered.' );

		// The live claim survives.
		$this->assertFalse( $this->claims->claim_author_post_generation( 300 ), 'Live claim must not be reaped.' );
		$this->assertNotFalse( $this->claims->claim_author_post_generation( 301 ), 'Expired resource should be free.' );
		$this->assertNotEmpty( $live );
	}

	/**
	 * refresh_claim() extends expiry for the holder.
	 */
	public function test_refresh_extends_expiry() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		global $wpdb;

		$token = $this->claims->claim_author_post_generation( 400, 60 );
		$before = (int) $wpdb->get_var( "SELECT expires_at FROM {$this->table} WHERE resource_id = 400" );

		$this->assertTrue( $this->claims->refresh_claim(
			AIPS_Generation_Claims_Repository::TYPE_AUTHOR_POST_GENERATION,
			400,
			$token,
			3600
		) );

		$after = (int) $wpdb->get_var( "SELECT expires_at FROM {$this->table} WHERE resource_id = 400" );
		$this->assertGreaterThan( $before, $after, 'Refresh should push expiry further into the future.' );
	}
}
