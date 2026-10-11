<?php
/**
 * Tests that the Content Indexer page loads the scripts its run controls depend on.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Indexer_Assets extends WP_UnitTestCase {

	public function tearDown(): void {
		wp_dequeue_script( 'aips-content-indexer-script' );
		wp_dequeue_script( 'aips-background-processes' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_content_indexer_assets_bring_in_the_background_process_script() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$assets = new AIPS_Admin_Assets();
		$method = new ReflectionMethod( $assets, 'enqueue_content_indexer_assets' );
		$method->setAccessible( true );
		$method->invoke( $assets );

		$this->assertTrue( wp_script_is( 'aips-background-processes', 'enqueued' ), 'The run controls need background-processes.js, even with the admin bar hidden.' );
		$this->assertTrue( wp_script_is( 'aips-content-indexer-script', 'enqueued' ) );
	}
}
