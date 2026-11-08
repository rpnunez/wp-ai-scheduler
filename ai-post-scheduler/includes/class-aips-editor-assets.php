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
	const METABOX_ID          = 'aips_classic_ai_edit_metabox';
	const METABOX_ID_TITLE    = 'aips_ai_edit_title_metabox';
	const METABOX_ID_SLUG     = 'aips_ai_edit_slug_metabox';
	const METABOX_ID_EXCERPT  = 'aips_ai_edit_excerpt_metabox';
	const METABOX_ID_CONTENT  = 'aips_ai_edit_content_metabox';
	const METABOX_ID_IMAGE    = 'aips_ai_edit_featured_image_metabox';
	const METABOX_ID_POST_IMAGES = 'aips_ai_edit_post_images_metabox';

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

		// Register per-component AI Edit widgets through the screen helper.
		AIPS_Screen_Meta_Box_Helper::register_box(
			self::METABOX_ID_TITLE,
			__('AI Edit: Post Title', 'ai-post-scheduler'),
			array($this, 'render_classic_component_metabox'),
			$post_type,
			'side',
			'high',
			array('component' => 'title', 'label' => __('Title', 'ai-post-scheduler'))
		);

		AIPS_Screen_Meta_Box_Helper::register_box(
			self::METABOX_ID_SLUG,
			__('AI Edit: Post Slug', 'ai-post-scheduler'),
			array($this, 'render_classic_component_metabox'),
			$post_type,
			'side',
			'high',
			array('component' => 'slug', 'label' => __('Slug', 'ai-post-scheduler'))
		);

		AIPS_Screen_Meta_Box_Helper::register_box(
			self::METABOX_ID_EXCERPT,
			__('AI Edit: Post Excerpt', 'ai-post-scheduler'),
			array($this, 'render_classic_component_metabox'),
			$post_type,
			'side',
			'default',
			array('component' => 'excerpt', 'label' => __('Excerpt', 'ai-post-scheduler'))
		);

		AIPS_Screen_Meta_Box_Helper::register_box(
			self::METABOX_ID_CONTENT,
			__('AI Edit: Post Content', 'ai-post-scheduler'),
			array($this, 'render_classic_component_metabox'),
			$post_type,
			'normal',
			'default',
			array('component' => 'content', 'label' => __('Content', 'ai-post-scheduler'))
		);

		AIPS_Screen_Meta_Box_Helper::register_box(
			self::METABOX_ID_IMAGE,
			__('AI Edit: Featured Image', 'ai-post-scheduler'),
			array($this, 'render_classic_component_metabox'),
			$post_type,
			'side',
			'default',
			array('component' => 'featured_image', 'label' => __('Featured Image', 'ai-post-scheduler'))
		);

		AIPS_Screen_Meta_Box_Helper::register_box(
			self::METABOX_ID_POST_IMAGES,
			__('AI Edit: Post Images', 'ai-post-scheduler'),
			array($this, 'render_classic_post_images_metabox'),
			$post_type,
			'normal',
			'default'
		);

		AIPS_Screen_Meta_Box_Helper::register_box(
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
	 * Render a per-component Classic Editor meta box template.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Meta box registration arguments.
	 * @return void
	 */
	public function render_classic_component_metabox($post, array $args = array()): void {
		$component       = isset($args['args']['component']) ? sanitize_key($args['args']['component']) : 'title';
		$component_label = isset($args['args']['label']) ? $args['args']['label'] : ucfirst($component);
		$template_path   = AIPS_PLUGIN_DIR . 'templates/admin/classic-ai-edit-component-metabox.php';
		if (file_exists($template_path)) {
			include $template_path;
		}
	}

	/**
	 * Render the Classic Editor meta box template.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	/**
	 * Render the Classic Editor Post Images meta box.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_classic_post_images_metabox($post): void {
		$template_path = AIPS_PLUGIN_DIR . 'templates/admin/classic-ai-edit-post-images-metabox.php';
		if (file_exists($template_path)) {
			include $template_path;
		}
	}

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

		if (!wp_script_is('aips-templates-script', 'registered')) {
			wp_register_script(
				'aips-templates-script',
				AIPS_PLUGIN_URL . 'assets/js/templates.js',
				array('jquery'),
				AIPS_VERSION,
				true
			);
		}
		wp_enqueue_script('aips-templates-script');

		if (!wp_script_is('aips-utilities-script', 'registered')) {
			wp_register_script(
				'aips-utilities-script',
				AIPS_PLUGIN_URL . 'assets/js/utilities.js',
				array('jquery'),
				AIPS_VERSION,
				true
			);
		}
		wp_enqueue_script('aips-utilities-script');

		wp_enqueue_script(
			'aips-editor-classic',
			AIPS_PLUGIN_URL . 'assets/js/admin-editor-classic.js',
			array('jquery', 'aips-templates-script', 'aips-utilities-script'),
			AIPS_VERSION,
			true
		);
		wp_localize_script('aips-editor-classic', 'aipsEditorL10n', $this->get_l10n($post_id));

		add_action('admin_footer', array($this, 'render_classic_templates'));

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
			'aips-ai-edit-style',
			AIPS_PLUGIN_URL . 'assets/css/admin-ai-edit.css',
			array('aips-design-tokens'),
			AIPS_VERSION
		);

		wp_register_style(
			'aips-editor-style',
			AIPS_PLUGIN_URL . 'assets/css/admin-editor.css',
			array('aips-design-tokens', 'aips-ai-edit-style'),
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
		$history_id = 0;
		if ($post_id > 0) {
			$container = AIPS_Container::get_instance();
			if ($container->has(AIPS_History_Repository_Interface::class)) {
				$repo = $container->make(AIPS_History_Repository_Interface::class);
				$rec  = $repo->get_by_post_id($post_id);
				if ($rec && isset($rec->id)) {
					$history_id = (int) $rec->id;
				}
			}
		}

		$default_config = AIPS_Config::get_instance()->get_ai_config();
		$available_models = array(
			''                           => __('Template Default', 'ai-post-scheduler'),
			'gpt-4o'                     => 'GPT-4o (OpenAI)',
			'gpt-4o-mini'                => 'GPT-4o Mini (OpenAI)',
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Anthropic)',
			'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Anthropic)',
			'gemini-1.5-pro'             => 'Gemini 1.5 Pro (Google)',
			'gemini-1.5-flash'           => 'Gemini 1.5 Flash (Google)',
		);

		return array(
			'ajaxurl'            => admin_url('admin-ajax.php'),
			'nonce'              => wp_create_nonce('aips_ajax_nonce'),
			'postId'             => $post_id,
			'historyId'          => $history_id,
			'postImages'         => $this->get_editor_post_images($post_id, $history_id),
			'presets'            => $this->directive_service->get_standard_presets(),
			'models'             => $available_models,
			'componentOverrides' => $post_id > 0 ? (get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true) ?: array()) : array(),
			'defaultAiConfig'    => array(
				'model'       => isset($default_config['model']) ? $default_config['model'] : '',
				'temperature' => isset($default_config['temperature']) ? floatval($default_config['temperature']) : 0.7,
			),
			'panelTitle'         => __('AI Edit & Directives', 'ai-post-scheduler'),
			'labels'     => array(
				'popoverTitle'  => __('AI Edit — Rewrite & Enhance', 'ai-post-scheduler'),
				'apply'         => __('Apply to Block', 'ai-post-scheduler'),
				'replace'       => __('Replace Selection', 'ai-post-scheduler'),
				'insert'        => __('Insert', 'ai-post-scheduler'),
				'inserted'      => __('Inserted', 'ai-post-scheduler'),
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

	/**
	 * Content images for the post's current history run.
	 *
	 * @param int $post_id    Post ID.
	 * @param int $history_id History container ID.
	 * @return array
	 */
	private function get_editor_post_images(int $post_id, int $history_id): array {
		if ($post_id <= 0) {
			return array();
		}

		$rows = (new AIPS_Post_Images_Generated_Repository())->get_for_post_history($post_id, $history_id);
		$list = array();
		foreach ($rows as $position => $row) {
			$url = '';
			if (!empty($row->image_url)) {
				$url = (string) $row->image_url;
			} elseif (!empty($row->attachment_id)) {
				$url = (string) wp_get_attachment_url((int) $row->attachment_id);
			}
			$list[] = array(
				'id'         => (int) $row->id,
				'imageIndex' => $position + 1,
				'status'     => (string) $row->status,
				'url'        => $url,
				'error'      => isset($row->error_message) ? (string) $row->error_message : '',
			);
		}

		return $list;
	}

	/**
	 * Render HTML templates for Classic Editor widgets.
	 *
	 * @return void
	 */
	public function render_classic_templates(): void {
		?>
		<script type="text/html" id="aips-tmpl-editor-revision-item">
			<div class="aips-revision-item" data-revision-id="{{id}}">
				<div class="aips-revision-header">
					<span><span class="dashicons dashicons-backup"></span> {{sourceBadge}} {{timeText}}</span>
					<button type="button" class="button button-small button-secondary aips-restore-revision-btn" data-revision-id="{{id}}">
						<span class="dashicons dashicons-undo"></span> {{restoreLabel}}
					</button>
				</div>
				<div class="aips-revision-body">{{snippet}}</div>
			</div>
		</script>
		<script type="text/html" id="aips-tmpl-editor-post-image-card">
			<div class="aips-editor-post-image-card" data-image-id="{{id}}">
				<div class="aips-editor-post-image-header">
					<strong>{{title}}</strong>
					<span class="aips-editor-post-image-status">{{status}}</span>
				</div>
				<div class="aips-editor-post-image-actions">
					<button type="button" class="button button-secondary aips-editor-post-image-regenerate">{{regenerateLabel}}</button>
					<button type="button" class="button button-link aips-editor-post-image-revisions">{{revisionsLabel}}</button>
				</div>
				<div class="aips-editor-post-image-compare" hidden></div>
				<div class="aips-editor-post-image-revisions" hidden></div>
			</div>
		</script>
		<script type="text/html" id="aips-tmpl-editor-variant-card">
			<div class="aips-variant-card">
				<div class="aips-variant-card-header"><span>Candidate {{candidateIndex}}</span></div>
				<div class="aips-variant-card-body">{{text}}</div>
				<button type="button" class="button button-small button-primary aips-apply-variant-btn">{{insertLabel}}</button>
			</div>
		</script>
		<?php
	}
}
