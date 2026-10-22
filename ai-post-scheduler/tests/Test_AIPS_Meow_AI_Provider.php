<?php
/**
 * Tests for AIPS_Meow_AI_Provider.
 *
 * Covers availability gating, parameter translation (canonical → Meow-native),
 * generate_text/json/image delegation, and error-code extraction. Every test
 * uses the $GLOBALS['mwai'] injection pattern already established in this suite
 * (see Test_AIPS_AI_Service.php / Test_AIPS_AI_Conversation.php).
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Meow_AI_Provider extends WP_UnitTestCase {

	private $original_mwai;
	private $original_mwai_core;

	public function setUp(): void {
		parent::setUp();
		global $mwai, $mwai_core;
		$this->original_mwai      = $mwai;
		$this->original_mwai_core = $mwai_core;
		AIPS_AI_Provider_Factory::reset_cache();
	}

	public function tearDown(): void {
		global $mwai, $mwai_core;
		$mwai      = $this->original_mwai;
		$mwai_core = $this->original_mwai_core;
		AIPS_AI_Provider_Factory::reset_cache();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Availability
	// -------------------------------------------------------------------------

	public function test_is_available_returns_true_when_mwai_is_set() {
		global $mwai;
		$mwai = new AIPS_Test_Meow_Engine_Stub();

		$this->assertTrue( ( new AIPS_Meow_AI_Provider() )->is_available() );
	}

	public function test_is_available_returns_false_when_mwai_is_null() {
		global $mwai;
		$mwai = null;

		$provider = new AIPS_Meow_AI_Provider();
		$this->assertFalse( $provider->is_available() );
	}

	public function test_get_unavailable_reason_is_non_empty_when_mwai_absent() {
		global $mwai;
		$mwai = null;

		$this->assertNotEmpty( ( new AIPS_Meow_AI_Provider() )->get_unavailable_reason() );
	}

	// -------------------------------------------------------------------------
	// Parameter mapping (canonical → Meow-native)
	// -------------------------------------------------------------------------

	public function test_map_params_translates_canonical_keys_to_meow_native() {
		$provider   = new AIPS_Meow_AI_Provider();
		$reflection = new ReflectionMethod( $provider, 'map_params' );
		$reflection->setAccessible( true );

		$native = $reflection->invoke( $provider, array(
			'model'        => 'gpt-4',
			'env_id'       => 'env-123',
			'max_tokens'   => 2000,
			'temperature'  => 0.7,
			'context'      => 'Voice style.',
			'instructions' => 'Output HTML.',
		) );

		$this->assertSame( 'gpt-4',        $native['model'] );
		$this->assertSame( 'env-123',      $native['envId'] );     // canonical env_id → envId
		$this->assertSame( 2000,           $native['maxTokens'] ); // canonical max_tokens → maxTokens
		$this->assertSame( 0.7,            $native['temperature'] );
		$this->assertSame( 'Voice style.', $native['context'] );
		$this->assertSame( 'Output HTML.', $native['instructions'] );

		// Canonical keys must not leak through alongside the native names.
		$this->assertArrayNotHasKey( 'env_id',    $native );
		$this->assertArrayNotHasKey( 'max_tokens', $native );
	}

	public function test_map_params_tolerates_legacy_camel_case_env_id() {
		$provider   = new AIPS_Meow_AI_Provider();
		$reflection = new ReflectionMethod( $provider, 'map_params' );
		$reflection->setAccessible( true );

		$native = $reflection->invoke( $provider, array( 'envId' => 'env-legacy' ) );

		$this->assertSame( 'env-legacy', $native['envId'] );
		// The key must appear exactly once (not duplicated under env_id as well).
		$env_keys = array_filter( array_keys( $native ), fn( $k ) => stripos( $k, 'env' ) !== false );
		$this->assertCount( 1, $env_keys );
	}

	public function test_map_params_canonical_env_id_takes_priority_over_legacy_envId() {
		$provider   = new AIPS_Meow_AI_Provider();
		$reflection = new ReflectionMethod( $provider, 'map_params' );
		$reflection->setAccessible( true );

		$native = $reflection->invoke( $provider, array(
			'env_id' => 'canonical',
			'envId'  => 'legacy',
		) );

		$this->assertSame( 'canonical', $native['envId'] );
	}

	public function test_map_params_translates_model_role_to_assistant_for_meow() {
		$provider   = new AIPS_Meow_AI_Provider();
		$reflection = new ReflectionMethod( $provider, 'map_params' );
		$reflection->setAccessible( true );

		$native = $reflection->invoke( $provider, array(
			'messages' => array(
				array( 'role' => AIPS_AI_Conversation::ROLE_USER,  'text' => 'Write an article.' ),
				array( 'role' => AIPS_AI_Conversation::ROLE_MODEL, 'text' => 'The article body.' ),
			),
		) );

		$this->assertArrayHasKey( 'messages', $native );
		$this->assertSame( 'user',      $native['messages'][0]['role'] );
		$this->assertSame( 'assistant', $native['messages'][1]['role'] );
		$this->assertSame( 'Write an article.', $native['messages'][0]['content'] );
		$this->assertSame( 'The article body.', $native['messages'][1]['content'] );
	}

	// -------------------------------------------------------------------------
	// generate_text
	// -------------------------------------------------------------------------

	public function test_generate_text_delegates_to_simple_text_query_with_mapped_params() {
		global $mwai;
		$stub = new AIPS_Test_Meow_Engine_Stub();
		$mwai = $stub;

		$result = ( new AIPS_Meow_AI_Provider() )->generate_text( 'Write something.', array(
			'model'      => 'gpt-4',
			'max_tokens' => 1000,
			'temperature' => 0.5,
		) );

		$this->assertSame( 'The generated text.', $result );
		$this->assertSame( 'Write something.', $stub->last_text_prompt );
		$this->assertSame( 'gpt-4', $stub->last_text_params['model'] );
		$this->assertSame( 1000,    $stub->last_text_params['maxTokens'] );
		$this->assertSame( 0.5,     $stub->last_text_params['temperature'] );
	}

	// -------------------------------------------------------------------------
	// generate_json
	// -------------------------------------------------------------------------

	public function test_generate_json_returns_null_when_simple_json_query_unavailable() {
		global $mwai;
		// Stub without simpleJsonQuery to simulate a Meow version that lacks the method.
		$mwai = new class {
			public function simpleTextQuery( $prompt, $params = array() ) {
				return '{}';
			}
		};

		$result = ( new AIPS_Meow_AI_Provider() )->generate_json( 'Return JSON.', array() );
		$this->assertNull( $result, 'Provider must signal text-based fallback by returning null.' );
	}

	public function test_generate_json_returns_null_when_conversation_history_is_present() {
		global $mwai;
		$stub = new AIPS_Test_Meow_Engine_Stub();
		$mwai = $stub;

		// simpleJsonQuery cannot carry conversation history; the provider must defer
		// to the service-layer text-based extraction by returning null.
		$result = ( new AIPS_Meow_AI_Provider() )->generate_json( 'Metadata prompt.', array(
			'messages' => array(
				array( 'role' => 'user', 'text' => 'Write the article.' ),
				array( 'role' => 'model', 'text' => 'The article body.' ),
			),
		) );

		$this->assertNull( $result );
		$this->assertSame( 0, $stub->json_call_count, 'simpleJsonQuery must not be called when history is present.' );
	}

	public function test_generate_json_delegates_to_simple_json_query_with_model_and_env_id_only() {
		global $mwai;
		$stub = new AIPS_Test_Meow_Engine_Stub();
		$mwai = $stub;

		$result = ( new AIPS_Meow_AI_Provider() )->generate_json( 'Return JSON.', array(
			'model'       => 'gpt-4',
			'env_id'      => 'env-123',
			'max_tokens'  => 2000,
			'temperature' => 0.5,
		) );

		$this->assertSame( array( 'ok' => true ), $result );
		$this->assertSame( 1, $stub->json_call_count );
		// simpleJsonQuery accepts only model and env_id; other keys must be stripped.
		$this->assertArrayHasKey( 'model',  $stub->last_json_params );
		$this->assertArrayHasKey( 'env_id', $stub->last_json_params );
		$this->assertArrayNotHasKey( 'max_tokens',   $stub->last_json_params );
		$this->assertArrayNotHasKey( 'maxTokens',    $stub->last_json_params );
		$this->assertArrayNotHasKey( 'temperature',  $stub->last_json_params );
	}

	// -------------------------------------------------------------------------
	// generate_image
	// -------------------------------------------------------------------------

	public function test_generate_image_returns_string_url() {
		global $mwai;
		$stub                 = new AIPS_Test_Meow_Engine_Stub();
		$stub->image_response = 'https://example.com/image.png';
		$mwai                 = $stub;

		$result = ( new AIPS_Meow_AI_Provider() )->generate_image( 'A photo of a garden.', array() );

		$this->assertSame( 'https://example.com/image.png', $result );
	}

	public function test_generate_image_unwraps_single_element_array_to_url() {
		global $mwai;
		$stub                 = new AIPS_Test_Meow_Engine_Stub();
		$stub->image_response = array( 'https://example.com/image.png' );
		$mwai                 = $stub;

		$result = ( new AIPS_Meow_AI_Provider() )->generate_image( 'A photo.', array() );

		$this->assertSame( 'https://example.com/image.png', $result );
	}

	public function test_generate_image_throws_on_empty_array_response() {
		global $mwai;
		$stub                 = new AIPS_Test_Meow_Engine_Stub();
		$stub->image_response = array();
		$mwai                 = $stub;

		$this->expectException( Exception::class );

		( new AIPS_Meow_AI_Provider() )->generate_image( 'A photo.', array() );
	}

	// -------------------------------------------------------------------------
	// Capability flags
	// -------------------------------------------------------------------------

	public function test_supports_conversation_returns_true_when_mwai_available() {
		global $mwai;
		$mwai = new AIPS_Test_Meow_Engine_Stub();

		$this->assertTrue( ( new AIPS_Meow_AI_Provider() )->supports_conversation() );
	}

	public function test_supports_conversation_returns_false_when_mwai_absent() {
		global $mwai;
		$mwai = null;

		$this->assertFalse( ( new AIPS_Meow_AI_Provider() )->supports_conversation() );
	}

	public function test_supports_embeddings_requires_query_embed_class_and_mwai_core() {
		global $mwai_core;
		$original = $mwai_core;

		$mwai_core = null;
		$this->assertFalse( ( new AIPS_Meow_AI_Provider() )->supports_embeddings() );

		$mwai_core = $original;
	}

	public function test_supports_native_json_requires_simple_json_query_method() {
		global $mwai;

		$mwai = new AIPS_Test_Meow_Engine_Stub(); // has simpleJsonQuery
		$this->assertTrue( ( new AIPS_Meow_AI_Provider() )->supports_native_json() );

		$mwai = new class {
			public function simpleTextQuery( $prompt, $params = array() ) {
				return '';
			}
		};
		$this->assertFalse( ( new AIPS_Meow_AI_Provider() )->supports_native_json() );
	}

	// -------------------------------------------------------------------------
	// Error-code extraction
	// -------------------------------------------------------------------------

	public function test_extract_error_code_delegates_to_resilience_service() {
		$provider = new AIPS_Meow_AI_Provider();

		// Known patterns must match what the resilience service classifies.
		$this->assertSame(
			AIPS_Resilience_Service::extract_error_code_from_message( 'rate limit exceeded' ),
			$provider->extract_error_code( 'rate limit exceeded' )
		);

		// Unrecognised messages must produce an empty string (not a fabricated code).
		$this->assertSame(
			AIPS_Resilience_Service::extract_error_code_from_message( 'some completely unknown error text' ),
			$provider->extract_error_code( 'some completely unknown error text' )
		);
	}
}

// -----------------------------------------------------------------------------
// Test stubs
// -----------------------------------------------------------------------------

/**
 * Minimal Meow AI Engine stand-in that captures call arguments for assertion.
 */
class AIPS_Test_Meow_Engine_Stub {

	public $last_text_prompt  = null;
	public $last_text_params  = array();
	public $text_response     = 'The generated text.';

	public $json_call_count   = 0;
	public $last_json_prompt  = null;
	public $last_json_params  = array();
	public $json_response     = array( 'ok' => true );

	/** @var string|array */
	public $image_response = 'https://example.com/image.png';

	public function simpleTextQuery( $prompt, $params = array() ) {
		$this->last_text_prompt = $prompt;
		$this->last_text_params = $params;
		return $this->text_response;
	}

	public function simpleJsonQuery( $prompt, $params = array() ) {
		$this->json_call_count++;
		$this->last_json_prompt = $prompt;
		$this->last_json_params = $params;
		return $this->json_response;
	}

	public function simpleImageQuery( $prompt, $params = array() ) {
		return $this->image_response;
	}
}
