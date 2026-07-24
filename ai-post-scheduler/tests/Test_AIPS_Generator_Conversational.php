<?php
/**
 * Tests for conversational generation in AIPS_Generator.
 *
 * Covers the three configurations that must behave differently:
 * - flag off                      -> today's self-contained prompts (regression guard)
 * - flag on, provider supports it -> follow-up prompts that omit the article
 * - flag on, provider does not    -> falls back to the self-contained prompts
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generator_Conversational extends WP_UnitTestCase {

	const BODY = '<p>The generated article body about widgets.</p>';

	public function tearDown(): void {
		delete_option('aips_conversational_generation');
		delete_option('aips_conversational_metadata_turn');
		AIPS_Config::get_instance()->flush_option_cache();
		parent::tearDown();
	}

	public function test_flag_off_keeps_article_body_in_the_title_prompt() {
		$ai_service = $this->make_service(array(self::BODY, 'A Title', 'An excerpt.'));
		$ai_service->conversation_supported = true;

		$this->run_generation($ai_service);

		$title_call = $ai_service->calls[1];

		// Regression guard on the untouched path: the body must still be pasted in.
		$this->assertStringContainsString(self::BODY, $title_call['prompt']);
		$this->assertArrayNotHasKey('conversation', $title_call['options']);
	}

	public function test_flag_on_omits_article_body_and_sends_the_transcript() {
		update_option('aips_conversational_generation', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = $this->make_service(array(self::BODY, 'A Title', 'An excerpt.'));
		$ai_service->conversation_supported = true;

		$this->run_generation($ai_service);

		$title_call = $ai_service->calls[1];

		$this->assertStringNotContainsString(self::BODY, $title_call['prompt']);
		$this->assertInstanceOf(AIPS_AI_Conversation::class, $title_call['options']['conversation']);

		// The content prompt and the body it produced are the two recorded turns.
		$turns = $title_call['options']['conversation']->to_array();
		$this->assertCount(2, $turns);
		$this->assertSame(self::BODY, $turns[1]['text']);

		// By the excerpt turn the title exchange has been appended too.
		$excerpt_call = $ai_service->calls[2];
		$this->assertStringNotContainsString(self::BODY, $excerpt_call['prompt']);
		$this->assertSame(4, count($excerpt_call['options']['conversation']->to_array()));
	}

	public function test_flag_on_falls_back_when_provider_cannot_replay_history() {
		update_option('aips_conversational_generation', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = $this->make_service(array(self::BODY, 'A Title', 'An excerpt.'));
		$ai_service->conversation_supported = false;

		$this->run_generation($ai_service);

		$title_call = $ai_service->calls[1];

		$this->assertStringContainsString(self::BODY, $title_call['prompt']);
		$this->assertArrayNotHasKey('conversation', $title_call['options']);
	}

	public function test_metadata_turn_collapses_title_and_excerpt_into_one_call() {
		update_option('aips_conversational_generation', 1);
		update_option('aips_conversational_metadata_turn', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = $this->make_service(array(self::BODY));
		$ai_service->conversation_supported = true;
		$ai_service->json_responses = array(
			array('title' => 'A Combined Title', 'excerpt' => 'A combined excerpt.'),
		);

		$post_manager = $this->run_generation($ai_service);

		// One text call for the body, one JSON call for everything else.
		$this->assertCount(1, $ai_service->calls);
		$this->assertCount(1, $ai_service->json_calls);
		$this->assertSame('A Combined Title', $post_manager->created_post_data['title']);
		$this->assertSame('A combined excerpt.', $post_manager->created_post_data['excerpt']);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_title']);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_excerpt']);
	}

	public function test_metadata_turn_failure_falls_back_to_separate_calls() {
		update_option('aips_conversational_generation', 1);
		update_option('aips_conversational_metadata_turn', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = $this->make_service(array(self::BODY, 'A Title', 'An excerpt.'));
		$ai_service->conversation_supported = true;
		$ai_service->json_responses = array(new WP_Error('json_parse_error', 'Malformed JSON.'));

		$post_manager = $this->run_generation($ai_service);

		// Body, then the per-component title and excerpt calls.
		$this->assertCount(3, $ai_service->calls);
		$this->assertSame('A Title', $post_manager->created_post_data['title']);
		$this->assertSame('An excerpt.', $post_manager->created_post_data['excerpt']);
		$this->assertTrue($post_manager->created_post_data['component_statuses']['post_title']);
	}

	public function test_metadata_turn_without_a_title_falls_back() {
		update_option('aips_conversational_generation', 1);
		update_option('aips_conversational_metadata_turn', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = $this->make_service(array(self::BODY, 'A Title', 'An excerpt.'));
		$ai_service->conversation_supported = true;
		$ai_service->json_responses = array(array('excerpt' => 'Only an excerpt.'));

		$post_manager = $this->run_generation($ai_service);

		$this->assertCount(3, $ai_service->calls);
		$this->assertSame('A Title', $post_manager->created_post_data['title']);
	}

	public function test_set_conversation_is_ignored_when_the_feature_is_off() {
		$ai_service = $this->make_service(array('An excerpt.'));
		$ai_service->conversation_supported = true;

		$generator = new AIPS_Generator(null, $ai_service);

		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange('Write the article.', self::BODY);

		// The setting is off, so seeding must be refused rather than silently
		// producing follow-up prompts the provider will never get history for.
		$generator->set_conversation($conversation);
		$generator->generate_excerpt('A Title', self::BODY);

		$this->assertStringContainsString(self::BODY, $ai_service->calls[0]['prompt']);
	}

	public function test_seeded_conversation_drives_excerpt_followup_prompt() {
		update_option('aips_conversational_generation', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$ai_service = $this->make_service(array('An excerpt.'));
		$ai_service->conversation_supported = true;

		$generator = new AIPS_Generator(null, $ai_service);

		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange('Write the article.', self::BODY);
		$conversation->add_exchange('Write the title.', 'A Title');

		$generator->set_conversation($conversation);
		$generator->generate_excerpt('A Title', self::BODY);

		$call = $ai_service->calls[0];

		$this->assertStringNotContainsString(self::BODY, $call['prompt']);
		$this->assertSame(4, count($call['options']['conversation']->to_array()));
	}

	/**
	 * Run a generation and return the post manager that captured the result.
	 *
	 * @param object $ai_service Recording AI service double.
	 * @return AIPS_Test_Conversational_Post_Manager
	 */
	private function run_generation($ai_service) {
		$post_manager = new AIPS_Test_Conversational_Post_Manager();

		$generator = new AIPS_Generator(
			null,
			$ai_service,
			null,
			null,
			null,
			$post_manager,
			new AIPS_Test_Conversational_History_Service()
		);

		$generator->generate_post($this->make_context());

		return $post_manager;
	}

	/**
	 * @param string[] $text_responses Ordered generate_text() responses.
	 * @return AIPS_Test_Conversational_AI_Service
	 */
	private function make_service(array $text_responses) {
		return new AIPS_Test_Conversational_AI_Service($text_responses);
	}

	private function make_context() {
		$template = (object) array(
			'id'                      => 321,
			'name'                    => 'Conversational Test',
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

		return new AIPS_Template_Context($template, null, 'Widgets', 'scheduled');
	}
}

/**
 * AI service double that records every prompt and the options it was called with.
 */
class AIPS_Test_Conversational_AI_Service implements AIPS_AI_Service_Interface {

	public $calls = array();
	public $json_calls = array();
	public $conversation_supported = true;
	public $json_responses = array();

	private $text_responses;

	public function __construct(array $text_responses) {
		$this->text_responses = $text_responses;
	}

	public function is_available() {
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		// Snapshot the transcript as it stood for this call; the object is mutated
		// afterwards, so storing the reference alone would assert nothing.
		$snapshot = $options;

		if (isset($options['conversation']) && $options['conversation'] instanceof AIPS_AI_Conversation) {
			$snapshot['conversation'] = AIPS_AI_Conversation::from_array($options['conversation']->to_array());
		}

		$this->calls[] = array('prompt' => $prompt, 'options' => $snapshot);

		if (empty($this->text_responses)) {
			return new WP_Error('unexpected_ai_call', 'Unexpected AI text call.');
		}

		return array_shift($this->text_responses);
	}

	public function generate_json($prompt, $options = array()) {
		$this->json_calls[] = array('prompt' => $prompt, 'options' => $options);

		if (empty($this->json_responses)) {
			return new WP_Error('unexpected_json_call', 'Unexpected AI JSON call.');
		}

		return array_shift($this->json_responses);
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
		return $this->conversation_supported;
	}

	public function get_call_log() {
		return array();
	}
}

class AIPS_Test_Conversational_Post_Manager {

	public $created_post_data = array();

	public function create_post($data) {
		$this->created_post_data = $data;

		return 777;
	}

	public function set_featured_image($post_id, $attachment_id) {
		return true;
	}

	public function update_generation_status_meta($post_id, $component_statuses, $incomplete) {
		return true;
	}
}

class AIPS_Test_Conversational_History_Service implements AIPS_History_Service_Interface {

	public $container;

	public function __construct() {
		$this->container = new AIPS_Test_Conversational_History_Container();
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
		return false;
	}

	public function find_incomplete($type, $metadata = array()) {
		return array();
	}
}

class AIPS_Test_Conversational_History_Container {

	public $records = array();

	public function with_session($context) {
		return $this;
	}

	public function get_id() {
		return 1;
	}

	public function record($type, $message = '', $request = null, $response = null, $context = array()) {
		$this->records[] = array('type' => $type, 'message' => $message, 'context' => $context);

		return $this;
	}

	public function record_error($message, $context = array()) {
		return $this->record('error', $message, null, null, $context);
	}

	public function complete_success($data = array()) {
		return $this;
	}

	public function complete_failure($message, $data = array()) {
		return $this;
	}
}
