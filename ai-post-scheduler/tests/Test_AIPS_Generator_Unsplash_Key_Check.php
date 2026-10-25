<?php
/**
 * Tests for Unsplash API key check in AIPS_Generator.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generator_Unsplash_Key_Check extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option('aips_unsplash_access_key');
	}

	public function tearDown(): void {
		delete_option('aips_unsplash_access_key');
		parent::tearDown();
	}

	public function test_unsplash_image_skipped_when_api_key_missing() {
		delete_option('aips_unsplash_access_key');

		$image_service = $this->createMock('AIPS_Image_Service');
		$image_service->method('has_unsplash_access_key')->willReturn(false);
		$image_service->expects($this->never())->method('fetch_and_upload_unsplash_image');

		$logger = $this->createMock('AIPS_Logger_Interface');
		$logged_messages = array();
		$logger->method('log')->willReturnCallback(function($msg, $level) use (&$logged_messages) {
			$logged_messages[] = array('msg' => $msg, 'level' => $level);
		});

		$history_service = $this->createMock('AIPS_History_Service_Interface');
		$history_container = $this->createMock('AIPS_History_Container');
		$recorded_events = array();
		$history_container->method('record')->willReturnCallback(function($event_type, $message, $input = null, $output = null, $meta = array()) use (&$recorded_events) {
			$recorded_events[] = array(
				'type'    => $event_type,
				'message' => $message,
				'input'   => $input,
				'output'  => $output,
				'meta'    => $meta,
			);
			return true;
		});

		$generator = new AIPS_Generator(
			$logger,
			null, // ai_service
			null, // template_processor
			null, // structure_manager
			$image_service,
			null, // post_manager
			$history_service
		);

		// Set current_history via reflection
		$ref = new ReflectionClass($generator);
		$history_prop = $ref->getProperty('current_history');
		$history_prop->setAccessible(true);
		$history_prop->setValue($generator, $history_container);

		$fired_actions = array();
		add_action('aips_integration_error', function($payload) use (&$fired_actions) {
			$fired_actions[] = $payload;
		});

		$template = (object) array(
			'id'                               => 123,
			'name'                             => 'Unsplash Test Template',
			'generate_featured_image'          => 1,
			'featured_image_source'            => 'unsplash',
			'featured_image_unsplash_keywords' => 'mountains, drone',
			'prompt_template'                  => 'Test content',
			'title_prompt'                     => 'Test title',
		);
		$context = new AIPS_Template_Context($template);

		$method = $ref->getMethod('set_featured_image_from_context');
		$method->setAccessible(true);

		$component_success = null;
		$result = $method->invokeArgs($generator, array($context, 999, 'Test Post', &$component_success));

		// Assertions
		$this->assertNull($result);
		$this->assertFalse($component_success);

		// Verify action payload
		$this->assertCount(1, $fired_actions);
		$this->assertSame('Unsplash Integration Error', $fired_actions[0]['title']);
		$this->assertSame('Featured image generation attempt requested with Unsplash, but there is no API key set.', $fired_actions[0]['message']);
		$this->assertSame('unsplash_key_missing', $fired_actions[0]['error_code']);
		$this->assertSame(1800, $fired_actions[0]['dedupe_window']);

		// Verify history container warning and metric snapshot
		$found_warning = false;
		$metric_event = null;
		foreach ($recorded_events as $event) {
			if ($event['type'] === 'warning' && strpos($event['message'], 'Unsplash API key is missing') !== false) {
				$found_warning = true;
			}
			if ($event['type'] === 'metric_generation_result') {
				$metric_event = $event;
			}
		}
		$this->assertTrue($found_warning, 'History container should record missing Unsplash key warning');
		$this->assertNotNull($metric_event, 'History container should record metric_generation_result');
		$this->assertTrue($metric_event['input']['image_attempted']);
		$this->assertFalse($metric_event['input']['image_success']);
		$this->assertSame('failed', $metric_event['input']['outcome']);
		$this->assertSame('unsplash', $metric_event['input']['image_source']);
	}
}
