<?php
/**
 * Internal Links Editor Panel
 *
 * Adds an "Internal Links" panel to the post editor: a sidebar meta box in
 * the Classic Editor and a document settings panel in the Block Editor.
 * It shows the post's link counts and the posts linking to it, and lets
 * editors find, insert, undo and dismiss inbound link suggestions without
 * leaving the editor. Data comes from AIPS_Link_Report_Controller's AJAX
 * endpoints.
 *
 * The edited post is always the link target, so inserting a suggestion
 * changes other posts only and never conflicts with unsaved editor changes.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Editor_Panel
 */
class AIPS_Internal_Links_Editor_Panel {

	/**
	 * Meta box ID.
	 */
	const METABOX_ID = 'aips_internal_links_panel';

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index;

	/**
	 * @param AIPS_Link_Index_Service|null $link_index Link index service.
	 */
	public function __construct(?AIPS_Link_Index_Service $link_index = null) {
		$container        = AIPS_Container::get_instance();
		$this->link_index = $link_index ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());

		add_action('add_meta_boxes', array($this, 'register_metabox'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_classic_assets'));
		add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
	}

	/**
	 * Whether the panel applies to a post type for the current user.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function is_supported(string $post_type): bool {
		return current_user_can('manage_options') && in_array($post_type, $this->link_index->get_post_types(), true);
	}

	/**
	 * Register the Classic Editor meta box (hidden in the Block Editor, which
	 * renders its own panel).
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function register_metabox($post_type): void {
		if (!$this->is_supported((string) $post_type)) {
			return;
		}

		add_meta_box(
			self::METABOX_ID,
			__('Internal Links', 'ai-post-scheduler'),
			array($this, 'render_metabox'),
			$post_type,
			'side',
			'default',
			array('__back_compat_meta_box' => true)
		);
	}

	/**
	 * Render the Classic Editor meta box shell; contents load over AJAX.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_metabox($post): void {
		include AIPS_PLUGIN_DIR . 'templates/admin/internal-links-metabox.php';
	}

	/**
	 * Enqueue Classic Editor assets on post.php / post-new.php.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public function enqueue_classic_assets($hook_suffix): void {
		if (!in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || !$this->is_supported((string) $screen->post_type) || (method_exists($screen, 'is_block_editor') && $screen->is_block_editor())) {
			return;
		}

		wp_enqueue_style('aips-internal-links-panel', AIPS_PLUGIN_URL . 'assets/css/admin-internal-links-panel.css', array(), AIPS_VERSION);
		wp_enqueue_script('aips-templates-script', AIPS_PLUGIN_URL . 'assets/js/templates.js', array('jquery'), AIPS_VERSION, true);
		wp_enqueue_script(
			'aips-internal-links-metabox',
			AIPS_PLUGIN_URL . 'assets/js/admin-internal-links-metabox.js',
			array('jquery', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);
		wp_localize_script('aips-internal-links-metabox', 'aipsLinkPanelL10n', $this->get_l10n());
	}

	/**
	 * Enqueue the Block Editor panel.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets(): void {
		global $post;
		if (!$post instanceof WP_Post || !$this->is_supported($post->post_type)) {
			return;
		}

		wp_enqueue_style('aips-internal-links-panel', AIPS_PLUGIN_URL . 'assets/css/admin-internal-links-panel.css', array('wp-components'), AIPS_VERSION);
		wp_enqueue_script(
			'aips-internal-links-gutenberg',
			AIPS_PLUGIN_URL . 'assets/js/admin-internal-links-gutenberg.js',
			array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'jquery'),
			AIPS_VERSION,
			true
		);
		wp_localize_script('aips-internal-links-gutenberg', 'aipsLinkPanelL10n', $this->get_l10n($post->ID));
	}

	/**
	 * Strings and settings shared by both editor scripts.
	 *
	 * @param int $post_id Post being edited (Block Editor).
	 * @return array
	 */
	private function get_l10n(int $post_id = 0): array {
		return array(
			'ajaxurl'         => admin_url('admin-ajax.php'),
			'nonce'           => wp_create_nonce('aips_ajax_nonce'),
			'postId'          => $post_id,
			'reportUrl'       => admin_url('admin.php?page=aips-generated-posts&tab=link-report'),
			'panelTitle'      => __('Internal Links', 'ai-post-scheduler'),
			'loading'         => __('Loading…', 'ai-post-scheduler'),
			'loadError'       => __('Could not load internal link data.', 'ai-post-scheduler'),
			'notPublished'    => __('Publish this post to see its internal links and get link suggestions.', 'ai-post-scheduler'),
			'notIndexed'      => __('This post has not been scanned for links yet. Save it or run a scan from the Link Report.', 'ai-post-scheduler'),
			'inbound'         => __('Linked from', 'ai-post-scheduler'),
			'outbound'        => __('Links out', 'ai-post-scheduler'),
			'external'        => __('External', 'ai-post-scheduler'),
			'broken'          => __('Broken', 'ai-post-scheduler'),
			'orphan'          => __('Orphan: no other post links here', 'ai-post-scheduler'),
			'linkedFrom'      => __('Posts linking here', 'ai-post-scheduler'),
			'suggestions'     => __('Suggested inbound links', 'ai-post-scheduler'),
			'suggestBtn'      => __('Suggest Links', 'ai-post-scheduler'),
			'suggestAgainBtn' => __('Find Again', 'ai-post-scheduler'),
			'suggesting'      => __('Finding posts that could link here…', 'ai-post-scheduler'),
			'noSuggestions'   => __('No suitable posts found yet.', 'ai-post-scheduler'),
			'insertBtn'       => __('Insert', 'ai-post-scheduler'),
			'dismissBtn'      => __('Dismiss', 'ai-post-scheduler'),
			'undoBtn'         => __('Undo', 'ai-post-scheduler'),
			'inserted'        => __('Link inserted', 'ai-post-scheduler'),
			'noAnchor'        => __('(no anchor text found)', 'ai-post-scheduler'),
			'wellLinked'      => __('This post is well linked. Suggestions appear for posts with fewer than 3 inbound links.', 'ai-post-scheduler'),
			'openReport'      => __('Open Link Report', 'ai-post-scheduler'),
			'actionError'     => __('That did not work. Please try again.', 'ai-post-scheduler'),
		);
	}
}
