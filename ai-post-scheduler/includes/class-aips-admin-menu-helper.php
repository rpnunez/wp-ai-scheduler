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
		'templates'            => 'aips-templates',
		'authors'              => 'aips-authors',
		'post_slices'          => 'aips-post-slices',
		'schedule'             => 'aips-schedule',
		'generated_posts'      => 'aips-generated-posts',
		'author_topics'        => 'aips-author-topics',
		'observability'        => 'aips-observability',
		'system_status'        => 'aips-observability',
		'system-status'        => 'aips-observability',
		'operations_insights'  => 'aips-observability',
		'telemetry'            => 'aips-observability',
		'settings'             => 'aips-settings',
		'onboarding'           => 'aips-onboarding',
		'history'              => 'aips-history',
		'seeder'               => 'aips-seeder',
		'research'             => 'aips-research',
	);

	/**
	 * Default query arguments for logical page names.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $page_args = array(
		'system_status'       => array('tab' => 'health'),
		'system-status'       => array('tab' => 'health'),
		'operations_insights' => array('tab' => 'operations'),
		'telemetry'           => array('tab' => 'telemetry'),
	);

	/**
	 * Get the admin URL for a specific plugin page.
	 *
	 * @param string $page The logical name of the page (e.g., 'dashboard', 'templates', 'schedule').
	 * @param array<string, string|int> $args Optional query arguments to append to the URL.
	 * @return string The escaped admin URL.
	 */
	public static function get_page_url($page, $args = array()) {
		if (!isset(self::$page_slugs[$page])) {
			// Fallback to the provided slug if it's not in our map
			$slug = $page;
		} else {
			$slug = self::$page_slugs[$page];
		}

		$url = admin_url('admin.php?page=' . $slug);

		$query_args = isset(self::$page_args[$page]) ? self::$page_args[$page] : array();

		if (!empty($args)) {
			$query_args = array_merge($query_args, $args);
		}

		if (!empty($query_args)) {
			$url = add_query_arg($query_args, $url);
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
