<?php
/**
 * Tests for scheduler cron hook registration.
 *
 * Verifies that cron hooks (aips_generate_*) and the cron_schedules filter are
 * not registered as a side effect of instantiation, and that the bootstrap
 * registration pattern produces exactly one callback per hook.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Scheduler_Hook_Registration extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Ensure a clean action-hook state at the start of each test in limited mode.
		if ( isset( $GLOBALS['aips_test_hooks'] ) ) {
			$GLOBALS['aips_test_hooks']['actions'] = array();
			$GLOBALS['aips_test_hooks']['filters'] = array();
		}
	}

	/**
	 * Count all callbacks registered for an action hook.
	 *
	 * Works in both the full WordPress test environment (via $wp_filter) and the
	 * limited-mode environment (via $GLOBALS['aips_test_hooks']).
	 *
	 * @param string $hook The action hook name.
	 * @return int
	 */
	private function count_action_callbacks( $hook ) {
		global $wp_filter;
		if ( isset( $wp_filter[ $hook ] ) && is_object( $wp_filter[ $hook ] ) && isset( $wp_filter[ $hook ]->callbacks ) ) {
			$count = 0;
			foreach ( $wp_filter[ $hook ]->callbacks as $priority_callbacks ) {
				$count += count( $priority_callbacks );
			}
			return $count;
		}

		if ( isset( $GLOBALS['aips_test_hooks']['actions'][ $hook ] ) ) {
			$count = 0;
			foreach ( $GLOBALS['aips_test_hooks']['actions'][ $hook ] as $priority_callbacks ) {
				$count += count( $priority_callbacks );
			}
			return $count;
		}

		return 0;
	}

	/**
	 * Count all callbacks registered for a filter hook.
	 *
	 * @param string $hook The filter hook name.
	 * @return int
	 */
	private function count_filter_callbacks( $hook ) {
		global $wp_filter;
		if ( isset( $wp_filter[ $hook ] ) && is_object( $wp_filter[ $hook ] ) && isset( $wp_filter[ $hook ]->callbacks ) ) {
			$count = 0;
			foreach ( $wp_filter[ $hook ]->callbacks as $priority_callbacks ) {
				$count += count( $priority_callbacks );
			}
			return $count;
		}

		if ( isset( $GLOBALS['aips_test_hooks']['filters'][ $hook ] ) ) {
			$count = 0;
			foreach ( $GLOBALS['aips_test_hooks']['filters'][ $hook ] as $priority_callbacks ) {
				$count += count( $priority_callbacks );
			}
			return $count;
		}

		return 0;
	}

	// -------------------------------------------------------------------------
	// Constructor must not register any cron hook
	// -------------------------------------------------------------------------

	/**
	 * AIPS_Scheduler constructor must not register aips_generate_scheduled_posts.
	 */
	public function test_scheduler_constructor_does_not_register_cron_action() {
		$count_before = $this->count_action_callbacks( 'aips_generate_scheduled_posts' );
		new AIPS_Scheduler();
		$this->assertSame(
			$count_before,
			$this->count_action_callbacks( 'aips_generate_scheduled_posts' ),
			'AIPS_Scheduler constructor must not register aips_generate_scheduled_posts'
		);
	}

	/**
	 * AIPS_Scheduler constructor must not register the cron_schedules filter.
	 */
	public function test_scheduler_constructor_does_not_register_cron_schedules_filter() {
		$count_before = $this->count_filter_callbacks( 'cron_schedules' );
		new AIPS_Scheduler();
		$this->assertSame(
			$count_before,
			$this->count_filter_callbacks( 'cron_schedules' ),
			'AIPS_Scheduler constructor must not register cron_schedules'
		);
	}

	/**
	 * AIPS_Author_Topics_Scheduler constructor must not register aips_generate_author_topics.
	 */
	public function test_author_topics_scheduler_constructor_does_not_register_cron_action() {
		$count_before = $this->count_action_callbacks( 'aips_generate_author_topics' );
		new AIPS_Author_Topics_Scheduler();
		$this->assertSame(
			$count_before,
			$this->count_action_callbacks( 'aips_generate_author_topics' ),
			'AIPS_Author_Topics_Scheduler constructor must not register aips_generate_author_topics'
		);
	}

	/**
	 * AIPS_Author_Post_Generator constructor must not register aips_generate_author_posts.
	 */
	public function test_author_post_generator_constructor_does_not_register_cron_action() {
		$count_before = $this->count_action_callbacks( 'aips_generate_author_posts' );
		new AIPS_Author_Post_Generator();
		$this->assertSame(
			$count_before,
			$this->count_action_callbacks( 'aips_generate_author_posts' ),
			'AIPS_Author_Post_Generator constructor must not register aips_generate_author_posts'
		);
	}

	// -------------------------------------------------------------------------
	// Bootstrap pattern: exactly one closure callback per hook; additional class
	// instantiations must not stack more callbacks.
	// These tests are limited-mode only because in full WP mode the plugin
	// bootstrap has already registered the hooks once before the test runs.
	// -------------------------------------------------------------------------

	/**
	 * The closure-based bootstrap must register exactly one callback on
	 * aips_generate_scheduled_posts, and additional AIPS_Scheduler instantiations
	 * must not stack further callbacks.
	 */
	public function test_multiple_scheduler_instantiations_do_not_stack_cron_action() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Hook-stacking test requires the limited-mode environment.' );
		}

		// Simulate the closure-based bootstrap introduced in Phase B.4.
		add_action( 'aips_generate_scheduled_posts', function() {
			AIPS_Scheduler::instance()->process();
		} );
		$this->assertSame( 1, $this->count_action_callbacks( 'aips_generate_scheduled_posts' ) );

		// Additional instantiations (e.g. from AIPS_Schedule_Controller) must not
		// add more callbacks because constructors do not register hooks.
		new AIPS_Scheduler();
		new AIPS_Scheduler();

		$this->assertSame(
			1,
			$this->count_action_callbacks( 'aips_generate_scheduled_posts' ),
			'aips_generate_scheduled_posts must have exactly one callback regardless of how many AIPS_Scheduler instances are created'
		);
	}

	/**
	 * The closure-based bootstrap must register exactly one callback on
	 * cron_schedules, and additional AIPS_Scheduler instantiations must not
	 * stack further callbacks.
	 */
	public function test_multiple_scheduler_instantiations_do_not_stack_cron_schedules_filter() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Hook-stacking test requires the limited-mode environment.' );
		}

		// Simulate the closure-based bootstrap.
		add_filter( 'cron_schedules', function( $schedules ) {
			return AIPS_Scheduler::instance()->add_cron_intervals( $schedules );
		} );
		$this->assertSame( 1, $this->count_filter_callbacks( 'cron_schedules' ) );

		new AIPS_Scheduler();
		new AIPS_Scheduler();

		$this->assertSame(
			1,
			$this->count_filter_callbacks( 'cron_schedules' ),
			'cron_schedules must have exactly one callback regardless of how many AIPS_Scheduler instances are created'
		);
	}

	/**
	 * The closure-based bootstrap must register exactly one callback on
	 * aips_generate_author_topics, and additional AIPS_Author_Topics_Scheduler
	 * instantiations must not stack further callbacks.
	 */
	public function test_multiple_author_topics_scheduler_instantiations_do_not_stack_callbacks() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Hook-stacking test requires the limited-mode environment.' );
		}

		// Simulate the closure-based bootstrap.
		add_action( 'aips_generate_author_topics', function() {
			AIPS_Author_Topics_Scheduler::instance()->process_topic_generation();
		} );
		$this->assertSame( 1, $this->count_action_callbacks( 'aips_generate_author_topics' ) );

		new AIPS_Author_Topics_Scheduler();
		new AIPS_Author_Topics_Scheduler();

		$this->assertSame(
			1,
			$this->count_action_callbacks( 'aips_generate_author_topics' ),
			'aips_generate_author_topics must have exactly one callback regardless of how many AIPS_Author_Topics_Scheduler instances are created'
		);
	}

	/**
	 * The closure-based bootstrap must register exactly one callback on
	 * aips_generate_author_posts, and additional AIPS_Author_Post_Generator
	 * instantiations must not stack further callbacks.
	 */
	public function test_multiple_author_post_generator_instantiations_do_not_stack_callbacks() {
		if ( ! isset( $GLOBALS['aips_test_hooks'] ) ) {
			$this->markTestSkipped( 'Hook-stacking test requires the limited-mode environment.' );
		}

		// Simulate the closure-based bootstrap.
		add_action( 'aips_generate_author_posts', function() {
			AIPS_Author_Post_Generator::instance()->process();
		} );
		$this->assertSame( 1, $this->count_action_callbacks( 'aips_generate_author_posts' ) );

		new AIPS_Author_Post_Generator();
		new AIPS_Author_Post_Generator();

		$this->assertSame(
			1,
			$this->count_action_callbacks( 'aips_generate_author_posts' ),
			'aips_generate_author_posts must have exactly one callback regardless of how many AIPS_Author_Post_Generator instances are created'
		);
	}
}
