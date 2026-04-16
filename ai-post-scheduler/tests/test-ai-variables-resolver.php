<?php
/**
 * Tests for AIPS_AI_Variables_Resolver
 *
 * Verifies that the resolver correctly extracts AI variables from prompts,
 * delegates to the AI callback, parses responses, and handles error paths.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_AI_Variables_Resolver extends WP_UnitTestCase {

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * @var AIPS_Generation_Logger
	 */
	private $logger;

	/**
	 * Messages captured by the mock logger.
	 *
	 * @var array
	 */
	private $log_messages;

	public function setUp(): void {
		parent::setUp();

		$this->template_processor = new AIPS_Template_Processor();
		$this->log_messages       = array();
		$log_messages_ref         = &$this->log_messages;

		$mock_inner_logger = new class( $log_messages_ref ) implements AIPS_Logger_Interface {
			private $log_messages;

			public function __construct( &$log_messages ) {
				$this->log_messages = &$log_messages;
			}

			public function log( $message, $level = 'info', $context = array() ) {
				$this->log_messages[] = array(
					'message' => $message,
					'level'   => $level,
				);
			}

			public function addSeparator( $text ) {}
		};

		// AIPS_Generation_Logger needs logger, history_repository, and session.
		// History repo and session are only used when ai_data contains 'type'/'prompt'
		// keys; the resolver never passes those keys, so null is safe here.
		$mock_session = new class {
			public function log_ai_call() {}
			public function add_error() {}
		};

		$this->logger = new AIPS_Generation_Logger( $mock_inner_logger, null, $mock_session );
	}

	// -------------------------------------------------------------------------
	// resolve_from_context() — no AI variables in prompt
	// -------------------------------------------------------------------------

	/**
	 * When the title prompt contains only system variables, resolve_from_context()
	 * should return an empty array without calling the AI callback.
	 */
	public function test_resolve_from_context_returns_empty_when_no_ai_variables() {
		$ai_called = false;
		$callback  = function( $prompt, $options, $type ) use ( &$ai_called ) {
			$ai_called = true;
			return '{}';
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		$template = (object) array(
			'title_prompt'    => 'Write a title about {{topic}} for {{date}}',
			'prompt_template' => 'Write an article.',
		);
		$context  = new AIPS_Template_Context( $template, null, null );

		$result = $resolver->resolve_from_context( $context, 'Some generated content.' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
		$this->assertFalse( $ai_called, 'AI callback must not be invoked when there are no AI variables.' );
	}

	// -------------------------------------------------------------------------
	// resolve_from_context() — successful resolution
	// -------------------------------------------------------------------------

	/**
	 * When AI returns valid JSON, resolve_from_context() should return the
	 * correctly parsed and sanitized values for every AI variable.
	 */
	public function test_resolve_from_context_returns_resolved_values_on_success() {
		$callback = function( $prompt, $options, $type ) {
			return '{"ProductAngle":"Security-First Approach","FrameworkChoice":"Laravel"}';
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		$template = (object) array(
			'title_prompt'    => 'Compare {{ProductAngle}} vs {{FrameworkChoice}}',
			'prompt_template' => 'Write a deep-dive comparison.',
		);
		$context  = new AIPS_Template_Context( $template, null, null );

		$result = $resolver->resolve_from_context( $context, 'Article content here.' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'ProductAngle', $result );
		$this->assertArrayHasKey( 'FrameworkChoice', $result );
		$this->assertSame( 'Security-First Approach', $result['ProductAngle'] );
		$this->assertSame( 'Laravel', $result['FrameworkChoice'] );
	}

	// -------------------------------------------------------------------------
	// resolve_from_context() — WP_Error from AI callback
	// -------------------------------------------------------------------------

	/**
	 * When the AI callback returns a WP_Error, resolve_from_context() should
	 * return an empty array and log a warning message.
	 */
	public function test_resolve_from_context_returns_empty_on_wp_error() {
		$callback = function( $prompt, $options, $type ) {
			return new WP_Error( 'ai_failed', 'Service unavailable' );
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		$template = (object) array(
			'title_prompt'    => 'A title about {{CustomTopic}}',
			'prompt_template' => 'Write an article.',
		);
		$context  = new AIPS_Template_Context( $template, null, null );

		$result = $resolver->resolve_from_context( $context, 'Article body.' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// A warning must have been logged.
		$has_warning = false;
		foreach ( $this->log_messages as $entry ) {
			if ( $entry['level'] === 'warning' ) {
				$has_warning = true;
				break;
			}
		}
		$this->assertTrue( $has_warning, 'A warning must be logged when the AI callback returns WP_Error.' );
	}

	// -------------------------------------------------------------------------
	// resolve_from_context() — unparseable AI response
	// -------------------------------------------------------------------------

	/**
	 * When the AI callback returns a response that cannot be parsed as JSON,
	 * resolve_from_context() should return an empty array and log a warning.
	 */
	public function test_resolve_from_context_returns_empty_on_unparseable_response() {
		$callback = function( $prompt, $options, $type ) {
			return 'Sorry, I cannot help with that.'; // Plain text — not JSON.
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		$template = (object) array(
			'title_prompt'    => 'Write about {{DynamicAngle}}',
			'prompt_template' => 'Write an article.',
		);
		$context  = new AIPS_Template_Context( $template, null, null );

		$result = $resolver->resolve_from_context( $context, 'Content.' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		$has_warning = false;
		foreach ( $this->log_messages as $entry ) {
			if ( $entry['level'] === 'warning' ) {
				$has_warning = true;
				break;
			}
		}
		$this->assertTrue( $has_warning, 'A warning must be logged when the AI response is unparseable.' );
	}

	// -------------------------------------------------------------------------
	// resolve_from_context() — voice title prompt takes precedence
	// -------------------------------------------------------------------------

	/**
	 * When a voice with a title_prompt is attached, the voice prompt supersedes
	 * the template title_prompt as the source of AI variables.
	 */
	public function test_resolve_from_context_prefers_voice_title_prompt() {
		$received_prompt = null;
		$callback        = function( $prompt, $options, $type ) use ( &$received_prompt ) {
			$received_prompt = $prompt;
			return '{"VoiceTopic":"Voice-driven topic"}';
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		// Template has no AI variables; voice does.
		$template = (object) array(
			'title_prompt'    => 'A plain title',
			'prompt_template' => 'Write an article.',
		);
		$voice = (object) array(
			'id'           => 5,
			'title_prompt' => 'Write a title about {{VoiceTopic}}',
		);
		$context = new AIPS_Template_Context( $template, $voice, null );

		$result = $resolver->resolve_from_context( $context, 'Content.' );

		$this->assertArrayHasKey( 'VoiceTopic', $result, 'Voice title_prompt AI variables must be resolved.' );
		$this->assertSame( 'Voice-driven topic', $result['VoiceTopic'] );
	}

	// -------------------------------------------------------------------------
	// resolve() — convenience wrapper
	// -------------------------------------------------------------------------

	/**
	 * resolve() is a convenience wrapper around resolve_from_context() that
	 * accepts a raw template object and optional voice instead of a context.
	 * Its result must match what resolve_from_context() returns for an
	 * equivalent AIPS_Template_Context.
	 */
	public function test_resolve_delegates_to_resolve_from_context() {
		$callback = function( $prompt, $options, $type ) {
			return '{"Angle":"Test angle"}';
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		$template = (object) array(
			'title_prompt'    => 'A title about {{Angle}}',
			'prompt_template' => 'Write about it.',
		);

		$result = $resolver->resolve( $template, 'Article content.' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'Angle', $result );
		$this->assertSame( 'Test angle', $result['Angle'] );
	}

	// -------------------------------------------------------------------------
	// smart_truncate_content — tested indirectly via long content
	// -------------------------------------------------------------------------

	/**
	 * When content longer than 2,000 characters is passed, the prompt sent to
	 * the AI callback must still be non-empty and must contain the truncation
	 * marker, confirming that smart_truncate_content() fired.
	 */
	public function test_long_content_is_truncated_in_ai_prompt() {
		$prompt_received = '';
		$callback        = function( $prompt, $options, $type ) use ( &$prompt_received ) {
			$prompt_received = $prompt;
			return '{"Topic":"Truncated"}';
		};

		$resolver = new AIPS_AI_Variables_Resolver( $this->template_processor, $callback, $this->logger );

		$template = (object) array(
			'title_prompt'    => 'Write about {{Topic}}',
			'prompt_template' => 'Write an article.',
		);
		$context  = new AIPS_Template_Context( $template, null, null );

		// Build content well over 2 000 characters.
		$long_content = str_repeat( 'A', 5000 );

		$resolver->resolve_from_context( $context, $long_content );

		$this->assertStringContainsString(
			'[...]',
			$prompt_received,
			'Prompt must contain the truncation marker when content exceeds the limit.'
		);
	}
}
