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
	 * Reset context globals before each test.
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
	public function test_plugin_class_has_init_method() {
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
	public function test_plugin_class_has_all_five_boot_methods() {
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
	public function test_all_boot_methods_have_private_visibility() {
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

	// -------------------------------------------------------------------------
	// Lazy instantiation: scheduler singletons must NOT be created on non-cron
	// page loads (Phase B.4 — Step 6 regression guard).
	// -------------------------------------------------------------------------

	/**
	 * Helper: read the private static $instance property of a class via Reflection.
	 *
	 * Returns the current value of the singleton holder, or false if the property
	 * does not exist on the class.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return mixed|false
	 */
	private function get_singleton_instance( $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			return false;
		}
		$rc = new ReflectionClass( $class_name );
		if ( ! $rc->hasProperty( 'instance' ) ) {
			return false;
		}
		$prop = $rc->getProperty( 'instance' );
		$prop->setAccessible( true );
		return $prop->getValue( null );
	}

	/**
	 * Helper: reset the private static $instance property of a class to null.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	private function reset_singleton_instance( $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			return;
		}
		$rc = new ReflectionClass( $class_name );
		if ( ! $rc->hasProperty( 'instance' ) ) {
			return;
		}
		$prop = $rc->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * On an admin page load, boot_admin() must not instantiate AIPS_Scheduler.
	 *
	 * Closures registered in boot_cron() are only bound when boot_cron() runs;
	 * they never run on admin requests. Therefore AIPS_Scheduler::$instance must
	 * remain null after init() completes in an admin context.
	 */
	public function test_admin_boot_does_not_instantiate_scheduler_singleton() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		// Reset the singleton so a previous test cannot pollute this one.
		$this->reset_singleton_instance( 'AIPS_Scheduler' );

		$GLOBALS['aips_test_is_admin'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Scheduler' ),
			'AIPS_Scheduler::$instance must be null after admin boot — schedulers must only be instantiated when their cron hook fires'
		);
	}

	/**
	 * On a frontend page load, boot_frontend() must not instantiate AIPS_Scheduler.
	 */
	public function test_frontend_boot_does_not_instantiate_scheduler_singleton() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$this->reset_singleton_instance( 'AIPS_Scheduler' );

		// All context globals default to false — frontend context.
		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Scheduler' ),
			'AIPS_Scheduler::$instance must be null after frontend boot — schedulers must not be instantiated on frontend requests'
		);
	}

	/**
	 * On an admin page load, boot_admin() must not instantiate AIPS_Author_Topics_Scheduler.
	 */
	public function test_admin_boot_does_not_instantiate_author_topics_scheduler_singleton() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$this->reset_singleton_instance( 'AIPS_Author_Topics_Scheduler' );

		$GLOBALS['aips_test_is_admin'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Author_Topics_Scheduler' ),
			'AIPS_Author_Topics_Scheduler::$instance must be null after admin boot'
		);
	}

	/**
	 * On an admin page load, boot_admin() must not instantiate AIPS_Author_Post_Generator.
	 */
	public function test_admin_boot_does_not_instantiate_author_post_generator_singleton() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$this->reset_singleton_instance( 'AIPS_Author_Post_Generator' );

		$GLOBALS['aips_test_is_admin'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Author_Post_Generator' ),
			'AIPS_Author_Post_Generator::$instance must be null after admin boot'
		);
	}

	/**
	 * On an admin page load, boot_admin() must not instantiate AIPS_Embeddings_Cron.
	 */
	public function test_admin_boot_does_not_instantiate_embeddings_cron_singleton() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Limited-mode environment required.' );
		}

		$this->reset_singleton_instance( 'AIPS_Embeddings_Cron' );

		$GLOBALS['aips_test_is_admin'] = true;

		$plugin = AI_Post_Scheduler::get_instance();
		$plugin->init();

		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Embeddings_Cron' ),
			'AIPS_Embeddings_Cron::$instance must be null after admin boot'
		);
	}

	/**
	 * Invoke a private boot method on AI_Post_Scheduler via Reflection for full-WP tests.
	 *
	 * @param string $method_name Private method name (e.g. 'boot_common', 'boot_cron').
	 * @return void
	 */
	private function invoke_boot_method( $method_name ) {
		$plugin = AI_Post_Scheduler::get_instance();
		$rm     = new ReflectionMethod( 'AI_Post_Scheduler', $method_name );
		$rm->setAccessible( true );
		$rm->invoke( $plugin );
	}

	/**
	 * Cross-context lazy listeners (notifications, reconciler, affiliate linking,
	 * REST click tracking) must be registered by boot_common() across contexts
	 * without eagerly instantiating AIPS_Notifications or AIPS_Partial_Generation_State_Reconciler.
	 */
	public function test_common_boot_registers_lazy_cross_context_hooks_without_eager_instantiation() {
		$this->reset_singleton_instance( 'AIPS_Notifications' );
		$this->reset_singleton_instance( 'AIPS_Partial_Generation_State_Reconciler' );
		AIPS_Notifications_Event_Handler::clear_registered_hooks();
		AIPS_Partial_Generation_State_Reconciler::clear_registered_hooks();

		if ( isset( $GLOBALS['aips_test_hooks'] ) ) {
			$GLOBALS['aips_test_doing_ajax'] = true;
			AI_Post_Scheduler::get_instance()->init();
		} else {
			$this->invoke_boot_method( 'boot_common' );
		}

		$this->assertGreaterThan( 0, $this->count_action_callbacks( 'aips_generation_failed' ) );
		$this->assertGreaterThan( 0, $this->count_action_callbacks( 'aips_post_components_updated' ) );
		$this->assertGreaterThan( 0, $this->count_action_callbacks( 'aips_post_generated' ) );
		$this->assertGreaterThan( 0, $this->count_action_callbacks( 'rest_api_init' ) );

		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Notifications' ),
			'AIPS_Notifications::$instance must stay null until an event actually fires'
		);
		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Partial_Generation_State_Reconciler' ),
			'AIPS_Partial_Generation_State_Reconciler::$instance must stay null until a post hook fires'
		);
	}

	/**
	 * In a cron context, aips_scheduled_research is registered lazily without
	 * instantiating AIPS_Research_Controller::$instance.
	 */
	public function test_cron_boot_registers_scheduled_research_lazily() {
		$this->reset_singleton_instance( 'AIPS_Research_Controller' );

		if ( isset( $GLOBALS['aips_test_hooks'] ) ) {
			$GLOBALS['aips_test_doing_cron'] = true;
			AI_Post_Scheduler::get_instance()->init();
		} else {
			$this->invoke_boot_method( 'boot_cron' );
		}

		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'aips_scheduled_research' ),
			'boot_cron() must register the aips_scheduled_research hook'
		);
		$this->assertNull(
			$this->get_singleton_instance( 'AIPS_Research_Controller' ),
			'AIPS_Research_Controller::$instance must stay null until aips_scheduled_research fires'
		);
	}

	/**
	 * In an admin context, admin_post_aips_operations_insights_export must be registered.
	 */
	public function test_admin_boot_registers_operations_insights_export_hook() {
		if ( isset( $GLOBALS['aips_test_hooks'] ) ) {
			$GLOBALS['aips_test_is_admin'] = true;
			AI_Post_Scheduler::get_instance()->init();
		} else {
			$this->invoke_boot_method( 'boot_admin' );
		}

		$this->assertGreaterThan(
			0,
			$this->count_action_callbacks( 'admin_post_aips_operations_insights_export' ),
			'boot_admin() must register admin_post_aips_operations_insights_export lazily'
		);
	}

	/**
	 * Unregistered aips_-prefixed AJAX actions must be ignored cleanly rather than
	 * falling back to register_lazy_ajax_hooks().
	 */
	public function test_ajax_boot_ignores_unregistered_aips_prefixed_action() {
		$has_prev = array_key_exists( 'action', $_REQUEST );
		$prev     = $has_prev ? $_REQUEST['action'] : null;

		$count_before = $this->count_action_callbacks( 'wp_ajax_aips_save_template' );

		try {
			$_REQUEST['action'] = 'aips_nonexistent_unknown_action';
			$this->invoke_boot_method( 'boot_ajax' );

			$this->assertSame(
				$count_before,
				$this->count_action_callbacks( 'wp_ajax_aips_save_template' ),
				'boot_ajax() must not fall back to register_lazy_ajax_hooks() for unknown aips_ actions'
			);
		} finally {
			if ( $has_prev ) {
				$_REQUEST['action'] = $prev;
			} else {
				unset( $_REQUEST['action'] );
			}
		}
	}

	/**
	 * When AIPS_Related_Posts_Frontend is constructed after WordPress init has already
	 * fired, it must register aips/related-posts immediately and tolerate repeated construction.
	 */
	public function test_related_posts_frontend_registers_block_when_constructed_after_init() {
		if ( ! function_exists( 'did_action' ) || ! did_action( 'init' ) || ! class_exists( 'WP_Block_Type_Registry' ) ) {
			$this->markTestSkipped( 'Full WordPress environment after init required.' );
		}

		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'aips/related-posts' ) && function_exists( 'unregister_block_type' ) ) {
			unregister_block_type( 'aips/related-posts' );
		}

		new AIPS_Related_Posts_Frontend();
		$this->assertTrue(
			$registry->is_registered( 'aips/related-posts' ),
			'AIPS_Related_Posts_Frontend must register aips/related-posts immediately when constructed after init'
		);

		// A second instantiation must return cleanly without duplicate registration warnings.
		new AIPS_Related_Posts_Frontend();
		$this->assertTrue( $registry->is_registered( 'aips/related-posts' ) );
	}
}
