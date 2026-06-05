<?php
/**
 * Tests for AIPS_Cacheable_Repository.
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('AIPS_Repository_Cache_Key_Builder')) {
	require_once dirname(__DIR__) . '/includes/class-aips-repository-cache-key-builder.php';
}

if (!class_exists('AIPS_Repository_Cache_Config')) {
	require_once dirname(__DIR__) . '/includes/class-aips-repository-cache-config.php';
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once dirname(__DIR__) . '/includes/trait-aips-cacheable-repository.php';
}

class AIPS_Test_Cacheable_Repository_Logger implements AIPS_Logger_Interface {
	public $entries = array();

	public function log($message, $level = 'info', $context = array()) {
		$this->entries[] = array(
			'message' => $message,
			'level'   => $level,
			'context' => $context,
		);
	}

	public function addSeparator($text) {}
}

class AIPS_Test_Cacheable_Repository_Subject {
	use AIPS_Cacheable_Repository;

	private $observer;
	private $policies;

	public function __construct( AIPS_Repository_Cache_Observer $observer, array $policies = array() ) {
		$this->observer = $observer;
		$this->policies = $policies;
	}

	public function read( $operation_id, array $args, callable $callback, array $options = array() ) {
		return $this->cache_read( $operation_id, $args, $callback, $options );
	}

	public function bypass( $operation_id, array $args, callable $callback, array $options = array() ) {
		return $this->cache_bypass_read( $operation_id, $args, $callback, $options );
	}

	public function invalidate_domain( $domain, array $context = array(), $reason = '' ) {
		$this->invalidate_cache_domain( $domain, $context, $reason );
	}

	public function invalidate_tags_public( array $tags, $reason = '' ) {
		$this->invalidate_cache_tags( $tags, $reason );
	}

	protected function repository_cache_group(): string {
		return 'test_repository';
	}

	protected function repository_cache_policies(): array {
		return $this->policies;
	}

	protected function repository_cache_observer() {
		return $this->observer;
	}
}

class Test_AIPS_Cacheable_Repository extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		update_option( 'aips_cache_driver', 'array' );
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
		remove_all_filters( 'wp_doing_cron' );
	}

	public function tearDown(): void {
		AIPS_Cache_Factory::reset();
		delete_option( 'aips_cache_driver' );
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
		remove_all_filters( 'wp_doing_cron' );
		parent::tearDown();
	}

	private function make_subject( array $policies, &$logger = null ) {
		$logger   = new AIPS_Test_Cacheable_Repository_Logger();
		$observer = new AIPS_Repository_Cache_Observer( $logger );

		return new AIPS_Test_Cacheable_Repository_Subject( $observer, $policies );
	}

	public function test_cache_read_uses_explicit_policy_and_returns_cached_value_on_second_call() {
		$subject = $this->make_subject(
			array(
				'authors.get_all' => array(
					'tier' => 'medium',
					'tags' => array( 'authors' ),
				),
			),
			$logger
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return array( 'count' => 3 );
		};

		$first  = $subject->read( 'authors.get_all', array( 'active_only' => false ), $callback );
		$second = $subject->read( 'authors.get_all', array( 'active_only' => false ), $callback );

		$this->assertSame( array( 'count' => 3 ), $first );
		$this->assertSame( array( 'count' => 3 ), $second );
		$this->assertSame( 1, $calls );
		$this->assertSame( 'Repository cache read', $logger->entries[0]['message'] );
		$this->assertFalse( $logger->entries[0]['context']['hit'] );
		$this->assertTrue( $logger->entries[0]['context']['miss'] );
		$this->assertSame( 'Repository cache write', $logger->entries[1]['message'] );
		$this->assertSame( 'Repository cache read', $logger->entries[2]['message'] );
		$this->assertTrue( $logger->entries[2]['context']['hit'] );
	}

	public function test_missing_policy_defaults_to_uncached_behavior() {
		$subject = $this->make_subject( array(), $logger );

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return 'value';
		};

		$subject->read( 'authors.get_all', array(), $callback );
		$subject->read( 'authors.get_all', array(), $callback );

		$this->assertSame( 2, $calls );
		$this->assertSame( 'Repository cache bypass', $logger->entries[0]['message'] );
		$this->assertSame( 'uncached_policy', $logger->entries[0]['context']['invalidation_reason'] );
	}

	public function test_cache_null_false_does_not_store_null_results() {
		$subject = $this->make_subject(
			array(
				'authors.get_by_id' => array(
					'tier'       => 'medium',
					'tags'       => array( 'authors' ),
					'cache_null' => false,
				),
			)
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return null;
		};

		$subject->read( 'authors.get_by_id', array( 'author_id' => 44 ), $callback );
		$subject->read( 'authors.get_by_id', array( 'author_id' => 44 ), $callback );

		$this->assertSame( 2, $calls );
	}

	public function test_cache_null_true_caches_null_results() {
		$subject = $this->make_subject(
			array(
				'authors.get_by_id' => array(
					'tier'       => 'medium',
					'tags'       => array( 'authors' ),
					'cache_null' => true,
				),
			)
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return null;
		};

		$first  = $subject->read( 'authors.get_by_id', array( 'author_id' => 44 ), $callback );
		$second = $subject->read( 'authors.get_by_id', array( 'author_id' => 44 ), $callback );

		$this->assertNull( $first );
		$this->assertNull( $second );
		$this->assertSame( 1, $calls );
	}

	public function test_policy_tag_placeholders_resolve_from_read_args() {
		$subject = $this->make_subject(
			array(
				'authors.get_by_id' => array(
					'tier' => 'medium',
					'tags' => array( 'authors', 'author:{author_id}' ),
				),
			),
			$logger
		);

		$subject->read(
			'authors.get_by_id',
			array(
				'author_id' => 44,
			),
			function() {
				return (object) array( 'id' => 44 );
			}
		);

		$this->assertSame( array( 'authors', 'author_44' ), $logger->entries[0]['context']['tags'] );
	}

	public function test_unknown_placeholder_records_warning_without_fatal_error() {
		$subject = $this->make_subject(
			array(
				'authors.get_by_id' => array(
					'tier' => 'medium',
					'tags' => array( 'authors', 'author:{missing_id}' ),
				),
			),
			$logger
		);

		$result = $subject->read(
			'authors.get_by_id',
			array(
				'author_id' => 44,
			),
			function() {
				return (object) array( 'id' => 44 );
			}
		);

		$this->assertEquals( 44, $result->id );
		$this->assertSame( 'Repository cache warning', $logger->entries[0]['message'] );
		$this->assertSame( 'warning', $logger->entries[0]['level'] );
		$this->assertSame( 'warning', $logger->entries[0]['context']['event_type'] );
		$this->assertSame( 'unknown_placeholder:missing_id', $logger->entries[0]['context']['invalidation_reason'] );
		$this->assertSame( array( 'authors' ), $logger->entries[1]['context']['tags'] );
	}

	public function test_force_refresh_rebuilds_cached_value_and_updates_cache() {
		$subject = $this->make_subject(
			array(
				'authors.get_all' => array(
					'tier' => 'medium',
					'tags' => array( 'authors' ),
				),
			),
			$logger
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return 'value_' . $calls;
		};

		$first   = $subject->read( 'authors.get_all', array(), $callback );
		$second  = $subject->read( 'authors.get_all', array(), $callback, array( 'force_refresh' => true ) );
		$third   = $subject->read( 'authors.get_all', array(), $callback );

		$this->assertSame( 'value_1', $first );
		$this->assertSame( 'value_2', $second );
		$this->assertSame( 'value_2', $third );
		$this->assertSame( 2, $calls );
		$this->assertSame( 'Repository cache bypass', $logger->entries[2]['message'] );
		$this->assertSame( 'force_refresh', $logger->entries[2]['context']['invalidation_reason'] );
	}

	public function test_cache_bypass_read_skips_cache_and_records_bypass() {
		$subject = $this->make_subject(
			array(
				'authors.get_due' => array(
					'tier' => 'medium',
					'tags' => array( 'authors' ),
				),
			),
			$logger
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return 'run_' . $calls;
		};

		$first  = $subject->bypass( 'authors.get_due', array(), $callback );
		$second = $subject->bypass( 'authors.get_due', array(), $callback );

		$this->assertSame( 'run_1', $first );
		$this->assertSame( 'run_2', $second );
		$this->assertSame( 2, $calls );
		$this->assertSame( 'Repository cache bypass', $logger->entries[0]['message'] );
		$this->assertTrue( $logger->entries[0]['context']['bypass'] );
		$this->assertSame( 'explicit_bypass', $logger->entries[0]['context']['invalidation_reason'] );
	}

	public function test_bypass_cache_option_skips_cache() {
		$subject = $this->make_subject(
			array(
				'authors.get_all' => array(
					'tier' => 'medium',
					'tags' => array( 'authors' ),
				),
			)
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return 'value';
		};

		$subject->read( 'authors.get_all', array(), $callback, array( 'bypass_cache' => true ) );
		$subject->read( 'authors.get_all', array(), $callback, array( 'bypass_cache' => true ) );

		$this->assertSame( 2, $calls );
	}

	public function test_bypass_ajax_policy_skips_cache_during_ajax_requests() {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = $this->make_subject(
			array(
				'authors.get_all' => array(
					'tier'        => 'medium',
					'tags'        => array( 'authors' ),
					'bypass_ajax' => true,
				),
			),
			$logger
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return 'value_' . $calls;
		};

		$first  = $subject->read( 'authors.get_all', array(), $callback );
		$second = $subject->read( 'authors.get_all', array(), $callback );

		$this->assertSame( 'value_1', $first );
		$this->assertSame( 'value_2', $second );
		$this->assertSame( 2, $calls );
		$this->assertSame( 'Repository cache bypass', $logger->entries[0]['message'] );
		$this->assertSame( 'ajax_bypass', $logger->entries[0]['context']['invalidation_reason'] );
	}

	public function test_invalidate_cache_tags_changes_tag_versions_without_deleting_existing_values() {
		$subject = $this->make_subject(
			array(
				'authors.get_all' => array(
					'tier' => 'medium',
					'tags' => array( 'authors' ),
				),
			)
		);

		$calls = 0;
		$callback = function() use ( &$calls ) {
			$calls++;
			return 'value_' . $calls;
		};

		$first = $subject->read( 'authors.get_all', array(), $callback );
		$subject->invalidate_tags_public( array( 'authors' ), 'author_saved' );
		$second = $subject->read( 'authors.get_all', array(), $callback );

		$this->assertSame( 'value_1', $first );
		$this->assertSame( 'value_2', $second );
		$this->assertSame( 2, $calls );
	}

	public function test_invalidate_cache_domain_falls_back_to_domain_tag() {
		$subject = $this->make_subject(
			array(
				'authors.get_all' => array(
					'tier' => 'medium',
					'tags' => array( 'authors' ),
				),
			),
			$logger
		);

		$subject->invalidate_domain( 'authors', array( 'author_id' => 12 ), 'author_saved' );

		$this->assertSame( 'Repository cache invalidation', $logger->entries[0]['message'] );
		$this->assertSame( array( 'authors' ), $logger->entries[0]['context']['tags'] );
		$this->assertSame( 'author_saved', $logger->entries[0]['context']['invalidation_reason'] );
	}
}
