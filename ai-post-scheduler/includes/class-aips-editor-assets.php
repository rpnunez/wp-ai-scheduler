<?php
/**
 * Editor Assets
 *
 * Manages script and style enqueuing, meta box registration, and localization
 * for AI Edit within the Gutenberg Block Editor and Classic Editor.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Editor_Assets {

	/**
	 * Meta box ID for the Classic Editor.
	 */
	const METABOX_ID = 'aips_classic_ai_edit_metabox';

	/**
	 * @var AIPS_Editor_Directive_Service
	 */
	private $directive_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Editor_Directive_Service|null $directive_service Directive service.
	 */
	public function __construct(?AIPS_Editor_Directive_Service $directive_service = null) {
		$container               = AIPS_Container::get_instance();
		$this->directive_service = $directive_service ?: ($container->has(AIPS_Editor_Directive_Service::class) ? $container->make(AIPS_Editor_Directive_Service::class) : new AIPS_Editor_Directive_Service());

		add_action('add_meta_boxes', array($this, 'register_classic_metabox'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_classic_assets'));
		add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
	}

	/**
	 * Whether the current screen and post type are supported.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function is_supported(string $post_type): bool {
		if (!current_user_can('edit_posts')) {
			return false;
		}

		$public_types = get_post_types(array('public' => true));
		return in_array($post_type, $public_types, true);
	}

	/**
	 * Register the Classic Editor meta box.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function register_classic_metabox($post_type): void {
		if (!$this->is_supported((string) $post_type)) {
			return;
		}

		add_meta_box(
			self::METABOX_ID,
			__('AI Edit & Directives', 'ai-post-scheduler'),
			array($this, 'render_classic_metabox'),
			$post_type,
			'side',
			'default',
			array('__back_compat_meta_box' => true)
		);
	}

	/**
	 * Render the Classic Editor meta box template.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_classic_metabox($post): void {
		$template_path = AIPS_PLUGIN_DIR . 'templates/admin/classic-ai-edit-metabox.php';
		if (file_exists($template_path)) {
			include $template_path;
		}
	}

	/**
	 * Enqueue Classic Editor assets.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_classic_assets($hook_suffix): void {
		if (!in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || !$this->is_supported((string) $screen->post_type)) {
			return;
		}

		if (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
			return;
		}

		global $post;
		$post_id = $post instanceof WP_Post ? $post->ID : 0;

		$this->register_styles();
		wp_enqueue_style('aips-editor-style');

		wp_enqueue_script(
			'aips-editor-classic',
			AIPS_PLUGIN_URL . 'assets/js/admin-editor-classic.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);
		wp_localize_script('aips-editor-classic', 'aipsEditorL10n', $this->get_l10n($post_id));

		// Hook TinyMCE filters for inline selection actions
		if (current_user_can('edit_posts') && 'true' === get_user_option('rich_editing')) {
			add_filter('mce_buttons', array($this, 'register_tinymce_button'));
			add_filter('mce_external_plugins', array($this, 'register_tinymce_plugin'));
		}
	}

	/**
	 * Enqueue Block Editor assets.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets(): void {
		global $post;
		if (!$post instanceof WP_Post || !$this->is_supported($post->post_type)) {
			return;
		}

		$this->register_styles();
		wp_enqueue_style('aips-editor-style');

		wp_enqueue_script(
			'aips-editor-gutenberg',
			AIPS_PLUGIN_URL . 'assets/js/admin-editor-gutenberg.js',
			array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-block-editor', 'jquery'),
			AIPS_VERSION,
			true
		);
		wp_localize_script('aips-editor-gutenberg', 'aipsEditorL10n', $this->get_l10n($post->ID));
	}

	/**
	 * Register shared editor styles.
	 *
	 * @return void
	 */
	private function register_styles(): void {
		if (!wp_style_is('aips-design-tokens', 'registered')) {
			wp_register_style('aips-design-tokens', AIPS_PLUGIN_URL . 'assets/css/design-tokens.css', array(), AIPS_VERSION);
		}

		wp_register_style(
			'aips-editor-style',
			AIPS_PLUGIN_URL . 'assets/css/admin-editor.css',
			array('aips-design-tokens'),
			AIPS_VERSION
		);
	}

	/**
	 * Register TinyMCE toolbar button.
	 *
	 * @param array $buttons Existing buttons.
	 * @return array
	 */
	public function register_tinymce_button(array $buttons): array {
		$buttons[] = 'aips_editor_tinymce_btn';
		return $buttons;
	}

	/**
	 * Register TinyMCE external plugin script.
	 *
	 * @param array $plugins Existing plugins.
	 * @return array
	 */
	public function register_tinymce_plugin(array $plugins): array {
		$plugins['aips_editor_tinymce'] = AIPS_PLUGIN_URL . 'assets/js/admin-editor-tinymce.js';
		return $plugins;
	}

	/**
	 * Build localization configuration shared across editor scripts.
	 *
	 * @param int $post_id Post ID being edited.
	 * @return array
	 */
	public function get_l10n(int $post_id = 0): array {
		return array(
			'ajaxurl'    => admin_url('admin-ajax.php'),
			'nonce'      => wp_create_nonce('aips_ajax_nonce'),
			'postId'     => $post_id,
			'presets'    => $this->directive_service->get_standard_presets(),
			'panelTitle' => __('AI Edit & Directives', 'ai-post-scheduler'),
			'labels'     => array(
				'popoverTitle'  => __('AI Edit — Rewrite & Enhance', 'ai-post-scheduler'),
				'apply'         => __('Apply to Block', 'ai-post-scheduler'),
				'replace'       => __('Replace Selection', 'ai-post-scheduler'),
				'insert'        => __('Insert', 'ai-post-scheduler'),
				'dismiss'       => __('Dismiss', 'ai-post-scheduler'),
				'generating'    => __('Generating with AI…', 'ai-post-scheduler'),
				'weaving'       => __('Weaving internal links…', 'ai-post-scheduler'),
				'variants'      => __('Candidate Variations', 'ai-post-scheduler'),
				'instruction'   => __('Custom instruction (e.g. "make it punchy")...', 'ai-post-scheduler'),
				'weaveBtn'      => __('Weave Internal Links', 'ai-post-scheduler'),
				'titleVariants' => __('Generate 3 Title Candidates', 'ai-post-scheduler'),
				'slugVariants' => __('Generate 3 Post Slug Candidates', 'ai-post-scheduler'),
				'excerptVariants' => __('Generate 3 Excerpt Candidates', 'ai-post-scheduler'),
				'success'       => __('Content updated!', 'ai-post-scheduler'),
				'selectPrompt'  => __('Choose a preset or enter instructions below:', 'ai-post-scheduler'),
				'variantCount'  => __('Variants:', 'ai-post-scheduler'),
				'actionError'   => __('That did not work. Please check your AI configuration.', 'ai-post-scheduler'),
			),
		);
	}
}
