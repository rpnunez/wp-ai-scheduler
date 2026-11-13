<?php
/**
 * Frontend Assets & Schema Delivery
 *
 * Handles conditional enqueuing of frontend component stylesheets/scripts
 * and delivers valid JSON-LD SEO structured data to the head of singular posts.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Frontend_Assets
 */
class AIPS_Frontend_Assets {

	/**
	 * Boot hooks.
	 */
	public static function init(): void {
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('wp_head', array(__CLASS__, 'render_schema_in_head'), 20);

		// Interop hooks for popular SEO plugins
		add_filter('rank_math/json_ld', array(__CLASS__, 'filter_rank_math_schema'), 20, 2);
		add_filter('wpseo_schema_graph', array(__CLASS__, 'filter_yoast_schema'), 20, 2);
	}

	/**
	 * Check if current singular post contains AIPS components.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if components are present.
	 */
	public static function post_has_components($post_id): bool {
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return false;
		}

		// Check post meta
		$schema = get_post_meta($post_id, '_aips_json_ld_schema', true);
		if (!empty($schema)) {
			return true;
		}

		// Check post content
		$post = get_post($post_id);
		if ($post && !empty($post->post_content)) {
			if (strpos($post->post_content, 'aips-') !== false || strpos($post->post_content, '[aips_') !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Conditionally enqueue component styles and interaction scripts.
	 */
	public static function enqueue_assets(): void {
		if (!is_singular()) {
			return;
		}

		$post_id = get_the_ID();
		if (!self::post_has_components($post_id)) {
			return;
		}

		$css_url = AIPS_PLUGIN_URL . 'assets/css/components.css';
		$js_url  = AIPS_PLUGIN_URL . 'assets/js/components.js';

		wp_enqueue_style(
			'aips-components-css',
			$css_url,
			array(),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-components-js',
			$js_url,
			array(),
			AIPS_VERSION,
			true
		);
	}

	/**
	 * Render JSON-LD schema graph in wp_head if SEO plugins haven't intercepted it.
	 */
	public static function render_schema_in_head(): void {
		if (!is_singular()) {
			return;
		}

		$post_id = get_the_ID();
		$schema = get_post_meta($post_id, '_aips_json_ld_schema', true);
		if (empty($schema)) {
			return;
		}

		$schema_array = is_array($schema) ? $schema : json_decode($schema, true);
		if (empty($schema_array)) {
			return;
		}

		/**
		 * Filter the generated JSON-LD schema array before output.
		 *
		 * @param array $schema_array Schema graph array.
		 * @param int   $post_id      Current post ID.
		 */
		$schema_array = apply_filters('aips_json_ld_schema', $schema_array, $post_id);
		if (empty($schema_array)) {
			return;
		}

		// If Yoast or Rank Math is actively outputting schema, avoid duplicate output if opted-in
		if ((defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) && apply_filters('aips_suppress_native_schema_for_seo_plugins', true)) {
			// Handled via filter_rank_math_schema or filter_yoast_schema
			return;
		}

		$json = wp_json_encode($schema_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if (!empty($json)) {
			echo "\n<!-- AI Post Scheduler JSON-LD Schema -->\n";
			echo '<script type="application/ld+json" class="aips-schema-graph">' . "\n";
			echo $json . "\n";
			echo "</script>\n<!-- /AI Post Scheduler JSON-LD Schema -->\n\n";
		}
	}

	/**
	 * Rank Math integration: merge AIPS schema graphs into Rank Math output.
	 *
	 * @param array  $data    Existing Rank Math schema.
	 * @param object $context Rank Math context.
	 * @return array Modified schema.
	 */
	public static function filter_rank_math_schema($data, $context) {
		if (!is_singular()) {
			return $data;
		}

		$post_id = get_the_ID();
		$schema = get_post_meta($post_id, '_aips_json_ld_schema', true);
		if (empty($schema)) {
			return $data;
		}

		$schema_array = is_array($schema) ? $schema : json_decode($schema, true);
		if (!empty($schema_array['@graph']) && is_array($schema_array['@graph'])) {
			if (!is_array($data)) {
				$data = array();
			}
			foreach ($schema_array['@graph'] as $piece) {
				$data[] = $piece;
			}
		}

		return $data;
	}

	/**
	 * Yoast SEO integration: merge AIPS schema pieces into Yoast schema graph.
	 *
	 * @param array  $pieces  Existing Yoast schema pieces.
	 * @param object $context Yoast context.
	 * @return array Modified pieces.
	 */
	public static function filter_yoast_schema($pieces, $context) {
		if (!is_singular()) {
			return $pieces;
		}

		$post_id = get_the_ID();
		$schema = get_post_meta($post_id, '_aips_json_ld_schema', true);
		if (empty($schema)) {
			return $pieces;
		}

		$schema_array = is_array($schema) ? $schema : json_decode($schema, true);
		if (!empty($schema_array['@graph']) && is_array($schema_array['@graph'])) {
			if (!is_array($pieces)) {
				$pieces = array();
			}
			foreach ($schema_array['@graph'] as $piece) {
				$pieces[] = $piece;
			}
		}

		return $pieces;
	}
}
