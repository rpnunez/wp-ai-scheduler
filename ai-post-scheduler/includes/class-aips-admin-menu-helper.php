<?php
/**
 * Admin Menu Helper
 *
 * Provides a centralized way to generate URLs for admin pages to prevent
 * hardcoding admin menu page slugs throughout the application.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Menu_Helper
 *
 * Helper class for generating admin URLs.
 */
class AIPS_Admin_Menu_Helper {

	/**
	 * Map of logical page names to their actual slugs.
	 *
	 * @var array<string, string>
	 */
	private static $page_slugs = array(
		'dashboard'            => 'ai-post-scheduler',
		'content_setup_hub'    => 'aips-content-setup',
		'automation_hub'       => 'aips-automation',
		'outputs_hub'          => 'aips-outputs',
		'operations_hub'       => 'aips-operations',
		'site_context_hub'     => 'aips-site-context',
		'settings_hub'         => 'aips-settings-hub',
		'templates'            => 'aips-templates',
		'voices'               => 'aips-voices',
		'structures'           => 'aips-structures',
		'prompt_sections'      => 'aips-sections',
		'authors'              => 'aips-authors',
		'post_slices'          => 'aips-post-slices',
		'author_topics'        => 'aips-author-topics',
		'schedule'             => 'aips-schedule',
		'schedule_calendar'    => 'aips-schedule-calendar',
		'generated_posts'      => 'aips-generated-posts',
		'history'              => 'aips-history',
		'operations_insights'  => 'aips-operations-insights',
		'research'             => 'aips-research',
		'sources'              => 'aips-sources',
		'taxonomy'             => 'aips-taxonomy',
		'internal_links'       => 'aips-internal-links',
		'system_status'        => 'aips-status',
		'telemetry'            => 'aips-telemetry',
		'settings'             => 'aips-settings',
		'seeder'               => 'aips-seeder',
		'dev_tools'            => 'aips-dev-tools',
		'onboarding'           => 'aips-onboarding',
	);

	/**
	 * Get the admin URL for a specific plugin page.
	 *
	 * @param string $page The logical name of the page (e.g., 'dashboard', 'templates', 'schedule').
	 * @param array<string, string|int> $args Optional query arguments to append to the URL.
	 * @return string The escaped admin URL.
	 */
	public static function get_page_url($page, $args = array()) {
		$default_args = array();

		if (!isset(self::$page_slugs[$page])) {
			// Fallback to the provided slug if it's not in our map
			$slug = $page;
		} else {
			$slug = self::$page_slugs[$page];
		}

		switch ($page) {
			case 'dashboard':
				$slug         = self::$page_slugs['dashboard'];
				$default_args = array('tab' => 'overview');
				break;
			case 'onboarding':
				$slug         = self::$page_slugs['dashboard'];
				$default_args = array('tab' => 'onboarding');
				break;
			case 'templates':
				$slug         = self::$page_slugs['content_setup_hub'];
				$default_args = array('tab' => 'templates');
				break;
			case 'voices':
				$slug         = self::$page_slugs['content_setup_hub'];
				$default_args = array('tab' => 'voices');
				break;
			case 'structures':
				$slug         = self::$page_slugs['content_setup_hub'];
				$default_args = array(
					'tab'    => 'structures',
					'subtab' => 'aips-structures',
				);
				break;
			case 'prompt_sections':
				$slug         = self::$page_slugs['content_setup_hub'];
				$default_args = array(
					'tab' => 'prompt-blocks',
				);
				break;
			case 'post_slices':
				$slug         = self::$page_slugs['content_setup_hub'];
				$default_args = array(
					'tab' => 'post-slices',
				);
				break;
			case 'authors':
				$slug         = self::$page_slugs['automation_hub'];
				$default_args = array(
					'tab'    => 'authors',
					'subtab' => 'authors-list',
				);
				break;
			case 'author_topics':
				$slug         = self::$page_slugs['automation_hub'];
				$default_args = array(
					'tab'    => 'authors',
					'subtab' => 'author-topics',
				);
				break;
			case 'research':
				$slug         = self::$page_slugs['automation_hub'];
				$default_args = array(
					'tab'    => 'research',
					'subtab' => 'trending',
				);
				break;
			case 'schedule':
				$slug         = self::$page_slugs['automation_hub'];
				$default_args = array('tab' => 'schedule');
				break;
			case 'schedule_calendar':
				$slug         = self::$page_slugs['automation_hub'];
				$default_args = array('tab' => 'calendar');
				break;
			case 'generated_posts':
				$slug         = self::$page_slugs['outputs_hub'];
				$default_args = array(
					'tab'    => 'content-queue',
					'subtab' => 'aips-generated-posts',
				);
				break;
			case 'history':
				$slug         = self::$page_slugs['outputs_hub'];
				$default_args = array('tab' => 'history');
				break;
			case 'operations_insights':
				$slug         = self::$page_slugs['operations_hub'];
				$default_args = array('tab' => 'insights');
				break;
			case 'sources':
				$slug         = self::$page_slugs['site_context_hub'];
				$default_args = array('tab' => 'sources');
				break;
			case 'taxonomy':
				$slug         = self::$page_slugs['site_context_hub'];
				$default_args = array(
					'tab'    => 'taxonomy',
					'subtab' => 'categories',
				);
				break;
			case 'internal_links':
				$slug         = self::$page_slugs['site_context_hub'];
				$default_args = array(
					'tab'    => 'internal-links',
					'subtab' => 'suggestions',
				);
				break;
			case 'settings':
				$slug         = self::$page_slugs['settings_hub'];
				$default_args = array(
					'tab'    => 'general',
					'subtab' => 'settings-general',
				);
				break;
			case 'system_status':
				$slug         = self::$page_slugs['settings_hub'];
				$default_args = array('tab' => 'system');
				break;
			case 'seeder':
				$slug         = self::$page_slugs['settings_hub'];
				$default_args = array('tab' => 'utilities');
				break;
			case 'telemetry':
				$slug         = self::$page_slugs['settings_hub'];
				$default_args = array('tab' => 'telemetry');
				break;
			case 'dev_tools':
				$slug         = self::$page_slugs['settings_hub'];
				$default_args = array('tab' => 'developer');
				break;
		}

		$url = admin_url('admin.php?page=' . $slug);

		if (!empty($default_args) || !empty($args)) {
			$url = add_query_arg(array_merge($default_args, $args), $url);
		}

		return $url;
	}

	/**
	 * Return the actual slug for a given page name.
	 *
	 * @param string $page Logical page name.
	 * @return string The registered slug.
	 */
	public static function get_slug($page) {
		return isset(self::$page_slugs[$page]) ? self::$page_slugs[$page] : $page;
	}
}
