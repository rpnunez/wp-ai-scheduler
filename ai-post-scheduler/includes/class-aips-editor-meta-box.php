<?php
/**
 * Editor Sidebar Meta-Box
 *
 * Provides a dedicated tabbed sidebar meta-box on post edit screens (Classic Editor
 * and standard WordPress edit screens) for semantic link insertion and post SEO metrics.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Editor_Meta_Box
 */
class AIPS_Editor_Meta_Box {

	/**
	 * Meta box slug.
	 */
	const META_BOX_ID = 'aips-editor-semantic-links';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('add_meta_boxes', array($this, 'register_meta_box'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Register the sidebar meta box across public post types.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		$post_types = get_post_types(array('public' => true));
		unset($post_types['attachment']);

		/**
		 * Filter post types that receive the AIPS Semantic Links & SEO sidebar meta box.
		 *
		 * @param array $post_types Array of post type slugs.
		 */
		$post_types = apply_filters('aips_editor_meta_box_post_types', array_values($post_types));

		foreach ($post_types as $post_type) {
			add_meta_box(
				self::META_BOX_ID,
				__('AIPS Semantic Links & SEO', 'ai-post-scheduler'),
				array($this, 'render_meta_box'),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box($post) {
		wp_nonce_field('aips_editor_meta_box_nonce', 'aips_editor_meta_box_nonce');
		?>
		<div class="aips-mb-container" data-post-id="<?php echo esc_attr($post->ID); ?>">
			<!-- Nav Tabs -->
			<div class="aips-mb-nav">
				<button type="button" class="aips-mb-tab-btn active" data-tab="suggestions">
					<span class="dashicons dashicons-admin-links"></span>
					<?php esc_html_e('Link Suggestions', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-mb-tab-btn" data-tab="seo">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e('Post SEO', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<!-- Tab 1: Link Suggestions -->
			<div class="aips-mb-tab-panel active" id="aips-mb-panel-suggestions">
				<div class="aips-mb-search-row">
					<input type="search" id="aips-mb-search-input" class="aips-mb-input" placeholder="<?php esc_attr_e('Search keywords or topic…', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-mb-search-btn" class="button button-secondary button-small" title="<?php esc_attr_e('Search suggestions', 'ai-post-scheduler'); ?>">
						<span class="dashicons dashicons-search" style="margin-top:2px;"></span>
					</button>
				</div>
				<p class="description aips-mb-tip">
					<?php esc_html_e('Select text in editor and click "Insert" to weave a semantic internal link.', 'ai-post-scheduler'); ?>
				</p>
				<div id="aips-mb-suggestions-list" class="aips-mb-list">
					<div class="aips-mb-loading">
						<span class="spinner is-active" style="float:none;vertical-align:middle;margin-right:6px;"></span>
						<?php esc_html_e('Searching relevant articles…', 'ai-post-scheduler'); ?>
					</div>
				</div>
			</div>

			<!-- Tab 2: Post SEO & Graph Metrics -->
			<div class="aips-mb-tab-panel" id="aips-mb-panel-seo" style="display:none;">
				<div id="aips-mb-seo-metrics-wrap">
					<div class="aips-mb-loading">
						<span class="spinner is-active" style="float:none;vertical-align:middle;margin-right:6px;"></span>
						<?php esc_html_e('Loading SEO link metrics…', 'ai-post-scheduler'); ?>
					</div>
				</div>
				<div class="aips-mb-graph-link-wrap" style="margin-top:14px;border-top:1px solid #e2e8f0;padding-top:10px;">
					<a href="<?php echo esc_url(admin_url('admin.php?page=aips-internal-links#seo-graph')); ?>" target="_blank" class="button button-secondary button-small" style="display:block;text-align:center;width:100%;">
						<span class="dashicons dashicons-networking" style="vertical-align:middle;font-size:14px;"></span>
						<?php esc_html_e('Open Full SEO Link Graph', 'ai-post-scheduler'); ?> &rarr;
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue styles and scripts for post editor screens.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets($hook_suffix) {
		if (!in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$post = get_post();
		$post_id = $post ? (int) $post->ID : 0;

		$css_path = AIPS_PLUGIN_DIR . 'assets/css/admin-editor-meta-box.css';
		$js_path  = AIPS_PLUGIN_DIR . 'assets/js/admin-editor-meta-box.js';

		$css_ver = file_exists($css_path) ? filemtime($css_path) : AIPS_VERSION;
		$js_ver  = file_exists($js_path) ? filemtime($js_path) : AIPS_VERSION;

		wp_enqueue_style(
			'aips-admin-editor-meta-box',
			AIPS_PLUGIN_URL . 'assets/css/admin-editor-meta-box.css',
			array('dashicons'),
			$css_ver
		);

		wp_enqueue_script(
			'aips-admin-editor-meta-box',
			AIPS_PLUGIN_URL . 'assets/js/admin-editor-meta-box.js',
			array('jquery'),
			$js_ver,
			true
		);

		wp_localize_script(
			'aips-admin-editor-meta-box',
			'aipsEditorMetaBox',
			array(
				'ajaxUrl'   => admin_url('admin-ajax.php'),
				'restUrl'   => esc_url_raw(rest_url('aips/v1/editor/')),
				'restNonce' => wp_create_nonce('wp_rest'),
				'postId'    => $post_id,
				'i18n'      => array(
					'loading'        => __('Loading…', 'ai-post-scheduler'),
					'noSuggestions'  => __('No link suggestions found for this query.', 'ai-post-scheduler'),
					'insertLink'     => __('Insert', 'ai-post-scheduler'),
					'inserted'       => __('Inserted!', 'ai-post-scheduler'),
					'copyUrl'        => __('Copy', 'ai-post-scheduler'),
					'copied'         => __('Copied!', 'ai-post-scheduler'),
					'inboundLinks'   => __('Inbound Links', 'ai-post-scheduler'),
					'outboundLinks'  => __('Outbound Links', 'ai-post-scheduler'),
					'crawlDepth'     => __('Crawl Depth', 'ai-post-scheduler'),
					'seoStatus'      => __('SEO Status', 'ai-post-scheduler'),
					'orphanAlert'    => __('⚠️ Orphan Post: 0 inbound links pointing to this article. Check linking opportunities to build internal equity.', 'ai-post-scheduler'),
					'healthy'        => __('Healthy Inbound Equity', 'ai-post-scheduler'),
					'hub'            => __('🌟 Pillar Hub Post', 'ai-post-scheduler'),
				),
			)
		);
	}
}
