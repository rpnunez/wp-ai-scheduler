<?php
/**
 * Tests for AIPS_Admin_Assets — admin-embeddings.js scoping.
 *
 * Verifies that `aips-admin-embeddings` is enqueued only on the
 * Authors and Author Topics admin pages and NOT on any other plugin page.
 *
 * Works in both the full WordPress test environment (CI) and the limited
 * test environment (no WordPress library). The helper method
 * enqueued_scripts_for_hook() detects which environment is active and
 * uses the appropriate assertion mechanism:
 *  - Full WP env: inspects $wp_scripts->queue (WP-native).
 *  - Limited env: reads $GLOBALS['aips_test_enqueued_scripts'] populated
 *    by the bootstrap stubs.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Assets_Embeddings extends WP_UnitTestCase {

	/**
	 * @var AIPS_Admin_Assets
	 */
	private $admin_assets;

	public function setUp(): void {
		parent::setUp();
		$this->admin_assets = new AIPS_Admin_Assets();
	}

	public function tearDown(): void {
		parent::tearDown();
		// Reset limited-mode tracking between tests.
		$GLOBALS['aips_test_enqueued_scripts'] = array();
		$GLOBALS['aips_test_enqueued_styles']  = array();
	}

	/* ---------------------------------------------------------------------- */
	/* Helper                                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Whether the full WordPress test environment is available.
	 *
	 * @return bool
	 */
	private function is_wp_environment() {
		return isset( $GLOBALS['wp_scripts'] ) && $GLOBALS['wp_scripts'] instanceof WP_Scripts;
	}

	/**
	 * Call enqueue_admin_assets() with the given hook and return the list of
	 * enqueued script handles.
	 *
	 * In the full WordPress environment the WP_Scripts queue is saved,
	 * cleared, the method is called, and the resulting queue is captured
	 * before the original queue is restored — preventing cross-test
	 * contamination without side-effects on unrelated tests.
	 *
	 * In the limited test environment the bootstrap tracking global is used.
	 *
	 * @param string $hook Admin page hook suffix.
	 * @return array<string>
	 */
	private function enqueued_scripts_for_hook( $hook ) {
		if ( $this->is_wp_environment() ) {
			global $wp_scripts;
			$saved_queue       = $wp_scripts->queue;
			$wp_scripts->queue = array();

			$this->admin_assets->enqueue_admin_assets( $hook );

			$result            = $wp_scripts->queue;
			$wp_scripts->queue = $saved_queue;

			return $result;
		}

		// Limited-mode: use bootstrap tracking globals.
		$GLOBALS['aips_test_enqueued_scripts'] = array();
		$this->admin_assets->enqueue_admin_assets( $hook );
		return $GLOBALS['aips_test_enqueued_scripts'];
	}

	/* ---------------------------------------------------------------------- */
	/* Tests — embeddings script IS enqueued on authors pages                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * admin-embeddings.js must be enqueued on the Authors page.
	 */
	public function test_embeddings_enqueued_on_authors_page() {
		$scripts = $this->enqueued_scripts_for_hook( 'ai-post-scheduler_page_aips-authors' );
		$this->assertContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should be enqueued on the Authors page.'
		);
	}

	/**
	 * admin-embeddings.js must be enqueued on the Author Topics page.
	 */
	public function test_embeddings_enqueued_on_author_topics_page() {
		$scripts = $this->enqueued_scripts_for_hook( 'ai-post-scheduler_page_aips-author-topics' );
		$this->assertContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should be enqueued on the Author Topics page.'
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Tests — embeddings script is NOT enqueued on unrelated pages           */
	/* ---------------------------------------------------------------------- */

	/**
	 * admin-embeddings.js must NOT be enqueued on the Templates page.
	 */
	public function test_embeddings_not_enqueued_on_templates_page() {
		$scripts = $this->enqueued_scripts_for_hook( 'ai-post-scheduler_page_aips-templates' );
		$this->assertNotContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should not be enqueued on the Templates page.'
		);
	}

	/**
	 * admin-embeddings.js must NOT be enqueued on the Schedule page.
	 */
	public function test_embeddings_not_enqueued_on_schedule_page() {
		$scripts = $this->enqueued_scripts_for_hook( 'ai-post-scheduler_page_aips-schedule' );
		$this->assertNotContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should not be enqueued on the Schedule page.'
		);
	}

	/**
	 * admin-embeddings.js must NOT be enqueued on the dashboard (top-level) page.
	 */
	public function test_embeddings_not_enqueued_on_dashboard_page() {
		$scripts = $this->enqueued_scripts_for_hook( 'toplevel_page_ai-post-scheduler' );
		$this->assertNotContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should not be enqueued on the Dashboard page.'
		);
	}

	/**
	 * admin-embeddings.js must NOT be enqueued on the History page.
	 */
	public function test_embeddings_not_enqueued_on_history_page() {
		$scripts = $this->enqueued_scripts_for_hook( 'ai-post-scheduler_page_aips-history' );
		$this->assertNotContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should not be enqueued on the History page.'
		);
	}

	/**
	 * admin-embeddings.js must NOT be enqueued on a completely unrelated page.
	 */
	public function test_embeddings_not_enqueued_on_non_plugin_page() {
		// Non-plugin hook — enqueue_admin_assets() returns early, nothing registered.
		$scripts = $this->enqueued_scripts_for_hook( 'edit.php' );
		$this->assertNotContains(
			'aips-admin-embeddings',
			$scripts,
			'admin-embeddings.js should not be enqueued on non-plugin pages.'
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Hook registration                                                        */
	/* ---------------------------------------------------------------------- */

	/**
	 * AIPS_Admin_Assets must register its enqueue callback on admin_enqueue_scripts.
	 */
	public function test_admin_enqueue_scripts_hook_registered() {
		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $this->admin_assets, 'enqueue_admin_assets' ) ),
			'admin_enqueue_scripts hook should be registered by AIPS_Admin_Assets.'
		);
	}
}
