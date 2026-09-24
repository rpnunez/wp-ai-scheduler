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
		'automations'          => 'aips-automations',
		'studio'               => 'aips-studio',
		'research'             => 'aips-research',
		'generated_posts'      => 'aips-generated-posts',
		'content'              => 'aips-generated-posts',
		'history'              => 'aips-history',
		'settings'             => 'aips-settings',
		'diagnostics'          => 'aips-diagnostics',
		'campaign_wizard'      => 'aips-campaign-wizard',
		'campaign_detail'      => 'aips-campaign-detail',
		'author_topics'        => 'aips-author-topics',
		'onboarding'           => 'aips-onboarding',
		'dev_tools'            => 'aips-dev-tools',
	);

	/**
	 * Map of logical studio page names to Studio sections.
	 *
	 * @var array<string, string>
	 */
	private static $studio_tabs = array(
		'templates'          => 'templates',
		'voices'             => 'voices',
		'structures'         => 'structures',
		'article_structures' => 'structures',
		'post_slices'        => 'post-slices',
	);

	/**
	 * Map of logical automation page names to Automations tabs.
	 *
	 * @var array<string, string>
	 */
	private static $automations_tabs = array(
		'schedule'        => 'schedules',
		'schedules'       => 'schedules',
		'campaigns'       => 'campaigns',
		'authors'         => 'authors',
		'author_topics'   => 'author-topics',
		'sources'         => 'sources',
		'monetization'    => 'monetization',
		'affiliate_links' => 'monetization',
		'internal_links'  => 'internal-links',
		'taxonomy'        => 'taxonomy',
	);

	/**
	 * Map of logical diagnostics page names to Diagnostics tabs.
	 *
	 * @var array<string, string>
	 */
	private static $diagnostics_tabs = array(
		'system_status'       => 'status',
		'seeder'              => 'dev-tools',
		'operations_insights' => 'operations-insights',
		'telemetry'           => 'telemetry',
		'dev_tools'           => 'dev-tools',
	);

	/**
	 * Map of logical content page names to Content tabs.
	 *
	 * @var array<string, string>
	 */
	private static $content_tabs = array(
		'content_indexer' => 'content-indexer',
	);

	/**
	 * Get the admin URL for a specific plugin page.
	 *
	 * @param string $page The logical name of the page (e.g., 'dashboard', 'templates', 'schedule').
	 * @param array<string, string|int> $args Optional query arguments to append to the URL.
	 * @return string The escaped admin URL.
	 */
	public static function get_page_url($page, $args = array()) {
		if (isset(self::$studio_tabs[$page])) {
			$args = array_merge(array('tab' => self::$studio_tabs[$page]), $args);
			$url  = admin_url('admin.php?page=aips-studio');

			if (!empty($args)) {
				$url = add_query_arg($args, $url);
			}

			return $url;
		}

		if (isset(self::$automations_tabs[$page])) {
			$args = array_merge(array('tab' => self::$automations_tabs[$page]), $args);
			$url  = admin_url('admin.php?page=aips-automations');

			if (!empty($args)) {
				$url = add_query_arg($args, $url);
			}

			return $url;
		}

		if (isset(self::$diagnostics_tabs[$page])) {
			$args = array_merge(array('tab' => self::$diagnostics_tabs[$page]), $args);
			$url  = admin_url('admin.php?page=aips-diagnostics');

			if (!empty($args)) {
				$url = add_query_arg($args, $url);
			}

			return $url;
		}

		if (isset(self::$content_tabs[$page])) {
			$args = array_merge(array('tab' => self::$content_tabs[$page]), $args);
			$url  = admin_url('admin.php?page=aips-generated-posts');

			if (!empty($args)) {
				$url = add_query_arg($args, $url);
			}

			return $url;
		}

		if (!isset(self::$page_slugs[$page])) {
			// Fallback to the provided slug if it's not in our map
			$slug = $page;
		} else {
			$slug = self::$page_slugs[$page];
		}

		$url = admin_url('admin.php?page=' . $slug);

		if (!empty($args)) {
			$url = add_query_arg($args, $url);
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

	/**
	 * Safely execute a page or tab render callback with centralized exception catching and error logging.
	 *
	 * Prevents uncaught fatal exceptions or raw error messages from leaking into the admin UI,
	 * logging full trace details to AIPS_Logger and displaying a graceful user-facing fallback container.
	 *
	 * @param callable $callback Render callback.
	 * @param string   $title    User-facing module/page title.
	 * @param bool     $is_tab   True if rendering an embedded tab canvas, false if rendering a top-level page wrap.
	 * @return void
	 */
	public static function safe_render(callable $callback, string $title = 'Page', bool $is_tab = false) {
		try {
			call_user_func($callback);
		} catch (\Throwable $throwable) {
			if (class_exists('AIPS_Logger')) {
				AIPS_Logger::instance()->error(sprintf('%s render exception: %s', $title, $throwable->getMessage()), array(
					'file'  => $throwable->getFile(),
					'line'  => $throwable->getLine(),
					'trace' => $throwable->getTraceAsString(),
				));
			} else {
				error_log(sprintf('[AIPS] %s render exception: %s in %s:%d', $title, $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
			}

			if ($is_tab) {
				include AIPS_PLUGIN_DIR . 'templates/admin/errors/tab-error.php';
			} else {
				include AIPS_PLUGIN_DIR . 'templates/admin/errors/page-error.php';
			}
		}
	}
}

