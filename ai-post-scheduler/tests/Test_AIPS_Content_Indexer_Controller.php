<?php
/**
 * Tests for AIPS_Content_Indexer_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Indexer_Controller extends WP_UnitTestCase {

	/** @var AIPS_Content_Indexer_Controller */
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new AIPS_Content_Indexer_Controller();
	}

	/**
	 * Test controller registers expected AJAX actions in WordPress.
	 */
	public function test_ajax_hooks_registered() {
		$actions = array(
			'wp_ajax_aips_indexer_get_status',
			'wp_ajax_aips_indexer_process_batch',
			'wp_ajax_aips_indexer_get_graph_data',
			'wp_ajax_aips_indexer_search_posts',
			'wp_ajax_aips_indexer_cannibalization_audit',
			'wp_ajax_aips_indexer_save_settings',
			'wp_ajax_aips_indexer_clear_index',
			'wp_ajax_aips_indexer_fetch_meow_environments',
		);

		foreach ( $actions as $action ) {
			$this->assertGreaterThan(
				0,
				has_action( $action ),
				"Action {$action} should be registered by controller constructor"
			);
		}
	}
}
