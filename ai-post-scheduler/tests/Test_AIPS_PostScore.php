<?php
/**
 * Tests for internal post scoring and targeted revision.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_PostScore_Fake_AI_Service implements AIPS_AI_Service_Interface {
	public $responses = array();
	public $calls = array();

	public function __construct( array $responses ) {
		$this->responses = $responses;
	}

	public function is_available() {
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		$this->calls[] = array(
			'prompt'  => $prompt,
			'options' => $options,
		);

		if (empty($this->responses)) {
			return new WP_Error('no_fake_response', 'No fake response configured.');
		}

		$response = array_shift($this->responses);
		return $response;
	}

	public function generate_json($prompt, $options = array()) {
		$response = $this->generate_text($prompt, $options);
		if (is_wp_error($response)) {
			return $response;
		}
		return json_decode((string) $response, true);
	}

	public function generate_image($prompt, $options = array()) {
		return '';
	}

	public function get_call_log() {
		return $this->calls;
	}
}


class AIPS_PostScore_Fake_History {
	public $records = array();

	public function record($type, $message, $data = null, $response = null, $meta = array()) {
		$this->records[] = array(
			'type' => $type,
			'message' => $message,
			'data' => $data,
			'response' => $response,
			'meta' => $meta,
		);
	}
}

class AIPS_PostScore_Fake_Generation_Logger {
	public $warnings = array();

	public function warning($message, $context = array()) {
		$this->warnings[] = array(
			'message' => $message,
			'context' => $context,
		);
	}
}

class Test_AIPS_PostScore extends WP_UnitTestCase {

	private function make_context() {
		return (object) array(
			'topic' => 'Practical AI editorial workflows',
			'prompt_template' => 'Write a practical article for content teams about AI editorial workflows.',
		);
	}

	private function json_response( array $scores, array $guidance = array(), $summary = 'Summary.' ) {
		return wp_json_encode(array(
			'scores'   => $scores,
			'guidance' => $guidance,
			'summary'  => $summary,
		));
	}

	private function passing_scores() {
		return array(
			'coherence' => 9,
			'specificity' => 8,
			'originality' => 8,
			'citations_completeness' => 8,
			'reading_grade' => 8,
			'fluff' => 1,
			'hallucination_risk' => 1,
			'alignment' => 9,
		);
	}

	public function test_result_serializes_pass_fail_and_guidance() {
		$result = new AIPS_PostScore_Result(
			array('coherence' => 8, 'fluff' => 2),
			75.0,
			70,
			array('Add concrete examples'),
			'Good but could be more specific.'
		);

		$this->assertTrue($result->passed());
		$this->assertSame(8, $result->get_dimension_score('coherence'));
		$this->assertNull($result->get_dimension_score('missing'));
		$this->assertSame(array('Add concrete examples'), $result->get_guidance());

		$round_trip = AIPS_PostScore_Result::from_array($result->to_array());
		$this->assertSame(75.0, $round_trip->get_overall_score());
		$this->assertTrue($round_trip->passed());
	}

	public function test_prompt_builder_includes_scoring_dimensions_and_content() {
		$builder = new AIPS_Prompt_Builder_Post_Score();
		$prompt = $builder->build($this->make_context(), 'Draft body with claims and examples.', 'Draft Title');

		$this->assertStringContainsString('## Generation Configuration', $prompt);
		$this->assertStringContainsString('Practical AI editorial workflows', $prompt);
		$this->assertStringContainsString('Draft Title', $prompt);
		$this->assertStringContainsString('citations_completeness', $prompt);
		$this->assertStringContainsString('hallucination_risk', $prompt);
		$this->assertStringContainsString('Return ONLY valid JSON', $prompt);
	}

	public function test_service_calculates_overall_score_with_penalty_dimensions_inverted() {
		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			$this->json_response($this->passing_scores(), array(), 'Strong draft.'),
		));
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$result = $service->score($this->make_context(), 'Specific draft content.', 'Draft Title');

		$this->assertInstanceOf(AIPS_PostScore_Result::class, $result);
		$this->assertTrue($result->passed());
		$this->assertSame(85.0, $result->get_overall_score());
		$this->assertSame('post_score', $ai->calls[0]['options']['request_type']);
	}


	public function test_service_returns_error_for_malformed_score_response() {
		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			'This is not JSON.',
		));
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$result = $service->score($this->make_context(), 'Draft body.', 'Draft Title');

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('post_score_invalid_response', $result->get_error_code());
	}

	public function test_process_generated_draft_is_disabled_by_default() {
		update_option('aips_post_score_auto_enabled', 0);
		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			$this->json_response($this->passing_scores(), array(), 'Strong draft.'),
		));
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$payload = $service->process_generated_draft($this->make_context(), 'Original draft.', 'Draft Title');

		$this->assertFalse($payload['enabled']);
		$this->assertSame('Original draft.', $payload['content']);
		$this->assertSame(array(), $ai->calls);
	}

	public function test_service_adds_default_guidance_when_low_score_has_none() {
		$low_scores = array(
			'coherence' => 6,
			'specificity' => 3,
			'originality' => 8,
			'citations_completeness' => 4,
			'reading_grade' => 8,
			'fluff' => 8,
			'hallucination_risk' => 2,
			'alignment' => 8,
		);
		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			$this->json_response($low_scores, array(), 'Needs targeted revision.'),
		));
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$result = $service->score($this->make_context(), 'Generic draft.', 'Draft Title');

		$this->assertInstanceOf(AIPS_PostScore_Result::class, $result);
		$this->assertFalse($result->passed());
		$this->assertNotEmpty($result->get_guidance());
		$this->assertStringContainsString('concrete examples', implode(' ', $result->get_guidance()));
	}

	public function test_service_runs_targeted_revision_until_threshold_passes() {
		$low_scores = array(
			'coherence' => 6,
			'specificity' => 3,
			'originality' => 4,
			'citations_completeness' => 4,
			'reading_grade' => 7,
			'fluff' => 8,
			'hallucination_risk' => 6,
			'alignment' => 6,
		);

		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			$this->json_response($low_scores, array('Add concrete examples', 'Cite sources for all statistics'), 'Needs revision.'),
			'Revised post with concrete examples and citations.',
			$this->json_response($this->passing_scores(), array(), 'Revised draft is strong.'),
		));
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$revision = $service->score_and_revise_content($this->make_context(), 'Generic draft.', 'Draft Title');

		$this->assertIsArray($revision);
		$this->assertSame('Revised post with concrete examples and citations.', $revision['content']);
		$this->assertSame(1, $revision['revision_count']);
		$this->assertTrue($revision['revised']);
		$this->assertInstanceOf(AIPS_PostScore_Result::class, $revision['result']);
		$this->assertTrue($revision['result']->passed());
		$this->assertSame('post_score_revision', $ai->calls[1]['options']['request_type']);
		$this->assertStringContainsString('Add concrete examples', $ai->calls[1]['prompt']);
	}


	public function test_service_process_generated_draft_records_history_and_persists_score_payload() {
		update_option('aips_post_score_auto_enabled', 1);
		$low_scores = array(
			'coherence' => 6,
			'specificity' => 3,
			'originality' => 4,
			'citations_completeness' => 4,
			'reading_grade' => 7,
			'fluff' => 8,
			'hallucination_risk' => 6,
			'alignment' => 6,
		);
		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			$this->json_response($low_scores, array('Add concrete examples'), 'Needs revision.'),
			'Revised post with concrete examples and citations.',
			$this->json_response($this->passing_scores(), array(), 'Revised draft is strong.'),
		));
		$history = new AIPS_PostScore_Fake_History();
		$logger = new AIPS_PostScore_Fake_Generation_Logger();
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$payload = $service->process_generated_draft($this->make_context(), 'Generic draft.', 'Draft Title', $history, $logger);

		$this->assertSame('Revised post with concrete examples and citations.', $payload['content']);
		$this->assertSame(1, $payload['revision_count']);
		$this->assertCount(1, $history->records);
		$this->assertSame('post_score_passed', $history->records[0]['type']);
		$this->assertSame(array(), $logger->warnings);

		$post_id = self::factory()->post->create(array('post_title' => 'Scored Generated Post'));
		$service->save_generation_score_to_post($post_id, $payload);

		$this->assertIsArray(get_post_meta($post_id, AIPS_PostScore_Service::SCORE_META_KEY, true));
		$this->assertSame('1', (string) get_post_meta($post_id, AIPS_PostScore_Service::REVISION_COUNT_META_KEY, true));
	}

	public function test_service_process_generated_draft_logs_warning_and_keeps_content_on_error() {
		update_option('aips_post_score_auto_enabled', 1);
		$ai = new AIPS_PostScore_Fake_AI_Service(array(
			new WP_Error('ai_unavailable', 'AI unavailable.'),
		));
		$logger = new AIPS_PostScore_Fake_Generation_Logger();
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());

		$payload = $service->process_generated_draft($this->make_context(), 'Original draft.', 'Draft Title', null, $logger);

		$this->assertSame('Original draft.', $payload['content']);
		$this->assertInstanceOf(WP_Error::class, $payload['error']);
		$this->assertCount(1, $logger->warnings);
		$this->assertSame('AI unavailable.', $logger->warnings[0]['context']['error']);
	}

	public function test_controller_registers_actions_and_returns_stored_result() {
		$ai = new AIPS_PostScore_Fake_AI_Service(array());
		$service = new AIPS_PostScore_Service($ai, new AIPS_Prompt_Builder_Post_Score());
		$controller = new AIPS_PostScore_Controller($service);
		$post_id = self::factory()->post->create(array('post_title' => 'Scored Post'));
		$service->save_score_to_post($post_id, new AIPS_PostScore_Result($this->passing_scores(), 85.0, 70, array(), 'Stored result.'));

		$this->assertSame(10, has_action('wp_ajax_aips_post_score_get_result', array($controller, 'ajax_get_result')));
		$this->assertSame('AIPS_PostScore_Controller', AIPS_Ajax_Registry::get_controller_for('aips_post_score_get_result'));

		$user_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'post_id' => $post_id,
		);

		ob_start();
		try {
			$controller->ajax_get_result();
		} catch (WPAjaxDieContinueException $e) {
			// Expected in the WordPress AJAX test environment.
		} catch (WPAjaxDieStopException $e) {
			// Also acceptable depending on the die handler path.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertSame(85.0, $response['data']['overall_score']);
		$this->assertTrue($response['data']['passed']);
	}
}
