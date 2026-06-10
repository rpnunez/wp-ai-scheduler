<?php
/**
 * Tests for AIPS_Schedule_Controller overdue schedules renewal AJAX endpoint.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Renew extends WP_Ajax_UnitTestCase {

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $editor_user_id;

	/** @var int */
	private $template_id;

	/** @var int[] */
	private $created_schedule_ids = array();

	/** @var int[] */
	private $created_author_ids = array();

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->editor_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Create a test template
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'            => 'Test Template for Renew',
				'prompt_template' => 'Write about {{topic}}',
				'is_active'       => 1,
			)
		);
		$this->template_id = (int) $wpdb->insert_id;

		$this->controller = new AIPS_Schedule_Controller();
	}

	public function tearDown(): void {
		global $wpdb;

		// Clean up schedules
		if ( ! empty( $this->created_schedule_ids ) ) {
			$ids_placeholders = implode( ',', array_fill( 0, count( $this->created_schedule_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE id IN ($ids_placeholders)", $this->created_schedule_ids ) );
		}

		// Clean up template
		$wpdb->delete( $wpdb->prefix . 'aips_templates', array( 'id' => $this->template_id ), array( '%d' ) );

		// Clean up authors
		if ( ! empty( $this->created_author_ids ) ) {
			$ids_placeholders = implode( ',', array_fill( 0, count( $this->created_author_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_authors WHERE id IN ($ids_placeholders)", $this->created_author_ids ) );
		}

		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Call a controller method and capture its JSON output.
	 *
	 * @param callable $callable Controller callback.
	 * @return array Decoded JSON response.
	 */
	private function call_ajax( callable $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected when wp_send_json_* is called.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected for early exits.
		}

		$output = ob_get_clean();

		return json_decode( strtok( trim( $output ), "\r\n" ), true );
	}

	public function test_ajax_renew_overdue_schedules_unauthorized_for_non_admins() {
		wp_set_current_user( $this->editor_user_id );

		$_POST = array(
			'nonce' => wp_create_nonce( 'aips_ajax_nonce' ),
		);
		$_REQUEST = $_POST;

		$response = $this->call_ajax( array( $this->controller, 'ajax_renew_overdue_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Unauthorized.', $response['data']['message'] );
	}

	public function test_ajax_renew_overdue_schedules_invalid_nonce() {
		wp_set_current_user( $this->admin_user_id );

		$_POST = array(
			'nonce' => 'invalid-nonce-value',
		);
		$_REQUEST = $_POST;

		$response = $this->call_ajax( array( $this->controller, 'ajax_renew_overdue_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Invalid nonce.', $response['data']['message'] );
	}

	public function test_ajax_renew_overdue_schedules_success() {
		wp_set_current_user( $this->admin_user_id );
		global $wpdb;

		$now = time();
		$one_hour_ago = $now - 3600;
		$two_hours_future = $now + 7200;

		// 1. Insert overdue template schedule (next_run set to 1 hour ago)
		$wpdb->insert(
			$wpdb->prefix . 'aips_schedule',
			array(
				'template_id' => $this->template_id,
				'frequency'   => 'daily',
				'next_run'    => $one_hour_ago,
				'is_active'   => 1,
				'topic'       => 'Overdue Template Topic',
			)
		);
		$overdue_schedule_id = (int) $wpdb->insert_id;
		$this->created_schedule_ids[] = $overdue_schedule_id;

		// 2. Insert future template schedule (next_run set to 2 hours in the future)
		$wpdb->insert(
			$wpdb->prefix . 'aips_schedule',
			array(
				'template_id' => $this->template_id,
				'frequency'   => 'daily',
				'next_run'    => $two_hours_future,
				'is_active'   => 1,
				'topic'       => 'Future Template Topic',
			)
		);
		$future_schedule_id = (int) $wpdb->insert_id;
		$this->created_schedule_ids[] = $future_schedule_id;

		// 3. Insert author with overdue topic generation (topic_generation_next_run set to 1 hour ago)
		$wpdb->insert(
			$wpdb->prefix . 'aips_authors',
			array(
				'name'                          => 'Overdue Topic Author',
				'field_niche'                   => 'Technology',
				'is_active'                     => 1,
				'topic_generation_frequency'    => 'daily',
				'topic_generation_next_run'     => $one_hour_ago,
				'topic_generation_is_active'    => 1,
			)
		);
		$overdue_author_topic_id = (int) $wpdb->insert_id;
		$this->created_author_ids[] = $overdue_author_topic_id;

		// 4. Insert author with overdue post generation (post_generation_next_run set to 1 hour ago)
		$wpdb->insert(
			$wpdb->prefix . 'aips_authors',
			array(
				'name'                         => 'Overdue Post Author',
				'field_niche'                  => 'Science',
				'is_active'                    => 1,
				'post_generation_frequency'   => 'daily',
				'post_generation_next_run'    => $one_hour_ago,
				'post_generation_is_active'   => 1,
			)
		);
		$overdue_author_post_id = (int) $wpdb->insert_id;
		$this->created_author_ids[] = $overdue_author_post_id;

		$_POST = array(
			'nonce' => wp_create_nonce( 'aips_ajax_nonce' ),
		);
		$_REQUEST = $_POST;

		$response = $this->call_ajax( array( $this->controller, 'ajax_renew_overdue_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertGreaterThanOrEqual( 3, $response['data']['renewed_count'] );

		// Assertions: Overdue schedule updated to future
		$updated_overdue_sched = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $overdue_schedule_id ) );
		$this->assertGreaterThan( $now, (int) $updated_overdue_sched->next_run );

		// Assertions: Future schedule is unchanged
		$updated_future_sched = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $future_schedule_id ) );
		$this->assertEquals( $two_hours_future, (int) $updated_future_sched->next_run );

		// Assertions: Overdue author topic generation updated to future
		$updated_author_topic = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aips_authors WHERE id = %d", $overdue_author_topic_id ) );
		$this->assertGreaterThan( $now, (int) $updated_author_topic->topic_generation_next_run );

		// Assertions: Overdue author post generation updated to future
		$updated_author_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aips_authors WHERE id = %d", $overdue_author_post_id ) );
		$this->assertGreaterThan( $now, (int) $updated_author_post->post_generation_next_run );
	}
}
