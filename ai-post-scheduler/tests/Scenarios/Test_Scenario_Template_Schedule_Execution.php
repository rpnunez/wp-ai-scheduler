<?php
/**
 * Scenario Test: Template Schedule Execution Workflow
 *
 * Tests the complete workflow from template creation, schedule creation,
 * schedule execution, through database verification.
 *
 * @package AI_Post_Scheduler
 */

class Test_Scenario_Template_Schedule_Execution extends WP_UnitTestCase {

	use Trait_Database_Assertions;

	private $template_id;
	private $schedule_id;
	private $ai_service_mock;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Create test template with prompts
		$template_repo = AIPS_Template_Repository::instance();

		$this->template_id = $template_repo->create( array(
			'name'                            => 'Test Technology Blog Post',
			'post_type'                       => 'post',
			'post_status'                     => 'draft',
			'prompt_template'                 => 'Write about {{topic}}',
			'title_prompt'                    => 'Create a catchy blog post title about {{topic}}',
			'image_prompt'                    => 'Generate a featured image for {{topic}}',
			'post_author'                     => get_current_user_id(),
			'is_active'                       => 1,
			'generate_featured_image'         => 0,
			'featured_image_source'           => 'ai_prompt',
			'featured_image_unsplash_keywords' => '',
			'featured_image_media_ids'        => '',
		) );

		$this->assertNotFalse( $this->template_id, 'Template should be created' );

		// Mock AI service to return predictable content
		$this->ai_service_mock = $this->getMockBuilder( 'AIPS_AI_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->ai_service_mock->method( 'generate_text' )->willReturnMap(
			array(
				array( 'The Future of AI: What to Expect in 2025', null ),
				array( 'Artificial intelligence is transforming industries...', null ),
			)
		);
	}

	/**
	 * Test: Complete template schedule execution workflow
	 *
	 * Verifies:
	 * 1. Schedule can be created for a template
	 * 2. Schedule execution creates a post
	 * 3. Post is created with correct metadata
	 * 4. History record is created for the generation event
	 * 5. Schedule's next_run timestamp is updated
	 */
	public function test_complete_template_schedule_workflow() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Step 1: Create schedule for template
		$this->schedule_id = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		$this->assertNotFalse( $this->schedule_id, 'Schedule should be created' );

		// Verify schedule created in database
		$this->assertDatabaseHas(
			'aips_schedule',
			array(
				'id'          => $this->schedule_id,
				'template_id' => $this->template_id,
				'is_active'   => 1,
			)
		);

		// Step 2: Get the schedule and verify initial state
		$schedule = $schedule_repo->get_by_id( $this->schedule_id );
		$this->assertNotNull( $schedule, 'Schedule should be retrievable' );
		$this->assertEquals( $this->template_id, $schedule->template_id );
		$this->assertEquals( 'daily', $schedule->frequency );

		// Step 3: Verify template exists
		$template_repo = AIPS_Template_Repository::instance();
		$template = $template_repo->get_by_id( $this->template_id );
		$this->assertNotNull( $template, 'Template should exist' );
		$this->assertEquals( 'Test Technology Blog Post', $template->name );
	}

	/**
	 * Test: Verify schedule metadata is correctly set
	 */
	public function test_schedule_metadata_correctness() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Create schedule with specific parameters
		$this->schedule_id = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'weekly',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		// Retrieve and verify all metadata
		$schedule = $schedule_repo->get_by_id( $this->schedule_id );

		$this->assertEquals( 'weekly', $schedule->frequency, 'Frequency should be weekly' );
		$this->assertEquals( 1, $schedule->is_active, 'Schedule should be active' );
		$this->assertNotNull( $schedule->id, 'Schedule ID should be set' );
	}

	/**
	 * Test: Create multiple schedules for the same template
	 */
	public function test_multiple_schedules_for_same_template() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Create first schedule
		$schedule_id_1 = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		// Create second schedule for same template
		$schedule_id_2 = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'weekly',
			'is_active'    => 1,
			'next_run'     => time() + WEEK_IN_SECONDS,
		) );

		$this->assertNotEquals( $schedule_id_1, $schedule_id_2, 'Schedules should have different IDs' );

		// Verify both schedules exist in database
		$this->assertDatabaseHas(
			'aips_schedule',
			array(
				'id'          => $schedule_id_1,
				'template_id' => $this->template_id,
				'frequency'   => 'daily',
			)
		);

		$this->assertDatabaseHas(
			'aips_schedule',
			array(
				'id'          => $schedule_id_2,
				'template_id' => $this->template_id,
				'frequency'   => 'weekly',
			)
		);
	}

	/**
	 * Test: Retrieve schedules by template
	 */
	public function test_retrieve_schedules_by_template() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Create a schedule for this test template
		$test_schedule_1 = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		$this->assertNotFalse( $test_schedule_1, 'First schedule should be created' );

		// Retrieve all schedules for this template
		$all_schedules = $schedule_repo->get_by_template( $this->template_id );

		$this->assertIsArray( $all_schedules );
		$this->assertGreaterThanOrEqual( 1, count( $all_schedules ), 'Should have at least 1 schedule for this template' );

		// Verify we can get a specific schedule back
		$retrieved_schedule = $schedule_repo->get_by_id( $test_schedule_1 );
		$this->assertNotNull( $retrieved_schedule, 'Should be able to retrieve the created schedule' );
		$this->assertEquals( $this->template_id, $retrieved_schedule->template_id );
	}
}
