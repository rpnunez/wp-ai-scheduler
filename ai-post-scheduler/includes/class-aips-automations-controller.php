<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Automations_Controller
 *
 * Coordinates the Automations admin page and tab rendering.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Automations_Controller {

	/**
	 * Automations page slug.
	 */
	public const PAGE_SLUG = 'aips-automations';

	/**
	 * Default Automations tab key.
	 */
	private const DEFAULT_TAB = 'schedules';

	/**
	 * Render the Automations page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$tabs = $this->get_tabs();
		$active_tab = self::get_active_tab_key();
		$automations_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/automations.php';
	}

	/**
	 * Get available Automations tabs.
	 *
	 * @return array<string, array{label:string}>
	 */
	public function get_tabs() {
		return array(
			'schedules' => array(
				'label' => __('Schedules', 'ai-post-scheduler'),
			),
			'campaigns' => array(
				'label' => __('Campaigns', 'ai-post-scheduler'),
			),
			'templates' => array(
				'label' => __('Templates', 'ai-post-scheduler'),
			),
			'authors' => array(
				'label' => __('Authors', 'ai-post-scheduler'),
			),
			'sources' => array(
				'label' => __('Sources', 'ai-post-scheduler'),
			),
			'internal-links' => array(
				'label' => __('Internal Links', 'ai-post-scheduler'),
			),
			'taxonomy' => array(
				'label' => __('Taxonomy', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Get the active Automations tab key for the current request.
	 *
	 * @return string
	 */
	public static function get_active_tab_key() {
		$active_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$active_tab = $active_tab ? sanitize_key($active_tab) : self::DEFAULT_TAB;

		if (!self::is_tab_available($active_tab)) {
			return self::DEFAULT_TAB;
		}

		return $active_tab;
	}

	/**
	 * Determine whether an Automations tab is currently available.
	 *
	 * @param string $tab Tab key.
	 * @return bool
	 */
	public static function is_tab_available($tab) {
		return in_array(
			$tab,
			array('schedules', 'campaigns', 'templates', 'authors', 'sources', 'internal-links', 'taxonomy'),
			true
		);
	}

	/**
	 * Get the admin URL for an Automations tab.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public function get_tab_url($tab) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url('admin.php')
		);
	}

	/**
	 * Render content for the requested Automations tab.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	public function render_tab_content($active_tab) {
		switch ($active_tab) {
			case 'campaigns':
				$this->render_campaigns_tab();
				break;
			case 'templates':
				$this->render_templates_tab();
				break;
			case 'authors':
				$this->render_authors_tab();
				break;
			case 'sources':
				$this->render_sources_tab();
				break;
			case 'internal-links':
				$this->render_internal_links_tab();
				break;
			case 'taxonomy':
				$this->render_taxonomy_tab();
				break;
			case 'schedules':
			default:
				$this->render_schedules_tab();
				break;
		}
	}

	/**
	 * Render schedules tab content.
	 *
	 * @return void
	 */
	private function render_schedules_tab() {
		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
	}

	/**
	 * Render campaigns tab content.
	 *
	 * @return void
	 */
	private function render_campaigns_tab() {
		$controller = new AIPS_Campaigns_Controller();
		$controller->render_page();
	}

	/**
	 * Render templates tab content.
	 *
	 * @return void
	 */
	private function render_templates_tab() {
		$templates_handler = new AIPS_Templates();
		$templates_handler->render_page();
	}

	/**
	 * Render authors tab content.
	 *
	 * @return void
	 */
	private function render_authors_tab() {
		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
	}

	/**
	 * Render sources tab content.
	 *
	 * @return void
	 */
	private function render_sources_tab() {
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

		$all_source_ids = array_map(function($s) {
			return (int) $s->id;
		}, $sources);
		$source_term_ids_map = $repo->get_term_ids_for_sources($all_source_ids);

		$data_repo             = new AIPS_Sources_Data_Repository();
		$source_fetch_data_map = $data_repo->get_by_source_ids($all_source_ids);
		$source_content_count_map = $data_repo->get_counts_by_source_ids($all_source_ids);

		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/sources.php';
	}

	/**
	 * Render internal links tab content.
	 *
	 * @return void
	 */
	private function render_internal_links_tab() {
		global $aips_internal_links_controller;

		if ($aips_internal_links_controller instanceof AIPS_Internal_Links_Controller) {
			$aips_internal_links_controller->render_page();
			return;
		}

		echo '<div class="notice notice-error"><p>' .
			esc_html__('The Internal Links controller is not available, so the Internal Links page could not be loaded.', 'ai-post-scheduler') .
		'</p></div>';
	}

	/**
	 * Render taxonomy tab content.
	 *
	 * @return void
	 */
	private function render_taxonomy_tab() {
		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/taxonomy.php';
	}
}
