<?php
/**
 * Tests for AIPS_Setting_Definition.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Setting_Definition extends WP_UnitTestCase {

	public function test_instantiation_and_getters() {
		$args = array(
			'key'               => 'aips_test_setting',
			'type'              => AIPS_Setting_Definition::TYPE_INT,
			'default'           => 42,
			'tab'               => 'settings-general',
			'section'           => 'aips_general_section',
			'label'             => 'Test Label',
			'description'       => 'Test Description',
			'control_type'      => AIPS_Setting_Definition::CONTROL_NUMBER,
			'control_options'   => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
			'sanitize_callback' => 'absint',
			'dependencies'      => array( 'field' => 'aips_enable_logging', 'value' => 1 ),
		);

		$def = AIPS_Setting_Definition::from_array( $args );

		$this->assertSame( 'aips_test_setting', $def->get_key() );
		$this->assertSame( AIPS_Setting_Definition::TYPE_INT, $def->get_type() );
		$this->assertSame( 42, $def->get_default() );
		$this->assertSame( 'settings-general', $def->get_tab() );
		$this->assertSame( 'aips_general_section', $def->get_section() );
		$this->assertSame( 'Test Label', $def->get_label() );
		$this->assertSame( 'Test Description', $def->get_description() );
		$this->assertSame( AIPS_Setting_Definition::CONTROL_NUMBER, $def->get_control_type() );
		$this->assertSame( array( 'min' => 1, 'max' => 100, 'step' => 1 ), $def->get_control_options() );
		$this->assertSame( 'absint', $def->get_sanitize_callback() );
		$this->assertSame( array( 'field' => 'aips_enable_logging', 'value' => 1 ), $def->get_dependencies() );
	}

	public function test_type_coercion_boolean() {
		$def = new AIPS_Setting_Definition( array(
			'key'  => 'test_bool',
			'type' => AIPS_Setting_Definition::TYPE_BOOL,
		) );

		$this->assertTrue( $def->coerce_type( 'true' ) );
		$this->assertTrue( $def->coerce_type( '1' ) );
		$this->assertTrue( $def->coerce_type( 1 ) );
		$this->assertTrue( $def->coerce_type( true ) );

		$this->assertFalse( $def->coerce_type( 'false' ) );
		$this->assertFalse( $def->coerce_type( '0' ) );
		$this->assertFalse( $def->coerce_type( 0 ) );
		$this->assertFalse( $def->coerce_type( false ) );
	}

	public function test_type_coercion_integer_and_float() {
		$int_def = new AIPS_Setting_Definition( array(
			'key'  => 'test_int',
			'type' => AIPS_Setting_Definition::TYPE_INT,
		) );
		$this->assertSame( 15, $int_def->coerce_type( '15' ) );
		$this->assertSame( -5, $int_def->coerce_type( '-5' ) );

		$float_def = new AIPS_Setting_Definition( array(
			'key'  => 'test_float',
			'type' => AIPS_Setting_Definition::TYPE_FLOAT,
		) );
		$this->assertEqualsWithDelta( 0.75, $float_def->coerce_type( '0.75' ), 0.0001 );
	}

	public function test_type_coercion_array() {
		$def = new AIPS_Setting_Definition( array(
			'key'  => 'test_array',
			'type' => AIPS_Setting_Definition::TYPE_ARRAY,
		) );

		$this->assertSame( array( 'a', 'b' ), $def->coerce_type( array( 'a', 'b' ) ) );
		$this->assertSame( array( 'one', 'two' ), $def->coerce_type( '["one","two"]' ) );
		$this->assertSame( array( 'x', 'y' ), $def->coerce_type( 'x, y' ) );
		$this->assertSame( array(), $def->coerce_type( '' ) );
	}

	public function test_sanitization_with_custom_callback() {
		$def = new AIPS_Setting_Definition( array(
			'key'               => 'test_clamped',
			'type'              => AIPS_Setting_Definition::TYPE_INT,
			'sanitize_callback' => static function( $v ) {
				return min( 50, max( 5, absint( $v ) ) );
			},
		) );

		$this->assertSame( 5, $def->sanitize( 2 ) );
		$this->assertSame( 25, $def->sanitize( 25 ) );
		$this->assertSame( 50, $def->sanitize( 100 ) );
	}

	public function test_validation_callback() {
		$def = new AIPS_Setting_Definition( array(
			'key'               => 'test_valid',
			'type'              => AIPS_Setting_Definition::TYPE_STRING,
			'validate_callback' => static function( $v ) {
				return strlen( $v ) >= 3;
			},
		) );

		$this->assertTrue( $def->validate( 'valid' ) );
		$this->assertFalse( $def->validate( 'no' ) );
	}

	public function test_get_control_option() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'test_opts',
			'control_options' => array(
				'step'   => 5,
				'custom' => 'special_value',
			),
		) );

		$this->assertSame( 5, $def->get_control_option( 'step' ) );
		$this->assertSame( 'special_value', $def->get_control_option( 'custom' ) );
		$this->assertNull( $def->get_control_option( 'non_existent' ) );
		$this->assertSame( 'fallback', $def->get_control_option( 'non_existent', 'fallback' ) );
	}
}
