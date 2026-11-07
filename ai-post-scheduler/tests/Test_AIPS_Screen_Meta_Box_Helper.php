<?php
/**
 * Unit Tests for AIPS_Screen_Meta_Box_Helper and Dashboard Meta Boxes
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Screen_Meta_Box_Helper extends WP_UnitTestCase {

	/**
	 * Test init_screen execution.
	 */
	public function test_init_screen() {
		AIPS_Screen_Meta_Box_Helper::init_screen('test_screen_1');
		$this->assertTrue(true);
	}

	/**
	 * Test registering a meta box through helper.
	 */
	public function test_register_box() {
		global $wp_meta_boxes;

		$screen_id = 'test_screen_meta_box';
		AIPS_Screen_Meta_Box_Helper::register_box(
			'test_box_id',
			'Test Box Title',
			function() {
				echo '<p>Test Box Body</p>';
			},
			$screen_id,
			'normal',
			'high'
		);

		$this->assertNotEmpty($wp_meta_boxes[$screen_id]['normal']['high']['test_box_id']);
		$this->assertSame('Test Box Title', $wp_meta_boxes[$screen_id]['normal']['high']['test_box_id']['title']);
	}

	/**
	 * Test render_layout outputs standard 2-column #poststuff container with nonces.
	 */
	public function test_render_layout_two_columns() {
		$screen_id = 'test_screen_render';

		ob_start();
		AIPS_Screen_Meta_Box_Helper::render_layout($screen_id, array(), 2);
		$output = ob_get_clean();

		$this->assertStringContainsString('id="poststuff"', $output);
		$this->assertStringContainsString('columns-2', $output);
		$this->assertStringContainsString('id="postbox-container-1"', $output);
		$this->assertStringContainsString('id="postbox-container-2"', $output);
		$this->assertStringContainsString('closedpostboxesnonce', $output);
		$this->assertStringContainsString('meta-box-order-nonce', $output);
	}

	/**
	 * Test render_layout outputs 1-column layout when specified.
	 */
	public function test_render_layout_single_column() {
		$screen_id = 'test_screen_render_single';

		ob_start();
		AIPS_Screen_Meta_Box_Helper::render_layout($screen_id, array(), 1);
		$output = ob_get_clean();

		$this->assertStringContainsString('columns-1', $output);
		$this->assertStringNotContainsString('id="postbox-container-1"', $output);
		$this->assertStringContainsString('id="postbox-container-2"', $output);
	}

	/**
	 * Test render_isolated_box outputs standard postbox card markup.
	 */
	public function test_render_isolated_box() {
		ob_start();
		AIPS_Screen_Meta_Box_Helper::render_isolated_box(
			'isolated-box-test',
			'Isolated Title',
			'<div class="custom-isolated-content">Sample isolated content</div>'
		);
		$output = ob_get_clean();

		$this->assertStringContainsString('postbox', $output);
		$this->assertStringContainsString('Isolated Title', $output);
		$this->assertStringContainsString('custom-isolated-content', $output);
	}

	/**
	 * Test AIPS_Dashboard_Controller registers all 5 native meta boxes.
	 */
	public function test_dashboard_controller_registers_meta_boxes() {
		global $wp_meta_boxes;

		$screen_id = 'toplevel_page_ai-post-scheduler';
		AIPS_Dashboard_Controller::register_meta_boxes($screen_id);

		$this->assertArrayHasKey($screen_id, (array) $wp_meta_boxes);
		$this->assertArrayHasKey('aips-dashboard-overview-box', $wp_meta_boxes[$screen_id]['normal']['high']);
		$this->assertArrayHasKey('aips-dashboard-activity-box', $wp_meta_boxes[$screen_id]['normal']['default']);
		$this->assertArrayHasKey('aips-dashboard-charts-box', $wp_meta_boxes[$screen_id]['normal']['default']);
		$this->assertArrayHasKey('aips-dashboard-upcoming-box', $wp_meta_boxes[$screen_id]['side']['high']);
		$this->assertArrayHasKey('aips-dashboard-status-box', $wp_meta_boxes[$screen_id]['side']['default']);
	}

	/**
	 * Test Dashboard meta box callback renders.
	 */
	public function test_dashboard_meta_box_callbacks() {
		$mock_data = array(
			'ai_provider_available'   => true,
			'completed_in_period'     => 15,
			'success_rate_in_period'  => 93.3,
			'failed_in_period'        => 1,
			'partial_in_period'       => 0,
			'ai_calls_in_period'      => 45,
			'ai_error_rate_in_period' => 2.2,
			'ai_errors_in_period'     => 1,
			'topics_created_in_period'=> 8,
			'topics_pending_in_period'=> 3,
			'recent_posts'            => array(),
			'recent_topics'           => array(),
			'posts_by_topic'          => array(),
			'executed_schedules'      => array(),
			'chart_data'              => array(),
			'upcoming_runs_count'     => 4,
			'upcoming_5'              => array(),
			'topics_in_queue'         => 7,
			'partial_generations'     => 0,
		);

		// Overview Box
		ob_start();
		AIPS_Dashboard_Controller::render_overview_meta_box($mock_data);
		$overview_output = ob_get_clean();
		$this->assertStringContainsString('Posts Completed', $overview_output);
		$this->assertStringContainsString('15', $overview_output);

		// Activity Box
		ob_start();
		AIPS_Dashboard_Controller::render_activity_meta_box($mock_data);
		$activity_output = ob_get_clean();
		$this->assertStringContainsString('detail-tabs-panel', $activity_output);
		$this->assertStringContainsString('tab-posts', $activity_output);

		// Charts Box
		ob_start();
		AIPS_Dashboard_Controller::render_charts_meta_box($mock_data);
		$charts_output = ob_get_clean();
		$this->assertStringContainsString('aips-chart-ai-calls', $charts_output);
		$this->assertStringContainsString('aips-chart-posts-by-day', $charts_output);

		// Upcoming Box
		ob_start();
		AIPS_Dashboard_Controller::render_upcoming_meta_box($mock_data);
		$upcoming_output = ob_get_clean();
		$this->assertStringContainsString('aips-outlook-content', $upcoming_output);
		$this->assertStringContainsString('4 Scheduled Runs', $upcoming_output);

		// Status Box
		ob_start();
		AIPS_Dashboard_Controller::render_status_meta_box($mock_data);
		$status_output = ob_get_clean();
		$this->assertStringContainsString('AI Provider', $status_output);
		$this->assertStringContainsString('Connected', $status_output);
		$this->assertStringContainsString('Topics in Queue', $status_output);
	}
}
