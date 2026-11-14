<?php
/**
 * Tests for migrated ActionScheduler call sites and AS-A4 argument parity (issue #2375).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cron_Backend_Call_Sites extends WP_UnitTestCase {

	/**
	 * Test double / spy for AIPS_Cron_Backend_Interface.
	 *
	 * @var AIPS_Cron_Backend_Interface
	 */
	private $backend_spy;

	/**
	 * Log of recorded backend calls.
	 *
	 * @var array
	 */
	public $recorded_calls = array();

	public function setUp(): void {
		parent::setUp();
		$this->recorded_calls = array();

		$spy = new class($this) implements AIPS_Cron_Backend_Interface {
			private $test_case;

			public function __construct($test_case) {
				$this->test_case = $test_case;
			}

			public function schedule_single($timestamp, $hook, array $args = array(), $group = '') {
				$this->test_case->recorded_calls[] = array(
					'method'    => 'schedule_single',
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'args'      => $args,
					'group'     => $group,
				);
				return true;
			}

			public function schedule_recurring($timestamp, $interval, $hook, array $args = array(), $group = '') {
				$this->test_case->recorded_calls[] = array(
					'method'    => 'schedule_recurring',
					'timestamp' => $timestamp,
					'interval'  => $interval,
					'hook'      => $hook,
					'args'      => $args,
					'group'     => $group,
				);
				return true;
			}

			public function next_scheduled($hook, array $args = array()) {
				return 1234567890;
			}

			public function has_scheduled($hook, array $args = array(), $group = '') {
				$this->test_case->recorded_calls[] = array(
					'method' => 'has_scheduled',
					'hook'   => $hook,
					'args'   => $args,
					'group'  => $group,
				);
				return true;
			}

			public function unschedule($hook, array $args = array()) {
				$this->test_case->recorded_calls[] = array(
					'method' => 'unschedule',
					'hook'   => $hook,
					'args'   => $args,
				);
				return true;
			}

			public function unschedule_all($hook, $group = '') {
				$this->test_case->recorded_calls[] = array(
					'method' => 'unschedule_all',
					'hook'   => $hook,
					'group'  => $group,
				);
				return true;
			}

			public function get_scheduled_actions($hook = '', $group = '') {
				return array();
			}

			public function get_backend_name() {
				return 'spy';
			}
		};

		$this->backend_spy = $spy;
	}

	// -------------------------------------------------------------------------
	// AS-A4 Fix: Author Embeddings Dispatch Argument Shape
	// -------------------------------------------------------------------------

	public function test_embeddings_cron_schedules_wrapped_args_and_group() {
		$embeddings_cron = new AIPS_Embeddings_Cron( null, null, null, $this->backend_spy );

		$reflector = new ReflectionClass( $embeddings_cron );
		$method    = $reflector->getMethod( 'schedule_next_batch' );
		$method->setAccessible( true );

		$method->invoke( $embeddings_cron, 123, 25, 500 );

		$this->assertCount( 1, $this->recorded_calls );
		$call = $this->recorded_calls[0];

		$this->assertSame( 'schedule_single', $call['method'] );
		$this->assertSame( 'aips_process_author_embeddings', $call['hook'] );
		$this->assertSame( AIPS_Cron_Registry::GROUP_EMBEDDINGS, $call['group'] );

		// AS-A4 assertion: $args must be wrapped in array($payload) so ActionScheduler
		// spreading does not unpack associative keys into separate parameters.
		$this->assertIsArray( $call['args'] );
		$this->assertCount( 1, $call['args'] );
		$this->assertSame(
			array(
				'author_id'         => 123,
				'batch_size'        => 25,
				'last_processed_id' => 500,
			),
			$call['args'][0]
		);
	}

	public function test_embeddings_cron_dispatch_handler_receives_associative_payload() {
		$registry = new AIPS_Cron_Registry();
		$handlers = $registry->get_dispatch_handlers( 'cron' );

		$this->assertArrayHasKey( 'aips_process_author_embeddings', $handlers );
		$entry = $handlers['aips_process_author_embeddings'];

		$this->assertSame( 1, $entry['accepted_args'] );
		$this->assertIsCallable( $entry['callback'] );
	}

	// -------------------------------------------------------------------------
	// Content Indexer Queue Worker Migration
	// -------------------------------------------------------------------------

	public function test_indexer_service_schedules_via_cron_backend() {
		$indexer = new AIPS_Content_Indexer_Service(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$this->backend_spy
		);

		// When queue is not paused and not scheduled:
		// Mock has_scheduled to return false for this check
		$backend_mock = $this->createMock( AIPS_Cron_Backend_Interface::class );
		$backend_mock->method( 'has_scheduled' )->willReturn( false );
		$backend_mock->expects( $this->once() )
			->method( 'schedule_single' )
			->with(
				$this->greaterThan( 0 ),
				'aips_process_pending_indexer_queue',
				array(),
				AIPS_Cron_Registry::GROUP_INDEXER
			)
			->willReturn( true );

		$indexer_with_mock = new AIPS_Content_Indexer_Service(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$backend_mock
		);

		delete_option( AIPS_Content_Indexer_Service::QUEUE_PAUSED_OPTION );
		$indexer_with_mock->schedule_queue_worker( time() + 10 );
	}

	public function test_indexer_service_unschedules_via_cron_backend() {
		$indexer = new AIPS_Content_Indexer_Service(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$this->backend_spy
		);

		$indexer->unschedule_queue_worker();

		$this->assertCount( 1, $this->recorded_calls );
		$this->assertSame( 'unschedule_all', $this->recorded_calls[0]['method'] );
		$this->assertSame( 'aips_process_pending_indexer_queue', $this->recorded_calls[0]['hook'] );
		$this->assertSame( AIPS_Cron_Registry::GROUP_INDEXER, $this->recorded_calls[0]['group'] );
	}

	public function test_indexer_service_checks_scheduled_via_cron_backend() {
		$indexer = new AIPS_Content_Indexer_Service(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$this->backend_spy
		);

		$is_scheduled = $indexer->is_queue_worker_scheduled();

		$this->assertTrue( $is_scheduled );
		$this->assertCount( 1, $this->recorded_calls );
		$this->assertSame( 'has_scheduled', $this->recorded_calls[0]['method'] );
		$this->assertSame( 'aips_process_pending_indexer_queue', $this->recorded_calls[0]['hook'] );
		$this->assertSame( AIPS_Cron_Registry::GROUP_INDEXER, $this->recorded_calls[0]['group'] );
	}

	// -------------------------------------------------------------------------
	// Internal Links Controller Migration
	// -------------------------------------------------------------------------

	public function test_internal_links_controller_schedules_via_cron_backend() {
		$controller = new AIPS_Internal_Links_Controller(
			null,
			null,
			null,
			null,
			null,
			null,
			$this->backend_spy
		);

		$reflector = new ReflectionClass( $controller );
		$method    = $reflector->getMethod( 'schedule_indexing_batch' );
		$method->setAccessible( true );

		$method->invoke( $controller, 999, 15 );

		$this->assertCount( 1, $this->recorded_calls );
		$call = $this->recorded_calls[0];

		$this->assertSame( 'schedule_single', $call['method'] );
		$this->assertSame( 'aips_index_posts_batch', $call['hook'] );
		$this->assertSame( AIPS_Cron_Registry::GROUP_INTERNAL_LINKS, $call['group'] );
		$this->assertSame(
			array(
				array(
					'last_post_id' => 999,
					'batch_size'   => 15,
				),
			),
			$call['args']
		);
	}
}
