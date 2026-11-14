<?php
/**
 * Tests for ActionScheduler-aware deactivation cleanup (AS-A5) and diagnostics visibility (AS-A7).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cron_Deactivation_And_Diagnostics extends WP_UnitTestCase {

	/** @var AIPS_Cron_Manager */
	private $cron_manager;

	/** @var AIPS_Cron_Registry */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry     = new AIPS_Cron_Registry();
		$this->cron_manager = new AIPS_Cron_Manager( $this->registry );

		$this->clear_all_crons();
	}

	public function tearDown(): void {
		$this->clear_all_crons();
		parent::tearDown();
	}

	private function clear_all_crons() {
		foreach ( $this->registry->get_all_hooks() as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}

	// -------------------------------------------------------------------------
	// AS-A5: Deactivation Cleanup Tests
	// -------------------------------------------------------------------------

	public function test_unschedule_all_plugin_crons_clears_all_hooks() {
		// Schedule events across various types
		$this->cron_manager->schedule_single( 'aips_process_author_embeddings', time() + 100, array( array( 'author_id' => 1 ) ) );
		$this->cron_manager->schedule_single( 'aips_index_posts_batch', time() + 200, array( array( 'batch_size' => 10 ) ) );
		$this->cron_manager->schedule_single( 'aips_process_pending_indexer_queue', time() + 300, array() );

		$this->assertNotFalse( wp_next_scheduled( 'aips_process_author_embeddings', array( array( 'author_id' => 1 ) ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'aips_index_posts_batch', array( array( 'batch_size' => 10 ) ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'aips_process_pending_indexer_queue', array() ) );

		// Run full deactivation cleanup
		$this->cron_manager->unschedule_all_plugin_crons();

		// Every event must be gone
		$this->assertFalse( wp_next_scheduled( 'aips_process_author_embeddings', array( array( 'author_id' => 1 ) ) ) );
		$this->assertFalse( wp_next_scheduled( 'aips_index_posts_batch', array( array( 'batch_size' => 10 ) ) ) );
		$this->assertFalse( wp_next_scheduled( 'aips_process_pending_indexer_queue', array() ) );
	}

	public function test_deactivate_method_calls_unschedule_all_plugin_crons() {
		wp_schedule_single_event( time() + 100, 'aips_process_pending_indexer_queue' );
		$this->assertNotFalse( wp_next_scheduled( 'aips_process_pending_indexer_queue' ) );

		$plugin = new AI_Post_Scheduler();
		$plugin->deactivate();

		$this->assertFalse( wp_next_scheduled( 'aips_process_pending_indexer_queue' ) );
	}

	// -------------------------------------------------------------------------
	// AS-A7: Diagnostics Scheduler Provider Tests
	// -------------------------------------------------------------------------

	public function test_diagnostics_reports_scheduling_backend() {
		$provider    = new AIPS_System_Diagnostics_Scheduler_Provider();
		$diagnostics = $provider->get_diagnostics();

		$this->assertArrayHasKey( 'scheduler health', $diagnostics );
		$health = $diagnostics['scheduler health'];

		$this->assertArrayHasKey( 'scheduling_backend', $health );
		$backend_check = $health['scheduling_backend'];

		$this->assertSame( 'Scheduling Backend', $backend_check['label'] );
		$this->assertSame( 'ok', $backend_check['status'] );
		$this->assertNotEmpty( $backend_check['value'] );
		$this->assertNotEmpty( $backend_check['details'] );
	}

	public function test_diagnostics_surfaces_scheduled_on_demand_hooks() {
		$time = time() + 600;
		wp_schedule_single_event( $time, 'aips_process_pending_indexer_queue' );

		$provider    = new AIPS_System_Diagnostics_Scheduler_Provider();
		$diagnostics = $provider->get_diagnostics();
		$health      = $diagnostics['scheduler health'];

		$hook_key = 'cron_hook_aips_process_pending_indexer_queue';
		$this->assertArrayHasKey( $hook_key, $health );
		$this->assertSame( 'ok', $health[ $hook_key ]['status'] );
		$this->assertStringContainsString( 'Active (1)', $health[ $hook_key ]['value'] );

		wp_unschedule_hook( 'aips_process_pending_indexer_queue' );
	}

	public function test_diagnostics_surfaces_idle_on_demand_hooks() {
		wp_unschedule_hook( 'aips_process_pending_indexer_queue' );

		$provider    = new AIPS_System_Diagnostics_Scheduler_Provider();
		$diagnostics = $provider->get_diagnostics();
		$health      = $diagnostics['scheduler health'];

		$hook_key = 'cron_hook_aips_process_pending_indexer_queue';
		$this->assertArrayHasKey( $hook_key, $health );
		$this->assertSame( 'info', $health[ $hook_key ]['status'] );
		$this->assertSame( 'Idle (no active job)', $health[ $hook_key ]['value'] );
	}
}
