<?php
/**
 * Tests for AIPS_History_Operation_Type.
 *
 * Verifies:
 *  - All constants are non-empty strings
 *  - get_label() returns a non-empty string for each valid type
 *  - get_label() returns a sensible fallback (not empty) for unknown types
 *  - get_all_types() contains every defined type constant
 *  - get_parent_types() contains batch/run types and not individual-item types
 *
 * @package AI_Post_Scheduler
 */

class Test_History_Operation_Type extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * All type constants as name => value pairs.
	 *
	 * @return array<string,string>
	 */
	private function all_constants() {
		return array(
			'SCHEDULE_RUN'            => AIPS_History_Operation_Type::SCHEDULE_RUN,
			'TOPIC_GENERATION_BATCH'  => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
			'POST_GENERATION_BATCH'   => AIPS_History_Operation_Type::POST_GENERATION_BATCH,
			'BULK_GENERATE_FROM_QUEUE' => AIPS_History_Operation_Type::BULK_GENERATE_FROM_QUEUE,
			'BULK_GENERATE_TOPICS'    => AIPS_History_Operation_Type::BULK_GENERATE_TOPICS,
			'POST_GENERATION'         => AIPS_History_Operation_Type::POST_GENERATION,
			'AUTHOR_TOPIC_GENERATION' => AIPS_History_Operation_Type::AUTHOR_TOPIC_GENERATION,
			'TOPIC_APPROVAL_GENERATE' => AIPS_History_Operation_Type::TOPIC_APPROVAL_GENERATE,
		);
	}

	/**
	 * All parent/batch-level type constants as name => value pairs.
	 *
	 * @return array<string,string>
	 */
	private function parent_constants() {
		return array(
			'SCHEDULE_RUN'             => AIPS_History_Operation_Type::SCHEDULE_RUN,
			'TOPIC_GENERATION_BATCH'   => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
			'POST_GENERATION_BATCH'    => AIPS_History_Operation_Type::POST_GENERATION_BATCH,
			'BULK_GENERATE_FROM_QUEUE' => AIPS_History_Operation_Type::BULK_GENERATE_FROM_QUEUE,
			'BULK_GENERATE_TOPICS'     => AIPS_History_Operation_Type::BULK_GENERATE_TOPICS,
		);
	}

	/**
	 * Item-level type constants (should NOT appear in get_parent_types()).
	 *
	 * @return array<string,string>
	 */
	private function item_constants() {
		return array(
			'POST_GENERATION'         => AIPS_History_Operation_Type::POST_GENERATION,
			'AUTHOR_TOPIC_GENERATION' => AIPS_History_Operation_Type::AUTHOR_TOPIC_GENERATION,
			'TOPIC_APPROVAL_GENERATE' => AIPS_History_Operation_Type::TOPIC_APPROVAL_GENERATE,
		);
	}

	// -------------------------------------------------------------------------
	// Constant value tests
	// -------------------------------------------------------------------------

	/**
	 * Every constant must be a non-empty string.
	 */
	public function test_all_constants_are_non_empty_strings() {
		foreach ( $this->all_constants() as $name => $value ) {
			$this->assertIsString( $value, "Constant {$name} must be a string." );
			$this->assertNotEmpty( $value, "Constant {$name} must not be empty." );
		}
	}

	/**
	 * All constants must be unique — no two constants share the same value.
	 */
	public function test_all_constants_are_unique() {
		$values  = array_values( $this->all_constants() );
		$unique  = array_unique( $values );
		$this->assertCount(
			count( $values ),
			$unique,
			'All AIPS_History_Operation_Type constants must have unique values.'
		);
	}

	// -------------------------------------------------------------------------
	// get_label() tests
	// -------------------------------------------------------------------------

	/**
	 * get_label() returns a non-empty string for each defined type constant.
	 */
	public function test_get_label_returns_string_for_all_defined_types() {
		foreach ( $this->all_constants() as $name => $value ) {
			$label = AIPS_History_Operation_Type::get_label( $value );
			$this->assertIsString( $label, "get_label({$name}) must return a string." );
			$this->assertNotEmpty( $label, "get_label({$name}) must not return an empty string." );
		}
	}

	/**
	 * get_label() with an unknown type returns a non-empty fallback string.
	 */
	public function test_get_label_returns_non_empty_fallback_for_unknown_type() {
		$fallback = AIPS_History_Operation_Type::get_label( 'completely_unknown_type_xyz' );
		$this->assertIsString( $fallback );
		$this->assertNotEmpty( $fallback, 'get_label() must return a non-empty fallback for unknown types.' );
	}

	/**
	 * get_label() with an empty string returns a non-empty fallback string.
	 */
	public function test_get_label_returns_non_empty_for_empty_string() {
		$fallback = AIPS_History_Operation_Type::get_label( '' );
		$this->assertIsString( $fallback );
		$this->assertNotEmpty( $fallback );
	}

	// -------------------------------------------------------------------------
	// get_all_types() tests
	// -------------------------------------------------------------------------

	/**
	 * get_all_types() returns an array.
	 */
	public function test_get_all_types_returns_array() {
		$types = AIPS_History_Operation_Type::get_all_types();
		$this->assertIsArray( $types, 'get_all_types() must return an array.' );
		$this->assertNotEmpty( $types, 'get_all_types() must not return an empty array.' );
	}

	/**
	 * get_all_types() contains all parent/batch type constants as keys.
	 */
	public function test_get_all_types_contains_all_parent_types_as_keys() {
		$types = AIPS_History_Operation_Type::get_all_types();
		foreach ( $this->parent_constants() as $name => $value ) {
			$this->assertArrayHasKey(
				$value,
				$types,
				"get_all_types() must contain type constant {$name} (value: {$value}) as a key."
			);
		}
	}

	/**
	 * get_all_types() values are all non-empty strings (the labels).
	 */
	public function test_get_all_types_values_are_non_empty_strings() {
		foreach ( AIPS_History_Operation_Type::get_all_types() as $key => $label ) {
			$this->assertIsString( $label, "Label for type '{$key}' must be a string." );
			$this->assertNotEmpty( $label, "Label for type '{$key}' must not be empty." );
		}
	}

	// -------------------------------------------------------------------------
	// get_parent_types() tests
	// -------------------------------------------------------------------------

	/**
	 * get_parent_types() returns an array.
	 */
	public function test_get_parent_types_returns_array() {
		$types = AIPS_History_Operation_Type::get_parent_types();
		$this->assertIsArray( $types, 'get_parent_types() must return an array.' );
		$this->assertNotEmpty( $types, 'get_parent_types() must not return an empty array.' );
	}

	/**
	 * get_parent_types() contains every batch/run-level constant.
	 */
	public function test_get_parent_types_contains_all_batch_constants() {
		$parent_types = AIPS_History_Operation_Type::get_parent_types();
		foreach ( $this->parent_constants() as $name => $value ) {
			$this->assertContains(
				$value,
				$parent_types,
				"get_parent_types() must contain constant {$name} (value: {$value})."
			);
		}
	}

	/**
	 * get_parent_types() does NOT contain individual item-level type constants.
	 */
	public function test_get_parent_types_excludes_item_level_types() {
		$parent_types = AIPS_History_Operation_Type::get_parent_types();
		foreach ( $this->item_constants() as $name => $value ) {
			$this->assertNotContains(
				$value,
				$parent_types,
				"get_parent_types() must not contain item-level constant {$name} (value: {$value})."
			);
		}
	}

	/**
	 * get_parent_types() values are all non-empty strings.
	 */
	public function test_get_parent_types_values_are_strings() {
		foreach ( AIPS_History_Operation_Type::get_parent_types() as $type ) {
			$this->assertIsString( $type );
			$this->assertNotEmpty( $type );
		}
	}
}
