<?php
/**
 * Tests for AIPS_AI_Conversation and its provider-side translation.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_AI_Conversation extends WP_UnitTestCase {

	public function test_add_exchange_records_alternating_turns() {
		$conversation = new AIPS_AI_Conversation();

		$this->assertTrue($conversation->add_exchange('Write the article.', 'The body.'));

		$turns = $conversation->get_turns();

		$this->assertCount(2, $turns);
		$this->assertSame(AIPS_AI_Conversation::ROLE_USER, $turns[0]['role']);
		$this->assertSame('Write the article.', $turns[0]['text']);
		$this->assertSame(AIPS_AI_Conversation::ROLE_MODEL, $turns[1]['role']);
		$this->assertSame(1, $conversation->count_exchanges());
	}

	public function test_add_exchange_rejects_half_exchanges() {
		$conversation = new AIPS_AI_Conversation();

		$this->assertFalse($conversation->add_exchange('Prompt', ''));
		$this->assertFalse($conversation->add_exchange('', 'Response'));
		$this->assertTrue($conversation->is_empty());
	}

	public function test_consecutive_same_role_turns_are_rejected() {
		$conversation = new AIPS_AI_Conversation();

		$this->assertTrue($conversation->add_user('First prompt.'));
		$this->assertFalse($conversation->add_user('Second prompt.'));
		$this->assertTrue($conversation->add_model('A response.'));
		$this->assertFalse($conversation->add_model('Another response.'));

		$this->assertSame(1, $conversation->count_exchanges());
	}

	public function test_transcript_must_open_on_a_user_turn() {
		$conversation = new AIPS_AI_Conversation();

		$this->assertFalse($conversation->add_model('Model speaks first.'));
		$this->assertTrue($conversation->is_empty());
	}

	public function test_trailing_user_turn_is_dropped() {
		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange('Write the article.', 'The body.');
		$conversation->add_user('Now the title.');

		// The pending prompt is sent separately, so an incomplete trailing turn must
		// not appear in the history or the SDK would see two user messages in a row.
		$turns = $conversation->get_turns();

		$this->assertCount(2, $turns);
		$this->assertSame(AIPS_AI_Conversation::ROLE_MODEL, $turns[1]['role']);
	}

	public function test_empty_and_non_string_text_is_ignored() {
		$conversation = new AIPS_AI_Conversation();

		$this->assertFalse($conversation->add_user('   '));
		$this->assertFalse($conversation->add_user(array('not a string')));
		$this->assertTrue($conversation->is_empty());
	}

	public function test_round_trips_through_array() {
		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange('Write the article.', 'The body.');
		$conversation->add_exchange('Now the title.', 'A Title');

		$restored = AIPS_AI_Conversation::from_array($conversation->to_array());

		$this->assertSame($conversation->to_array(), $restored->to_array());
		$this->assertSame(2, $restored->count_exchanges());
	}

	public function test_from_array_skips_malformed_entries() {
		$restored = AIPS_AI_Conversation::from_array(array(
			array('role' => 'user', 'text' => 'Prompt'),
			array('nonsense' => true),
			'not an array',
			array('role' => 'model', 'text' => 'Response'),
		));

		$this->assertSame(1, $restored->count_exchanges());
	}

	public function test_from_array_tolerates_non_array_input() {
		$this->assertTrue(AIPS_AI_Conversation::from_array(null)->is_empty());
		$this->assertTrue(AIPS_AI_Conversation::from_array('nope')->is_empty());
	}

	public function test_estimated_tokens_covers_every_turn() {
		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange(str_repeat('a', 400), str_repeat('b', 400));

		// 800 characters at the shared 4-chars-per-token approximation.
		$this->assertSame(200, $conversation->estimated_tokens());
	}

	public function test_meow_provider_maps_model_role_to_assistant() {
		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange('Write the article.', 'The body.');

		$provider = new AIPS_Meow_AI_Provider();

		$reflection = new ReflectionMethod($provider, 'map_params');
		$reflection->setAccessible(true);

		$native = $reflection->invoke($provider, array('messages' => $conversation->to_array()));

		$this->assertSame(
			array(
				array('role' => 'user', 'content' => 'Write the article.'),
				array('role' => 'assistant', 'content' => 'The body.'),
			),
			$native['messages']
		);
	}

	public function test_meow_provider_passes_through_native_format_messages() {
		$provider = new AIPS_Meow_AI_Provider();

		$reflection = new ReflectionMethod($provider, 'map_params');
		$reflection->setAccessible(true);

		$native_input = array(array('role' => 'assistant', 'content' => 'Already native.'));
		$native = $reflection->invoke($provider, array('messages' => $native_input));

		// 'messages' was a documented free-form pass-through before conversation
		// support existed; unrecognised entries must not be silently dropped.
		$this->assertSame($native_input, $native['messages']);
	}

	public function test_meow_json_defers_to_text_fallback_when_history_is_present() {
		global $mwai;
		$original_mwai = $mwai;

		// simpleJsonQuery cannot carry history, so the provider must return null to
		// request the text-based fallback rather than answering a follow-up prompt
		// with no article in context.
		$mwai = new AIPS_Test_Conversation_Meow_Stub();

		try {
			$provider = new AIPS_Meow_AI_Provider();

			$conversation = new AIPS_AI_Conversation();
			$conversation->add_exchange('Write the article.', 'The body.');

			$with_history = $provider->generate_json('Now the metadata.', array(
				'messages' => $conversation->to_array(),
			));

			$this->assertNull($with_history);
			$this->assertSame(0, $mwai->json_call_count);

			// Without history the native path is still used.
			$without_history = $provider->generate_json('Give me JSON.', array());

			$this->assertSame(array('ok' => true), $without_history);
			$this->assertSame(1, $mwai->json_call_count);
		} finally {
			$mwai = $original_mwai;
		}
	}

	public function test_wp_ai_client_rejects_malformed_history_instead_of_dropping_it() {
		global $aips_wp_ai_client_test_builder;

		if (!class_exists('WordPress\\AiClient\\Messages\\DTO\\UserMessage')) {
			$this->markTestSkipped('WordPress AI Client message DTOs are not available.');
		}

		$builder = new AIPS_Test_WP_AI_Client_Builder();
		$aips_wp_ai_client_test_builder = $builder;

		$this->expectException(Exception::class);
		$this->expectExceptionMessageMatches('/invalid_conversation_history/');

		// A follow-up prompt omits the article, so sending it without the history
		// would silently yield a fabricated answer rather than an error.
		(new AIPS_WP_AI_Client_Provider())->generate_text('Now the title.', array(
			'messages' => array(
				array('role' => 'user', 'text' => 'Write the article.'),
				array('role' => 'model'),
			),
		));
	}

	public function test_ai_service_expands_conversation_into_messages_param() {
		$conversation = new AIPS_AI_Conversation();
		$conversation->add_exchange('Write the article.', 'The body.');

		$service = new AIPS_AI_Service();

		$reflection = new ReflectionMethod($service, 'prepare_options');
		$reflection->setAccessible(true);

		$params = $reflection->invoke($service, array('conversation' => $conversation), 'Now the title.');

		$this->assertSame($conversation->to_array(), $params['messages']);
	}

	public function test_ai_service_omits_messages_for_an_empty_conversation() {
		$service = new AIPS_AI_Service();

		$reflection = new ReflectionMethod($service, 'prepare_options');
		$reflection->setAccessible(true);

		$params = $reflection->invoke($service, array('conversation' => new AIPS_AI_Conversation()), 'Prompt');

		$this->assertArrayNotHasKey('messages', $params);
	}
}

/**
 * Minimal AI Engine stand-in exposing the two query methods the provider probes.
 */
class AIPS_Test_Conversation_Meow_Stub {

	public $json_call_count = 0;

	public function simpleTextQuery($prompt, $params = array()) {
		return '{"ok":true}';
	}

	public function simpleJsonQuery($prompt, $params = array()) {
		$this->json_call_count++;

		return array('ok' => true);
	}
}
