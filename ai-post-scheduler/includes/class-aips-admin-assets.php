<?php
/**
 * Admin Assets Coordinator
 *
 * Coordinates the enqueueing of admin styles and scripts across all plugin hubs and views,
 * delegating domain-specific assets to specialized registrars behind a unified context resolver.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Assets
 */
class AIPS_Admin_Assets {

	/**
	 * Plugin page slug prefix.
	 */
	public const PAGE_PREFIX = AIPS_Admin_Asset_Context_Resolver::PAGE_PREFIX;

	/**
	 * Main dashboard page slug.
	 */
	public const PAGE_DASHBOARD = AIPS_Admin_Asset_Context_Resolver::PAGE_DASHBOARD;

	/**
	 * Dashboard hook suffix.
	 */
	public const HOOK_DASHBOARD = AIPS_Admin_Asset_Context_Resolver::HOOK_DASHBOARD;

	/**
	 * Admin page slugs.
	 */
	public const PAGE_AUTHORS = AIPS_Admin_Asset_Context_Resolver::PAGE_AUTHORS;
	public const PAGE_AUTHOR_TOPICS = AIPS_Admin_Asset_Context_Resolver::PAGE_AUTHOR_TOPICS;
	public const PAGE_POST_SLICES = AIPS_Admin_Asset_Context_Resolver::PAGE_POST_SLICES;
	public const PAGE_TEMPLATES = AIPS_Admin_Asset_Context_Resolver::PAGE_TEMPLATES;
	public const PAGE_VOICES = AIPS_Admin_Asset_Context_Resolver::PAGE_VOICES;
	public const PAGE_STRUCTURES = AIPS_Admin_Asset_Context_Resolver::PAGE_STRUCTURES;
	public const PAGE_SCHEDULE = AIPS_Admin_Asset_Context_Resolver::PAGE_SCHEDULE;
	public const PAGE_CAMPAIGNS = AIPS_Admin_Asset_Context_Resolver::PAGE_CAMPAIGNS;
	public const PAGE_CAMPAIGN_WIZARD = AIPS_Admin_Asset_Context_Resolver::PAGE_CAMPAIGN_WIZARD;
	public const PAGE_STUDIO = AIPS_Admin_Asset_Context_Resolver::PAGE_STUDIO;
	public const PAGE_RESEARCH = AIPS_Admin_Asset_Context_Resolver::PAGE_RESEARCH;
	public const PAGE_GENERATED_POSTS = AIPS_Admin_Asset_Context_Resolver::PAGE_GENERATED_POSTS;
	public const PAGE_HISTORY = AIPS_Admin_Asset_Context_Resolver::PAGE_HISTORY;
	public const PAGE_ONBOARDING = AIPS_Admin_Asset_Context_Resolver::PAGE_ONBOARDING;
	public const PAGE_DIAGNOSTICS = AIPS_Admin_Asset_Context_Resolver::PAGE_DIAGNOSTICS;
	public const PAGE_AUTOMATIONS = AIPS_Admin_Asset_Context_Resolver::PAGE_AUTOMATIONS;
	public const PAGE_DEV_TOOLS = AIPS_Admin_Asset_Context_Resolver::PAGE_DEV_TOOLS;
	public const PAGE_STATUS = AIPS_Admin_Asset_Context_Resolver::PAGE_STATUS;
	public const PAGE_TAXONOMY = AIPS_Admin_Asset_Context_Resolver::PAGE_TAXONOMY;
	public const PAGE_SOURCES = AIPS_Admin_Asset_Context_Resolver::PAGE_SOURCES;
	public const PAGE_SOURCE_DATA = AIPS_Admin_Asset_Context_Resolver::PAGE_SOURCE_DATA;
	public const PAGE_SETTINGS = AIPS_Admin_Asset_Context_Resolver::PAGE_SETTINGS;
	public const PAGE_TELEMETRY = AIPS_Admin_Asset_Context_Resolver::PAGE_TELEMETRY;
	public const PAGE_INTERNAL_LINKS = AIPS_Admin_Asset_Context_Resolver::PAGE_INTERNAL_LINKS;
	public const PAGE_CONTENT_INTELLIGENCE = AIPS_Admin_Asset_Context_Resolver::PAGE_CONTENT_INTELLIGENCE;
	public const PAGE_POST_CLUSTERS = AIPS_Admin_Asset_Context_Resolver::PAGE_POST_CLUSTERS;
	public const PAGE_CANNIBALIZATION = AIPS_Admin_Asset_Context_Resolver::PAGE_CANNIBALIZATION;
	public const PAGE_CONTENT_INDEXER = AIPS_Admin_Asset_Context_Resolver::PAGE_CONTENT_INDEXER;
	public const PAGE_CACHE_MONITOR  = AIPS_Admin_Asset_Context_Resolver::PAGE_CACHE_MONITOR;
	public const PAGE_STRESS_TEST    = AIPS_Admin_Asset_Context_Resolver::PAGE_STRESS_TEST;

	/**
	 * @var AIPS_Admin_Asset_Context_Resolver
	 */
	private $resolver;

	/**
	 * @var AIPS_Admin_Asset_Registrar_Global
	 */
	private $global_registrar;

	/**
	 * @var AIPS_Admin_Asset_Registrar_Content_Strategy
	 */
	private $content_strategy_registrar;

	/**
	 * @var AIPS_Admin_Asset_Registrar_Monitoring
	 */
	private $monitoring_registrar;

	/**
	 * @var AIPS_Admin_Asset_Registrar_Management
	 */
	private $management_registrar;

	/**
	 * @var AIPS_Admin_Asset_Registrar_Links_Indexer
	 */
	private $links_indexer_registrar;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->resolver                   = new AIPS_Admin_Asset_Context_Resolver();
		$this->global_registrar           = new AIPS_Admin_Asset_Registrar_Global();
		$this->content_strategy_registrar = new AIPS_Admin_Asset_Registrar_Content_Strategy();
		$this->monitoring_registrar       = new AIPS_Admin_Asset_Registrar_Monitoring();
		$this->management_registrar       = new AIPS_Admin_Asset_Registrar_Management();
		$this->links_indexer_registrar    = new AIPS_Admin_Asset_Registrar_Links_Indexer();

		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_filter('style_loader_src', array($this, 'filter_asset_version_for_dev'), 999, 2);
		add_filter('script_loader_src', array($this, 'filter_asset_version_for_dev'), 999, 2);
	}

	/**
	 * Get asset version string.
	 *
	 * Uses file modification timestamp in development mode (WP_DEBUG or SCRIPT_DEBUG)
	 * for instant automatic cache-busting, otherwise falls back to AIPS_VERSION.
	 *
	 * @param string $relative_path Path relative to plugin root (e.g. 'assets/css/admin.css').
	 * @return string|int
	 */
	public static function get_asset_version($relative_path = '') {
		$is_debug = (defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG);
		if ($is_debug && !empty($relative_path)) {
			$file = AIPS_PLUGIN_DIR . ltrim($relative_path, '/');
			if (file_exists($file)) {
				return filemtime($file);
			}
			return time();
		}

		return AIPS_VERSION;
	}

	/**
	 * Filter script and style loader URLs to auto-cache-bust in development (WP_DEBUG/SCRIPT_DEBUG).
	 *
	 * @param string $src    The source URL of the enqueued style or script.
	 * @param string $handle The style or script handle.
	 * @return string
	 */
	public function filter_asset_version_for_dev($src, $handle) {
		$is_debug = (defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG);
		if (!$is_debug || empty($src)) {
			return $src;
		}

		if (strpos($handle, 'aips-') === 0 || strpos($src, 'ai-post-scheduler/assets/') !== false) {
			$parsed = wp_parse_url($src);
			if (!empty($parsed['path']) && preg_match('#ai-post-scheduler/(assets/.+)$#', $parsed['path'], $matches)) {
				$rel_file = $matches[1];
				$full_file = AIPS_PLUGIN_DIR . $rel_file;
				if (file_exists($full_file)) {
					return add_query_arg('ver', (string) filemtime($full_file), remove_query_arg('ver', $src));
				}
			}
			return add_query_arg('ver', (string) time(), remove_query_arg('ver', $src));
		}

		return $src;
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * Loads CSS and JS assets only on plugin-specific pages.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets($hook) {
		$ctx = $this->resolver->resolve($hook);

		if (!$ctx['is_plugin_page']) {
			if ($ctx['is_native_post']) {
				$this->global_registrar->enqueue_history_modal_opener();
			}
			return;
		}

		$this->global_registrar->enqueue_global();

		if ($ctx['is_dashboard']) {
			$this->monitoring_registrar->enqueue_dashboard();
		}

		if ($ctx['is_authors']) {
			$this->content_strategy_registrar->enqueue_authors($hook, $ctx['page'], $ctx['tab']);
		}

		if ($ctx['is_studio']) {
			$this->content_strategy_registrar->enqueue_templates();
			$this->content_strategy_registrar->enqueue_voices();
			$this->content_strategy_registrar->enqueue_structures();
			$this->content_strategy_registrar->enqueue_post_slices();
		}

		if ($ctx['is_schedule']) {
			$this->content_strategy_registrar->enqueue_schedule($hook);
		}

		if ($ctx['is_campaigns']) {
			$this->content_strategy_registrar->enqueue_campaigns();
		}

		if ($ctx['is_campaign_wizard']) {
			$this->content_strategy_registrar->enqueue_campaign_wizard();
		}

		if ($ctx['is_research']) {
			$this->content_strategy_registrar->enqueue_research();
		}

		if ($ctx['is_generated_posts']) {
			$this->links_indexer_registrar->enqueue_generated_posts();
			$this->links_indexer_registrar->enqueue_content_indexer();
			$this->links_indexer_registrar->enqueue_link_report();
			$this->links_indexer_registrar->enqueue_broken_links();
			$this->links_indexer_registrar->enqueue_link_rules();
			$this->links_indexer_registrar->enqueue_redirects();
			$this->links_indexer_registrar->enqueue_consolidation();
			$this->links_indexer_registrar->enqueue_silos();
		}

		if ($ctx['is_history']) {
			$this->monitoring_registrar->enqueue_history();
		}

		if ($ctx['is_onboarding']) {
			$this->management_registrar->enqueue_onboarding();
		}

		if ($ctx['is_dev_tools']) {
			$this->monitoring_registrar->enqueue_dev_tools();
		}

		if ($ctx['is_background_processes'] && class_exists('AIPS_Background_Process_Assets')) {
			AIPS_Background_Process_Assets::enqueue();
		}

		if ($ctx['is_status']) {
			$this->monitoring_registrar->enqueue_status_1();
			$this->monitoring_registrar->enqueue_status_2();
		}

		if ($ctx['is_taxonomy']) {
			$this->management_registrar->enqueue_taxonomy();
		}

		if ($ctx['is_sources']) {
			$this->management_registrar->enqueue_sources();
		}

		if ($ctx['is_settings']) {
			$this->management_registrar->enqueue_settings();
		}

		if ($ctx['is_telemetry']) {
			$this->monitoring_registrar->enqueue_telemetry();
		}

		if ($ctx['is_internal_links']) {
			if (class_exists('AIPS_Background_Process_Assets')) {
				AIPS_Background_Process_Assets::enqueue();
			}
			$this->links_indexer_registrar->enqueue_internal_links();
		}

		if ($ctx['is_content_intelligence']) {
			$this->links_indexer_registrar->enqueue_content_indexer();
			$this->links_indexer_registrar->enqueue_consolidation();
		}

		if ($ctx['is_cache_monitor']) {
			$this->monitoring_registrar->enqueue_cache_monitor();
		}

		if ($ctx['is_stress_test']) {
			$this->monitoring_registrar->enqueue_stress_test();
		}
	}

	/**
	 * Output the History modal scaffold HTML in the admin footer.
	 *
	 * @return void
	 */
	public function render_history_modal_scaffold() {
		$this->global_registrar->render_history_modal_scaffold();
	}

	/**
	 * Backward compatibility delegates for existing tests or callers.
	 */
	public function enqueue_global_assets() {
		$this->global_registrar->enqueue_global();
	}

	public function enqueue_history_modal_opener_assets() {
		$this->global_registrar->enqueue_history_modal_opener();
	}

	public function enqueue_history_modal_opener_script() {
		$this->global_registrar->enqueue_history_modal_opener_script();
	}

	public function enqueue_authors_assets($hook = '') {
		$this->content_strategy_registrar->enqueue_authors($hook);
	}

	public function enqueue_ai_assistance_assets(array $extra_script_deps = array(), $context = 'authors') {
		$this->content_strategy_registrar->enqueue_ai_assistance($extra_script_deps, $context);
	}

	public function enqueue_post_slices_assets() {
		$this->content_strategy_registrar->enqueue_post_slices();
	}

	public function enqueue_templates_assets() {
		$this->content_strategy_registrar->enqueue_templates();
	}

	public function enqueue_voices_assets() {
		$this->content_strategy_registrar->enqueue_voices();
	}

	public function enqueue_structures_assets() {
		$this->content_strategy_registrar->enqueue_structures();
	}

	public function enqueue_schedule_assets($hook = '') {
		$this->content_strategy_registrar->enqueue_schedule($hook);
	}

	public function enqueue_campaign_wizard_assets() {
		$this->content_strategy_registrar->enqueue_campaign_wizard();
	}

	public function enqueue_campaigns_assets() {
		$this->content_strategy_registrar->enqueue_campaigns();
	}

	public function enqueue_research_assets() {
		$this->content_strategy_registrar->enqueue_research();
	}

	public function enqueue_generated_posts_assets() {
		$this->links_indexer_registrar->enqueue_generated_posts();
	}

	public function enqueue_history_assets() {
		$this->monitoring_registrar->enqueue_history();
	}

	public function enqueue_onboarding_assets() {
		$this->management_registrar->enqueue_onboarding();
	}

	public function enqueue_dev_tools_assets() {
		$this->monitoring_registrar->enqueue_dev_tools();
	}

	public function enqueue_status_1_assets() {
		$this->monitoring_registrar->enqueue_status_1();
	}

	public function enqueue_taxonomy_assets() {
		$this->management_registrar->enqueue_taxonomy();
	}

	public function enqueue_sources_assets() {
		$this->management_registrar->enqueue_sources();
	}

	public function enqueue_settings_assets() {
		$this->management_registrar->enqueue_settings();
	}

	public function enqueue_status_2_assets() {
		$this->monitoring_registrar->enqueue_status_2();
	}

	public function enqueue_dashboard_assets() {
		$this->monitoring_registrar->enqueue_dashboard();
	}

	public function enqueue_telemetry_assets() {
		$this->monitoring_registrar->enqueue_telemetry();
	}

	public function enqueue_internal_links_assets() {
		$this->links_indexer_registrar->enqueue_internal_links();
	}

	public function enqueue_content_indexer_assets() {
		$this->links_indexer_registrar->enqueue_content_indexer();
	}

	public function enqueue_link_report_assets() {
		$this->links_indexer_registrar->enqueue_link_report();
	}

	public function enqueue_broken_links_assets() {
		$this->links_indexer_registrar->enqueue_broken_links();
	}

	public function enqueue_redirects_assets() {
		$this->links_indexer_registrar->enqueue_redirects();
	}

	public function enqueue_silos_assets() {
		$this->links_indexer_registrar->enqueue_silos();
	}

	public function enqueue_consolidation_assets() {
		$this->links_indexer_registrar->enqueue_consolidation();
	}

	public function enqueue_link_rules_assets() {
		$this->links_indexer_registrar->enqueue_link_rules();
	}

	public function enqueue_cache_monitor_assets() {
		$this->monitoring_registrar->enqueue_cache_monitor();
	}

	public function enqueue_stress_test_assets() {
		$this->monitoring_registrar->enqueue_stress_test();
	}

	/**
	 * Context helper delegates.
	 */
	public function get_current_page_slug() {
		return $this->resolver->get_current_page_slug();
	}

	public function get_current_tab_key() {
		return $this->resolver->get_current_tab_key();
	}

	public function hook_contains($hook, $needle) {
		return $this->resolver->hook_contains($hook, $needle);
	}

	public function is_plugin_admin_page($hook, $page) {
		return $this->resolver->is_plugin_admin_page($hook, $page);
	}

	public function is_native_post_admin_page($hook) {
		return $this->resolver->is_native_post_admin_page($hook);
	}

	public function is_diagnostics_tab($page, $tab) {
		return $this->resolver->is_diagnostics_tab($page, $tab);
	}

	public function is_automations_tab($page, $tab) {
		return $this->resolver->is_automations_tab($page, $tab);
	}
}
