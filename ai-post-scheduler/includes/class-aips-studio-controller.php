<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Studio_Controller
 *
 * Coordinates the Studio admin hub and focused section workspaces.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */
class AIPS_Studio_Controller {

	/**
	 * Studio page slug.
	 */
	public const PAGE_SLUG = 'aips-studio';

	/**
	 * Get available Studio sections with localized metadata.
	 *
	 * @return array<string, array{label:string, icon:string, description:string, action_label:string, action_class:string}>
	 */
	public static function get_sections(): array {
		return array(
			'templates' => array(
				'label'        => __('Templates', 'ai-post-scheduler'),
				'icon'         => 'dashicons-media-document',
				'description'  => __('Create and configure AI post generation templates, prompt strategies, and format guidelines.', 'ai-post-scheduler'),
				'action_label' => __('Add Template', 'ai-post-scheduler'),
				'action_class' => 'aips-btn aips-btn-primary aips-add-template-btn',
			),
			'voices' => array(
				'label'        => __('Voices', 'ai-post-scheduler'),
				'icon'         => 'dashicons-megaphone',
				'description'  => __('Define brand personality, tone of voice, stylistic rules, and custom excerpt guidelines.', 'ai-post-scheduler'),
				'action_label' => __('Add Voice', 'ai-post-scheduler'),
				'action_class' => 'aips-btn aips-btn-primary aips-add-voice-btn',
			),
			'structures' => array(
				'label'        => __('Article Structures', 'ai-post-scheduler'),
				'icon'         => 'dashicons-editor-ol',
				'description'  => __('Build reusable article frameworks, required section outlines, and heading constraints.', 'ai-post-scheduler'),
				'action_label' => __('Add Structure', 'ai-post-scheduler'),
				'action_class' => 'aips-btn aips-btn-primary aips-add-structure-btn',
			),
			'structure-sections' => array(
				'label'        => __('Structure Sections', 'ai-post-scheduler'),
				'icon'         => 'dashicons-editor-ul',
				'description'  => __('Manage reusable sections used inside article structures.', 'ai-post-scheduler'),
				'action_label' => __('Add Section', 'ai-post-scheduler'),
				'action_class' => 'aips-btn aips-btn-primary aips-add-section-btn',
			),
			'post-slices' => array(
				'label'        => __('Post Slices', 'ai-post-scheduler'),
				'icon'         => 'dashicons-grid-view',
				'description'  => __('Manage modular content blocks, dynamic CTAs, and automated slice insertions.', 'ai-post-scheduler'),
				'action_label' => __('Add Slice', 'ai-post-scheduler'),
				'action_class' => 'aips-btn aips-btn-primary aips-add-slice-btn',
			),
		);
	}

	/**
	 * Get the currently requested Studio section key.
	 *
	 * @return string
	 */
	public static function get_active_section_key(): string {
		$raw = '';
		if (isset($_GET['tab'])) {
			$raw = sanitize_key(wp_unslash($_GET['tab']));
		} elseif (isset($_GET['section'])) {
			$raw = sanitize_key(wp_unslash($_GET['section']));
		}

		$sections = self::get_sections();
		if (array_key_exists($raw, $sections)) {
			return $raw;
		}

		return 'templates';
	}

	/**
	 * Render the Studio admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$active_section    = self::get_active_section_key();
		$stats             = $this->get_studio_stats();
		$page_context      = $this->get_page_context($active_section, $stats);
		$studio_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/studio.php';
	}

	/**
	 * Build page context model for the current Studio view.
	 *
	 * @param string $active_section Active section key.
	 * @param array  $stats          Aggregated statistics.
	 * @return AIPS_Admin_Page_Context
	 */
	public function get_page_context($active_section, $stats = array()) {
		$summary_items = array();

		$sec_stat = isset($stats[$active_section]) ? $stats[$active_section] : array('total' => 0, 'active' => 0);
		$summary_items = array(
			array('label' => __('Total Items', 'ai-post-scheduler'), 'value' => $sec_stat['total'], 'type' => 'neutral'),
		);
		if ($sec_stat['active'] > 0) {
			$summary_items[] = array('label' => __('Active', 'ai-post-scheduler'), 'value' => $sec_stat['active'], 'type' => 'success', 'icon' => 'dashicons-yes-alt');
		}

		$tab_actions = $this->get_tab_actions($active_section);

		return AIPS_Admin_Page_Context::resolve(
			self::PAGE_SLUG,
			$active_section,
			null,
			array(
				'summary_items' => $summary_items,
				'actions'       => $tab_actions,
			)
		);
	}

	/**
	 * Get Studio sections with localized labels, icons, and descriptions.
	 *
	 * @param string $active_section Active section key.
	 * @return array<string, array{label:string, icon:string, description:string}>
	 */
	public function get_tabs($active_section = '') {
		return array(
			'templates' => array(
				'label'       => __('Templates', 'ai-post-scheduler'),
				'icon'        => 'dashicons-media-document',
				'description' => __('AI post generation templates', 'ai-post-scheduler'),
			),
			'voices' => array(
				'label'       => __('Voices', 'ai-post-scheduler'),
				'icon'        => 'dashicons-megaphone',
				'description' => __('Brand voice personas & rules', 'ai-post-scheduler'),
			),
			'structures' => array(
				'label'       => __('Article Structures', 'ai-post-scheduler'),
				'icon'        => 'dashicons-editor-ol',
				'description' => __('Article frameworks & outlines', 'ai-post-scheduler'),
			),
			'structure-sections' => array(
				'label'       => __('Structure Sections', 'ai-post-scheduler'),
				'icon'        => 'dashicons-editor-ul',
				'description' => __('Reusable article structure sections', 'ai-post-scheduler'),
			),
			'post-slices' => array(
				'label'       => __('Post Slices', 'ai-post-scheduler'),
				'icon'        => 'dashicons-layout',
				'description' => __('Modular content blocks & CTAs', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Get header actions for the active Studio section.
	 *
	 * @param string $active_section Active section key.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tab_actions($active_section) {
		switch ($active_section) {
			case 'templates':
				return array(
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-template-btn',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add Template', 'ai-post-scheduler'),
					),
				);
			case 'voices':
				return array(
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-voice-btn',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add Voice', 'ai-post-scheduler'),
					),
				);
			case 'structures':
				return array(
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-structure-btn',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add Structure', 'ai-post-scheduler'),
					),
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-secondary aips-add-section-btn',
						'icon'  => 'dashicons-plus-alt2',
						'label' => __('Add Section', 'ai-post-scheduler'),
					),
				);
			case 'structure-sections':
				return array(
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-section-btn',
						'icon'  => 'dashicons-plus-alt2',
						'label' => __('Add Section', 'ai-post-scheduler'),
					),
				);
			case 'post-slices':
				return array(
					array(
						'type'  => 'button',
						'class' => 'aips-btn aips-btn-primary aips-add-slice-btn',
						'icon'  => 'dashicons-plus-alt',
						'label' => __('Add Slice', 'ai-post-scheduler'),
					),
				);
			default:
				return array();
		}
	}

	/**
	 * Get aggregated statistics for each Studio section.
	 *
	 * @return array<string, array{total:int, active:int}>
	 */
	public function get_studio_stats(): array {
		// 1. Templates
		$template_repo = AIPS_Template_Repository::instance();
		$all_templates = $template_repo->get_all();
		$active_templates = 0;
		if (is_array($all_templates)) {
			foreach ($all_templates as $t) {
				if (!empty($t->active)) {
					$active_templates++;
				}
			}
		}

		// 2. Voices
		$voice_repo = AIPS_Voices_Repository::instance();
		$all_voices = $voice_repo->get_all();
		$active_voices = 0;
		if (is_array($all_voices)) {
			foreach ($all_voices as $v) {
				if (!empty($v->is_active)) {
					$active_voices++;
				}
			}
		}

		// 3. Article Structures
		$struct_repo = AIPS_Article_Structure_Repository::instance();
		$all_structs = $struct_repo->get_all();
		$active_structs = 0;
		if (is_array($all_structs)) {
			foreach ($all_structs as $s) {
				if (!empty($s->is_active)) {
					$active_structs++;
				}
			}
		}

		// 4. Structure Sections
		$section_repo = new AIPS_Prompt_Section_Repository();
		$all_sections = $section_repo->get_all(false);
		$active_sections = 0;
		if (is_array($all_sections)) {
			foreach ($all_sections as $section) {
				if (!empty($section->is_active)) {
					$active_sections++;
				}
			}
		}

		// 5. Post Slices
		$slice_repo = AIPS_Post_Slices_Repository::instance();
		$all_slices = $slice_repo->get_all();
		$active_slices = 0;
		if (is_array($all_slices)) {
			foreach ($all_slices as $sl) {
				if (!empty($sl->is_active)) {
					$active_slices++;
				}
			}
		}

		return array(
			'templates' => array(
				'total'  => is_array($all_templates) ? count($all_templates) : 0,
				'active' => $active_templates,
			),
			'voices' => array(
				'total'  => is_array($all_voices) ? count($all_voices) : 0,
				'active' => $active_voices,
			),
			'structures' => array(
				'total'  => is_array($all_structs) ? count($all_structs) : 0,
				'active' => $active_structs,
			),
			'structure-sections' => array(
				'total'  => is_array($all_sections) ? count($all_sections) : 0,
				'active' => $active_sections,
			),
			'post-slices' => array(
				'total'  => is_array($all_slices) ? count($all_slices) : 0,
				'active' => $active_slices,
			),
		);
	}

	/**
	 * Get URL for a given Studio section.
	 *
	 * @param string $section Section key.
	 * @param array<string, mixed> $extra_args Extra query parameters.
	 * @return string
	 */
	public function get_section_url(string $section = '', array $extra_args = array()): string {
		$args = array('page' => self::PAGE_SLUG);
		if (!empty($section)) {
			$args['tab'] = $section;
		}
		if (!empty($extra_args)) {
			$args = array_merge($args, $extra_args);
		}
		return add_query_arg($args, admin_url('admin.php'));
	}

	/**
	 * Render the content of a specific Studio section.
	 *
	 * @param string $section Section key.
	 * @return void
	 */
	public function render_section_content(string $section) {
		switch ($section) {
			case 'templates':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$templates_handler = new AIPS_Templates();
					$templates_handler->render_page();
				}, __('Templates', 'ai-post-scheduler'), true);
				break;

			case 'voices':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$voices_handler = new AIPS_Voices();
					$voices = $voices_handler->get_all(false);
					include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
				}, __('Voices', 'ai-post-scheduler'), true);
				break;

			case 'structures':
			case 'structure-sections':
				$initial_structure_tab = 'structure-sections' === $section ? 'aips-structure-sections' : 'aips-structures';
				AIPS_Admin_Menu_Helper::safe_render(function() use ($initial_structure_tab) {
					$structures_handler = new AIPS_Structures_Controller();
					$repo = new AIPS_Article_Structure_Repository();
					$structures = $repo->get_all(false);
					$section_repo = new AIPS_Prompt_Section_Repository();
					$sections = $section_repo->get_all(false);
					include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
				}, __('Article Structures', 'ai-post-scheduler'), true);
				break;

			case 'post-slices':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$slices_repo = new AIPS_Post_Slices_Repository();
					$post_slices = $slices_repo->get_all();
					$post_slice_counts = $slices_repo->get_counts();
					include AIPS_PLUGIN_DIR . 'templates/admin/post-slices.php';
				}, __('Post Slices', 'ai-post-scheduler'), true);
				break;
		}
	}
}
