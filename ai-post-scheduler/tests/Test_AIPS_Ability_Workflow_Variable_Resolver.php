<?php
/**
 * Tests for AIPS_Ability_Workflow_Variable_Resolver
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Variable_Resolver extends WP_UnitTestCase {

	private $resolver;
	private $variables;

	public function setUp(): void {
		parent::setUp();

		$this->resolver = new AIPS_Ability_Workflow_Variable_Resolver();

		$this->variables = array(
			'trigger' => array( 'topic' => 'AI in gardening' ),
			'steps'   => array(
				'outline' => array( 'output' => array( 'sections_count' => 5, 'title' => 'My Outline' ) ),
			),
		);
	}

	public function test_resolve_whole_token_returns_typed_value() {
		$this->assertSame( 'AI in gardening', $this->resolver->resolve( '{{trigger.topic}}', $this->variables ) );
		$this->assertSame( 5, $this->resolver->resolve( '{{steps.outline.output.sections_count}}', $this->variables ) );
	}

	public function test_resolve_embedded_token_interpolates_as_string() {
		$this->assertSame( 'Topic: AI in gardening', $this->resolver->resolve( 'Topic: {{trigger.topic}}', $this->variables ) );
	}

	public function test_resolve_missing_token_returns_null_for_whole_match() {
		$this->assertNull( $this->resolver->resolve( '{{missing.token}}', $this->variables ) );
	}

	public function test_resolve_missing_token_becomes_empty_string_when_embedded() {
		$this->assertSame( 'Value: ', $this->resolver->resolve( 'Value: {{missing.token}}', $this->variables ) );
	}

	public function test_resolve_plain_string_passes_through() {
		$this->assertSame( 'no tokens here', $this->resolver->resolve( 'no tokens here', $this->variables ) );
	}

	public function test_resolve_map_recurses_through_nested_arrays() {
		$map = array(
			'topic'   => '{{trigger.topic}}',
			'nested'  => array(
				'count' => '{{steps.outline.output.sections_count}}',
			),
			'literal' => 'static value',
		);

		$resolved = $this->resolver->resolve_map( $map, $this->variables );

		$this->assertSame( 'AI in gardening', $resolved['topic'] );
		$this->assertSame( 5, $resolved['nested']['count'] );
		$this->assertSame( 'static value', $resolved['literal'] );
	}

	public function test_extract_tokens_finds_all_tokens() {
		$tokens = $this->resolver->extract_tokens( '{{trigger.topic}} and {{steps.outline.output.title}}' );

		$this->assertSame( array( 'trigger.topic', 'steps.outline.output.title' ), $tokens );
	}

	public function test_validate_token_accepts_trigger_and_steps_paths() {
		$this->assertTrue( $this->resolver->validate_token( 'trigger' ) );
		$this->assertTrue( $this->resolver->validate_token( 'trigger.topic' ) );
		$this->assertTrue( $this->resolver->validate_token( 'steps.outline.output.sections_count' ) );
	}

	public function test_validate_token_rejects_unknown_root() {
		$this->assertWPError( $this->resolver->validate_token( 'bogus.path' ) );
		$this->assertWPError( $this->resolver->validate_token( 'steps.outline.sections_count' ) ); // missing .output
	}
}
