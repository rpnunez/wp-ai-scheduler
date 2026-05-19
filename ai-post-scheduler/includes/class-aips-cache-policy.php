<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Policy
 *
 * Shared cache invalidation policy for subsystem-level scoped flushes.
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */
class AIPS_Cache_Policy {

	/**
	 * Return invalidation rules for known subsystems.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_subsystem_rules() {
		return array(
			'config' => array(
				'group'  => 'default',
				'prefix' => 'aips_config:',
			),
			'telemetry' => array(
				'group'  => 'telemetry',
				'prefix' => '',
			),
			'internal_links' => array(
				'group'  => 'internal_links',
				'prefix' => '',
			),
			'sources' => array(
				'group'  => 'sources',
				'prefix' => '',
			),
			'embeddings' => array(
				'group'  => 'embeddings',
				'prefix' => '',
			),
		);
	}

	/**
	 * Get one subsystem rule.
	 *
	 * @param string $subsystem Subsystem identifier.
	 * @return array<string, string>|null
	 */
	public static function get_rule( $subsystem ) {
		$rules = self::get_subsystem_rules();
		return isset( $rules[ $subsystem ] ) ? $rules[ $subsystem ] : null;
	}
}
