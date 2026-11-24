<?php
/**
 * Tests for Configurable Per-Component Inline Retries at Generation Time (#2404)
 *
 * @package AI_Post_Scheduler
 */

require_once __DIR__ . '/Test_AIPS_Generator_Conversational.php';

class Test_AIPS_Component_Retry_Runner extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option('aips_component_generation_retries');
		delete_option('aips_conversational_generation');
		delete_option('aips_conversational_metadata_turn');
		AIPS_Config::get_instance()->flush_option_cache();
	}

	public function tearDown(): void {
		remove_all_filters('aips_component_retry_delay_ms');
		delete_option('aips_component_generation_retries');
		delete_option('aips_conversational_generation');
		delete_option('aips_conversational_metadata_turn');
		AIPS_Config::get_instance()->flush_option_cache();
		parent::tearDown();
	}

	public function test_config_returns_expected_defaults_and_clamps_values() {
		$config = AIPS_Config::get_instance();
		$defaults = $config->get_component_generation_retries();

		$this->assertSame(2, $defaults['post_content']);
		$this->assertSame(2, $defaults['post_title']);
		$this->assertSame(1, $defaults['post_excerpt']);
		$this->assertSame(0, $defaults['post_slug']);
		$this->assertSame(2, $defaults['post_featured_image']);
		$this->assertSame(3, $defaults['post_images']);
		$this->assertSame(1, $defaults['post_meta']);

		update_option('aips_component_generation_retries', array(
			'post_slug'   => 99,
			'post_images' => -5,
		));
		$config->flush_option_cache();

		$this->assertSame(5, $config->get_component_retry_limit('post_slug'));
		$this->assertSame(0, $config->get_component_retry_limit('post_images'));
		$this->assertSame(0, $config->get_component_retry_limit('unknown_component'));
	}

	public function test_settings_registry_exposes_persisted_definition_and_defaults() {
		$registry = AIPS_Settings_Registry::instance();
		$this->assertTrue($registry->has_definition('aips_component_generation_retries'));

		$def = $registry->get_definition('aips_component_generation_retries');
		$this->assertInstanceOf('AIPS_Setting_Definition', $def);
		$this->assertFalse($def->is_action(), 'aips_component_generation_retries must be a persisted setting, not a virtual action.');
		$this->assertSame(AIPS_Setting_Definition::TYPE_ARRAY, $def->get_type());

		$defaults = $registry->get_defaults();
		$this->assertArrayHasKey('aips_component_generation_retries', $defaults);
		$this->assertSame(3, $defaults['aips_component_generation_retries']['post_images']);
		$this->assertSame(0, $defaults['aips_component_generation_retries']['post_slug']);

		$renderer = new AIPS_Settings_Form_Renderer();
		$html = $renderer->get_field_html($def);
		$this->assertStringContainsString('aips-component-retries-grid', $html);
		$this->assertStringContainsString('aips_component_generation_retries[post_images]', $html);
	}

	public function test_settings_ui_sanitizer_clamps_and_strips_unknown_keys() {
		$ui = new AIPS_Settings_UI();
		$sanitized = $ui->sanitize_component_generation_retries(array(
			'post_content'        => '10',
			'post_title'          => '-3',
			'post_excerpt'        => '2',
			'post_slug'           => '0',
			'post_featured_image' => '4',
			'post_images'         => '5',
			'post_meta'           => '1',
			'injected_evil_key'   => '3',
		));

		$this->assertSame(5, $sanitized['post_content']);
		$this->assertSame(0, $sanitized['post_title']);
		$this->assertSame(2, $sanitized['post_excerpt']);
		$this->assertSame(0, $sanitized['post_slug']);
		$this->assertSame(4, $sanitized['post_featured_image']);
		$this->assertSame(5, $sanitized['post_images']);
		$this->assertSame(1, $sanitized['post_meta']);
		$this->assertArrayNotHasKey('injected_evil_key', $sanitized);
	}

	public function test_execute_succeeds_without_retries_or_sleep() {
		$sleeps = array();
		$runner = new AIPS_Component_Retry_Runner(function ($ms) use (&$sleeps) {
			$sleeps[] = $ms;
		});

		$run = $runner->execute(
			'post_title',
			function () {
				return 'Great Title';
			},
			function ($value) {
				return is_string($value) && '' !== trim($value);
			}
		);

		$this->assertTrue($run['valid']);
		$this->assertSame('Great Title', $run['result']);
		$this->assertSame(1, $run['attempts']);
		$this->assertSame(0, $run['retries_used']);
		$this->assertSame(array(), $sleeps);
	}

	public function test_execute_retries_with_backoff_and_records_history_telemetry() {
		$sleeps = array();
		$runner = new AIPS_Component_Retry_Runner(function ($ms) use (&$sleeps) {
			$sleeps[] = $ms;
		});

		$history = new AIPS_Test_Conversational_History_Container();
		$calls   = 0;

		$run = $runner->execute(
			'post_title',
			function ($attempt) use (&$calls) {
				$calls++;
				return $attempt < 2 ? '' : 'Recovered Title';
			},
			function ($value) {
				return is_string($value) && '' !== trim($value);
			},
			$history
		);

		$this->assertTrue($run['valid']);
		$this->assertSame('Recovered Title', $run['result']);
		$this->assertSame(3, $calls);
		$this->assertSame(2, $run['retries_used']);
		$this->assertCount(2, $sleeps);
		foreach ($sleeps as $delay) {
			$this->assertGreaterThanOrEqual(AIPS_Component_Retry_Runner::MIN_BACKOFF_MS, $delay);
			$this->assertLessThanOrEqual(AIPS_Component_Retry_Runner::MAX_BACKOFF_MS, $delay);
		}

		$retry_entries = array_values(array_filter($history->records, function ($r) {
			return $r['type'] === 'ai_request' && isset($r['context']['retry_attempt']);
		}));
		$this->assertCount(2, $retry_entries);
		$this->assertSame(1, $retry_entries[0]['context']['retry_attempt']);
		$this->assertSame('post_title', $retry_entries[0]['context']['component']);
		$this->assertSame('empty_or_invalid_output', $retry_entries[0]['context']['reason']);
		$this->assertSame(2, $retry_entries[1]['context']['retry_attempt']);
	}

	public function test_execute_short_circuits_on_non_retryable_ai_unavailable_error() {
		$sleeps = array();
		$runner = new AIPS_Component_Retry_Runner(function ($ms) use (&$sleeps) {
			$sleeps[] = $ms;
		});

		$calls = 0;
		$run = $runner->execute(
			'post_content',
			function () use (&$calls) {
				$calls++;
				return new WP_Error('ai_unavailable', 'AI Engine plugin is not active.');
			},
			function ($value) {
				return !is_wp_error($value);
			}
		);

		$this->assertFalse($run['valid']);
		$this->assertSame(1, $calls);
		$this->assertSame(0, $run['retries_used']);
		$this->assertSame(array(), $sleeps);
	}

	public function test_execute_deficit_recovery_narrows_missing_indexes_across_passes() {
		$runner = new AIPS_Component_Retry_Runner(function () {});
		$history = new AIPS_Test_Conversational_History_Container();
		$seen_missing = array();

		$run = $runner->execute_deficit_recovery(
			'post_images',
			array(1, 2, 3),
			function (array $missing, $retry_attempt) use (&$seen_missing) {
				$seen_missing[] = $missing;
				if (1 === $retry_attempt) {
					return array(1 => 'Recovered 1', 3 => 'Recovered 3');
				}
				return array(2 => 'Recovered 2');
			},
			$history
		);

		$this->assertSame(array(array(1, 2, 3), array(2)), $seen_missing);
		$this->assertSame(2, $run['retries_used']);
		$this->assertSame(array(), $run['remaining']);
		$this->assertSame('Recovered 1', $run['recovered'][1]);
		$this->assertSame('Recovered 2', $run['recovered'][2]);
		$this->assertSame('Recovered 3', $run['recovered'][3]);
	}

	public function test_execute_deficit_recovery_respects_zero_budget() {
		update_option('aips_component_generation_retries', array('post_images' => 0));
		AIPS_Config::get_instance()->flush_option_cache();

		$runner = new AIPS_Component_Retry_Runner(function () {});
		$called = false;

		$run = $runner->execute_deficit_recovery(
			'post_images',
			array(1, 2),
			function () use (&$called) {
				$called = true;
				return array(1 => 'Should not run');
			}
		);

		$this->assertFalse($called);
		$this->assertSame(0, $run['retries_used']);
		$this->assertSame(array(1, 2), $run['remaining']);
	}

	public function test_generator_retries_title_and_recovers_on_second_attempt() {
		add_filter('aips_component_retry_delay_ms', '__return_zero');

		$ai_service = new AIPS_Test_Conversational_AI_Service(array(
			'<p>Article body paragraph with useful content.</p>',
			new WP_Error('timeout', 'Transient API timeout.'),
			'Recovered Resilient Title',
			'A valid excerpt.',
		));

		$post_manager = new AIPS_Test_Conversational_Post_Manager();
		$history_service = new AIPS_Test_Conversational_History_Service();
		$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, $history_service);

		$template = (object) array(
			'id'                      => 901,
			'name'                    => 'Retry Title Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'title_prompt'            => 'Write a title.',
			'image_prompt'            => '',
			'generate_featured_image' => false,
			'generate_slug'           => 0,
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
		);

		$post_id = $generator->generate_post(new AIPS_Template_Context($template, null, 'Resilience'));

		$this->assertIsInt($post_id);
		$this->assertSame('Recovered Resilient Title', $post_manager->created_post_data['title']);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_title']);
	}

	public function test_generator_slug_retries_when_configured_above_zero() {
		add_filter('aips_component_retry_delay_ms', '__return_zero');
		update_option('aips_component_generation_retries', array(
			'post_slug' => 2,
		));
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = new AIPS_Test_Conversational_AI_Service(array(
			'<p>Article body paragraph with useful content.</p>',
			'Great Slug Article',
			'A clean excerpt.',
			'',
			'custom-retried-slug',
		));

		$post_manager = new AIPS_Test_Conversational_Post_Manager();
		$history_service = new AIPS_Test_Conversational_History_Service();
		$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, $history_service);

		$template = (object) array(
			'id'                      => 902,
			'name'                    => 'Retry Slug Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'title_prompt'            => 'Write a title.',
			'generate_featured_image' => false,
			'generate_slug'           => 1,
			'slug_prompt'             => 'Use 3 words.',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
		);

		$post_id = $generator->generate_post(new AIPS_Template_Context($template, null, 'Slugs'));

		$this->assertIsInt($post_id);
		$this->assertSame('custom-retried-slug', $post_manager->created_post_data['slug']);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_slug']);
	}

	public function test_generator_excerpt_exhaustion_falls_back_to_content_and_marks_incomplete() {
		add_filter('aips_component_retry_delay_ms', '__return_zero');
		update_option('aips_component_generation_retries', array(
			'post_excerpt' => 1,
		));
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = new AIPS_Test_Conversational_AI_Service(array(
			'<p>First paragraph fallback text for excerpt.</p>',
			'Valid Title',
			'',
			'',
		));

		$post_manager = new AIPS_Test_Conversational_Post_Manager();
		$history_service = new AIPS_Test_Conversational_History_Service();
		$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, $history_service);

		$template = (object) array(
			'id'                      => 903,
			'name'                    => 'Excerpt Fallback Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'title_prompt'            => 'Write a title.',
			'generate_featured_image' => false,
			'generate_slug'           => 0,
			'post_status'             => 'publish',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
		);

		$post_id = $generator->generate_post(new AIPS_Template_Context($template, null, 'Excerpts'));

		$this->assertIsInt($post_id);
		$this->assertStringContainsString('First paragraph fallback text', $post_manager->created_post_data['excerpt']);
		$this->assertFalse($post_manager->created_post_data['component_statuses']['post_excerpt']);
	}

	public function test_generator_blank_title_exhaustion_falls_back_to_safe_title_and_marks_title_failed() {
		add_filter('aips_component_retry_delay_ms', '__return_zero');
		update_option('aips_component_generation_retries', array(
			'post_title' => 2,
		));
		AIPS_Config::get_instance()->flush_option_cache();

		// Content returned, but all 3 title attempts return blank whitespace.
		$ai_service = new AIPS_Test_Conversational_AI_Service(array(
			'<p>Article body for testing blank titles.</p>',
			'   ',
			'',
			" \n\t ",
			'Valid excerpt text here.',
		));

		$post_manager = new AIPS_Test_Conversational_Post_Manager();
		$history_service = new AIPS_Test_Conversational_History_Service();
		$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, $history_service);

		$template = (object) array(
			'id'                      => 904,
			'name'                    => 'Blank Title Fallback Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'title_prompt'            => 'Generate title.',
			'generate_featured_image' => false,
			'generate_slug'           => 0,
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
		);

		$post_id = $generator->generate_post(new AIPS_Template_Context($template, null, 'Blank Titles'));

		$this->assertIsInt($post_id);
		$this->assertStringStartsWith('AIPS Generated Post: Blank Titles', $post_manager->created_post_data['title']);
		$this->assertFalse($post_manager->created_post_data['component_statuses']['post_title']);
	}

	public function test_generator_non_retryable_error_in_excerpt_aborts_without_retrying() {
		add_filter('aips_component_retry_delay_ms', '__return_zero');
		update_option('aips_component_generation_retries', array(
			'post_excerpt' => 3,
		));
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = new AIPS_Test_Conversational_AI_Service(array(
			'<p>Content body text for testing non retryable excerpt.</p>',
			'Valid Title',
			new WP_Error('circuit_breaker_open', 'Circuit breaker is open.'),
		));

		$post_manager = new AIPS_Test_Conversational_Post_Manager();
		$history_service = new AIPS_Test_Conversational_History_Service();
		$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, $history_service);

		$template = (object) array(
			'id'                      => 905,
			'name'                    => 'Circuit Breaker Excerpt Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'title_prompt'            => 'Generate title.',
			'generate_featured_image' => false,
			'generate_slug'           => 0,
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
		);

		$post_id = $generator->generate_post(new AIPS_Template_Context($template, null, 'Circuits'));

		$this->assertIsInt($post_id);
		// AI calls should be exactly 3: 1 content, 1 title, and only 1 excerpt call (0 retries because non-retryable).
		$this->assertCount(3, $ai_service->calls);
		$this->assertFalse($post_manager->created_post_data['component_statuses']['post_excerpt']);
	}

	public function test_execute_deficit_recovery_aborts_on_non_retryable_error() {
		$runner = new AIPS_Component_Retry_Runner(function () {});
		$calls = 0;

		$run = $runner->execute_deficit_recovery(
			'post_images',
			array(1, 2),
			function () use (&$calls) {
				$calls++;
				return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded.');
			}
		);

		$this->assertSame(1, $calls);
		$this->assertSame(1, $run['retries_used']);
		$this->assertSame(array(1, 2), $run['remaining']);
		$this->assertSame(array(), $run['recovered']);
	}

	public function test_generator_content_image_retries_and_succeeds_on_transient_failure() {
		add_filter('aips_component_retry_delay_ms', '__return_zero');
		update_option('aips_component_generation_retries', array(
			'post_images' => 2,
		));
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = new AIPS_Test_Conversational_AI_Service(array(
			"<p>Body with placeholder.</p>\n<p>[post_image index=\"1\"]</p>",
			'Article Title',
			'Excerpt text',
		));

		$img_calls = 0;
		$attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');

		$image_service = $this->getMockBuilder('AIPS_Image_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('generate_and_upload_featured_image'))
			->getMock();

		$image_service->expects($this->exactly(2))
			->method('generate_and_upload_featured_image')
			->willReturnCallback(function () use (&$img_calls, $attachment_id) {
				$img_calls++;
				if ($img_calls === 1) {
					return new WP_Error('timeout', 'Temporary 504 Gateway Timeout');
				}
				return $attachment_id;
			});

		$post_manager = new AIPS_Test_Conversational_Post_Manager();
		$history_service = new AIPS_Test_Conversational_History_Service();
		$generator = new AIPS_Generator(null, $ai_service, null, $image_service, null, $post_manager, $history_service);

		$template = (object) array(
			'id'                      => 906,
			'name'                    => 'Content Image Retry Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'title_prompt'            => 'Generate title.',
			'generate_featured_image' => false,
			'generate_slug'           => 0,
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
			'post_images'             => array(
				(object) array(
					'id'                => 11,
					'placeholder_index' => 1,
					'source'            => 'ai_prompt',
					'image_prompt'      => 'A photo of {{topic}}',
					'placement'         => 'auto',
					'caption'           => 'Test caption',
				),
			),
		);

		$post_id = $generator->generate_post(new AIPS_Template_Context($template, null, 'Robotics'));

		$this->assertIsInt($post_id);
		$this->assertSame(2, $img_calls);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_images']);
	}
}
