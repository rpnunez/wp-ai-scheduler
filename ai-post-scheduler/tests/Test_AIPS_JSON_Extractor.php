<?php
/**
 * Tests for AIPS_JSON_Extractor.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_JSON_Extractor extends WP_UnitTestCase {

	public function test_extracts_nested_json_from_markdown_fence() {
		$response = "```json\n{\"items\":[{\"title\":\"A brace: }\"}],\"enabled\":true}\n```";

		$result = AIPS_JSON_Extractor::extract_json_fragment( $response );

		$this->assertSame(
			array(
				'items'   => array( array( 'title' => 'A brace: }' ) ),
				'enabled' => true,
			),
			json_decode( $result, true )
		);
	}

	public function test_ignores_balanced_non_json_brackets_before_json() {
		$response = 'Analysis [draft]: the result is {"status":"ready"}.';

		$result = AIPS_JSON_Extractor::extract_json_fragment( $response );

		$this->assertSame( '{"status":"ready"}', $result );
	}

	public function test_sanitizes_unescaped_control_characters_inside_strings() {
		$response = "{\"summary\":\"first\nsecond\tcolumn\"}";

		$result = AIPS_JSON_Extractor::extract_json_fragment( $response );

		$this->assertSame( array( 'summary' => "first\nsecond\tcolumn" ), json_decode( $result, true ) );
	}

	public function test_decode_json_response_returns_an_associative_array() {
		$response = 'Result: {"status":"ready","items":[1,2]}';

		$result = AIPS_JSON_Extractor::decode_json_response( $response );

		$this->assertSame(
			array(
				'status' => 'ready',
				'items'  => array( 1, 2 ),
			),
			$result
		);
	}

	public function test_decode_json_response_propagates_extraction_errors() {
		$result = AIPS_JSON_Extractor::decode_json_response( 'No structured response was returned.' );

		$this->assertWPError( $result );
		$this->assertSame( 'json_extract_failed', $result->get_error_code() );
	}

	public function test_returns_error_when_no_json_start_token_exists() {
		$result = AIPS_JSON_Extractor::extract_json_fragment( 'No structured response was returned.' );

		$this->assertWPError( $result );
		$this->assertSame( 'json_extract_failed', $result->get_error_code() );
	}

	public function test_returns_error_for_mismatched_json_tokens() {
		$result = AIPS_JSON_Extractor::extract_json_fragment( '{"items":[1,2}' );

		$this->assertWPError( $result );
		$this->assertSame( 'json_extract_failed', $result->get_error_code() );
	}

	public function test_returns_error_for_truncated_json() {
		$result = AIPS_JSON_Extractor::extract_json_fragment( '{"items":[1,2]' );

		$this->assertWPError( $result );
		$this->assertSame( 'json_extract_failed', $result->get_error_code() );
	}
}
