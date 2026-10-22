<?php
/**
 * Admin Asset Context Resolver
 *
 * Resolves current admin screen context (hook suffix, $_GET['page'], $_GET['tab'])
 * and bridges with AIPS_Admin_Page_Context to determine which assets to enqueue.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Asset_Context_Resolver
 */
class AIPS_Admin_Asset_Context_Resolver {

	/**
	 * Plugin page slug prefix.
	 */
	public const PAGE_PREFIX = 'aips-';

	/**
	 * Main dashboard page slug.
	 */
	public const PAGE_DASHBOARD = 'ai-post-scheduler';

	/**
	 * Dashboard hook suffix.
	 */
	public const HOOK_DASHBOARD = 'toplevel_page_ai-post-scheduler';

	/**
	 * Admin page slugs.
	 */
	public const PAGE_AUTHORS = 'aips-authors';
	public const PAGE_AUTHOR_TOPICS = 'aips-author-topics';
	public const PAGE_POST_SLICES = 'aips-post-slices';
	public const PAGE_TEMPLATES = 'aips-templates';
	public const PAGE_VOICES = 'aips-voices';
	public const PAGE_STRUCTURES = 'aips-structures';
	public const PAGE_SCHEDULE = 'aips-schedule';
	public const PAGE_CAMPAIGNS = 'aips-campaigns';
	public const PAGE_CAMPAIGN_WIZARD = 'aips-campaign-wizard';
	public const PAGE_STUDIO = 'aips-studio';
	public const PAGE_RESEARCH = 'aips-research';
	public const PAGE_GENERATED_POSTS = 'aips-generated-posts';
	public const PAGE_HISTORY = 'aips-history';
	public const PAGE_ONBOARDING = 'aips-onboarding';
	public const PAGE_DIAGNOSTICS = 'aips-diagnostics';
	public const PAGE_AUTOMATIONS = 'aips-automations';
	public const PAGE_DEV_TOOLS = 'aips-dev-tools';
	public const PAGE_STATUS = 'aips-status';
	public const PAGE_TAXONOMY = 'aips-taxonomy';
	public const PAGE_SOURCES = 'aips-sources';
	public const PAGE_SOURCE_DATA = 'aips-source-data';
	public const PAGE_SETTINGS = 'aips-settings';
	public const PAGE_TELEMETRY = 'aips-telemetry';
	public const PAGE_INTERNAL_LINKS = 'aips-internal-links';
	public const PAGE_CONTENT_INTELLIGENCE = 'aips-content-intelligence';
	public const PAGE_POST_CLUSTERS = 'aips-post-clusters';
	public const PAGE_CANNIBALIZATION = 'aips-cannibalization';
	public const PAGE_CONTENT_INDEXER = 'aips-content-indexer';
	public const PAGE_CACHE_MONITOR  = 'aips-cache-monitor';
	public const PAGE_STRESS_TEST    = 'aips-stress-test';

	/**
	 * Get the current sanitized admin page slug from the request.
	 *
	 * @return string
	 */
	public function get_current_page_slug() {
		$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		if (!is_string($page) || '' === $page) {
			if (isset($_GET['page']) && is_string($_GET['page'])) {
				return sanitize_key(wp_unslash($_GET['page']));
			}
			return '';
		}

		return sanitize_key(wp_unslash($page));
	}

	/**
	 * Get the current sanitized tab key from the request.
	 *
	 * @return string
	 */
	public function get_current_tab_key() {
		$tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);

		if (!is_string($tab) || '' === $tab) {
			if (isset($_GET['tab']) && is_string($_GET['tab'])) {
				return sanitize_key(wp_unslash($_GET['tab']));
			}
			return '';
		}

		return sanitize_key(wp_unslash($tab));
	}

	/**
	 * Check whether the current admin hook includes a page slug or fragment.
	 *
	 * @param string $hook   Current admin page hook.
	 * @param string $needle Page slug or hook fragment.
	 * @return bool
	 */
	public function hook_contains($hook, $needle) {
		if (empty($hook) || empty($needle)) {
			return false;
		}
		return strpos($hook, $needle) !== false;
	}

	/**
	 * Determine whether the current request is one of this plugin's admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @param string $page Current sanitized page slug.
	 * @return bool
	 */
	public function is_plugin_admin_page($hook, $page) {
		if (self::PAGE_DASHBOARD === $page || 0 === strpos($page, self::PAGE_PREFIX)) {
			return true;
		}

		return $this->hook_contains($hook, self::PAGE_DASHBOARD) || $this->hook_contains($hook, self::PAGE_PREFIX);
	}

	/**
	 * Determine whether the current request is a native WordPress post edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	public function is_native_post_admin_page($hook) {
		$allowed_hooks = array('edit.php', 'post.php', 'post-new.php');

		if (!in_array($hook, $allowed_hooks, true)) {
			return false;
		}

		if (!current_user_can('manage_options')) {
			return false;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen) {
			return false;
		}

		return 'post' === $screen->post_type;
	}

	/**
	 * Determine whether the Diagnostics page is displaying a specific tab.
	 *
	 * @param string $page Current sanitized page slug.
	 * @param string $tab  Tab key to test.
	 * @return bool
	 */
	public function is_diagnostics_tab($page, $tab) {
		if (self::PAGE_DIAGNOSTICS !== $page) {
			return false;
		}

		$current_tab = $this->get_current_tab_key();
		if (!empty($current_tab)) {
			return $tab === $current_tab;
		}

		if (class_exists('AIPS_Diagnostics_Controller')) {
			return $tab === AIPS_Diagnostics_Controller::get_active_tab_key();
		}

		return 'system-info' === $tab;
	}

	/**
	 * Determine whether the Automations page is displaying a specific tab.
	 *
	 * @param string $page Current sanitized page slug.
	 * @param string $tab  Tab key to test.
	 * @return bool
	 */
	public function is_automations_tab($page, $tab) {
		if (self::PAGE_AUTOMATIONS !== $page) {
			return false;
		}

		$current_tab = $this->get_current_tab_key();
		if (!empty($current_tab)) {
			return $tab === $current_tab;
		}

		if (class_exists('AIPS_Automations_Controller')) {
			return $tab === AIPS_Automations_Controller::get_active_tab_key();
		}

		return 'schedules' === $tab;
	}

	/**
	 * Resolve current route context and feature flags for asset enqueueing.
	 *
	 * @param string $hook The current admin page hook.
	 * @return array<string, mixed>
	 */
	public function resolve($hook) {
		$page = $this->get_current_page_slug();
		$tab  = $this->get_current_tab_key();

		$is_plugin_page = $this->is_plugin_admin_page($hook, $page);
		$is_native_post = $this->is_native_post_admin_page($hook);

		$page_context = null;
		if ($is_plugin_page && class_exists('AIPS_Admin_Page_Context')) {
			$page_context = AIPS_Admin_Page_Context::resolve($page, $tab);
		}

		$is_dashboard = $this->hook_contains($hook, self::HOOK_DASHBOARD) || self::PAGE_DASHBOARD === $page;

		$author_topics_tab = class_exists('AIPS_Automations_Controller') ? AIPS_Automations_Controller::TAB_AUTHOR_TOPICS : 'author-topics';
		$is_authors = self::PAGE_AUTHORS === $page
			|| self::PAGE_AUTHOR_TOPICS === $page
			|| $this->hook_contains($hook, self::PAGE_AUTHORS)
			|| $this->hook_contains($hook, self::PAGE_AUTHOR_TOPICS)
			|| $this->is_automations_tab($page, 'authors')
			|| $this->is_automations_tab($page, $author_topics_tab);

		$is_studio = self::PAGE_STUDIO === $page
			|| $this->hook_contains($hook, self::PAGE_STUDIO)
			|| in_array($page, array(self::PAGE_TEMPLATES, self::PAGE_VOICES, self::PAGE_STRUCTURES, self::PAGE_POST_SLICES), true);

		$is_schedule = self::PAGE_SCHEDULE === $page
			|| $this->hook_contains($hook, self::PAGE_SCHEDULE)
			|| $this->is_automations_tab($page, 'schedules');

		$campaign_detail_slug = class_exists('AIPS_Campaigns_Controller') ? AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG : 'aips-campaign-detail';
		$is_campaigns = self::PAGE_CAMPAIGNS === $page
			|| $campaign_detail_slug === $page
			|| $this->hook_contains($hook, self::PAGE_CAMPAIGNS)
			|| $this->hook_contains($hook, $campaign_detail_slug)
			|| $this->is_automations_tab($page, 'campaigns');

		$is_campaign_wizard = self::PAGE_CAMPAIGN_WIZARD === $page || $this->hook_contains($hook, self::PAGE_CAMPAIGN_WIZARD);

		$is_research = self::PAGE_RESEARCH === $page || $this->hook_contains($hook, self::PAGE_RESEARCH);

		$is_generated_posts = self::PAGE_GENERATED_POSTS === $page || $this->hook_contains($hook, self::PAGE_GENERATED_POSTS);

		$is_history = self::PAGE_HISTORY === $page || $this->hook_contains($hook, self::PAGE_HISTORY);

		$is_onboarding = self::PAGE_ONBOARDING === $page || $this->hook_contains($hook, self::PAGE_ONBOARDING);

		$developer_mode = (bool) AIPS_Config::get_instance()->get_option('aips_developer_mode');
		$is_dev_tools = (self::PAGE_DEV_TOOLS === $page || $this->hook_contains($hook, self::PAGE_DEV_TOOLS) || $this->is_diagnostics_tab($page, 'dev-tools')) && $developer_mode;

		$is_background_processes = $this->is_diagnostics_tab($page, 'background-processes');

		$is_status = self::PAGE_STATUS === $page
			|| $this->hook_contains($hook, self::PAGE_STATUS)
			|| $this->is_diagnostics_tab($page, 'status')
			|| $this->is_diagnostics_tab($page, 'system-info')
			|| $this->is_diagnostics_tab($page, 'health')
			|| $this->is_diagnostics_tab($page, 'operations');

		$is_taxonomy = self::PAGE_TAXONOMY === $page
			|| $this->hook_contains($hook, self::PAGE_TAXONOMY)
			|| $this->is_automations_tab($page, 'taxonomy');

		$is_sources = self::PAGE_SOURCES === $page
			|| self::PAGE_SOURCE_DATA === $page
			|| $this->hook_contains($hook, self::PAGE_SOURCES)
			|| $this->hook_contains($hook, self::PAGE_SOURCE_DATA)
			|| $this->is_automations_tab($page, 'sources');

		$is_settings = self::PAGE_SETTINGS === $page || $this->hook_contains($hook, self::PAGE_SETTINGS);

		$telemetry_enabled = (bool) AIPS_Config::get_instance()->get_option('aips_enable_telemetry');
		$is_telemetry = (self::PAGE_TELEMETRY === $page || $this->hook_contains($hook, self::PAGE_TELEMETRY) || $this->is_diagnostics_tab($page, 'telemetry')) && $telemetry_enabled;

		$is_internal_links = self::PAGE_INTERNAL_LINKS === $page
			|| $this->hook_contains($hook, self::PAGE_INTERNAL_LINKS)
			|| $this->is_automations_tab($page, 'internal-links');

		$is_content_intelligence = self::PAGE_CONTENT_INTELLIGENCE === $page
			|| self::PAGE_POST_CLUSTERS === $page
			|| self::PAGE_CANNIBALIZATION === $page
			|| self::PAGE_CONTENT_INDEXER === $page
			|| $this->hook_contains($hook, self::PAGE_CONTENT_INTELLIGENCE)
			|| $this->hook_contains($hook, self::PAGE_POST_CLUSTERS)
			|| $this->hook_contains($hook, self::PAGE_CANNIBALIZATION)
			|| $this->hook_contains($hook, self::PAGE_CONTENT_INDEXER)
			|| $this->is_automations_tab($page, 'content-indexer');

		$is_cache_monitor = self::PAGE_CACHE_MONITOR === $page
			|| $this->hook_contains($hook, self::PAGE_CACHE_MONITOR)
			|| $this->is_diagnostics_tab($page, 'cache-monitor');

		$is_stress_test = self::PAGE_STRESS_TEST === $page
			|| $this->hook_contains($hook, self::PAGE_STRESS_TEST)
			|| $this->is_diagnostics_tab($page, 'stress-test');

		return array(
			'hook'                    => $hook,
			'page'                    => $page,
			'tab'                     => $tab,
			'is_plugin_page'          => $is_plugin_page,
			'is_native_post'          => $is_native_post,
			'page_context'            => $page_context,
			'is_dashboard'            => $is_dashboard,
			'is_authors'              => $is_authors,
			'is_studio'               => $is_studio,
			'is_schedule'             => $is_schedule,
			'is_campaigns'            => $is_campaigns,
			'is_campaign_wizard'      => $is_campaign_wizard,
			'is_research'             => $is_research,
			'is_generated_posts'      => $is_generated_posts,
			'is_history'              => $is_history,
			'is_onboarding'           => $is_onboarding,
			'is_dev_tools'            => $is_dev_tools,
			'is_background_processes' => $is_background_processes,
			'is_status'               => $is_status,
			'is_taxonomy'             => $is_taxonomy,
			'is_sources'              => $is_sources,
			'is_settings'             => $is_settings,
			'is_telemetry'            => $is_telemetry,
			'is_internal_links'       => $is_internal_links,
			'is_content_intelligence' => $is_content_intelligence,
			'is_cache_monitor'        => $is_cache_monitor,
			'is_stress_test'          => $is_stress_test,
		);
	}
}
