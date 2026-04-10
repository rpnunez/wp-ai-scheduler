<?php
/**
 * Tests for context-aware bootstrap dispatcher (Step 15).
 *
 * Verifies that AI_Post_Scheduler::init() dispatches to the correct boot method
 * based on the current request context (cron, AJAX, admin, or frontend), and
 * that each boot method registers only the subsystems appropriate for its context.
 *
 * These tests run in limited mode (no full WordPress environment).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Context_Boot extends WP_UnitTestCase {

	/**
	 * Reset the singleton and all context globals before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->reset_context_globals();
	}

	/**
	 * Reset context globals after each test.
	 */
	public function tearDown(): void {
		$this->reset_context_globals();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Reset all simulated request-context globals to their default (frontend) state.
	 */
	private function reset_context_globals() {
		$GLOBALS['aips_test_doing_cron']  = false;
		$GLOBALS['aips_test_doing_ajax']  = false;
		$GLOBALS['aips_test_is_admin']    = false;
	}

	/**
	 * Count callbacks registered for an action hook in the test hook store.
	 *
	 * @param string $hook The action hook name.
	 * @return int
	 */
	private function count_action_callbacks( $hook ) {
		global $wp_filter;
		if ( isset( $wp_filter[ $hook ] ) && is_object( $wp_filter[ $hook ] ) && isset( $wp_filter[ $hook ]->callbacks ) ) {
			$count = 0;
			foreach ( $wp_filter[ $hook ]->callbacks as $cbs ) {
				$count += count( $cbs );
			}
			return $count;
		}

		if ( isset( $GLOBALS['aips_test_hooks']['actions'][ $hook ] ) ) {
			$count = 0;
			foreach ( $GLOBALS['aips_test_hooks']['actions'][ $hook ] as $cbs ) {
				$count += count( $cbs );
			}
			return $count;
		}

		return 0;
	}

	// -------------------------------------------------------------------------
	// Plugin class structure assertions
	// -------------------------------------------------------------------------

	/**
	 * The plugin class must have a public init() method.
	 */
	public function test_init_method_exists() {
		$this->assertTrue(
			method_exists( 'AI_Post_Scheduler', 'init' ),
			'AI_Post_Scheduler must have an init() method'
		);
	}

	/**
	 * The five boot methods must all exist on the plugin class.
	 *
	 * They are private but PHP Reflection can confirm their presence.
	 */
	public function test_boot_methods_exist() {
		$rc = new ReflectionClass( 'AI_Post_Scheduler' );

		foreach ( array( 'boot_common', 'boot_cron', 'boot_ajax', 'boot_admin', 'boot_frontend' ) as $method ) {
			$this->assertTrue(
				$rc->hasMethod( $method ),
				"AI_Post_Scheduler must have a private {$method}() method"
			);
		}
	}

	/**
	 * All five boot methods must be declared private.
	 */
	public function test_boot_methods_are_private() {
		$rc = new ReflectionClass( 'AI_Post_Scheduler' );

		foreach ( array( 'boot_common', 'boot_cron', 'boot_ajax', 'boot_admin', 'boot_frontend' ) as $method ) {
			$this->assertTrue(
				$rc->getMethod( $method )->isPrivate(),
				"{$method}() must be declared private"
			);
		}
	}

	// -------------------------------------------------------------------------
	// Dispatcher: correct boot method called per context
	// -------------------------------------------------------------------------

	/**
	 * In a cron context, init() must invoke boot_cron().
	 *
	 * We verify this indirectly by asserting that the aips_generate_scheduled_posts
	 * action hook has been registered (which only boot_cron() does).
	 */
	public function test_cron_context_registers_scheduler_hook() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_doing_cron'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'aips_generate_scheduled_posts' ),
			'boot_cron() must register the aips_generate_scheduled_posts action hook'
		);
	}

	/**
	 * In a cron context, aips_generate_author_topics hook must be registered.
	 */
	public function test_cron_context_registers_author_topics_hook() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_doing_cron'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'aips_generate_author_topics' ),
			'boot_cron() must register the aips_generate_author_topics action hook'
		);
	}

	/**
	 * In a cron context, aips_generate_author_posts hook must be registered.
	 */
	public function test_cron_context_registers_author_posts_hook() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_doing_cron'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'aips_generate_author_posts' ),
			'boot_cron() must register the aips_generate_author_posts action hook'
		);
	}

	/**
	 * In a cron context, admin_menu hook must NOT be registered.
	 *
	 * boot_cron() must not boot the admin menu subsystem.
	 */
	public function test_cron_context_does_not_register_admin_menu_hook() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_doing_cron'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertSame(
			0,
			$this->count_action_callbacks( 'admin_menu' ),
			'boot_cron() must not register an admin_menu hook'
		);
	}

	/**
	 * In a frontend context, admin_menu hook must NOT be registered.
	 *
	 * boot_frontend() must not boot the admin menu subsystem.
	 */
	public function test_frontend_context_does_not_register_admin_menu_hook() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		// All context globals default to false (frontend).
		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertSame(
			0,
			$this->count_action_callbacks( 'admin_menu' ),
			'boot_frontend() must not register an admin_menu hook'
		);
	}

	/**
	 * In a frontend context, scheduler hooks must NOT be registered.
	 *
	 * Schedulers belong only to boot_cron().
	 */
	public function test_frontend_context_does_not_register_scheduler_hooks() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		// All context globals default to false (frontend).
		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertSame(
			0,
			$this->count_action_callbacks( 'aips_generate_scheduled_posts' ),
			'boot_frontend() must not register aips_generate_scheduled_posts'
		);
	}

	/**
	 * In an admin context, admin_menu hook must be registered.
	 *
	 * boot_admin() must boot the menu subsystem.
	 */
	public function test_admin_context_registers_admin_menu_hook() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_is_admin'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'admin_menu' ),
			'boot_admin() must register an admin_menu hook'
		);
	}

	/**
	 * In an admin context, scheduler hooks must NOT be registered.
	 *
	 * Schedulers belong only to boot_cron().
	 */
	public function test_admin_context_does_not_register_scheduler_hooks() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_is_admin'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertSame(
			0,
			$this->count_action_callbacks( 'aips_generate_scheduled_posts' ),
			'boot_admin() must not register aips_generate_scheduled_posts'
		);
	}

	// -------------------------------------------------------------------------
	// AJAX context: direct controller resolution
	// -------------------------------------------------------------------------

	/**
	 * boot_ajax() must resolve the correct controller for a known registry action.
	 *
	 * We assert that AIPS_Ajax_Registry::get_controller_for() returns a non-null
	 * value for a known action, confirming the registry is wired correctly.
	 */
	public function test_ajax_registry_resolves_known_action() {
		$controller_class = AIPS_Ajax_Registry::get_controller_for( 'aips_save_template' );

		$this->assertNotNull(
			$controller_class,
			'AIPS_Ajax_Registry must map aips_save_template to a controller class'
		);
		$this->assertSame(
			'AIPS_Templates_Controller',
			$controller_class,
			'aips_save_template must map to AIPS_Templates_Controller'
		);
	}

	/**
	 * boot_ajax() must return null for an unregistered action.
	 */
	public function test_ajax_registry_returns_null_for_unknown_action() {
		$controller_class = AIPS_Ajax_Registry::get_controller_for( 'aips_unknown_action_xyz' );

		$this->assertNull(
			$controller_class,
			'AIPS_Ajax_Registry must return null for an unregistered action'
		);
	}

	// -------------------------------------------------------------------------
	// Context priority order: cron > ajax > admin > frontend
	// -------------------------------------------------------------------------

	/**
	 * When both cron and admin globals are true, the cron boot path wins.
	 *
	 * wp_doing_cron() is checked first in the dispatcher.
	 */
	public function test_cron_takes_priority_over_admin() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$GLOBALS['aips_test_doing_cron'] = true;
		$GLOBALS['aips_test_is_admin']   = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		// Cron path must be taken: scheduler hook present, admin_menu absent.
		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'aips_generate_scheduled_posts' ),
			'Cron path must be taken when wp_doing_cron() is true'
		);
		$this->assertSame(
			0,
			$this->count_action_callbacks( 'admin_menu' ),
			'Admin menu must NOT be registered when wp_doing_cron() is true'
		);
	}
}
