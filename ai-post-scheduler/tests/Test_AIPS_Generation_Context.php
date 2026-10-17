<?php
/**
 * Test Generation Context Architecture
 *
 * Tests the new context-based architecture for the Generator.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generation_Context extends WP_UnitTestCase {

	/**
	 * Test that Template Context wraps template data correctly.
	 *
	 * @return void
	 */
	public function test_template_context_wraps_template() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'image_prompt' => 'Image of {{topic}}',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_category' => 1,
			'post_tags' => 'test,example',
			'post_author' => 1,
			'article_structure_id' => null,
		);
		
		$context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$this->assertEquals('template', $context->get_type());
		$this->assertEquals(123, $context->get_id());
		$this->assertEquals('Test Template', $context->get_name());
		$this->assertEquals('Write about {{topic}}', $context->get_content_prompt());
		$this->assertEquals('Generate a title', $context->get_title_prompt());
		$this->assertEquals('Image of {{topic}}', $context->get_image_prompt());
		$this->assertTrue($context->should_generate_featured_image());
		$this->assertEquals('ai_prompt', $context->get_featured_image_source());
		$this->assertEquals('draft', $context->get_post_status());
		$this->assertEquals('page', $context->get_post_type());
		$this->assertEquals(1, $context->get_post_category());
		$this->assertEquals('test,example', $context->get_post_tags());
		$this->assertEquals(1, $context->get_post_author());
		$this->assertEquals('Test Topic', $context->get_topic());
		$this->assertNull($context->get_article_structure_id());
	}
	
	/**
	 * Test that Topic Context wraps author and topic correctly.
	 *
	 * @return void
	 */
	public function test_topic_context_wraps_author_and_topic() {
		$author = (object) array(
			'id' => 456,
			'name' => 'Test Author',
			'field_niche' => 'Software Development',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_category' => 2,
			'post_tags' => 'coding,dev',
			'post_author' => 2,
			'article_structure_id' => 5,
		);
		
		$topic = (object) array(
			'id' => 789,
			'topic_title' => 'Best Practices for Clean Code',
			'author_id' => 456,
		);
		
		$expanded_context = 'Related topics: SOLID principles, Code review';
		
		$context = new AIPS_Topic_Context($author, $topic, $expanded_context);
		
		$this->assertEquals('topic', $context->get_type());
		$this->assertEquals(789, $context->get_id());
		$this->assertEquals('Test Author: Best Practices for Clean Code', $context->get_name());
		$this->assertStringContainsString('Write a comprehensive blog post about: Best Practices for Clean Code', $context->get_content_prompt());
		$this->assertStringContainsString('Field/Niche: Software Development', $context->get_content_prompt());
		$this->assertStringContainsString('Related topics: SOLID principles, Code review', $context->get_content_prompt());
		$this->assertEquals('Best Practices for Clean Code', $context->get_title_prompt());
		$this->assertEquals('Best Practices for Clean Code', $context->get_image_prompt());
		$this->assertTrue($context->should_generate_featured_image());
		$this->assertEquals('ai_prompt', $context->get_featured_image_source());
		$this->assertEquals('publish', $context->get_post_status());
		$this->assertEquals('page', $context->get_post_type());
		$this->assertEquals(2, $context->get_post_category());
		$this->assertEquals('coding,dev', $context->get_post_tags());
		$this->assertEquals(2, $context->get_post_author());
		$this->assertEquals('Best Practices for Clean Code', $context->get_topic());
		$this->assertEquals(5, $context->get_article_structure_id());
		$this->assertNull($context->get_voice_id());
	}
	
	/**
	 * Test that Generation Session accepts context objects.
	 *
	 * @return void
	 */
	public function test_generation_session_accepts_context() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => 0,
			'image_prompt' => '',
		);
		
		$context = new AIPS_Template_Context($template, null, 'Test Topic');
		$session = new AIPS_Generation_Session();
		
		$session->start($context);
		
		$context_data = $session->get_context();
		$this->assertNotNull($context_data);
		$this->assertEquals('template', $context_data['type']);
		$this->assertEquals(123, $context_data['id']);
		$this->assertEquals('Test Template', $context_data['name']);
	}
	
	/**
	 * Test backward compatibility: Generation Session still accepts template objects.
	 *
	 * @return void
	 */
	public function test_generation_session_backward_compatibility_with_templates() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => 0,
			'image_prompt' => '',
		);
		
		$session = new AIPS_Generation_Session();
		
		// Old way of calling start with template object
		$session->start($template, null);
		
		// Should still create context data
		$context_data = $session->get_context();
		$this->assertNotNull($context_data);
		$this->assertEquals('template', $context_data['type']);
		$this->assertEquals(123, $context_data['id']);
		$this->assertEquals('Test Template', $context_data['name']);
		
		// Should also still populate legacy template data
		$template_data = $session->get_template();
		$this->assertNotNull($template_data);
		$this->assertEquals(123, $template_data['id']);
	}

	/**
	 * Test that generation session duration is computed from MySQL timestamps.
	 *
	 * @return void
	 */
	public function test_generation_session_duration_from_mysql_timestamps() {
		$session = new AIPS_Generation_Session();

		$reflection = new ReflectionClass($session);
		$started_prop = $reflection->getProperty('started_at');
		$completed_prop = $reflection->getProperty('completed_at');
		$started_prop->setAccessible(true);
		$completed_prop->setAccessible(true);

		$started_prop->setValue($session, '2026-05-06 10:00:00');
		$completed_prop->setValue($session, '2026-05-06 10:02:30');

		$this->assertSame(150, $session->get_duration());
	}
	
	/**
	 * Test that context data is properly serialized to array.
	 *
	 * @return void
	 */
	public function test_context_to_array_serialization() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
			'article_structure_id' => 5,
		);
		
		$context = new AIPS_Template_Context($template, null, 'Test Topic');
		$array = $context->to_array();
		
		$this->assertIsArray($array);
		$this->assertEquals('template', $array['type']);
		$this->assertEquals(123, $array['id']);
		$this->assertEquals('Test Template', $array['name']);
		$this->assertEquals('Write about {{topic}}', $array['content_prompt']);
		$this->assertEquals('Test Topic', $array['topic']);
		$this->assertEquals('page', $array['post_type']);
		$this->assertEquals(5, $array['article_structure_id']);
	}
	
	/**
	 * Test that Template Context with voice prioritizes voice title prompt.
	 *
	 * @return void
	 */
	public function test_template_context_voice_title_prompt_precedence() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Template title prompt',
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
		);
		
		$voice = (object) array(
			'id' => 456,
			'name' => 'Test Voice',
			'title_prompt' => 'Voice title prompt',
			'content_instructions' => 'Voice instructions',
			'excerpt_instructions' => 'Voice excerpt',
		);
		
		$context = new AIPS_Template_Context($template, $voice, 'Test Topic');
		
		// Voice title prompt should take precedence
		$this->assertEquals('Voice title prompt', $context->get_title_prompt());
		$this->assertEquals(456, $context->get_voice_id());
	}

	/**
	 * Test that AIPS_Generation_Session serializes to array and JSON.
	 *
	 * @return void
	 */
	public function test_generation_session_to_json_serialization() {
		$template = (object) array(
			'id'                      => 123,
			'name'                    => 'Test Template',
			'prompt_template'         => 'Write about {{topic}}',
			'title_prompt'            => 'Generate a title',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => 1,
			'post_tags'               => 'test',
			'post_author'             => 1,
			'generate_featured_image' => 0,
		);

		$context = new AIPS_Template_Context($template, null, 'Serialization Topic');
		$session = new AIPS_Generation_Session();
		$session->start($context);
		$session->log_ai_call();
		$session->log_ai_call();
		$session->add_error();
		$session->complete(777);

		$decoded = json_decode($session->to_json(), true);

		$this->assertIsArray($decoded);
		$this->assertSame(2, $decoded['ai_call_count']);
		$this->assertSame(1, $decoded['error_count']);
		$this->assertSame(777, $decoded['result']);
		$this->assertSame('template', $decoded['context']['type']);
		$this->assertSame(123, $decoded['context']['id']);
		$this->assertArrayHasKey('duration', $decoded);
	}

	/**
	 * Test that AIPS_History_Container::record_post_created persists post_id immediately
	 * while keeping status in 'processing', and completion persists generation_log JSON.
	 *
	 * @return void
	 */
	public function test_history_container_records_post_created_and_persists_session_json() {
		$repo    = new AIPS_History_Repository();
		$service = new AIPS_History_Service($repo);

		$template = (object) array(
			'id'              => 55,
			'name'            => 'Crash Safe Template',
			'prompt_template' => 'Prompt',
			'title_prompt'    => 'Title',
			'post_status'     => 'publish',
			'post_type'       => 'post',
			'post_category'   => 1,
			'post_tags'       => '',
			'post_author'     => 1,
		);
		$context = new AIPS_Template_Context($template, null, 'Topic');

		$container = $service->create('post_generation', array(
			'template_id'     => 55,
			'schedule_id'     => 88,
			'creation_method' => 'scheduled',
			'record_kind'     => AIPS_History_Record_Kind::GENERATION,
		))->with_session($context);

		$container->record('ai_request', 'Requesting content');

		// Immediately record post creation before completion.
		$this->assertTrue($container->record_post_created(321, array(
			'post_type'         => 'post',
			'generated_title'   => 'Early Title',
			'generated_content' => 'Early Content',
		)));

		$mid_row = $repo->get_by_id($container->get_id());
		$this->assertSame('processing', $mid_row->status);
		$this->assertSame(321, (int) $mid_row->post_id);
		$this->assertSame(88, (int) $mid_row->schedule_id);
		$this->assertSame('Early Title', $mid_row->generated_title);

		// Complete container and verify generation_log is persisted.
		$this->assertTrue($container->complete_success(array(
			'post_id'           => 321,
			'generated_title'   => 'Final Title',
			'generated_content' => 'Final Content',
		)));

		$final_row = $repo->get_by_id($container->get_id());
		$this->assertSame('completed', $final_row->status);
		$this->assertNotEmpty($final_row->generation_log);

		$session_log = json_decode($final_row->generation_log, true);
		$this->assertIsArray($session_log);
		$this->assertSame(1, $session_log['ai_call_count']);
		$this->assertSame(321, $session_log['result']['post_id']);

		$repo->delete($container->get_id());
	}

	/**
	 * Test that AIPS_Generator stages 'publish' posts as 'draft' when featured image
	 * generation is enabled, records post_id on the history container before image
	 * generation, and transitions the post to 'publish' once image generation succeeds.
	 *
	 * @return void
	 */
	public function test_generator_stages_publish_as_draft_during_featured_image_and_records_schedule_id() {
		$repo    = new AIPS_History_Repository();
		$service = new AIPS_History_Service($repo);

		$ai_service = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_service->method('is_available')->willReturn(true);
		$ai_service->method('generate_text')->willReturn('Generated Text Output');

		$image_service = $this->getMockBuilder(AIPS_Image_Service::class)
			->disableOriginalConstructor()
			->onlyMethods(array('generate_and_upload_featured_image'))
			->getMock();
		$image_service->expects($this->once())
			->method('generate_and_upload_featured_image')
			->willReturn(555);

		$created_post_id = self::factory()->post->create(array('post_status' => 'draft'));
		$observed_status_during_create = null;
		$forced_statuses = array();

		$post_manager = $this->getMockBuilder(AIPS_Post_Manager::class)
			->onlyMethods(array('create_post', 'force_post_status', 'update_generation_status_meta'))
			->getMock();

		$post_manager->expects($this->once())
			->method('create_post')
			->willReturnCallback(function ($data) use ($created_post_id, &$observed_status_during_create) {
				$observed_status_during_create = $data['post_status'];
				return $created_post_id;
			});

		$post_manager->expects($this->once())
			->method('force_post_status')
			->with($created_post_id, 'publish')
			->willReturnCallback(function ($post_id, $status) use (&$forced_statuses) {
				$forced_statuses[] = array($post_id, $status);
				return $post_id;
			});

		$generator = new AIPS_Generator(
			null,
			$ai_service,
			null,
			$image_service,
			null,
			$post_manager,
			$service
		);

		$template = (object) array(
			'id'                      => 10,
			'name'                    => 'Staged Publish Template',
			'prompt_template'         => 'Write about {{topic}}',
			'title_prompt'            => 'Write title',
			'image_prompt'            => 'Draw {{topic}}',
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'post_status'             => 'publish',
			'post_type'               => 'post',
			'post_category'           => 1,
			'post_tags'               => '',
			'post_author'             => 1,
		);

		$context = new AIPS_Template_Context($template, null, 'Staged Publish Topic', 'scheduled');
		$context->set_trigger_context(array(
			'schedule_id'   => 444,
			'schedule_name' => 'Hourly Schedule',
			'frequency'     => 'hourly',
		));

		$result_post_id = $generator->generate_post($context);

		$this->assertSame($created_post_id, $result_post_id);
		$this->assertSame('draft', $observed_status_during_create, 'Post must be created as draft while featured image generation is pending.');
		$this->assertSame(array(array($created_post_id, 'publish')), $forced_statuses, 'Post must transition to publish after featured image succeeds.');

		$history_row = $repo->get_by_post_id($created_post_id);
		$this->assertNotNull($history_row);
		$history_row = $repo->get_by_id($history_row->id);
		$this->assertSame(444, (int) $history_row->schedule_id);
		$this->assertSame('completed', $history_row->status);
		$this->assertNotEmpty($history_row->generation_log);

		$repo->delete($history_row->id);
	}
}
