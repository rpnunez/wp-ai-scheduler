<?php
/**
 * Tests for required generated-content behavior in AIPS_Generator.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generator_Required_Content extends WP_UnitTestCase {

	public function test_content_generation_failure_skips_title_call_and_post_creation() {
		$ai_service      = new AIPS_Test_Generator_Required_Content_AI_Service(array(
			new WP_Error('content_failed', 'Content generation failed.'),
		));
		$history_service = new AIPS_Test_Generator_Required_Content_History_Service();
		$post_manager    = new AIPS_Test_Generator_Required_Content_Post_Manager();

		$generator = new AIPS_Generator(
			null,
			$ai_service,
			null,
			null,
			null,
			$post_manager,
			$history_service
		);

		$result = $generator->generate_post($this->make_context());

		$this->assertWPError($result);
		$this->assertSame('aips_generation_missing_required_content', $result->get_error_code());
		$this->assertSame(1, $ai_service->text_call_count);
		$this->assertSame(0, $post_manager->create_post_call_count);
		$this->assertArrayNotHasKey('failed_components', $result->get_error_data());
		$this->assertArrayNotHasKey('failed_components', $history_service->container->failure_data);
		$this->assertFalse($history_service->container->failure_data['component_statuses']['post_content']);
		$this->assertFalse($history_service->container->failure_data['component_statuses']['post_title']);
	}

	public function test_title_generation_failure_saves_content_as_partial_generation() {
		$ai_service      = new AIPS_Test_Generator_Required_Content_AI_Service(array(
			'<p>Generated body content.</p>',
			new WP_Error('title_failed', 'Title generation failed.'),
			'Generated excerpt.',
		));
		$history_service = new AIPS_Test_Generator_Required_Content_History_Service();
		$post_manager    = new AIPS_Test_Generator_Required_Content_Post_Manager();

		$generator = new AIPS_Generator(
			null,
			$ai_service,
			null,
			null,
			null,
			$post_manager,
			$history_service
		);

		$result = $generator->generate_post($this->make_context());

		$this->assertSame(999, $result);
		$this->assertSame(1, $post_manager->create_post_call_count);
		$this->assertSame('<p>Generated body content.</p>', $post_manager->created_post_data['content']);
		$this->assertNotSame('', $post_manager->created_post_data['title']);
		$this->assertTrue($post_manager->created_post_data['generation_incomplete']);
		$this->assertFalse($post_manager->created_post_data['component_statuses']['post_title']);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_content']);
	}

	private function make_context() {
		$template = (object) array(
			'id'                       => 123,
			'name'                     => 'Required Content Test',
			'prompt_template'          => 'Write the post body.',
			'title_prompt'             => 'Write the post title.',
			'image_prompt'             => '',
			'generate_featured_image'  => false,
			'post_status'              => 'draft',
			'post_type'                => 'post',
			'post_category'            => '',
			'post_tags'                => '',
			'post_author'              => 1,
			'article_structure_id'     => null,
		);

		return new AIPS_Template_Context($template, null, 'Test Topic', 'scheduled');
	}
}

class AIPS_Test_Generator_Required_Content_AI_Service implements AIPS_AI_Service_Interface {
	public $text_call_count = 0;
	private $responses;

	public function __construct($responses) {
		$this->responses = $responses;
	}

	public function is_available() {
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		$this->text_call_count++;
		if (empty($this->responses)) {
			return new WP_Error('unexpected_ai_call', 'Unexpected AI text call.');
		}

		return array_shift($this->responses);
	}

	public function generate_json($prompt, $options = array()) {
		return array();
	}

	public function generate_image($prompt, $options = array()) {
		return new WP_Error('image_not_expected', 'Image generation should not be called.');
	}

	public function generate_embedding($text, $options = array()) {
		return new WP_Error('embedding_not_expected', 'Embedding generation should not be called.');
	}

	public function supports_embeddings() {
		return false;
	}

	public function supports_conversation() {
		return false;
	}

	public function get_call_log() {
		return array();
	}
}

class AIPS_Test_Generator_Required_Content_History_Service implements AIPS_History_Service_Interface {
	public $container;

	public function __construct() {
		$this->container = new AIPS_Test_Generator_Required_Content_History_Container();
	}

	public function create($type, $metadata = array()) {
		return $this->container;
	}

	public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
		return array();
	}

	public function post_has_history_and_completed($post_id) {
		return false;
	}

	public function get_by_id($history_id) {
		return null;
	}

	public function update_history_record($history_id, $data) {
		return true;
	}

	public function find_incomplete($type, $metadata = array()) {
		return null;
	}
}

class AIPS_Test_Generator_Required_Content_History_Container {
	public $failure_data = array();

	public function get_id() {
		return 1;
	}

	public function with_session($context) {
		return $this;
	}

	public function record($log_type, $message, $input = null, $output = null, $context = array()) {
		return true;
	}

	public function record_error($message, $error_details = array(), $wp_error = null) {
		return true;
	}

	public function complete_success($result_data = array()) {
		return true;
	}

	public function complete_failure($error_message, $error_data = array()) {
		$this->failure_data = $error_data;
		return true;
	}
}

class AIPS_Test_Generator_Required_Content_Post_Manager {
	public $create_post_call_count = 0;
	public $created_post_data = array();

	public function create_post($data) {
		$this->create_post_call_count++;
		$this->created_post_data = $data;
		return 999;
	}

	public function update_generation_status_meta($post_id, $component_statuses = null, $generation_incomplete = null) {
		return true;
	}
}
