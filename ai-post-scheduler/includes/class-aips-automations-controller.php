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
	 * Author topics tab key.
	 */
	public const TAB_AUTHOR_TOPICS = 'author-topics';

	/**
	 * Render the Automations page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$active_tab = self::get_active_tab_key();
		$tabs = $this->get_tabs($active_tab);
		$tab_actions = $this->get_tab_actions($active_tab);
		$automations_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/automations.php';
	}

	/**
	 * Get available Automations tabs.
	 *
	 * @param string $active_tab Active tab key.
	 * @return array<string, array{label:string, special?:bool}>
	 */
	public function get_tabs($active_tab = '') {
		$tabs = array(
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
			'content-enhancements' => array(
				'label' => __('Content Enhancements', 'ai-post-scheduler'),
			),
		);

		if (self::TAB_AUTHOR_TOPICS === $active_tab) {
			$tabs = array_merge(
				array_slice($tabs, 0, 4, true),
				array(
					self::TAB_AUTHOR_TOPICS => array(
						'label'   => __("Author's Topics", 'ai-post-scheduler'),
						'special' => true,
					),
				),
				array_slice($tabs, 4, null, true)
			);
		}

		return $tabs;
	}


	/**
	 * Get header actions for the active Automations tab.
	 *
	 * These mirror the primary actions from the standalone pages because the
	 * embedded tab templates intentionally suppress their own page headers.
	 *
	 * @param string $active_tab Active tab key.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tab_actions($active_tab) {
		switch ($active_tab) {
			case 'campaigns':
				return array(
					array(
						'type'  => 'link',
						'url'   => AIPS_Admin_Menu_Helper::get_page_url('campaign_wizard'),
						'class' => 'aips-btn aips-btn-primary',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add New Campaign', 'ai-post-scheduler'),
					),
				);
			case 'templates':
				return array(
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-template-btn',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add Template', 'ai-post-scheduler'),
					),
				);
			case 'authors':
				return array(
					array(
						'type'  => 'button',
						'id'    => 'aips-suggest-authors-btn',
						'class' => 'aips-btn aips-btn-secondary',
						'icon'  => 'dashicons-lightbulb',
						'label' => __('Suggest Authors', 'ai-post-scheduler'),
					),
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-author-btn',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add Author', 'ai-post-scheduler'),
					),
				);
			case 'sources':
				return array(
					array(
						'type'  => 'button',
						'id'    => 'aips-manage-source-groups-btn',
						'class' => 'aips-btn aips-btn-secondary',
						'icon'  => 'dashicons-category',
						'label' => __('Manage Groups', 'ai-post-scheduler'),
					),
					array(
						'type'  => 'button',
						'id'    => 'aips-add-source-btn',
						'class' => 'aips-btn aips-btn-primary',
						'icon'  => 'dashicons-plus-alt2',
						'label' => __('Add Source', 'ai-post-scheduler'),
					),
				);
			case 'internal-links':
				return array(
					array(
						'type'  => 'button',
						'id'    => 'aips-start-indexing-btn',
						'class' => 'aips-btn aips-btn-secondary',
						'icon'  => 'dashicons-database-import',
						'label' => __('Index Posts', 'ai-post-scheduler'),
					),
					array(
						'type'  => 'button',
						'id'    => 'aips-clear-index-btn',
						'class' => 'aips-btn aips-btn-ghost aips-btn-danger',
						'icon'  => 'dashicons-trash',
						'label' => __('Clear Index', 'ai-post-scheduler'),
					),
				);
			case 'taxonomy':
				return array(
					array(
						'type'  => 'button',
						'id'    => 'aips-open-generate-modal',
						'class' => 'aips-btn aips-btn-primary aips-generate-taxonomy',
						'icon'  => 'dashicons-update',
						'label' => __('Generate Taxonomy', 'ai-post-scheduler'),
					),
				);
			case self::TAB_AUTHOR_TOPICS:
				return $this->get_author_topics_tab_actions();
			case 'schedules':
			default:
				return $this->get_schedule_tab_actions();
		}
	}

	/**
	 * Get header actions for the schedules tab.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_schedule_tab_actions() {
		$templates_handler = new AIPS_Templates();
		$templates = $templates_handler->get_all(true);

		if (!empty($templates)) {
			return array(
				array(
					'type'  => 'button',
					'class' => 'aips-btn aips-btn-primary aips-add-schedule-btn',
					'icon'  => 'dashicons-plus-alt',
					'label' => __('Add Template Schedule', 'ai-post-scheduler'),
				),
			);
		}

		return array(
			array(
				'type'  => 'link',
				'url'   => AIPS_Admin_Menu_Helper::get_page_url('templates'),
				'class' => 'aips-btn aips-btn-secondary',
				'icon'  => 'dashicons-media-document',
				'label' => __('Create Template First', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Get header actions for the Author Topics tab.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_author_topics_tab_actions() {
		$author_id = self::get_request_author_id();

		if ($author_id <= 0) {
			return array();
		}

		return array(
			array(
				'type'  => 'link',
				'url'   => AIPS_Admin_Menu_Helper::get_page_url('authors', array('author_id' => $author_id)),
				'class' => 'aips-btn aips-btn-secondary',
				'icon'  => 'dashicons-edit',
				'label' => __('Edit Author', 'ai-post-scheduler'),
			),
			array(
				'type'       => 'button',
				'class'      => 'aips-btn aips-btn-primary aips-generate-topics-now',
				'icon'       => 'dashicons-update',
				'label'      => __('Generate Topics', 'ai-post-scheduler'),
				'data_attrs' => array(
					'id' => $author_id,
				),
			),
			array(
				'type'  => 'link',
				'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('author_id' => $author_id)),
				'class' => 'aips-btn aips-btn-secondary',
				'icon'  => 'dashicons-admin-post',
				'label' => __('View Generated Posts', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Get the active Automations tab key for the current request.
	 *
	 * @return string
	 */
	public static function get_active_tab_key() {
		$active_tab = filter_input(INPUT_GET, 'tab', FILTER_UNSAFE_RAW);
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
		if (self::TAB_AUTHOR_TOPICS === $tab) {
			return self::get_request_author_id() > 0;
		}

		return in_array(
			$tab,
			array('schedules', 'campaigns', 'templates', 'authors', 'sources', 'internal-links', 'taxonomy', 'content-enhancements'),
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
		$args = array(
			'page' => self::PAGE_SLUG,
			'tab'  => $tab,
		);

		if (self::TAB_AUTHOR_TOPICS === $tab) {
			$author_id = self::get_request_author_id();
			if ($author_id > 0) {
				$args['author_id'] = $author_id;
			}
		}

		return add_query_arg($args, admin_url('admin.php'));
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
			case self::TAB_AUTHOR_TOPICS:
				$this->render_author_topics_tab();
				break;
			case 'internal-links':
				$this->render_internal_links_tab();
				break;
			case 'taxonomy':
				$this->render_taxonomy_tab();
				break;
			case 'content-enhancements':
				$this->render_content_enhancements_tab();
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
		$controller->render_page(true);
	}

	/**
	 * Render templates tab content.
	 *
	 * @return void
	 */
	private function render_templates_tab() {
		$templates_handler = new AIPS_Templates();
		$templates_handler->render_page(true);
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
			try {
				$aips_internal_links_controller->render_page(true);
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

	/**
	 * Render taxonomy tab content.
	 *
	 * @return void
	 */
	private function render_taxonomy_tab() {
		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/taxonomy.php';
	}

	/**
	 * Render author topics tab content.
	 *
	 * @return void
	 */
	private function render_author_topics_tab() {
		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/author-topics.php';
	}

	/**
	 * Read and sanitize author_id from request.
	 *
	 * @return int
	 */
	private static function get_request_author_id() {
		$author_id = filter_input(INPUT_GET, 'author_id', FILTER_VALIDATE_INT);

		if (null === $author_id || false === $author_id) {
			return 0;
		}

		return absint($author_id);
	}

	/**
	 * Render content enhancements tab content.
	 *
	 * @return void
	 */
	private function render_content_enhancements_tab() {
		$config = AIPS_Config::get_instance();
		$content_enhancements_repository = new AIPS_Content_Enhancement_Repository();
		$content_enhancements = $content_enhancements_repository->all();
		$content_enhancement_allowlist = $config->get_option('aips_content_enhancement_provider_allowlist', array());
		if (!is_array($content_enhancement_allowlist)) {
			$content_enhancement_allowlist = array();
		}
		$content_enhancement_default_disclosure = $config->get_option('aips_content_enhancement_default_disclosure_text');
		$content_enhancement_default_cta = $config->get_option('aips_content_enhancement_default_cta_text');

		$embedded = true;
		include AIPS_PLUGIN_DIR . 'templates/admin/content-enhancements.php';
	}
}
