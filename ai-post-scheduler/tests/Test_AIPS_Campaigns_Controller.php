<?php
/**
 * Tests for AIPS_Campaigns_Controller mutation error handling.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Campaigns_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);

		$_POST = array();
		$_GET = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	public function test_ajax_duplicate_campaign_forwards_wp_error_message_and_code() {
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('duplicate_campaign'))
			->getMock();
		$repository->method('duplicate_campaign')->willReturn(
			new WP_Error('campaign_duplicate_failed', 'Campaign could not be duplicated.')
		);

		$controller = new AIPS_Campaigns_Controller($repository);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'campaign_id' => 55,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_duplicate_campaign'));

		$this->assertFalse($response['success']);
		$this->assertSame('campaign_duplicate_failed', $response['data']['code']);
		$this->assertSame('Campaign could not be duplicated.', $response['data']['message']);
	}

	public function test_ajax_toggle_campaign_forwards_wp_error_message_and_code() {
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('set_active'))
			->getMock();
		$repository->method('set_active')->willReturn(
			new WP_Error('campaign_update_failed', 'Campaign could not be updated.')
		);

		$controller = new AIPS_Campaigns_Controller($repository);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'campaign_id' => 41,
			'is_active' => 0,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_toggle_campaign'));

		$this->assertFalse($response['success']);
		$this->assertSame('campaign_update_failed', $response['data']['code']);
		$this->assertSame('Campaign could not be updated.', $response['data']['message']);
	}

	public function test_ajax_delete_campaign_forwards_delete_blocked_error() {
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('can_delete_campaign', 'delete_campaign'))
			->getMock();
		$repository->method('can_delete_campaign')->willReturn(true);
		$repository->method('delete_campaign')->willReturn(
			new WP_Error('delete_blocked', 'This campaign has generated posts and can only be archived.')
		);

		$controller = new AIPS_Campaigns_Controller($repository);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'campaign_id' => 77,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_delete_campaign'));

		$this->assertFalse($response['success']);
		$this->assertSame('delete_blocked', $response['data']['code']);
		$this->assertSame('This campaign has generated posts and can only be archived.', $response['data']['message']);
	}

	public function test_ajax_archive_campaign_forwards_wp_error_message_and_code() {
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('archive_campaign'))
			->getMock();
		$repository->method('archive_campaign')->willReturn(
			new WP_Error('campaign_update_failed', 'Campaign could not be archived.')
		);

		$controller = new AIPS_Campaigns_Controller($repository);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'campaign_id' => 82,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_archive_campaign'));

		$this->assertFalse($response['success']);
		$this->assertSame('campaign_update_failed', $response['data']['code']);
		$this->assertSame('Campaign could not be archived.', $response['data']['message']);
	}

	public function test_ajax_restore_campaign_forwards_wp_error_message_and_code() {
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('restore_campaign'))
			->getMock();
		$repository->method('restore_campaign')->willReturn(
			new WP_Error('campaign_update_failed', 'Campaign could not be restored.')
		);

		$controller = new AIPS_Campaigns_Controller($repository);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'campaign_id' => 93,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_restore_campaign'));

		$this->assertFalse($response['success']);
		$this->assertSame('campaign_update_failed', $response['data']['code']);
		$this->assertSame('Campaign could not be restored.', $response['data']['message']);
	}

	public function test_ajax_finalize_campaign_forwards_repository_wp_error_without_try_catch_contract() {
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('create_campaign_bundle'))
			->getMock();
		$repository->method('create_campaign_bundle')->willReturn(
			new WP_Error('campaign_create_failed', 'Campaign could not be saved.')
		);

		$template_repository = $this->getMockBuilder('AIPS_Template_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_all'))
			->getMock();
		$template_repository->method('get_all')->willReturn(array());

		$controller = new AIPS_Campaigns_Controller($repository, $template_repository);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'payload' => wp_json_encode(array(
				'campaign_name' => 'Campaign Alpha',
				'content_goal' => 'Goal',
				'campaign_mode' => 'template',
				'template_mode' => 'custom',
				'prompt_template' => 'Prompt body',
				'frequency' => 'daily',
				'start_time' => '2026-06-01T09:00',
			)),
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_finalize_campaign'));

		$this->assertFalse($response['success']);
		$this->assertSame('campaign_create_failed', $response['data']['code']);
		$this->assertSame('Campaign could not be saved.', $response['data']['message']);
	}

	public function test_render_detail_page_shows_inline_error_notice_when_mutation_returns_wp_error() {
		$campaign = $this->get_campaign_object();
		$repository = $this->getMockBuilder('AIPS_Campaigns_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_campaign_by_id', 'update_campaign', 'get_templates_by_campaign', 'get_schedules_by_campaign', 'get_campaign_health', 'get_recent_activity', 'get_recent_generated_posts'))
			->getMock();
		$repository->method('get_campaign_by_id')->willReturn($campaign);
		$repository->method('update_campaign')->willReturn(new WP_Error('campaign_update_failed', 'Campaign could not be updated.'));
		$repository->method('get_templates_by_campaign')->willReturn(array());
		$repository->method('get_schedules_by_campaign')->willReturn(array());
		$repository->method('get_campaign_health')->willReturn($this->get_campaign_health());
		$repository->method('get_recent_activity')->willReturn(array());
		$repository->method('get_recent_generated_posts')->willReturn(array());

		$controller = new AIPS_Campaigns_Controller($repository);

		$_GET = array('campaign_id' => 14);
		$_POST = array(
			'aips_campaign_detail_nonce' => wp_create_nonce('aips_campaign_detail_save_14'),
			'detail_action' => 'save',
			'campaign_name' => 'Renamed Campaign',
			'content_goal' => 'Updated goal',
		);

		ob_start();
		$controller->render_detail_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('Campaign could not be updated.', $output);
		$this->assertStringNotContainsString('Campaign updated.', $output);
	}

	private function capture_ajax(callable $callable) {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}

		return json_decode(ob_get_clean(), true);
	}

	private function get_campaign_object() {
		return (object) array(
			'id' => 14,
			'name' => 'Campaign Alpha',
			'content_goal' => 'Goal',
			'campaign_mode' => 'template',
			'is_active' => 1,
			'is_archived' => 0,
			'next_run' => 0,
			'last_run' => 0,
			'linked_schedule_count' => 0,
			'primary_schedule_id' => 0,
		);
	}

	private function get_campaign_health() {
		return array(
			'failed_generation_count' => 0,
			'pending_review_count' => 0,
			'inactive_schedule_count' => 0,
			'empty_template_prompt_count' => 0,
			'has_future_run' => 0,
			'failed_last_run' => 0,
		);
	}
}
