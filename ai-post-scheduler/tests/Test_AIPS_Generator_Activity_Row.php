<?php
/**
 * Tests for the canonical generation-activity cross-reference row emitted by
 * AIPS_Generator.
 *
 * The per-schedule detail feed (AIPS_Unified_Schedule_Service::get_logs())
 * relies on AIPS_Generator recording exactly one ACTIVITY history-log row whose
 * `input` block carries an `event_type` discriminator, on the container that
 * already holds the author id. These tests pin that contract so the generator
 * unification cannot silently blank the author-post / template feeds.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generator_Activity_Row extends WP_UnitTestCase {

	public function test_topic_context_emits_single_author_post_generation_activity_row() {
		$container = new AIPS_Test_Activity_Row_History_Container();
		$generator = $this->make_generator($container);

		$result = $generator->generate_post($this->make_topic_context());

		$this->assertSame(999, $result);

		$activity_rows = $container->activity_rows();
		$this->assertCount(1, $activity_rows, 'Exactly one activity row should be emitted on success.');

		$row = $activity_rows[0];
		$this->assertSame('author_post_generation', $row['input']['event_type']);
		$this->assertSame('success', $row['input']['event_status']);
		// The schedule-feed query matches details LIKE '%"event_type":"..."%'.
		$this->assertStringContainsString(
			'"event_type":"author_post_generation"',
			wp_json_encode($row['input'])
		);
		// Author identity must ride along so the feed's author join has a target.
		$this->assertSame(7, $row['context']['author_id']);
	}

	public function test_template_context_emits_template_post_generation_activity_row() {
		$container = new AIPS_Test_Activity_Row_History_Container();
		$generator = $this->make_generator($container);

		$result = $generator->generate_post($this->make_template_context());

		$this->assertSame(999, $result);

		$activity_rows = $container->activity_rows();
		$this->assertCount(1, $activity_rows);
		$this->assertSame('template_post_generation', $activity_rows[0]['input']['event_type']);
		$this->assertSame('success', $activity_rows[0]['input']['event_status']);
	}

	public function test_content_failure_emits_failed_activity_row() {
		$container = new AIPS_Test_Activity_Row_History_Container();
		$generator = $this->make_generator($container, array(
			new WP_Error('content_failed', 'Content generation failed.'),
		));

		$result = $generator->generate_post($this->make_topic_context());

		$this->assertWPError($result);

		$activity_rows = $container->activity_rows();
		$this->assertCount(1, $activity_rows, 'A failed run still emits one canonical activity row.');
		$this->assertSame('author_post_generation', $activity_rows[0]['input']['event_type']);
		$this->assertSame('failed', $activity_rows[0]['input']['event_status']);
	}

	/**
	 * Build a generator wired to the capturing container and a scripted AI service.
	 *
	 * @param AIPS_Test_Activity_Row_History_Container $container Capturing container.
	 * @param array|null $responses Ordered generate_text responses. Defaults to a
	 *                              content/title/excerpt happy-path sequence.
	 * @return AIPS_Generator
	 */
	private function make_generator($container, $responses = null) {
		if ($responses === null) {
			$responses = array(
				'<p>Generated body content.</p>',
				'Generated Title',
				'Generated excerpt.',
			);
		}

		$ai_service      = new AIPS_Test_Activity_Row_AI_Service($responses);
		$history_service = new AIPS_Test_Activity_Row_History_Service($container);
		$post_manager    = new AIPS_Test_Activity_Row_Post_Manager();

		return new AIPS_Generator(
			null,
			$ai_service,
			null,
			null,
			null,
			$post_manager,
			$history_service
		);
	}

	private function make_topic_context() {
		$author = (object) array(
			'id'                      => 7,
			'name'                    => 'Test Author',
			'field_niche'             => 'Software Development',
			'voice_tone'              => '',
			'writing_style'           => '',
			'generate_featured_image' => 0,
			'post_status'             => 'draft',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
			'article_structure_id'    => null,
		);

		$topic = (object) array(
			'id'          => 42,
			'author_id'   => 7,
			'topic_title' => 'Clean Code Principles',
			'status'      => 'approved',
		);

		return new AIPS_Topic_Context($author, $topic, '', 'scheduled');
	}

	private function make_template_context() {
		$template = (object) array(
			'id'                      => 123,
			'name'                    => 'Activity Row Template',
			'prompt_template'         => 'Write the post body.',
			'title_prompt'            => 'Write the post title.',
			'image_prompt'            => '',
			'generate_featured_image' => false,
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => '',
			'post_author'             => 1,
			'article_structure_id'    => null,
		);

		return new AIPS_Template_Context($template, null, 'Test Topic', 'scheduled');
	}
}

class AIPS_Test_Activity_Row_AI_Service implements AIPS_AI_Service_Interface {
	private $responses;

	public function __construct($responses) {
		$this->responses = $responses;
	}

	public function is_available() {
		return true;
	}

	public function generate_text($prompt, $options = array()) {
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

class AIPS_Test_Activity_Row_History_Service implements AIPS_History_Service_Interface {
	private $container;

	public function __construct($container) {
		$this->container = $container;
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

class AIPS_Test_Activity_Row_History_Container {
	private $records = array();

	public function get_id() {
		return 1;
	}

	public function with_session($context) {
		return $this;
	}

	public function record($log_type, $message, $input = null, $output = null, $context = array()) {
		$this->records[] = array(
			'log_type' => $log_type,
			'message'  => $message,
			'input'    => is_array($input) ? $input : array(),
			'context'  => is_array($context) ? $context : array(),
		);
		return count($this->records);
	}

	public function record_error($message, $error_details = array(), $wp_error = null) {
		return true;
	}

	public function complete_success($result_data = array()) {
		return true;
	}

	public function complete_failure($error_message, $error_data = array()) {
		return true;
	}

	/**
	 * Only the ACTIVITY-type rows that carry an event_type discriminator — i.e.
	 * the canonical cross-reference rows the schedule feed reads.
	 *
	 * @return array
	 */
	public function activity_rows() {
		return array_values(array_filter($this->records, function($record) {
			return 'activity' === $record['log_type']
				&& isset($record['input']['event_type']);
		}));
	}
}

class AIPS_Test_Activity_Row_Post_Manager {
	public function create_post($data) {
		return 999;
	}

	public function update_generation_status_meta($post_id, $component_statuses = null, $generation_incomplete = null) {
		return true;
	}

	public function force_post_status($post_id, $status) {
		return true;
	}
}
