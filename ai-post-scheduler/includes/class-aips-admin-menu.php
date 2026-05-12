<?php
if (!defined('ABSPATH')) {
	die;
}

/**
 * Registers the plugin admin menu and legacy hidden pages.
 *
 * Visible navigation is intentionally short and task-oriented. Existing page
 * slugs stay available behind the scenes so links, AJAX localizations, and
 * page-specific scripts keep working while the menu UX is simplified.
 */
class AIPS_Admin_Menu {

	/**
	 * Initialize menu hooks.
	 */
	public function __construct() {
		add_action('admin_menu', array($this, 'add_menu_pages'));
		add_filter('parent_file', array($this, 'filter_parent_file'));
		add_filter('submenu_file', array($this, 'filter_submenu_file'));
	}

	/**
	 * Register visible hub pages plus hidden legacy pages.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_menu_page(
			__('AI Post Scheduler', 'ai-post-scheduler'),
			__('AI Post Scheduler', 'ai-post-scheduler'),
			'manage_options',
			'ai-post-scheduler',
			array($this, 'render_dashboard_page'),
			'dashicons-schedule',
			30
		);

		add_submenu_page(
			'ai-post-scheduler',
			__('Dashboard', 'ai-post-scheduler'),
			__('Dashboard', 'ai-post-scheduler'),
			'manage_options',
			'ai-post-scheduler',
			array($this, 'render_dashboard_page')
		);

		foreach (AIPS_Admin_Hub_Registry::get_hubs() as $hub_key => $hub) {
			if ('ai-post-scheduler' === $hub['slug']) {
				continue;
			}

			add_submenu_page(
				'ai-post-scheduler',
				$hub['page_title'],
				$hub['menu_title'],
				'manage_options',
				$hub['slug'],
				array($this, 'render_' . $hub_key . '_hub_page')
			);
		}

		$this->register_hidden_page('aips-templates', __('Templates', 'ai-post-scheduler'), array($this, 'render_templates_page'));
		$this->register_hidden_page('aips-voices', __('Voices', 'ai-post-scheduler'), array($this, 'render_voices_page'));
		$this->register_hidden_page('aips-structures', __('Article Structures', 'ai-post-scheduler'), array($this, 'render_structures_page'));
		$this->register_hidden_page('aips-sections', __('Prompt Blocks', 'ai-post-scheduler'), array($this, 'render_prompt_sections_page'));
		$this->register_hidden_page('aips-authors', __('Authors', 'ai-post-scheduler'), array($this, 'render_authors_page'));
		$this->register_hidden_page('aips-author-topics', __('Author Topics', 'ai-post-scheduler'), array($this, 'render_author_topics_page'));
		$this->register_hidden_page('aips-research', __('Research', 'ai-post-scheduler'), array($this, 'render_research_page'));
		$this->register_hidden_page('aips-schedule', __('Schedule', 'ai-post-scheduler'), array($this, 'render_schedule_page'));
		$this->register_hidden_page('aips-schedule-calendar', __('Schedule Calendar', 'ai-post-scheduler'), array($this, 'render_schedule_calendar_page'));
		$this->register_hidden_page('aips-generated-posts', __('Content', 'ai-post-scheduler'), array($this, 'render_generated_posts_page'));
		$this->register_hidden_page('aips-post-slices', __('Post Slices', 'ai-post-scheduler'), array($this, 'render_post_slices_page'));
		$this->register_hidden_page('aips-history', __('History', 'ai-post-scheduler'), array($this, 'render_history_page'));
		$this->register_hidden_page('aips-operations-insights', __('Operations Insights', 'ai-post-scheduler'), array($this, 'render_operations_insights_page'));
		$this->register_hidden_page('aips-sources', __('Sources', 'ai-post-scheduler'), array($this, 'render_sources_page'));
		$this->register_hidden_page('aips-taxonomy', __('Taxonomy', 'ai-post-scheduler'), array($this, 'render_taxonomy_page'));
		$this->register_hidden_page('aips-internal-links', __('Internal Links', 'ai-post-scheduler'), array($this, 'render_internal_links_page'));
		$this->register_hidden_page('aips-settings', __('Settings', 'ai-post-scheduler'), array($this, 'render_settings_page'));
		$this->register_hidden_page('aips-status', __('System Status', 'ai-post-scheduler'), array($this, 'render_status_page'));
		$this->register_hidden_page('aips-seeder', __('Seeder', 'ai-post-scheduler'), array($this, 'render_seeder_page'));
		$this->register_hidden_page('aips-onboarding', __('Onboarding', 'ai-post-scheduler'), array($this, 'render_onboarding_page'));

		if (AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			$this->register_hidden_page('aips-telemetry', __('Telemetry', 'ai-post-scheduler'), array($this, 'render_telemetry_page'));
		}

		if (AIPS_Config::get_instance()->get_option('aips_developer_mode')) {
			$this->register_hidden_page('aips-dev-tools', __('Dev Tools', 'ai-post-scheduler'), array($this, 'render_dev_tools_page'));
		}
	}

	/**
	 * Register a hidden page so direct links remain valid.
	 *
	 * @param string   $slug     Page slug.
	 * @param string   $title    Page title.
	 * @param callable $callback Render callback.
	 * @return void
	 */
	private function register_hidden_page($slug, $title, $callback) {
		add_submenu_page(
			null,
			$title,
			$title,
			'manage_options',
			$slug,
			$callback
		);
	}

	/**
	 * Keep the plugin menu expanded when viewing hidden pages.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function filter_parent_file($parent_file) {
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

		if (AIPS_Admin_Hub_Registry::get_visible_slug_for_page($page)) {
			return 'ai-post-scheduler';
		}

		return $parent_file;
	}

	/**
	 * Highlight the correct hub submenu item for hidden legacy pages.
	 *
	 * @param string $submenu_file Current submenu file.
	 * @return string
	 */
	public function filter_submenu_file($submenu_file) {
		$page         = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$visible_slug = AIPS_Admin_Hub_Registry::get_visible_slug_for_page($page);

		if ($visible_slug) {
			return $visible_slug;
		}

		return $submenu_file;
	}

	/**
	 * Render the Content Setup hub page.
	 *
	 * @return void
	 */
	public function render_content_setup_hub_page() {
		$this->render_hub_page('content_setup');
	}

	/**
	 * Render the Automation hub page.
	 *
	 * @return void
	 */
	public function render_automation_hub_page() {
		$this->render_hub_page('automation');
	}

	/**
	 * Render the Outputs hub page.
	 *
	 * @return void
	 */
	public function render_outputs_hub_page() {
		$this->render_hub_page('outputs');
	}

	/**
	 * Render the Operations hub page.
	 *
	 * @return void
	 */
	public function render_operations_hub_page() {
		$this->render_hub_page('operations');
	}

	/**
	 * Render the Site Context hub page.
	 *
	 * @return void
	 */
	public function render_site_context_hub_page() {
		$this->render_hub_page('site_context');
	}

	/**
	 * Render the Settings hub page.
	 *
	 * @return void
	 */
	public function render_settings_hub_page() {
		$this->render_hub_page('settings');
	}

	/**
	 * Render one shared hub layout.
	 *
	 * @param string $hub_key Hub registry key.
	 * @return void
	 */
	private function render_hub_page($hub_key) {
		$hub = AIPS_Admin_Hub_Registry::get_hub($hub_key);

		if (empty($hub)) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__('The requested admin workspace could not be loaded.', 'ai-post-scheduler') .
			'</p></div>';
			return;
		}

		AIPS_Admin_Hub_Layout::render($hub);
	}

	/**
	 * Render the main dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		$this->render_hub_page('dashboard');
	}

	/**
	 * Render the Voices management page.
	 *
	 * @return void
	 */
	public function render_voices_page() {
		$this->redirect_legacy_page('voices');
	}

	/**
	 * Render the Templates management page.
	 *
	 * @return void
	 */
	public function render_templates_page() {
		$this->redirect_legacy_page('templates');
	}

	/**
	 * Render the Schedule management page.
	 *
	 * @return void
	 */
	public function render_schedule_page() {
		$this->redirect_legacy_page('schedule');
	}

	/**
	 * Render the Schedule Calendar page.
	 *
	 * @return void
	 */
	public function render_schedule_calendar_page() {
		$this->redirect_legacy_page('schedule_calendar');
	}

	/**
	 * Render the research page.
	 *
	 * @return void
	 */
	public function render_research_page() {
		$this->redirect_legacy_page('research');
	}

	/**
	 * Render the authors page.
	 *
	 * @return void
	 */
	public function render_authors_page() {
		$this->redirect_legacy_page('authors');
	}

	/**
	 * Render the Post Slices management page.
	 *
	 * @return void
	 */
	public function render_post_slices_page() {
		$this->redirect_legacy_page('post_slices');
	}

	/**
	 * Render the author topics page.
	 *
	 * @return void
	 */
	public function render_author_topics_page() {
		$this->redirect_legacy_page('author_topics');
	}

	/**
	 * Render the content page.
	 *
	 * @return void
	 */
	public function render_generated_posts_page() {
		$this->redirect_legacy_page('generated_posts');
	}

	/**
	 * Render the article structures page.
	 *
	 * @return void
	 */
	public function render_structures_page() {
		$this->redirect_legacy_page('structures');
	}

	/**
	 * Render the prompt sections page.
	 *
	 * @return void
	 */
	public function render_prompt_sections_page() {
		$this->redirect_legacy_page('prompt_sections');
	}

	/**
	 * Render the history page.
	 *
	 * @return void
	 */
	public function render_history_page() {
		$this->redirect_legacy_page('history');
	}

	/**
	 * Render the operations insights page.
	 *
	 * @return void
	 */
	public function render_operations_insights_page() {
		$this->redirect_legacy_page('operations_insights');
	}

	/**
	 * Render the telemetry page.
	 *
	 * @return void
	 */
	public function render_telemetry_page() {
		$this->redirect_legacy_page('telemetry');
	}

	/**
	 * Render the sources page.
	 *
	 * @return void
	 */
	public function render_sources_page() {
		$this->redirect_legacy_page('sources');
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->redirect_legacy_page('settings');
	}

	/**
	 * Render the seeder page.
	 *
	 * @return void
	 */
	public function render_seeder_page() {
		$this->redirect_legacy_page('seeder');
	}

	/**
	 * Render the onboarding page.
	 *
	 * @return void
	 */
	public function render_onboarding_page() {
		$this->redirect_legacy_page('onboarding');
	}

	/**
	 * Render the system status page.
	 *
	 * @return void
	 */
	public function render_status_page() {
		$this->redirect_legacy_page('system_status');
	}

	/**
	 * Render the dev tools page.
	 *
	 * @return void
	 */
	public function render_dev_tools_page() {
		$this->redirect_legacy_page('dev_tools');
	}

	/**
	 * Render the taxonomy page.
	 *
	 * @return void
	 */
	public function render_taxonomy_page() {
		$this->redirect_legacy_page('taxonomy');
	}

	/**
	 * Render the internal links page.
	 *
	 * @return void
	 */
	public function render_internal_links_page() {
		$this->redirect_legacy_page('internal_links');
	}

	/**
	 * Redirect a hidden legacy route into its hub workspace.
	 *
	 * @param string $logical_page Logical page key for AIPS_Admin_Menu_Helper.
	 * @return void
	 */
	private function redirect_legacy_page($logical_page) {
		$args = $_GET;

		unset($args['page']);

		$url = AIPS_Admin_Menu_Helper::get_page_url($logical_page, $args);
		wp_safe_redirect($url);
		exit;
	}
}
