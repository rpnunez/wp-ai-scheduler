<?php
/**
 * Tests for the Author Suggestions Service.
 *
 * Covers prompt building logic and response parsing without making real AI calls.
 *
 * @package AI_Post_Scheduler
 */

class Test_Author_Suggestions_Service extends WP_UnitTestCase {

	/**
	 * Build a stub AI service that returns the provided JSON payload.
	 *
	 * @param array|WP_Error $payload What generate_json() should return.
	 * @return object
	 */
	private function make_ai_service( $payload ) {
		return new class( $payload ) {
			private $payload;
			public function __construct( $p ) { $this->payload = $p; }
			public function generate_json( $prompt, $options = array() ) { return $this->payload; }
		};
	}

	/**
	 * Build a stub logger that silently discards all log calls.
	 *
	 * @return object
	 */
	private function make_logger() {
		return new class {
			public function log( $message, $level = 'info', $context = array() ) {}
		};
	}

	/**
	 * Build a stub history service that returns a no-op history container.
	 *
	 * @return object
	 */
	private function make_history_service() {
		return new class {
			public function create( $type, $metadata = array() ) {
				return new class {
					public function record( $log_type, $message, $input = null, $output = null, $context = array() ) {}
					public function record_error( $message, $error_details = array(), $wp_error = null ) {}
					public function complete_success( $result_data = array() ) {}
					public function complete_failure( $error_message, $error_data = array() ) {}
				};
			}
		};
	}

	/**
	 * Build a minimal AIPS_Author_Suggestions_Service with all stubs injected.
	 *
	 * @param mixed $ai_payload What the AI service should return.
	 * @return AIPS_Author_Suggestions_Service
	 */
	private function make_service( $ai_payload ) {
		return new AIPS_Author_Suggestions_Service(
			$this->make_ai_service( $ai_payload ),
			$this->make_logger(),
			$this->make_history_service()
		);
	}

	/**
	 * Build a minimal valid suggestion payload as the AI would return.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function make_suggestion( $overrides = array() ) {
		return array_merge(
			array(
				'name'                    => 'Alex Rivera',
				'field_niche'             => 'Personal Finance for Millennials',
				'description'             => 'Alex helps millennials tackle debt and build wealth.',
				'keywords'                => 'budgeting, investing, debt payoff',
				'voice_tone'              => 'conversational',
				'writing_style'           => 'practical how-to guides',
				'topic_generation_prompt' => 'Generate actionable personal finance topics for millennials.',
			),
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// suggest_authors() — validation
	// -------------------------------------------------------------------------

	/**
	 * An empty site_niche must return a WP_Error.
	 */
	public function test_suggest_authors_returns_error_when_niche_empty() {
		$service = $this->make_service( array() );
		$result  = $service->suggest_authors( array( 'site_niche' => '' ), 3 );

		$this->assertTrue( is_wp_error( $result ), 'Should return WP_Error when site_niche is empty' );
		$this->assertEquals( 'missing_niche', $result->get_error_code() );
	}

	/**
	 * A WP_Error from the AI service propagates back to the caller.
	 */
	public function test_suggest_authors_propagates_ai_error() {
		$ai_error = new WP_Error( 'ai_unavailable', 'AI Engine not available.' );
		$service  = $this->make_service( $ai_error );
		$result   = $service->suggest_authors( array( 'site_niche' => 'Technology' ), 3 );

		$this->assertTrue( is_wp_error( $result ), 'Should return WP_Error when AI service fails' );
		$this->assertEquals( 'ai_unavailable', $result->get_error_code() );
	}

	/**
	 * An empty AI response array returns a WP_Error.
	 */
	public function test_suggest_authors_returns_error_when_ai_returns_empty_array() {
		$service = $this->make_service( array() );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Technology' ), 3 );

		$this->assertTrue( is_wp_error( $result ), 'Should return WP_Error when AI response is empty' );
		$this->assertEquals( 'no_suggestions_parsed', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// suggest_authors() — success path
	// -------------------------------------------------------------------------

	/**
	 * A valid AI response returns an array of parsed suggestion arrays.
	 */
	public function test_suggest_authors_returns_suggestions_on_success() {
		$payload = array( $this->make_suggestion() );
		$service = $this->make_service( $payload );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Personal Finance' ), 1 );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'Alex Rivera', $result[0]['name'] );
		$this->assertEquals( 'Personal Finance for Millennials', $result[0]['field_niche'] );
	}

	/**
	 * The count parameter limits the number of returned suggestions.
	 */
	public function test_suggest_authors_respects_count_limit() {
		$payload = array(
			$this->make_suggestion( array( 'name' => 'Author One', 'field_niche' => 'Niche A' ) ),
			$this->make_suggestion( array( 'name' => 'Author Two', 'field_niche' => 'Niche B' ) ),
			$this->make_suggestion( array( 'name' => 'Author Three', 'field_niche' => 'Niche C' ) ),
		);
		$service = $this->make_service( $payload );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Tech' ), 2 );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result, 'Only 2 suggestions should be returned when count is 2' );
	}

	/**
	 * Suggestions missing the required "name" field are silently skipped.
	 */
	public function test_suggest_authors_skips_items_without_name() {
		$payload = array(
			array( 'field_niche' => 'No Name Niche' ), // Missing 'name'
			$this->make_suggestion( array( 'name' => 'Valid Author' ) ),
		);
		$service = $this->make_service( $payload );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Tech' ), 3 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result, 'Items without name should be skipped' );
		$this->assertEquals( 'Valid Author', $result[0]['name'] );
	}

	/**
	 * Suggestions missing the required "field_niche" field are silently skipped.
	 */
	public function test_suggest_authors_skips_items_without_field_niche() {
		$payload = array(
			array( 'name' => 'No Niche Author' ), // Missing 'field_niche'
			$this->make_suggestion( array( 'name' => 'Complete Author' ) ),
		);
		$service = $this->make_service( $payload );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Tech' ), 3 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result, 'Items without field_niche should be skipped' );
	}

	/**
	 * Optional suggestion fields default to empty strings when absent.
	 */
	public function test_suggest_authors_optional_fields_default_to_empty() {
		$payload = array(
			array( 'name' => 'Minimal Author', 'field_niche' => 'Tech' ),
		);
		$service = $this->make_service( $payload );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Tech' ), 1 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( '', $result[0]['description'] );
		$this->assertEquals( '', $result[0]['keywords'] );
		$this->assertEquals( '', $result[0]['voice_tone'] );
		$this->assertEquals( '', $result[0]['writing_style'] );
		$this->assertEquals( '', $result[0]['topic_generation_prompt'] );
	}

	/**
	 * The count parameter is clamped to between 1 and 10.
	 */
	public function test_suggest_authors_clamps_count() {
		$many = array();
		for ( $i = 1; $i <= 15; $i++ ) {
			$many[] = $this->make_suggestion( array( 'name' => "Author $i", 'field_niche' => "Niche $i" ) );
		}

		$service = $this->make_service( $many );

		// Count of 0 should be clamped to 1
		$result_low = $service->suggest_authors( array( 'site_niche' => 'Tech' ), 0 );
		$this->assertIsArray( $result_low );
		$this->assertCount( 1, $result_low );

		// Count of 99 should be clamped to 10
		$result_high = $service->suggest_authors( array( 'site_niche' => 'Tech' ), 99 );
		$this->assertIsArray( $result_high );
		$this->assertCount( 10, $result_high );
	}

	// -------------------------------------------------------------------------
	// suggest_authors() — all required fields are returned
	// -------------------------------------------------------------------------

	/**
	 * All expected field keys are present in each suggestion.
	 */
	public function test_suggest_authors_returns_all_expected_keys() {
		$payload = array( $this->make_suggestion() );
		$service = $this->make_service( $payload );
		$result  = $service->suggest_authors( array( 'site_niche' => 'Finance' ), 1 );

		$this->assertIsArray( $result );
		$suggestion = $result[0];

		foreach ( array( 'name', 'field_niche', 'description', 'keywords', 'voice_tone', 'writing_style', 'topic_generation_prompt' ) as $key ) {
			$this->assertArrayHasKey( $key, $suggestion, "Suggestion should contain key '$key'" );
		}
	}
}
