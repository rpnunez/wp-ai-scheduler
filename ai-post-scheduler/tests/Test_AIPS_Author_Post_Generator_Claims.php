<?php
/**
 * Tests that AIPS_Author_Post_Generator acquires and always releases claims,
 * and reports multi-post outcomes accurately (findings 2 and 9).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Post_Generator_Claims extends WP_UnitTestCase {

	/** @var AIPS_Generation_Claims_Repository */
	private $claims;

	/** @var string */
	private $table;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->table  = $wpdb->prefix . 'aips_generation_claims';
		$this->claims = new AIPS_Generation_Claims_Repository();

		if ( ! $this->should_skip() ) {
			$wpdb->query( "DELETE FROM {$this->table}" );
		}
	}

	public function tearDown(): void {
		global $wpdb;
		if ( ! $this->should_skip() ) {
			$wpdb->query( "DELETE FROM {$this->table}" );
		}
		parent::tearDown();
	}

	private function should_skip() {
		global $wpdb;
		if ( property_exists( $wpdb, 'get_results_return_val' ) ) {
			return true;
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
		return $found !== $this->table;
	}

	private function inject_property( $object, $property_name, $value ) {
		$reflection = new ReflectionClass( $object );
		while ( $reflection && ! $reflection->hasProperty( $property_name ) ) {
			$reflection = $reflection->getParentClass();
		}
		$property = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	private function make_author( $overrides = array() ) {
		return (object) array_merge( array(
			'id'                                 => 4242,
			'name'                               => 'Claim Author',
			'manual_post_generation_quantity'    => 2,
			'scheduled_post_generation_quantity' => 2,
			'max_posts_per_topic'                => 1,
		), $overrides );
	}

	private function make_topics_repository( $topics ) {
		return new class( $topics ) {
			private $topics;
			public function __construct( $t ) { $this->topics = $t; }
			public function get_approved_for_generation( $author_id, $limit = 1, $after_id = 0, $max = 1 ) {
				return array_slice( $this->topics, 0, $limit );
			}
		};
	}

	/**
	 * On success, both author-run and per-topic claims are released.
	 */
	public function test_claims_released_after_success() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$topics    = array( (object) array( 'id' => 501, 'topic_title' => 'T1' ) );
		$generator = new class extends AIPS_Author_Post_Generator {
			public function generate_post_from_topic( $topic, $author, $creation_method = 'manual' ) {
				return 9000 + (int) $topic->id;
			}
		};
		$this->inject_property( $generator, 'topics_repository', $this->make_topics_repository( $topics ) );

		$author = $this->make_author();
		$result = $generator->generate_posts_for_author( $author, null, 'manual', false );

		$this->assertSame( 'success', $result->get_status() );
		$this->assertSame( array( 9501 ), $result->get_post_ids() );

		$this->assertClaimsReleased( (int) $author->id, 501 );
	}

	/**
	 * A WP_Error from generation still releases claims and is recorded.
	 */
	public function test_claims_released_after_wp_error() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$topics    = array( (object) array( 'id' => 502, 'topic_title' => 'T2' ) );
		$generator = new class extends AIPS_Author_Post_Generator {
			public function generate_post_from_topic( $topic, $author, $creation_method = 'manual' ) {
				return new WP_Error( 'generation_failed', 'nope' );
			}
		};
		$this->inject_property( $generator, 'topics_repository', $this->make_topics_repository( $topics ) );

		$author = $this->make_author();
		$result = $generator->generate_posts_for_author( $author, null, 'manual', false );

		$this->assertSame( 'failed', $result->get_status() );
		$this->assertCount( 1, $result->get_failures() );

		$this->assertClaimsReleased( (int) $author->id, 502 );
	}

	/**
	 * An exception thrown mid-run still releases the author claim (finally block).
	 */
	public function test_author_claim_released_after_exception() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$topics    = array( (object) array( 'id' => 503, 'topic_title' => 'T3' ) );
		$generator = new class extends AIPS_Author_Post_Generator {
			public function generate_post_from_topic( $topic, $author, $creation_method = 'manual' ) {
				throw new RuntimeException( 'kaboom' );
			}
		};
		$this->inject_property( $generator, 'topics_repository', $this->make_topics_repository( $topics ) );

		$author = $this->make_author();

		try {
			$generator->generate_posts_for_author( $author, null, 'manual', false );
			$this->fail( 'Expected exception to propagate.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'kaboom', $e->getMessage() );
		}

		// The author-run claim must have been released by the finally block.
		$this->assertNotFalse(
			$this->claims->claim_author_post_generation( (int) $author->id ),
			'Author claim should be released even when an exception propagates.'
		);
	}

	/**
	 * A second concurrent author run is reported as already_running.
	 */
	public function test_second_run_reports_already_running() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Claims table not available.' );
		}

		$author = $this->make_author();

		// Pre-hold the author-run claim to simulate a competing worker.
		$held = $this->claims->claim_author_post_generation( (int) $author->id );
		$this->assertNotFalse( $held );

		$topics    = array( (object) array( 'id' => 504, 'topic_title' => 'T4' ) );
		$generator = new class extends AIPS_Author_Post_Generator {
			public $called = false;
			public function generate_post_from_topic( $topic, $author, $creation_method = 'manual' ) {
				$this->called = true;
				return 1;
			}
		};
		$this->inject_property( $generator, 'topics_repository', $this->make_topics_repository( $topics ) );

		$result = $generator->generate_posts_for_author( $author, null, 'manual', false );

		$this->assertSame( 'already_running', $result->get_status() );
		$this->assertFalse( $generator->called, 'Generation must not run while another run holds the claim.' );
	}

	/**
	 * Assert both claim types for the resources are free (claimable again).
	 */
	private function assertClaimsReleased( $author_id, $topic_id ) {
		$this->assertNotFalse(
			$this->claims->claim_author_post_generation( $author_id ),
			'Author-run claim should be released.'
		);
		$this->assertNotFalse(
			$this->claims->claim_topic_post_generation( $topic_id ),
			'Per-topic claim should be released.'
		);
	}
}
