<?php
/**
 * Tests for typed DTOs introduced in Step 16.
 *
 * Covers:
 *   AIPS_Generation_Result — named constructors, status constants, helper methods
 *   AIPS_Schedule_Entry    — from_row() factory, type coercions, helpers
 *   AIPS_Template_Data     — from_row() factory, type coercions, helpers
 *   AIPS_Template_Entry    — from_template_and_overrides() factory, type coercions
 *
 * @package AI_Post_Scheduler
 */

// ---------------------------------------------------------------------------
// AIPS_Generation_Result tests
// ---------------------------------------------------------------------------

/**
 * Class Test_AIPS_Generation_Result
 */
class Test_AIPS_Generation_Result extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// ::success()
	// -----------------------------------------------------------------------

	/**
	 * success() creates a completed result with the given post_id.
	 */
	public function test_success_sets_status_completed() {
		$result = AIPS_Generation_Result::success( 42 );

		$this->assertInstanceOf( AIPS_Generation_Result::class, $result );
		$this->assertSame( 42, $result->post_id );
		$this->assertSame( AIPS_Generation_Result::STATUS_COMPLETED, $result->status );
		$this->assertEmpty( $result->errors );
		$this->assertEmpty( $result->component_statuses );
		$this->assertSame( 0.0, $result->generation_time );
	}

	/**
	 * success() stores component_statuses and generation_time when provided.
	 */
	public function test_success_stores_optional_fields() {
		$statuses = array( 'post_title' => true, 'post_content' => true );
		$result   = AIPS_Generation_Result::success( 99, $statuses, 1.5 );

		$this->assertSame( $statuses, $result->component_statuses );
		$this->assertSame( 1.5, $result->generation_time );
	}

	/**
	 * is_success() returns true; is_partial() and is_failure() return false.
	 */
	public function test_success_is_helpers() {
		$result = AIPS_Generation_Result::success( 1 );

		$this->assertTrue( $result->is_success() );
		$this->assertFalse( $result->is_partial() );
		$this->assertFalse( $result->is_failure() );
		$this->assertTrue( $result->has_post() );
	}

	// -----------------------------------------------------------------------
	// ::partial()
	// -----------------------------------------------------------------------

	/**
	 * partial() creates a partial result with errors and a post_id.
	 */
	public function test_partial_sets_status_partial() {
		$errors  = array( 'Featured image failed' );
		$result  = AIPS_Generation_Result::partial( 7, $errors );

		$this->assertSame( 7, $result->post_id );
		$this->assertSame( AIPS_Generation_Result::STATUS_PARTIAL, $result->status );
		$this->assertSame( $errors, $result->errors );
	}

	/**
	 * is_partial() returns true; other helpers return false.
	 */
	public function test_partial_is_helpers() {
		$result = AIPS_Generation_Result::partial( 3 );

		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->is_partial() );
		$this->assertFalse( $result->is_failure() );
		$this->assertTrue( $result->has_post() );
	}

	// -----------------------------------------------------------------------
	// ::failure()
	// -----------------------------------------------------------------------

	/**
	 * failure() creates a failed result with null post_id.
	 */
	public function test_failure_sets_status_failed() {
		$errors = array( 'AI service unavailable' );
		$result = AIPS_Generation_Result::failure( $errors, 0.3 );

		$this->assertNull( $result->post_id );
		$this->assertSame( AIPS_Generation_Result::STATUS_FAILED, $result->status );
		$this->assertSame( $errors, $result->errors );
		$this->assertSame( 0.3, $result->generation_time );
	}

	/**
	 * is_failure() returns true; other helpers return false.
	 */
	public function test_failure_is_helpers() {
		$result = AIPS_Generation_Result::failure();

		$this->assertFalse( $result->is_success() );
		$this->assertFalse( $result->is_partial() );
		$this->assertTrue( $result->is_failure() );
		$this->assertFalse( $result->has_post() );
	}

	/**
	 * failure() with no arguments produces empty errors array.
	 */
	public function test_failure_defaults_empty_errors() {
		$result = AIPS_Generation_Result::failure();

		$this->assertEmpty( $result->errors );
		$this->assertNull( $result->post_id );
	}

	// -----------------------------------------------------------------------
	// ::from_wp_error()
	// -----------------------------------------------------------------------

	/**
	 * from_wp_error() wraps a WP_Error as a failed result.
	 */
	public function test_from_wp_error_wraps_message() {
		$error  = new WP_Error( 'ai_failed', 'AI engine timed out' );
		$result = AIPS_Generation_Result::from_wp_error( $error, 2.1 );

		$this->assertTrue( $result->is_failure() );
		$this->assertContains( 'AI engine timed out', $result->errors );
		$this->assertSame( 2.1, $result->generation_time );
	}

	// -----------------------------------------------------------------------
	// Immutability
	// -----------------------------------------------------------------------

	/**
	 * Properties are readonly and cannot be overwritten after construction.
	 */
	public function test_properties_are_readonly() {
		$result = AIPS_Generation_Result::success( 10 );

		try {
			$result->post_id = 99;
			$this->fail( 'Expected Error was not thrown' );
		} catch ( Error $e ) {
			$this->assertStringContainsString( 'readonly', $e->getMessage() );
		}
	}
}

// ---------------------------------------------------------------------------
// AIPS_Schedule_Entry tests
// ---------------------------------------------------------------------------

/**
 * Class Test_AIPS_Schedule_Entry
 */
class Test_AIPS_Schedule_Entry extends WP_UnitTestCase {

	/**
	 * Build a minimal stdClass row with only required fields.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return object
	 */
	private function make_row( array $overrides = array() ): object {
		$defaults = array(
			'id'          => '5',
			'template_id' => '3',
			'frequency'   => 'daily',
			'next_run'    => '2025-01-15 08:00:00',
			'is_active'   => '1',
			'status'      => 'active',
			'schedule_type'  => 'post_generation',
			'circuit_state'  => 'closed',
			'created_at'     => '2025-01-01 00:00:00',
		);
		return (object) array_merge( $defaults, $overrides );
	}

	// -----------------------------------------------------------------------
	// Type coercions
	// -----------------------------------------------------------------------

	/**
	 * from_row() coerces string id / template_id to int.
	 */
	public function test_from_row_coerces_ids_to_int() {
		$entry = AIPS_Schedule_Entry::from_row( $this->make_row() );

		$this->assertSame( 5, $entry->id );
		$this->assertSame( 3, $entry->template_id );
	}

	/**
	 * from_row() coerces is_active string '1' to true.
	 */
	public function test_from_row_coerces_is_active_to_bool() {
		$active   = AIPS_Schedule_Entry::from_row( $this->make_row( array( 'is_active' => '1' ) ) );
		$inactive = AIPS_Schedule_Entry::from_row( $this->make_row( array( 'is_active' => '0' ) ) );

		$this->assertTrue( $active->is_active );
		$this->assertFalse( $inactive->is_active );
	}

	/**
	 * Nullable string fields return null when empty or missing.
	 */
	public function test_from_row_nullable_string_fields() {
		$entry = AIPS_Schedule_Entry::from_row( $this->make_row() );

		$this->assertNull( $entry->title );
		$this->assertNull( $entry->topic );
		$this->assertNull( $entry->last_run );
		$this->assertNull( $entry->rotation_pattern );
		$this->assertNull( $entry->run_state );
		$this->assertNull( $entry->batch_progress );
		$this->assertNull( $entry->template_name );
	}

	/**
	 * Nullable integer fields return null when missing from row.
	 */
	public function test_from_row_nullable_int_fields() {
		$entry = AIPS_Schedule_Entry::from_row( $this->make_row() );

		$this->assertNull( $entry->article_structure_id );
		$this->assertNull( $entry->schedule_history_id );
	}

	/**
	 * Populated nullable fields are mapped correctly.
	 */
	public function test_from_row_populated_optional_fields() {
		$row   = $this->make_row( array(
			'title'                => 'Daily Tech',
			'article_structure_id' => '7',
			'rotation_pattern'     => 'sequential',
			'topic'                => 'WordPress plugins',
			'last_run'             => '2025-01-14 08:00:00',
			'schedule_history_id'  => '12',
			'run_state'            => '{"step":2}',
			'batch_progress'       => '{"done":1}',
			'template_name'        => 'My Template',
		) );
		$entry = AIPS_Schedule_Entry::from_row( $row );

		$this->assertSame( 'Daily Tech', $entry->title );
		$this->assertSame( 7, $entry->article_structure_id );
		$this->assertSame( 'sequential', $entry->rotation_pattern );
		$this->assertSame( 'WordPress plugins', $entry->topic );
		$this->assertSame( '2025-01-14 08:00:00', $entry->last_run );
		$this->assertSame( 12, $entry->schedule_history_id );
		$this->assertSame( '{"step":2}', $entry->run_state );
		$this->assertSame( '{"done":1}', $entry->batch_progress );
		$this->assertSame( 'My Template', $entry->template_name );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * is_due() returns true when next_run is before the given current time.
	 */
	public function test_is_due_past_next_run() {
		$entry = AIPS_Schedule_Entry::from_row( $this->make_row( array(
			'next_run' => '2020-01-01 00:00:00',
		) ) );

		$this->assertTrue( $entry->is_due( '2025-01-01 00:00:00' ) );
	}

	/**
	 * is_due() returns false when next_run is in the future.
	 */
	public function test_is_due_future_next_run() {
		$entry = AIPS_Schedule_Entry::from_row( $this->make_row( array(
			'next_run' => '2099-01-01 00:00:00',
		) ) );

		$this->assertFalse( $entry->is_due( '2025-01-01 00:00:00' ) );
	}

	/**
	 * is_circuit_open() returns true when circuit_state is 'open'.
	 */
	public function test_is_circuit_open() {
		$open   = AIPS_Schedule_Entry::from_row( $this->make_row( array( 'circuit_state' => 'open' ) ) );
		$closed = AIPS_Schedule_Entry::from_row( $this->make_row( array( 'circuit_state' => 'closed' ) ) );

		$this->assertTrue( $open->is_circuit_open() );
		$this->assertFalse( $closed->is_circuit_open() );
	}

	// -----------------------------------------------------------------------
	// Immutability
	// -----------------------------------------------------------------------

	/**
	 * Properties are readonly.
	 */
	public function test_properties_are_readonly() {
		$entry = AIPS_Schedule_Entry::from_row( $this->make_row() );

		try {
			$entry->id = 999;
			$this->fail( 'Expected Error was not thrown' );
		} catch ( Error $e ) {
			$this->assertStringContainsString( 'readonly', $e->getMessage() );
		}
	}
}

// ---------------------------------------------------------------------------
// AIPS_Template_Data tests
// ---------------------------------------------------------------------------

/**
 * Class Test_AIPS_Template_Data
 */
class Test_AIPS_Template_Data extends WP_UnitTestCase {

	/**
	 * Build a minimal stdClass row with only required fields.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return object
	 */
	private function make_row( array $overrides = array() ): object {
		$defaults = array(
			'id'              => '1',
			'name'            => 'Test Template',
			'prompt_template' => 'Write about {{topic}}.',
			'is_active'       => '1',
			'post_status'     => 'draft',
			'post_quantity'   => '1',
			'generate_featured_image' => '0',
			'featured_image_source'   => 'ai_prompt',
			'include_sources' => '0',
			'created_at'      => '2025-01-01 00:00:00',
			'updated_at'      => '2025-01-02 00:00:00',
		);
		return (object) array_merge( $defaults, $overrides );
	}

	// -----------------------------------------------------------------------
	// Type coercions
	// -----------------------------------------------------------------------

	/**
	 * from_row() coerces string id to int.
	 */
	public function test_from_row_coerces_id_to_int() {
		$tpl = AIPS_Template_Data::from_row( $this->make_row() );

		$this->assertSame( 1, $tpl->id );
	}

	/**
	 * from_row() coerces boolean flags from tinyint strings.
	 *
	 * Validates that wpdb tinyint string '0' maps to false, not true.
	 * (Direct (bool) cast on a non-empty string would incorrectly produce true.)
	 */
	public function test_from_row_coerces_boolean_flags() {
		$active    = AIPS_Template_Data::from_row( $this->make_row( array( 'is_active' => '1' ) ) );
		$inactive  = AIPS_Template_Data::from_row( $this->make_row( array( 'is_active' => '0' ) ) );
		$with_img  = AIPS_Template_Data::from_row( $this->make_row( array( 'generate_featured_image' => '1' ) ) );
		$no_img    = AIPS_Template_Data::from_row( $this->make_row( array( 'generate_featured_image' => '0' ) ) );
		$with_src  = AIPS_Template_Data::from_row( $this->make_row( array( 'include_sources' => '1' ) ) );
		$no_src    = AIPS_Template_Data::from_row( $this->make_row( array( 'include_sources' => '0' ) ) );

		$this->assertTrue( $active->is_active );
		$this->assertFalse( $inactive->is_active );
		$this->assertTrue( $with_img->generate_featured_image );
		$this->assertFalse( $no_img->generate_featured_image );
		$this->assertTrue( $with_src->include_sources );
		$this->assertFalse( $no_src->include_sources );
	}

	/**
	 * from_row() returns null for missing nullable text fields.
	 */
	public function test_from_row_nullable_text_fields() {
		$tpl = AIPS_Template_Data::from_row( $this->make_row() );

		$this->assertNull( $tpl->description );
		$this->assertNull( $tpl->title_prompt );
		$this->assertNull( $tpl->image_prompt );
		$this->assertNull( $tpl->featured_image_unsplash_keywords );
		$this->assertNull( $tpl->featured_image_media_ids );
		$this->assertNull( $tpl->post_tags );
		$this->assertNull( $tpl->source_group_ids );
	}

	/**
	 * from_row() returns null for missing nullable int fields.
	 */
	public function test_from_row_nullable_int_fields() {
		$tpl = AIPS_Template_Data::from_row( $this->make_row() );

		$this->assertNull( $tpl->voice_id );
		$this->assertNull( $tpl->post_category );
		$this->assertNull( $tpl->post_author );
	}

	/**
	 * Populated nullable fields are mapped correctly.
	 */
	public function test_from_row_populated_optional_fields() {
		$row = $this->make_row( array(
			'description'                      => 'My description',
			'title_prompt'                     => 'Create title for {{topic}}',
			'voice_id'                         => '4',
			'post_quantity'                    => '3',
			'image_prompt'                     => 'Abstract tech image',
			'generate_featured_image'          => '1',
			'featured_image_source'            => 'unsplash',
			'featured_image_unsplash_keywords' => 'technology,abstract',
			'featured_image_media_ids'         => '10,11',
			'post_status'                      => 'publish',
			'post_category'                    => '5',
			'post_tags'                        => 'tech,ai',
			'post_author'                      => '2',
			'include_sources'                  => '1',
			'source_group_ids'                 => '[1,2]',
		) );
		$tpl = AIPS_Template_Data::from_row( $row );

		$this->assertSame( 'My description', $tpl->description );
		$this->assertSame( 'Create title for {{topic}}', $tpl->title_prompt );
		$this->assertSame( 4, $tpl->voice_id );
		$this->assertSame( 3, $tpl->post_quantity );
		$this->assertSame( 'Abstract tech image', $tpl->image_prompt );
		$this->assertTrue( $tpl->generate_featured_image );
		$this->assertSame( 'unsplash', $tpl->featured_image_source );
		$this->assertSame( 'technology,abstract', $tpl->featured_image_unsplash_keywords );
		$this->assertSame( '10,11', $tpl->featured_image_media_ids );
		$this->assertSame( 'publish', $tpl->post_status );
		$this->assertSame( 5, $tpl->post_category );
		$this->assertSame( 'tech,ai', $tpl->post_tags );
		$this->assertSame( 2, $tpl->post_author );
		$this->assertTrue( $tpl->include_sources );
		$this->assertSame( '[1,2]', $tpl->source_group_ids );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * has_title_prompt() returns correct value based on title_prompt field.
	 */
	public function test_has_title_prompt() {
		$with    = AIPS_Template_Data::from_row( $this->make_row( array( 'title_prompt' => 'A prompt' ) ) );
		$without = AIPS_Template_Data::from_row( $this->make_row() );

		$this->assertTrue( $with->has_title_prompt() );
		$this->assertFalse( $without->has_title_prompt() );
	}

	/**
	 * has_image_prompt() returns correct value based on image_prompt field.
	 */
	public function test_has_image_prompt() {
		$with    = AIPS_Template_Data::from_row( $this->make_row( array( 'image_prompt' => 'A prompt' ) ) );
		$without = AIPS_Template_Data::from_row( $this->make_row() );

		$this->assertTrue( $with->has_image_prompt() );
		$this->assertFalse( $without->has_image_prompt() );
	}

	/**
	 * has_voice() returns correct value based on voice_id field.
	 */
	public function test_has_voice() {
		$with    = AIPS_Template_Data::from_row( $this->make_row( array( 'voice_id' => '3' ) ) );
		$without = AIPS_Template_Data::from_row( $this->make_row() );

		$this->assertTrue( $with->has_voice() );
		$this->assertFalse( $without->has_voice() );
	}

	// -----------------------------------------------------------------------
	// Immutability
	// -----------------------------------------------------------------------

	/**
	 * Properties are readonly.
	 */
	public function test_properties_are_readonly() {
		$tpl = AIPS_Template_Data::from_row( $this->make_row() );

		try {
			$tpl->name = 'Modified';
			$this->fail( 'Expected Error was not thrown' );
		} catch ( Error $e ) {
			$this->assertStringContainsString( 'readonly', $e->getMessage() );
		}
	}
}

// ---------------------------------------------------------------------------
// AIPS_Template_Entry tests
// ---------------------------------------------------------------------------

/**
 * Class Test_AIPS_Template_Entry
 */
class Test_AIPS_Template_Entry extends WP_UnitTestCase {

	/**
	 * Build a minimal source object (simulating a raw template DB row).
	 *
	 * @param array $overrides Optional field overrides.
	 * @return object
	 */
	private function make_source( array $overrides = array() ): object {
		$defaults = array(
			'name'                    => 'Test Template',
			'prompt_template'         => 'Write about {{topic}}.',
			'post_status'             => 'draft',
			'generate_featured_image' => '0',
			'featured_image_source'   => 'ai_prompt',
			'include_sources'         => '0',
		);
		return (object) array_merge( $defaults, $overrides );
	}

	// -----------------------------------------------------------------------
	// Type coercions
	// -----------------------------------------------------------------------

	/**
	 * from_template_and_overrides() sets id and post_quantity from explicit params.
	 */
	public function test_explicit_params_are_stored() {
		$entry = AIPS_Template_Entry::from_template_and_overrides(
			42,
			$this->make_source(),
			5,
			7
		);

		$this->assertSame( 42, $entry->id );
		$this->assertSame( 5, $entry->post_quantity );
		$this->assertSame( 7, $entry->article_structure_id );
	}

	/**
	 * article_structure_id defaults to null when omitted.
	 */
	public function test_article_structure_id_defaults_to_null() {
		$entry = AIPS_Template_Entry::from_template_and_overrides( 1, $this->make_source(), 3 );

		$this->assertNull( $entry->article_structure_id );
	}

	/**
	 * Boolean flags are coerced from tinyint strings correctly.
	 * '0' must produce false; '1' must produce true.
	 */
	public function test_boolean_coercions() {
		$with_img = AIPS_Template_Entry::from_template_and_overrides(
			1,
			$this->make_source( array( 'generate_featured_image' => '1' ) ),
			1
		);
		$no_img = AIPS_Template_Entry::from_template_and_overrides(
			1,
			$this->make_source( array( 'generate_featured_image' => '0' ) ),
			1
		);
		$with_src = AIPS_Template_Entry::from_template_and_overrides(
			1,
			$this->make_source( array( 'include_sources' => '1' ) ),
			1
		);
		$no_src = AIPS_Template_Entry::from_template_and_overrides(
			1,
			$this->make_source( array( 'include_sources' => '0' ) ),
			1
		);

		$this->assertTrue( $with_img->generate_featured_image );
		$this->assertFalse( $no_img->generate_featured_image );
		$this->assertTrue( $with_src->include_sources );
		$this->assertFalse( $no_src->include_sources );
	}

	/**
	 * Nullable string fields return null when missing or empty.
	 */
	public function test_nullable_string_fields_default_to_null() {
		$entry = AIPS_Template_Entry::from_template_and_overrides( 1, $this->make_source(), 1 );

		$this->assertNull( $entry->title_prompt );
		$this->assertNull( $entry->image_prompt );
		$this->assertNull( $entry->featured_image_unsplash_keywords );
		$this->assertNull( $entry->featured_image_media_ids );
		$this->assertNull( $entry->post_tags );
		$this->assertNull( $entry->source_group_ids );
	}

	/**
	 * Nullable integer fields return null when missing from source.
	 */
	public function test_nullable_int_fields_default_to_null() {
		$entry = AIPS_Template_Entry::from_template_and_overrides( 1, $this->make_source(), 1 );

		$this->assertNull( $entry->post_category );
		$this->assertNull( $entry->post_author );
	}

	/**
	 * Populated optional fields are mapped correctly.
	 */
	public function test_populated_optional_fields() {
		$source = $this->make_source( array(
			'title_prompt'                     => 'Create a title',
			'image_prompt'                     => 'Abstract landscape',
			'generate_featured_image'          => '1',
			'featured_image_source'            => 'unsplash',
			'featured_image_unsplash_keywords' => 'nature,mountains',
			'featured_image_media_ids'         => '10,11',
			'post_status'                      => 'publish',
			'post_category'                    => '5',
			'post_tags'                        => 'ai,tech',
			'post_author'                      => '3',
			'include_sources'                  => '1',
			'source_group_ids'                 => '[1,2]',
		) );

		$entry = AIPS_Template_Entry::from_template_and_overrides( 99, $source, 10, 4 );

		$this->assertSame( 99, $entry->id );
		$this->assertSame( 'Create a title', $entry->title_prompt );
		$this->assertSame( 'Abstract landscape', $entry->image_prompt );
		$this->assertTrue( $entry->generate_featured_image );
		$this->assertSame( 'unsplash', $entry->featured_image_source );
		$this->assertSame( 'nature,mountains', $entry->featured_image_unsplash_keywords );
		$this->assertSame( '10,11', $entry->featured_image_media_ids );
		$this->assertSame( 'publish', $entry->post_status );
		$this->assertSame( 5, $entry->post_category );
		$this->assertSame( 'ai,tech', $entry->post_tags );
		$this->assertSame( 3, $entry->post_author );
		$this->assertSame( 10, $entry->post_quantity );
		$this->assertSame( 4, $entry->article_structure_id );
		$this->assertTrue( $entry->include_sources );
		$this->assertSame( '[1,2]', $entry->source_group_ids );
	}

	/**
	 * Defaults are applied when optional fields are absent from the source object.
	 */
	public function test_defaults_applied_for_missing_fields() {
		$entry = AIPS_Template_Entry::from_template_and_overrides(
			1,
			(object) array( 'name' => 'Min', 'prompt_template' => 'Prompt' ),
			2
		);

		$this->assertSame( 'draft', $entry->post_status );
		$this->assertSame( 'ai_prompt', $entry->featured_image_source );
		$this->assertFalse( $entry->generate_featured_image );
		$this->assertFalse( $entry->include_sources );
	}

	// -----------------------------------------------------------------------
	// Source object flexibility
	// -----------------------------------------------------------------------

	/**
	 * Factory works with a merged schedule+template object where the template_id
	 * is passed explicitly and may differ from the source object's own `id` field.
	 */
	public function test_explicit_template_id_overrides_source_id() {
		$source = $this->make_source( array( 'id' => '77' ) ); // schedule id, not template id
		$entry  = AIPS_Template_Entry::from_template_and_overrides( 42, $source, 1 );

		// The explicitly provided template_id (42) must win.
		$this->assertSame( 42, $entry->id );
	}

	// -----------------------------------------------------------------------
	// Immutability
	// -----------------------------------------------------------------------

	/**
	 * Properties are readonly and cannot be overwritten after construction.
	 */
	public function test_properties_are_readonly() {
		$entry = AIPS_Template_Entry::from_template_and_overrides( 1, $this->make_source(), 1 );

		try {
			$entry->id = 999;
			$this->fail( 'Expected Error was not thrown' );
		} catch ( Error $e ) {
			$this->assertStringContainsString( 'readonly', $e->getMessage() );
		}
	}
}
