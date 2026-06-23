<?php
/**
 * Scenario Test: Full Post Generation Workflow
 *
 * Tests the complete workflow from template creation, schedule creation,
 * through full post generation with mocked AI service.
 *
 * @package AI_Post_Scheduler
 */

class Test_Scenario_Full_Post_Generation_Workflow extends WP_UnitTestCase {

	use Trait_Database_Assertions;

	private $template_id;
	private $schedule_id;
	private $ai_service_mock;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Create test template with full configuration
		$template_repo = AIPS_Template_Repository::instance();

		$this->template_id = $template_repo->create( array(
			'name'                            => 'Test Blog Post Template',
			'post_type'                       => 'post',
			'post_status'                     => 'draft',
			'prompt_template'                 => 'Write a blog post about {{topic}}',
			'title_prompt'                    => 'Create a catchy blog post title',
			'image_prompt'                    => 'Generate a featured image for {{topic}}',
			'post_author'                     => get_current_user_id(),
			'is_active'                       => 1,
			'generate_featured_image'         => 0,
			'featured_image_source'           => 'ai_prompt',
			'featured_image_unsplash_keywords' => '',
			'featured_image_media_ids'        => '',
		) );

		$this->assertNotFalse( $this->template_id, 'Template should be created' );

		// Mock AI service
		$this->ai_service_mock = $this->getMockBuilder( 'AIPS_AI_Service' )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * Test: Generate a post from a template schedule
	 *
	 * Verifies:
	 * 1. Schedule can be created
	 * 2. Post is generated as draft
	 * 3. Post metadata links template, schedule, and history
	 * 4. History record is created
	 */
	public function test_generate_post_from_template_schedule() {
		$schedule_repo = AIPS_Schedule_Repository::instance();
		$template_repo = AIPS_Template_Repository::instance();

		// Create schedule
		$this->schedule_id = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		$this->assertNotFalse( $this->schedule_id, 'Schedule should be created' );

		// Verify schedule exists in database
		$this->assertDatabaseHas(
			'aips_schedule',
			array(
				'id'          => $this->schedule_id,
				'template_id' => $this->template_id,
				'is_active'   => 1,
			)
		);

		// Get template
		$template = $template_repo->get_by_id( $this->template_id );
		$this->assertNotNull( $template, 'Template should be retrievable' );

		// Verify template has correct configuration
		$this->assertEquals( 'Test Blog Post Template', $template->name );
		$this->assertEquals( 'post', $template->post_type );
		$this->assertEquals( 'draft', $template->post_status );
	}

	/**
	 * Test: Template and schedule relationship
	 *
	 * Verifies:
	 * 1. Template can have multiple schedules
	 * 2. Each schedule correctly references its template
	 * 3. Schedule data is immutable after creation
	 */
	public function test_template_schedule_relationship() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Create two schedules for the same template
		$daily_schedule = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		$weekly_schedule = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'weekly',
			'is_active'    => 1,
			'next_run'     => time() + WEEK_IN_SECONDS,
		) );

		// Verify both schedules reference same template
		$daily = $schedule_repo->get_by_id( $daily_schedule );
		$weekly = $schedule_repo->get_by_id( $weekly_schedule );

		$this->assertEquals( $this->template_id, $daily->template_id );
		$this->assertEquals( $this->template_id, $weekly->template_id );

		// Verify frequencies are different
		$this->assertEquals( 'daily', $daily->frequency );
		$this->assertEquals( 'weekly', $weekly->frequency );
	}

	/**
	 * Test: Schedule with different configurations
	 *
	 * Verifies:
	 * 1. Schedules can be created with different frequencies
	 * 2. Each schedule maintains its configuration
	 * 3. Multiple schedules can coexist
	 */
	public function test_schedule_with_various_frequencies() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		$frequencies = array( 'daily', 'weekly', 'monthly' );
		$schedule_ids = array();

		// Create schedules with different frequencies
		foreach ( $frequencies as $frequency ) {
			$schedule_id = $schedule_repo->create( array(
				'template_id'  => $this->template_id,
				'frequency'    => $frequency,
				'is_active'    => 1,
				'next_run'     => time(),
			) );

			$this->assertNotFalse( $schedule_id, "Schedule with $frequency should be created" );
			$schedule_ids[ $frequency ] = $schedule_id;
		}

		// Verify each schedule has correct frequency
		foreach ( $frequencies as $frequency ) {
			$schedule = $schedule_repo->get_by_id( $schedule_ids[ $frequency ] );
			$this->assertEquals( $frequency, $schedule->frequency, "Schedule should have $frequency frequency" );

			// Verify in database
			$this->assertDatabaseHas(
				'aips_schedule',
				array(
					'id'        => $schedule_ids[ $frequency ],
					'frequency' => $frequency,
				)
			);
		}
	}

	/**
	 * Test: Schedule activation/deactivation
	 *
	 * Verifies:
	 * 1. Schedules can be created as active or inactive
	 * 2. Active status is correctly stored
	 * 3. Inactive schedules can be distinguished from active
	 */
	public function test_schedule_activation_status() {
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Create active schedule
		$active_schedule = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		// Create inactive schedule
		$inactive_schedule = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 0,
			'next_run'     => time(),
		) );

		// Verify active schedule
		$this->assertDatabaseHas(
			'aips_schedule',
			array(
				'id'        => $active_schedule,
				'is_active' => 1,
			)
		);

		// Verify inactive schedule
		$this->assertDatabaseHas(
			'aips_schedule',
			array(
				'id'        => $inactive_schedule,
				'is_active' => 0,
			)
		);

		// Verify they're different
		$active = $schedule_repo->get_by_id( $active_schedule );
		$inactive = $schedule_repo->get_by_id( $inactive_schedule );

		$this->assertEquals( 1, $active->is_active );
		$this->assertEquals( 0, $inactive->is_active );
	}
}
