<?php
/**
 * Tests for AIPS_AI_Assistance_Settings.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_AI_Assistance_Settings extends WP_UnitTestCase {
	public function tearDown(): void {
		$option_suffixes = array(
			'enabled',
			'contexts',
			'response_length',
			'guidance',
			'overwrite_mode',
			'retention_days',
		);

		foreach ( $option_suffixes as $key ) {
			delete_option( 'aips_ai_assistance_' . $key );
		}
		parent::tearDown();
	}

	public function test_policy_enforces_global_and_context_switches() {
		update_option( 'aips_ai_assistance_enabled', 1 );
		update_option( 'aips_ai_assistance_contexts', array( 'authors', 'templates' ) );
		$this->assertTrue( AIPS_AI_Assistance_Settings::is_enabled_for( 'authors' ) );
		$this->assertFalse( AIPS_AI_Assistance_Settings::is_enabled_for( 'sources' ) );
		update_option( 'aips_ai_assistance_enabled', 0 );
		$this->assertFalse( AIPS_AI_Assistance_Settings::is_enabled_for( 'authors' ) );
	}

	/** @dataProvider retention_values */
	public function test_retention_values_are_limited_to_supported_periods($value, $expected) {
		$this->assertSame( $expected, AIPS_AI_Assistance_Settings::sanitize_retention_days( $value ) );
	}

	public function retention_values(): array {
		return array(
			array( 0, 0 ),
			array( 7, 7 ),
			array( 30, 30 ),
			array( 99, 30 ),
		);
	}
}
