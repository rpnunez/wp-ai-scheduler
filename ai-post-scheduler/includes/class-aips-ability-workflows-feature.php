<?php
/**
 * Ability Workflows feature switch
 *
 * Single place that answers "is the Ability Workflows feature on?" so the
 * menu, assets, AJAX endpoints and the cron executor all honour the same
 * `aips_enable_ability_workflows` setting. Hiding the menu alone does not
 * disable anything: the AJAX actions are routed by AIPS_Ajax_Registry
 * whether or not the page is visible.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Ability_Workflows_Feature {

	/**
	 * Whether Ability Workflows are enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) AIPS_Config::get_instance()->get_option('aips_enable_ability_workflows');
	}

	/**
	 * Stop an AJAX request with an error when the feature is off.
	 *
	 * @return void Exits through AIPS_Ajax_Response when disabled.
	 */
	public static function guard_ajax(): void {
		if (!self::is_enabled()) {
			AIPS_Ajax_Response::error(
				__('Ability Workflows are disabled. Enable them under Settings > Developer Settings.', 'ai-post-scheduler'),
				'ability_workflows_disabled',
				403
			);
		}
	}
}
