<?php
/**
 * Test AIPS_Generation_Session
 *
 * Tests for the generation session tracker class.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generation_Session extends WP_UnitTestCase {

	/**
	 * Test session initialization.
	 */
	public function test_session_initialization() {
		$session = new AIPS_Generation_Session();

		$this->assertNull($session->get_started_at());
		$this->assertNull($session->get_completed_at());
		$this->assertNull($session->get_template());
		$this->assertNull($session->get_voice());
		$this->assertEmpty($session->get_ai_calls());
		$this->assertEmpty($session->get_errors());
		$this->assertNull($session->get_result());
		$this->assertEquals(0, $session->get_ai_call_count());
		$this->assertEquals(0, $session->get_error_count());
		$this->assertFalse($session->was_successful());
	}

	/**
	 * Test session start with template only.
	 */
	public function test_session_start_with_template_only() {
		$session = new AIPS_Generation_Session();

		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate title',
			'post_status' => 'draft',
			'post_category' => '1',
			'post_tags' => 'ai,test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$session->start($template);

		$this->assertNotNull($session->get_started_at());
		$this->assertNotNull($session->get_template());
		$this->assertNull($session->get_voice());

		$stored_template = $session->get_template();
		$this->assertEquals(1, $stored_template['id']);
		$this->assertEquals('Test Template', $stored_template['name']);
	}

	/**
	 * Test session start with template and voice.
	 */
	public function test_session_start_with_voice() {
		$session = new AIPS_Generation_Session();

		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate title',
			'post_status' => 'draft',
			'post_category' => '1',
			'post_tags' => 'ai,test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$voice = (object) array(
			'id' => 5,
			'name' => 'Professional Voice',
			'title_prompt' => 'Professional title',
			'content_instructions' => 'Write professionally',
			'excerpt_instructions' => 'Professional excerpt',
		);

		$session->start($template, $voice);

		$this->assertNotNull($session->get_voice());

		$stored_voice = $session->get_voice();
		$this->assertEquals(5, $stored_voice['id']);
		$this->assertEquals('Professional Voice', $stored_voice['name']);
	}

	/**
	 * Test logging AI calls.
	 */
	public function test_log_ai_call_success() {
		$session = new AIPS_Generation_Session();

		$session->log_ai_call(
			'content',
			'Write about AI',
			'AI is amazing...',
			array('max_tokens' => 500),
			null
		);

		$this->assertEquals(1, $session->get_ai_call_count());

		$ai_calls = $session->get_ai_calls();
		$this->assertCount(1, $ai_calls);

		$call = $ai_calls[0];
		$this->assertEquals('content', $call['type']);
		$this->assertEquals('Write about AI', $call['request']['prompt']);
		$this->assertEquals('AI is amazing...', $call['response']['content']);
		$this->assertTrue($call['response']['success']);
		$this->assertNull($call['response']['error']);
		$this->assertArrayHasKey('max_tokens', $call['request']['options']);
	}

	/**
	 * Test logging AI call with error.
	 */
	public function test_log_ai_call_with_error() {
		$session = new AIPS_Generation_Session();

		$session->log_ai_call(
			'title',
			'Generate title',
			null,
			array(),
			'API timeout'
		);

		$this->assertEquals(1, $session->get_ai_call_count());
		$this->assertEquals(1, $session->get_error_count());

		$ai_calls = $session->get_ai_calls();
		$call = $ai_calls[0];
		$this->assertFalse($call['response']['success']);
		$this->assertEquals('API timeout', $call['response']['error']);

		$errors = $session->get_errors();
		$this->assertCount(1, $errors);
		$this->assertEquals('title', $errors[0]['type']);
		$this->assertEquals('API timeout', $errors[0]['message']);
	}

	/**
	 * Test adding errors directly.
	 */
	public function test_add_error() {
		$session = new AIPS_Generation_Session();

		$session->add_error('featured_image', 'Image generation failed');

		$this->assertEquals(1, $session->get_error_count());

		$errors = $session->get_errors();
		$this->assertEquals('featured_image', $errors[0]['type']);
		$this->assertEquals('Image generation failed', $errors[0]['message']);
		$this->assertArrayHasKey('timestamp', $errors[0]);
	}

	/**
	 * Test multiple AI calls.
	 */
	public function test_multiple_ai_calls() {
		$session = new AIPS_Generation_Session();

		$session->log_ai_call('title', 'Gen title', 'Great Title', array(), null);
		$session->log_ai_call('content', 'Gen content', 'Great Content', array(), null);
		$session->log_ai_call('excerpt', 'Gen excerpt', 'Great Excerpt', array(), null);

		$this->assertEquals(3, $session->get_ai_call_count());
		$this->assertEquals(0, $session->get_error_count());

		$ai_calls = $session->get_ai_calls();
		$this->assertCount(3, $ai_calls);
		$this->assertEquals('title', $ai_calls[0]['type']);
		$this->assertEquals('content', $ai_calls[1]['type']);
		$this->assertEquals('excerpt', $ai_calls[2]['type']);
	}

	/**
	 * Test completing session with success.
	 */
	public function test_complete_with_success() {
		$session = new AIPS_Generation_Session();

		$result = array(
			'success' => true,
			'post_id' => 42,
			'generated_title' => 'My Post',
		);

		$session->complete($result);

		$this->assertNotNull($session->get_completed_at());
		$this->assertTrue($session->was_successful());
		$this->assertEquals($result, $session->get_result());
	}

	/**
	 * Test completing session with failure.
	 */
	public function test_complete_with_failure() {
		$session = new AIPS_Generation_Session();

		$result = array(
			'success' => false,
			'error' => 'AI unavailable',
		);

		$session->complete($result);

		$this->assertNotNull($session->get_completed_at());
		$this->assertFalse($session->was_successful());
	}

	/**
	 * Test to_array conversion.
	 */
	public function test_to_array() {
		$session = new AIPS_Generation_Session();

		$template = (object) array(
			'id' => 1,
			'name' => 'Test',
			'prompt_template' => 'prompt',
			'title_prompt' => '',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$session->start($template);
		$session->log_ai_call('content', 'prompt', 'response', array(), null);
		$session->complete(array('success' => true, 'post_id' => 10));

		$array = $session->to_array();

		$this->assertIsArray($array);
		$this->assertArrayHasKey('started_at', $array);
		$this->assertArrayHasKey('completed_at', $array);
		$this->assertArrayHasKey('template', $array);
		$this->assertArrayHasKey('voice', $array);
		$this->assertArrayHasKey('ai_calls', $array);
		$this->assertArrayHasKey('errors', $array);
		$this->assertArrayHasKey('result', $array);

		$this->assertNotNull($array['started_at']);
		$this->assertNotNull($array['completed_at']);
		$this->assertCount(1, $array['ai_calls']);
	}

	/**
	 * Test to_json conversion.
	 */
	public function test_to_json() {
		$session = new AIPS_Generation_Session();

		$template = (object) array(
			'id' => 1,
			'name' => 'Test',
			'prompt_template' => 'prompt',
			'title_prompt' => '',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$session->start($template);
		$session->complete(array('success' => true));

		$json = $session->to_json();

		$this->assertIsString($json);
		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('started_at', $decoded);
		$this->assertArrayHasKey('result', $decoded);
	}

	/**
	 * Test get_duration before completion.
	 */
	public function test_get_duration_before_completion() {
		$session = new AIPS_Generation_Session();

		$template = (object) array(
			'id' => 1,
			'name' => 'Test',
			'prompt_template' => 'prompt',
			'title_prompt' => '',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$session->start($template);

		$this->assertNull($session->get_duration());
	}

	/**
	 * Test get_duration after completion.
	 */
	public function test_get_duration_after_completion() {
		$session = new AIPS_Generation_Session();

		$template = (object) array(
			'id' => 1,
			'name' => 'Test',
			'prompt_template' => 'prompt',
			'title_prompt' => '',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$session->start($template);
		sleep(1); // Wait at least 1 second
		$session->complete(array('success' => true));

		$duration = $session->get_duration();
		$this->assertIsNumeric($duration);
		$this->assertGreaterThanOrEqual(1, $duration);
	}

	/**
	 * Test full generation lifecycle.
	 */
	public function test_full_generation_lifecycle() {
		$session = new AIPS_Generation_Session();

		// Start session
		$template = (object) array(
			'id' => 1,
			'name' => 'Blog Post',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate title',
			'post_status' => 'draft',
			'post_category' => '1',
			'post_tags' => 'test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => true,
			'image_prompt' => 'Image of {{topic}}',
		);

		$voice = (object) array(
			'id' => 2,
			'name' => 'Casual',
			'title_prompt' => 'Casual title',
			'content_instructions' => 'Write casually',
			'excerpt_instructions' => 'Casual excerpt',
		);

		$session->start($template, $voice);

		// Log AI calls
		$session->log_ai_call('title', 'Generate title', 'Amazing AI', array('max_tokens' => 100), null);
		$session->log_ai_call('content', 'Generate content', 'Content here...', array('max_tokens' => 500), null);
		$session->log_ai_call('excerpt', 'Generate excerpt', 'Short summary', array('max_tokens' => 150), null);
		$session->log_ai_call('featured_image', 'Generate image', 'image-url.jpg', array(), null);

		// Complete session
		$session->complete(array(
			'success' => true,
			'post_id' => 123,
			'generated_title' => 'Amazing AI',
			'generated_content' => 'Content here...',
			'generated_excerpt' => 'Short summary',
			'featured_image_id' => 456,
		));

		// Verify session state
		$this->assertTrue($session->was_successful());
		$this->assertEquals(4, $session->get_ai_call_count());
		$this->assertEquals(0, $session->get_error_count());
		$this->assertNotNull($session->get_template());
		$this->assertNotNull($session->get_voice());

		// Verify serialization
		$json = $session->to_json();
		$this->assertIsString($json);

		$decoded = json_decode($json, true);
		$this->assertEquals('Blog Post', $decoded['template']['name']);
		$this->assertEquals('Casual', $decoded['voice']['name']);
		$this->assertCount(4, $decoded['ai_calls']);
		$this->assertEquals(123, $decoded['result']['post_id']);
	}
}
