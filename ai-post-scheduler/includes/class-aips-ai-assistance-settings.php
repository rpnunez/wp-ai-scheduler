<?php
/**
 * Central policy for AI Assistance.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Assistance_Settings {
	const CONTEXTS = array(
		'authors',
		'templates',
		'sources',
		'content_strategy',
	);

	public static function get_option_schema(): array {
		$defaults = AIPS_Config::get_instance()->get_default_options();
		return array(
			'aips_ai_assistance_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_ai_assistance_enabled'],
			),
			'aips_ai_assistance_contexts' => array(
				'sanitize_callback' => array(__CLASS__, 'sanitize_contexts'),
				'default'           => $defaults['aips_ai_assistance_contexts'],
			),
			'aips_ai_assistance_response_length' => array(
				'sanitize_callback' => array(__CLASS__, 'sanitize_response_length'),
				'default'           => $defaults['aips_ai_assistance_response_length'],
			),
			'aips_ai_assistance_guidance' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $defaults['aips_ai_assistance_guidance'],
			),
			'aips_ai_assistance_overwrite_mode' => array(
				'sanitize_callback' => array(__CLASS__, 'sanitize_overwrite_mode'),
				'default'           => $defaults['aips_ai_assistance_overwrite_mode'],
			),
			'aips_ai_assistance_retention_days' => array(
				'sanitize_callback' => array(__CLASS__, 'sanitize_retention_days'),
				'default'           => $defaults['aips_ai_assistance_retention_days'],
			),
		);
	}

	public static function sanitize_contexts($contexts): array {
		$contexts = is_array($contexts) ? $contexts : array();
		return array_values( array_intersect( self::CONTEXTS, array_map( 'sanitize_key', $contexts ) ) );
	}

	public static function sanitize_response_length($value): string {
		return in_array( $value, array( 'concise', 'standard', 'detailed' ), true ) ? $value : 'standard';
	}

	public static function sanitize_overwrite_mode($value): string {
		return in_array( $value, array( 'replace', 'preview' ), true ) ? $value : 'preview';
	}

	public static function sanitize_retention_days($value): int {
		$value = absint($value);
		return in_array( $value, array( 0, 7, 30, 90, 365 ), true ) ? $value : 30;
	}

	public static function is_enabled_for(string $context): bool {
		return (bool) AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_enabled' )
			&& in_array( $context, self::get_enabled_contexts(), true );
	}

	public static function get_enabled_contexts(): array {
		$contexts = AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_contexts' );
		return self::sanitize_contexts($contexts);
	}

	public static function get_policy(): array {
		return array(
			'enabled'        => (bool) AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_enabled' ),
			'contexts'       => self::get_enabled_contexts(),
			'overwriteMode'  => self::sanitize_overwrite_mode( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_overwrite_mode' ) ),
			'responseLength' => self::sanitize_response_length( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_response_length' ) ),
		);
	}

	public static function get_retention_days(): int {
		return self::sanitize_retention_days( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_retention_days' ) );
	}
}
