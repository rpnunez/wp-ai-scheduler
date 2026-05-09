<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registry for the new admin hub pages.
 */
class AIPS_Admin_Hub_Registry {

	/**
	 * Return all visible hub page definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_hubs() {
		$hubs = array(
			'content_setup' => array(
				'slug'         => 'aips-content-setup',
				'menu_title'   => __('Content Setup', 'ai-post-scheduler'),
				'page_title'   => __('Content Setup', 'ai-post-scheduler'),
				'description'  => __('Configure the reusable building blocks that shape generated posts: templates, voices, article structures, and prompt blocks.', 'ai-post-scheduler'),
				'legacy_pages' => array(
					'aips-templates',
					'aips-voices',
					'aips-structures',
					'aips-sections',
				),
				'tabs'         => array(
					array(
						'key'     => 'templates',
						'label'   => __('Templates', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/templates.php',
					),
					array(
						'key'     => 'voices',
						'label'   => __('Voices', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/voices.php',
					),
					array(
						'key'     => 'structures',
						'label'   => __('Structures', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/structures.php',
					),
					array(
						'key'     => 'prompt-blocks',
						'label'   => __('Prompt Blocks', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/prompt-blocks.php',
					),
				),
			),
			'automation'    => array(
				'slug'         => 'aips-automation',
				'menu_title'   => __('Automation', 'ai-post-scheduler'),
				'page_title'   => __('Automation', 'ai-post-scheduler'),
				'description'  => __('Plan what gets generated, when it runs, and how author-specific workflows move from research to scheduled output.', 'ai-post-scheduler'),
				'legacy_pages' => array(
					'aips-authors',
					'aips-author-topics',
					'aips-research',
					'aips-schedule',
					'aips-schedule-calendar',
				),
				'tabs'         => array(
					array(
						'key'     => 'schedule',
						'label'   => __('Schedule', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/schedule.php',
					),
					array(
						'key'     => 'calendar',
						'label'   => __('Calendar', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/calendar.php',
					),
					array(
						'key'     => 'authors',
						'label'   => __('Authors', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/authors.php',
					),
					array(
						'key'     => 'research',
						'label'   => __('Research', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/research.php',
					),
				),
			),
			'outputs'       => array(
				'slug'         => 'aips-outputs',
				'menu_title'   => __('Outputs', 'ai-post-scheduler'),
				'page_title'   => __('Outputs', 'ai-post-scheduler'),
				'description'  => __('Review generated content, keep drafts moving, and inspect generation history without hunting through separate menu branches.', 'ai-post-scheduler'),
				'legacy_pages' => array(
					'aips-generated-posts',
					'aips-history',
				),
				'tabs'         => array(
					array(
						'key'     => 'content-queue',
						'label'   => __('Content Queue', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/outputs/content-queue.php',
					),
					array(
						'key'     => 'review-pipeline',
						'label'   => __('Review Pipeline', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/outputs/review-pipeline.php',
					),
					array(
						'key'     => 'history',
						'label'   => __('History', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/outputs/history.php',
					),
				),
			),
			'site_context'  => array(
				'slug'         => 'aips-site-context',
				'menu_title'   => __('Site Context', 'ai-post-scheduler'),
				'page_title'   => __('Site Context', 'ai-post-scheduler'),
				'description'  => __('Manage the site knowledge the generators rely on: reference sources, taxonomy terms, and internal linking guidance.', 'ai-post-scheduler'),
				'legacy_pages' => array(
					'aips-sources',
					'aips-taxonomy',
					'aips-internal-links',
				),
				'tabs'         => array(
					array(
						'key'     => 'sources',
						'label'   => __('Sources', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/site-context/sources.php',
					),
					array(
						'key'     => 'taxonomy',
						'label'   => __('Taxonomy', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/site-context/taxonomy.php',
					),
					array(
						'key'     => 'internal-links',
						'label'   => __('Internal Links', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/site-context/internal-links.php',
					),
				),
			),
			'settings'      => array(
				'slug'         => 'aips-settings-hub',
				'menu_title'   => __('Settings', 'ai-post-scheduler'),
				'page_title'   => __('Settings', 'ai-post-scheduler'),
				'description'  => __('Keep plugin configuration, system diagnostics, seed data, telemetry, and developer tools in one administrative workspace.', 'ai-post-scheduler'),
				'legacy_pages' => array(
					'aips-settings',
					'aips-status',
					'aips-seeder',
					'aips-telemetry',
					'aips-dev-tools',
					'aips-onboarding',
				),
				'tabs'         => self::get_settings_tabs(),
			),
		);

		return $hubs;
	}

	/**
	 * Return the visible settings hub tabs, honoring feature flags.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_settings_tabs() {
		$tabs = array(
			array(
				'key'     => 'general',
				'label'   => __('General', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/general.php',
			),
			array(
				'key'     => 'system',
				'label'   => __('System Status', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/system.php',
			),
			array(
				'key'     => 'utilities',
				'label'   => __('Utilities', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/utilities.php',
			),
		);

		if (AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			$tabs[] = array(
				'key'     => 'telemetry',
				'label'   => __('Telemetry', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/telemetry.php',
			);
		}

		if (AIPS_Config::get_instance()->get_option('aips_developer_mode')) {
			$tabs[] = array(
				'key'     => 'developer',
				'label'   => __('Developer', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/developer.php',
			);
		}

		return $tabs;
	}

	/**
	 * Fetch one hub definition by internal key.
	 *
	 * @param string $hub_key Hub registry key.
	 * @return array<string, mixed>|null
	 */
	public static function get_hub($hub_key) {
		$hubs = self::get_hubs();

		return isset($hubs[ $hub_key ]) ? $hubs[ $hub_key ] : null;
	}

	/**
	 * Resolve a visible hub definition from an admin page slug.
	 *
	 * @param string $slug Page slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_hub_by_slug($slug) {
		foreach (self::get_hubs() as $hub) {
			if ($hub['slug'] === $slug) {
				return $hub;
			}
		}

		return null;
	}

	/**
	 * Map any visible or hidden plugin page slug to its visible hub slug.
	 *
	 * @param string $page_slug Current admin page slug.
	 * @return string
	 */
	public static function get_visible_slug_for_page($page_slug) {
		if ('ai-post-scheduler' === $page_slug) {
			return 'ai-post-scheduler';
		}

		foreach (self::get_hubs() as $hub) {
			if ($hub['slug'] === $page_slug) {
				return $hub['slug'];
			}

			if (in_array($page_slug, $hub['legacy_pages'], true)) {
				return $hub['slug'];
			}
		}

		return '';
	}
}
