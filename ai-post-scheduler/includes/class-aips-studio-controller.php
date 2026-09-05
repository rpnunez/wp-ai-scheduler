<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Studio_Controller
 *
 * Coordinates the Studio admin hub, launchpad cards, and focused section workspaces.
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
	 * Available Studio sections.
	 *
	 * @var array<string, array{label:string, icon:string, description:string, action_label:string, action_class:string}>
	 */
	public const SECTIONS = array(
		'templates' => array(
			'label'        => 'Templates',
			'icon'         => 'dashicons-media-document',
			'description'  => 'Create and configure AI post generation templates, prompt strategies, and format guidelines.',
			'action_label' => 'Add Template',
			'action_class' => 'aips-btn aips-btn-primary aips-add-template-btn',
		),
		'voices' => array(
			'label'        => 'Voices',
			'icon'         => 'dashicons-megaphone',
			'description'  => 'Define brand personality, tone of voice, stylistic rules, and custom excerpt guidelines.',
			'action_label' => 'Add Voice',
			'action_class' => 'aips-btn aips-btn-primary aips-add-voice-btn',
		),
		'structures' => array(
			'label'        => 'Article Structures',
			'icon'         => 'dashicons-editor-ol',
			'description'  => 'Build reusable article frameworks, required section outlines, and heading constraints.',
			'action_label' => 'Add Structure',
			'action_class' => 'aips-btn aips-btn-primary aips-add-structure-btn',
		),
		'post-slices' => array(
			'label'        => 'Post Slices',
			'icon'         => 'dashicons-grid-view',
			'description'  => 'Manage modular content blocks, dynamic CTAs, and automated slice insertions.',
			'action_label' => 'Add Slice',
			'action_class' => 'aips-btn aips-btn-primary aips-add-slice-btn',
		),
	);

	/**
	 * Get the currently requested Studio section key (or empty for Launchpad).
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

		if (array_key_exists($raw, self::SECTIONS)) {
			return $raw;
		}

		return '';
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

		$active_section = self::get_active_section_key();
		$stats = $this->get_studio_stats();
		$studio_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/studio.php';
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

		// 4. Post Slices
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
			'post-slices' => array(
				'total'  => is_array($all_slices) ? count($all_slices) : 0,
				'active' => $active_slices,
			),
		);
	}

	/**
	 * Get URL for a given Studio section or launchpad.
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
				$templates_handler = new AIPS_Templates();
				include AIPS_PLUGIN_DIR . 'templates/admin/templates.php';
				break;

			case 'voices':
				$voices_handler = new AIPS_Voices();
				include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
				break;

			case 'structures':
				$structures_handler = new AIPS_Structures_Controller();
				include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
				break;

			case 'post-slices':
				include AIPS_PLUGIN_DIR . 'templates/admin/post-slices.php';
				break;
		}
	}
}
