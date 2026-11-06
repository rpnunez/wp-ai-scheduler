<?php
/**
 * Test Case: AIPS_AI_Error_Classifier
 *
 * Verifies smart error classification, heuristic pattern matching,
 * secret redaction, and support report markdown generation.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

class Test_AIPS_AI_Error_Classifier extends WP_UnitTestCase {

	/**
	 * Test Rate Limit classification for HTTP 429 and quota keywords.
	 */
	public function test_classify_rate_limit() {
		// HTTP 429 in context
		$res1 = AIPS_AI_Error_Classifier::classify( array(
			'context' => array( 'http_code' => 429, 'error' => 'Too Many Requests' ),
		) );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_RATE_LIMIT, $res1['category'] );
		$this->assertSame( 429, $res1['http_code'] );
		$this->assertSame( 'aips-badge-warning', $res1['badge_class'] );
		$this->assertStringContainsString( 'rate limit', strtolower( $res1['root_cause'] ) );

		// Quota exceeded in message string
		$res2 = AIPS_AI_Error_Classifier::classify( 'You exceeded your current quota, please check your plan and billing details.' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_RATE_LIMIT, $res2['category'] );
	}

	/**
	 * Test Authentication classification for HTTP 401/403 and invalid api key messages.
	 */
	public function test_classify_auth() {
		$res1 = AIPS_AI_Error_Classifier::classify( array(
			'context' => array( 'http_code' => 401, 'error' => 'Incorrect API key provided' ),
		) );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_AUTH, $res1['category'] );
		$this->assertSame( 401, $res1['http_code'] );
		$this->assertSame( 'aips-badge-danger', $res1['badge_class'] );

		$res2 = AIPS_AI_Error_Classifier::classify( 'Error: permission_denied for model claude-3-opus' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_AUTH, $res2['category'] );
	}

	/**
	 * Test Context Length classification for prompt overflow messages.
	 */
	public function test_classify_context_length() {
		$res1 = AIPS_AI_Error_Classifier::classify( 'This model maximum context length is 16385 tokens. However, your messages resulted in 18200 tokens.' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_CONTEXT_LENGTH, $res1['category'] );
		$this->assertSame( 'aips-badge-purple', $res1['badge_class'] );

		$res2 = AIPS_AI_Error_Classifier::classify( array(
			'message' => 'AI generation failed for content: context_length_exceeded',
		) );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_CONTEXT_LENGTH, $res2['category'] );
	}

	/**
	 * Test Timeout classification for cURL 28 and 504 gateway timeout.
	 */
	public function test_classify_timeout() {
		$res1 = AIPS_AI_Error_Classifier::classify( 'cURL error 28: Operation timed out after 30000 milliseconds' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_TIMEOUT, $res1['category'] );
		$this->assertSame( 'aips-badge-info', $res1['badge_class'] );

		$res2 = AIPS_AI_Error_Classifier::classify( array(
			'context' => array( 'http_code' => 504, 'error' => 'Gateway Timeout' ),
		) );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_TIMEOUT, $res2['category'] );
		$this->assertSame( 504, $res2['http_code'] );
	}

	/**
	 * Test Provider Down classification for 500, 502, 503 and server errors.
	 */
	public function test_classify_provider_down() {
		$res1 = AIPS_AI_Error_Classifier::classify( array(
			'context' => array( 'http_code' => 503, 'error' => 'Service Unavailable' ),
		) );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_PROVIDER_DOWN, $res1['category'] );
		$this->assertSame( 503, $res1['http_code'] );

		$res2 = AIPS_AI_Error_Classifier::classify( 'The server had an internal_server_error while processing your request.' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_PROVIDER_DOWN, $res2['category'] );
	}

	/**
	 * Test Content Policy classification for safety and moderation triggers.
	 */
	public function test_classify_content_policy() {
		$res1 = AIPS_AI_Error_Classifier::classify( 'The response was blocked by safety filters due to content_policy violation.' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_CONTENT_POLICY, $res1['category'] );
		$this->assertSame( 'aips-badge-danger', $res1['badge_class'] );
	}

	/**
	 * Test JSON Parsing classification for decode errors.
	 */
	public function test_classify_json_parsing() {
		$res1 = AIPS_AI_Error_Classifier::classify( 'json_decode syntax error: unexpected token < in response' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_JSON_PARSING, $res1['category'] );
		$this->assertSame( 'aips-badge-teal', $res1['badge_class'] );
	}

	/**
	 * Test General fallback for unclassified errors.
	 */
	public function test_classify_general_fallback() {
		$res = AIPS_AI_Error_Classifier::classify( 'An unexpected local logic failure occurred.' );
		$this->assertSame( AIPS_AI_Error_Classifier::CATEGORY_GENERAL, $res['category'] );
		$this->assertSame( 'aips-badge-light', $res['badge_class'] );
	}

	/**
	 * Test secret redaction for API keys, Bearer tokens, and passwords.
	 */
	public function test_redact_secrets() {
		$raw = 'Failed request with key sk-proj-1234567890abcdef1234567890 and Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.token123 and ant-api03-abcdef1234567890abcdef12345';
		$redacted = AIPS_AI_Error_Classifier::redact_secrets( $raw );

		$this->assertStringNotContainsString( 'sk-proj-1234567890abcdef1234567890', $redacted );
		$this->assertStringContainsString( 'sk-***[REDACTED_API_KEY]***', $redacted );
		$this->assertStringNotContainsString( 'ant-api03-abcdef1234567890abcdef12345', $redacted );
		$this->assertStringContainsString( 'ant-***[REDACTED_API_KEY]***', $redacted );
		$this->assertStringContainsString( 'Bearer ***[REDACTED_TOKEN]***', $redacted );
	}

	/**
	 * Test Markdown support report generation.
	 */
	public function test_generate_support_report() {
		$error_data = array(
			'id'              => 88,
			'formatted_date'  => '2026-10-25 14:00:00',
			'history_id'      => 42,
			'component_label' => 'Content',
			'error_message'   => 'Quota exceeded using key sk-123456789012345678901234567890',
			'options'         => array( 'model' => 'gpt-4o', 'provider' => 'OpenAI' ),
			'classification'  => array(
				'label'      => 'Rate Limit / Quota Exceeded',
				'http_code'  => 429,
				'root_cause' => 'Provider rejected request due to quota.',
				'action'     => 'Check OpenAI billing.',
			),
		);

		$report = AIPS_AI_Error_Classifier::generate_support_report( $error_data );

		$this->assertStringContainsString( 'WP AI Scheduler - Diagnostic Error Report', $report );
		$this->assertStringContainsString( 'Log ID**: #88', $report );
		$this->assertStringContainsString( 'Rate Limit / Quota Exceeded (HTTP 429)', $report );
		$this->assertStringContainsString( 'Provider / Model**: OpenAI / gpt-4o', $report );
		$this->assertStringContainsString( 'sk-***[REDACTED_API_KEY]***', $report );
		$this->assertStringNotContainsString( 'sk-123456789012345678901234567890', $report );
	}
}
