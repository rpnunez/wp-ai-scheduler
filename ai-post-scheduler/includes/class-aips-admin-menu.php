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
		$this->register_hidden_page('aips-history', __('History', 'ai-post-scheduler'), array($this, 'render_history_page'));
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
		$controller = new AIPS_Dashboard_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Voices management page.
	 *
	 * @return void
	 */
	public function render_voices_page() {
		$voices_handler = new AIPS_Voices();
		$voices_handler->render_page();
	}

	/**
	 * Render the Templates management page.
	 *
	 * @return void
	 */
	public function render_templates_page() {
		$templates_handler = new AIPS_Templates();
		$templates_handler->render_page();
	}

	/**
	 * Render the Schedule management page.
	 *
	 * @return void
	 */
	public function render_schedule_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
	}

	/**
	 * Render the Schedule Calendar page.
	 *
	 * @return void
	 */
	public function render_schedule_calendar_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/calendar.php';
	}

	/**
	 * Render the research page.
	 *
	 * @return void
	 */
	public function render_research_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
	}

	/**
	 * Render the authors page.
	 *
	 * @return void
	 */
	public function render_authors_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
	}

	/**
	 * Render the author topics page.
	 *
	 * @return void
	 */
	public function render_author_topics_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/author-topics.php';
	}

	/**
	 * Render the content page.
	 *
	 * @return void
	 */
	public function render_generated_posts_page() {
		$controller = new AIPS_Generated_Posts_Controller();
		$controller->render_page();
	}

	/**
	 * Render the article structures page.
	 *
	 * @return void
	 */
	public function render_structures_page() {
		$structure_repo = new AIPS_Article_Structure_Repository();
		$section_repo   = new AIPS_Prompt_Section_Repository();

		$structures = $structure_repo->get_all(false);
		$sections   = $section_repo->get_all(false);

		include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
	}

	/**
	 * Render the prompt sections page.
	 *
	 * @return void
	 */
	public function render_prompt_sections_page() {
		$section_repo = new AIPS_Prompt_Section_Repository();
		$sections     = $section_repo->get_all(false);

		include AIPS_PLUGIN_DIR . 'templates/admin/sections.php';
	}

	/**
	 * Render the history page.
	 *
	 * @return void
	 */
	public function render_history_page() {
		$history_handler = new AIPS_History();
		$history_handler->render_page();
	}

	/**
	 * Render the telemetry page.
	 *
	 * @return void
	 */
	public function render_telemetry_page() {
		$controller = new AIPS_Telemetry_Controller();
		$controller->render_page();
	}

	/**
	 * Render the sources page.
	 *
	 * @return void
	 */
	public function render_sources_page() {
		$repo    = new AIPS_Sources_Repository();
		$sources = $repo->get_all(false);

		$source_groups = get_terms(array(
			'taxonomy'   => 'aips_source_group',
			'hide_empty' => false,
		));
		if (is_wp_error($source_groups)) {
			$source_groups = array();
		}

		$source_group_name_map = array();
		foreach ($source_groups as $group) {
			$source_group_name_map[(int) $group->term_id] = $group->name;
		}

		$all_source_ids         = array_map(function ($source) {
			return (int) $source->id;
		}, $sources);
		$source_term_ids_map    = $repo->get_term_ids_for_sources($all_source_ids);
		$data_repo              = new AIPS_Sources_Data_Repository();
		$source_fetch_data_map  = $data_repo->get_by_source_ids($all_source_ids);
		$source_content_count_map = $data_repo->get_counts_by_source_ids($all_source_ids);

		include AIPS_PLUGIN_DIR . 'templates/admin/sources.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Render the seeder page.
	 *
	 * @return void
	 */
	public function render_seeder_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
	}

	/**
	 * Render the onboarding page.
	 *
	 * @return void
	 */
	public function render_onboarding_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/onboarding.php';
	}

	/**
	 * Render the system status page.
	 *
	 * @return void
	 */
	public function render_status_page() {
		$status_handler = new AIPS_System_Status();
		$status_handler->render_page();
	}

	/**
	 * Render the dev tools page.
	 *
	 * @return void
	 */
	public function render_dev_tools_page() {
		$dev_tools = new AIPS_Dev_Tools();
		$dev_tools->render_page();
	}

	/**
	 * Render the taxonomy page.
	 *
	 * @return void
	 */
	public function render_taxonomy_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/taxonomy.php';
	}

	/**
	 * Render the internal links page.
	 *
	 * @return void
	 */
	public function render_internal_links_page() {
		global $aips_internal_links_controller;

		if ($aips_internal_links_controller instanceof AIPS_Internal_Links_Controller) {
			try {
				$aips_internal_links_controller->render_page();
				return;
			} catch (Throwable $throwable) {
				echo '<div class="notice notice-error"><p>' .
					esc_html__('The Internal Links page could not be rendered. Please reload the page or check the plugin configuration.', 'ai-post-scheduler') .
				'</p></div>';
				return;
			}
		}

		echo '<div class="notice notice-error"><p>' .
			esc_html__('The Internal Links controller is not available, so the Internal Links page could not be loaded.', 'ai-post-scheduler') .
		'</p></div>';
	}
}
