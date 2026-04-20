<?php
/**
 * Tests for AIPS_Author_Topics_Repository — daily count aggregation.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Repository_Test extends WP_UnitTestCase {

	/** @var AIPS_Author_Topics_Repository */
	private $repository;

	/** @var AIPS_Authors_Repository */
	private $authors_repository;

	/** @var int */
	private $author_id;

	public function setUp(): void {
		parent::setUp();

		$this->repository       = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();

		$this->author_id = $this->authors_repository->create( array(
			'name'        => 'Daily Count Test Author',
			'field_niche' => 'Testing',
			'is_active'   => 1,
		) );
	}

	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_author_topics WHERE author_id = " . (int) $this->author_id );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_authors WHERE id = " . (int) $this->author_id );

		parent::tearDown();
	}

	/**
	 * Test that get_daily_topic_counts returns correct per-day counts.
	 */
	public function test_get_daily_topic_counts_returns_per_day_counts() {
		global $wpdb;

		if ( property_exists( $wpdb, 'get_results_return_val' ) ) {
			$this->markTestSkipped( 'get_daily_topic_counts requires a real wpdb instance.' );
		}

		$table     = $wpdb->prefix . 'aips_author_topics';
		$today     = gmdate( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
		$extra_ids = array();

		// Insert 3 topics for today and 2 for yesterday.
		foreach ( range( 1, 3 ) as $i ) {
			$wpdb->insert(
				$table,
				array(
					'author_id'   => $this->author_id,
					'topic_title' => 'Today topic ' . $i,
					'status'      => 'pending',
					'created_at'  => $today . ' 09:00:00',
				),
				array( '%d', '%s', '%s', '%s' )
			);
			$extra_ids[] = $wpdb->insert_id;
		}

		foreach ( range( 1, 2 ) as $i ) {
			$wpdb->insert(
				$table,
				array(
					'author_id'   => $this->author_id,
					'topic_title' => 'Yesterday topic ' . $i,
					'status'      => 'pending',
					'created_at'  => $yesterday . ' 09:00:00',
				),
				array( '%d', '%s', '%s', '%s' )
			);
			$extra_ids[] = $wpdb->insert_id;
		}

		$result = $this->repository->get_daily_topic_counts( 14 );

		// Today's bucket should have 3 topics.
		$this->assertArrayHasKey( $today, $result, 'Today should have a bucket in the result.' );
		$this->assertSame( 3, $result[$today], 'Expected 3 topics today.' );

		// Yesterday's bucket should have 2 topics.
		$this->assertArrayHasKey( $yesterday, $result, 'Yesterday should have a bucket in the result.' );
		$this->assertSame( 2, $result[$yesterday], 'Expected 2 topics yesterday.' );

		// A date outside the 14-day window must not appear.
		$out_of_range = gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$this->assertArrayNotHasKey( $out_of_range, $result, 'Out-of-range date should not appear.' );

		// Clean up extra rows.
		foreach ( $extra_ids as $id ) {
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}
	}

	/**
	 * Test that get_daily_topic_counts omits days with no records.
	 */
	public function test_get_daily_topic_counts_omits_empty_days() {
		global $wpdb;

		if ( property_exists( $wpdb, 'get_results_return_val' ) ) {
			$this->markTestSkipped( 'get_daily_topic_counts requires a real wpdb instance.' );
		}

		$result = $this->repository->get_daily_topic_counts( 1 );

		$this->assertIsArray( $result );

		// Every returned key must be a valid Y-m-d string.
		foreach ( array_keys( $result ) as $day ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $day );
		}

		// Every value must be a positive integer.
		foreach ( $result as $count ) {
			$this->assertIsInt( $count );
			$this->assertGreaterThan( 0, $count );
		}
	}

	/**
	 * Test that get_daily_topic_counts respects the $days window.
	 */
	public function test_get_daily_topic_counts_respects_days_window() {
		global $wpdb;

		if ( property_exists( $wpdb, 'get_results_return_val' ) ) {
			$this->markTestSkipped( 'get_daily_topic_counts requires a real wpdb instance.' );
		}

		$table        = $wpdb->prefix . 'aips_author_topics';
		$far_past_day = gmdate( 'Y-m-d', time() - 60 * DAY_IN_SECONDS );

		// Insert a topic 60 days ago (outside the 14-day window).
		$wpdb->insert(
			$table,
			array(
				'author_id'   => $this->author_id,
				'topic_title' => 'Old topic outside window',
				'status'      => 'pending',
				'created_at'  => $far_past_day . ' 12:00:00',
			),
			array( '%d', '%s', '%s', '%s' )
		);
		$inserted_id = $wpdb->insert_id;

		$result = $this->repository->get_daily_topic_counts( 14 );

		$this->assertArrayNotHasKey( $far_past_day, $result, 'Topic 60 days ago should not appear in a 14-day window.' );

		$wpdb->delete( $table, array( 'id' => $inserted_id ), array( '%d' ) );
	}
}
