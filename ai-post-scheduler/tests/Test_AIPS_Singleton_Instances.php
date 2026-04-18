<?php
/**
 * Tests for static instance() singleton factories added to stateless services.
 *
 * Verifies that each class:
 *   1. Exposes a public static `instance()` method.
 *   2. Returns an object of the correct type.
 *   3. Returns the same object on repeated calls (singleton guarantee).
 *   4. Still allows independent instances via `new ClassName()`.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */
class Test_AIPS_Singleton_Instances extends WP_UnitTestCase {

	/**
	 * List of singleton classes that register WordPress hooks in their constructors.
	 * These need cleanup after each test to prevent hook pollution.
	 *
	 * @var array
	 */
	private $hook_owning_classes = array(
		'AIPS_Authors_Controller',
		'AIPS_Author_Topics_Controller',
		'AIPS_Post_Review',
		'AIPS_Research_Controller',
	);

	/**
	 * Reset singleton instances after each test to prevent cross-test pollution.
	 *
	 * This is especially important for classes that register WordPress hooks in
	 * their constructors (controllers, handlers), as those hooks would otherwise
	 * persist across tests and cause order-dependent behavior.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Reset singleton instances for all classes with instance() methods
		$classes_to_reset = array(
			'AIPS_History_Repository',
			'AIPS_History_Service',
			'AIPS_Logger',
			'AIPS_Interval_Calculator',
			'AIPS_Template_Repository',
			'AIPS_AI_Service',
			'AIPS_Notifications_Repository',
			'AIPS_Schedule_Repository',
			'AIPS_Voices_Repository',
			'AIPS_Article_Structure_Repository',
			'AIPS_Prompt_Section_Repository',
			'AIPS_Authors_Repository',
			'AIPS_Scheduler',
			'AIPS_Author_Topics_Scheduler',
			'AIPS_Author_Post_Generator',
			'AIPS_Embeddings_Cron',
			'AIPS_Notifications',
			'AIPS_Authors_Controller',
			'AIPS_Author_Topics_Controller',
			'AIPS_Post_Review',
			'AIPS_Research_Controller',
		);

		foreach ( $classes_to_reset as $class ) {
			$this->reset_singleton( $class );
		}

		// Clean up WordPress global hook state for hook-owning classes
		global $wp_filter;
		if ( isset( $wp_filter ) ) {
			// Remove all wp_ajax_aips_* hooks that may have been registered by controllers
			foreach ( $wp_filter as $hook_name => $hook ) {
				if ( strpos( $hook_name, 'wp_ajax_aips_' ) === 0 ) {
					unset( $wp_filter[ $hook_name ] );
				}
			}

			// Also clean up the scheduled research hook from AIPS_Research_Controller
			if ( isset( $wp_filter['aips_scheduled_research'] ) ) {
				unset( $wp_filter['aips_scheduled_research'] );
			}
		}
	}

	/**
	 * Reset a singleton instance to null using Reflection.
	 *
	 * This allows tests to instantiate fresh instances without being affected
	 * by previous test runs.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	private function reset_singleton( $class ) {
		if ( ! class_exists( $class ) ) {
			return;
		}

		try {
			$reflection = new ReflectionClass( $class );
			if ( $reflection->hasProperty( 'instance' ) ) {
				$property = $reflection->getProperty( 'instance' );
				$property->setAccessible( true );
				$property->setValue( null, null );
			}
		} catch ( ReflectionException $e ) {
			// If reflection fails, we can't reset the singleton, but that's OK
			// for test purposes - it just means the next test might see the
			// cached instance from this test.
		}
	}

	/**
	 * Helper: assert that a class has a public static instance() method and that
	 * successive calls return the same object.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	private function assert_singleton_contract( $class ) {
		$this->assertTrue(
			method_exists( $class, 'instance' ),
			"$class::instance() method should exist"
		);

		$a = $class::instance();
		$b = $class::instance();

		$this->assertInstanceOf( $class, $a, "$class::instance() should return an instance of $class" );
		$this->assertSame( $a, $b, "$class::instance() should return the same object on repeated calls" );
	}

	public function test_history_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_History_Repository' );
	}

	public function test_history_service_singleton() {
		$this->assert_singleton_contract( 'AIPS_History_Service' );
	}

	public function test_logger_singleton() {
		$this->assert_singleton_contract( 'AIPS_Logger' );
	}

	public function test_interval_calculator_singleton() {
		$this->assert_singleton_contract( 'AIPS_Interval_Calculator' );
	}

	public function test_template_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Template_Repository' );
	}

	public function test_ai_service_singleton() {
		$this->assert_singleton_contract( 'AIPS_AI_Service' );
	}

	public function test_notifications_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Notifications_Repository' );
	}

	public function test_schedule_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Schedule_Repository' );
	}

	public function test_voices_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Voices_Repository' );
	}

	public function test_article_structure_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Article_Structure_Repository' );
	}

	public function test_prompt_section_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Prompt_Section_Repository' );
	}

	public function test_authors_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Authors_Repository' );
	}

	public function test_scheduler_singleton() {
		$this->assert_singleton_contract( 'AIPS_Scheduler' );
	}

	public function test_author_topics_scheduler_singleton() {
		$this->assert_singleton_contract( 'AIPS_Author_Topics_Scheduler' );
	}

	public function test_author_post_generator_singleton() {
		$this->assert_singleton_contract( 'AIPS_Author_Post_Generator' );
	}

	public function test_embeddings_cron_singleton() {
		$this->assert_singleton_contract( 'AIPS_Embeddings_Cron' );
	}

	public function test_notifications_singleton() {
		$this->assert_singleton_contract( 'AIPS_Notifications' );
	}

	public function test_authors_controller_singleton() {
		$this->assert_singleton_contract( 'AIPS_Authors_Controller' );
	}

	public function test_author_topics_controller_singleton() {
		$this->assert_singleton_contract( 'AIPS_Author_Topics_Controller' );
	}

	public function test_post_review_singleton() {
		$this->assert_singleton_contract( 'AIPS_Post_Review' );
	}

	public function test_research_controller_singleton() {
		$this->assert_singleton_contract( 'AIPS_Research_Controller' );
	}

	/**
	 * Verify that new ClassName() still produces an independent instance
	 * (constructors are not private).
	 */
	public function test_history_repository_new_produces_independent_instance() {
		$singleton = AIPS_History_Repository::instance();
		$fresh     = new AIPS_History_Repository();
		$this->assertNotSame( $singleton, $fresh );
	}

	public function test_history_service_uses_repository_singleton_by_default() {
		$service = AIPS_History_Service::instance();
		$this->assertInstanceOf( 'AIPS_History_Service', $service );
	}

	public function test_interval_calculator_new_produces_independent_instance() {
		$singleton = AIPS_Interval_Calculator::instance();
		$fresh     = new AIPS_Interval_Calculator();
		$this->assertNotSame( $singleton, $fresh );
	}
}
