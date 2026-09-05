<?php
/**
 * Related Posts Frontend
 *
 * Handles public display of related posts via the_content filter,
 * shortcode, Gutenberg block, and PHP template helpers.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Related_Posts_Frontend
 */
class AIPS_Related_Posts_Frontend {

	/**
	 * @var AIPS_Related_Posts_Service
	 */
	private $related_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Initialize the frontend integration.
	 */
	public function __construct(?AIPS_Related_Posts_Service $related_service = null, ?AIPS_Config $config = null) {
		$container             = AIPS_Container::get_instance();
		$this->related_service = $related_service ?: ($container->has(AIPS_Related_Posts_Service::class) ? $container->make(AIPS_Related_Posts_Service::class) : new AIPS_Related_Posts_Service());
		$this->config          = $config          ?: AIPS_Config::get_instance();

		$this->init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		add_filter('the_content', array($this, 'filter_content'));
		add_shortcode('aips_related_posts', array($this, 'render_shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('init', array($this, 'register_block'));
	}

	/**
	 * Enqueue public stylesheet for related posts.
	 */
	public function enqueue_assets() {
		if ($this->config->get_option('aips_related_posts_enabled', true)) {
			wp_register_style(
				'aips-related-posts',
				AIPS_PLUGIN_URL . 'assets/css/related-posts.css',
				array(),
				AIPS_VERSION
			);

			if (is_singular()) {
				wp_enqueue_style('aips-related-posts');
			}
		}
	}

	/**
	 * Filter post content to automatically append related posts if enabled.
	 *
	 * @param string $content Post content HTML.
	 * @return string Modified content HTML.
	 */
	public function filter_content($content) {
		if (!is_singular() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		if (!$this->config->get_option('aips_related_posts_enabled', true)) {
			return $content;
		}

		if (!$this->config->get_option('aips_related_posts_auto_append', false)) {
			return $content;
		}

		$post_id = get_the_ID();
		if (!$post_id) {
			return $content;
		}

		if (has_shortcode($content, 'aips_related_posts') || (function_exists('has_block') && has_block('aips/related-posts', $post_id))) {
			return $content;
		}

		$related_html = $this->related_service->render_related_posts_html($post_id);
		if (!empty($related_html)) {
			$content .= "\n\n" . $related_html;
		}

		return $content;
	}

	/**
	 * Render [aips_related_posts] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_shortcode($atts) {
		if (!$this->config->get_option('aips_related_posts_enabled', true)) {
			return '';
		}

		wp_enqueue_style('aips-related-posts');

		$parsed = shortcode_atts(
			array(
				'id'              => get_the_ID(),
				'count'           => (int) $this->config->get_option('aips_related_posts_count', 4),
				'heading'         => $this->config->get_option('aips_related_posts_heading', __('Related Articles', 'ai-post-scheduler')),
				'layout'          => $this->config->get_option('aips_related_posts_layout', 'grid'),
				'show_thumbnails' => (bool) $this->config->get_option('aips_related_posts_show_thumbnails', true),
				'show_excerpts'   => (bool) $this->config->get_option('aips_related_posts_show_excerpts', true),
				'class'           => '',
			),
			$atts,
			'aips_related_posts'
		);

		$post_id = absint($parsed['id']);
		if ($post_id <= 0) {
			return '';
		}

		return $this->related_service->render_related_posts_html($post_id, $parsed);
	}

	/**
	 * Register Gutenberg block for related posts.
	 */
	public function register_block() {
		if (!function_exists('register_block_type')) {
			return;
		}

		register_block_type('aips/related-posts', array(
			'render_callback' => array($this, 'render_block'),
			'attributes'      => array(
				'count' => array(
					'type'    => 'number',
					'default' => 4,
				),
				'heading' => array(
					'type'    => 'string',
					'default' => 'Related Articles',
				),
				'layout' => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'showThumbnails' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showExcerpts' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
		));
	}

	/**
	 * Render Gutenberg dynamic block callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render_block($attributes) {
		wp_enqueue_style('aips-related-posts');

		$post_id = get_the_ID();
		if (!$post_id) {
			return '';
		}

		return $this->related_service->render_related_posts_html($post_id, array(
			'count'           => isset($attributes['count']) ? (int) $attributes['count'] : 4,
			'heading'         => isset($attributes['heading']) ? sanitize_text_field($attributes['heading']) : '',
			'layout'          => isset($attributes['layout']) ? sanitize_key($attributes['layout']) : 'grid',
			'show_thumbnails' => isset($attributes['showThumbnails']) ? (bool) $attributes['showThumbnails'] : true,
			'show_excerpts'   => isset($attributes['showExcerpts']) ? (bool) $attributes['showExcerpts'] : true,
		));
	}
}

// Global helper functions for theme developers
if (!function_exists('aips_get_related_posts')) {
	/**
	 * Retrieve related posts array for a post.
	 *
	 * @param int   $post_id WordPress post ID (defaults to current post).
	 * @param array $args    Query overrides.
	 * @return array Array of related posts.
	 */
	function aips_get_related_posts($post_id = 0, $args = array()) {
		$post_id = $post_id ? absint($post_id) : get_the_ID();
		if (!$post_id) {
			return array();
		}
		$service = AIPS_Container::get_instance()->make(AIPS_Related_Posts_Service::class);
		return $service->get_related_posts($post_id, $args);
	}
}

if (!function_exists('aips_render_related_posts')) {
	/**
	 * Echo related posts HTML for a post.
	 *
	 * @param int   $post_id WordPress post ID (defaults to current post).
	 * @param array $args    Display parameters.
	 * @return void
	 */
	function aips_render_related_posts($post_id = 0, $args = array()) {
		$post_id = $post_id ? absint($post_id) : get_the_ID();
		if (!$post_id) {
			return;
		}
		$service = AIPS_Container::get_instance()->make(AIPS_Related_Posts_Service::class);
		echo $service->render_related_posts_html($post_id, $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
