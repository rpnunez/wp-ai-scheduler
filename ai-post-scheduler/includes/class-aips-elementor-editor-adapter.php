<?php
/**
 * Elementor Editor Adapter
 *
 * Implements AIPS_Editor_Adapter_Interface for the Elementor Page Builder.
 * Extracts content from _elementor_data JSON element trees and hooks into
 * Elementor editor lifecycles.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Elementor_Editor_Adapter
 */
class AIPS_Elementor_Editor_Adapter implements AIPS_Editor_Adapter_Interface {

	/**
	 * Unique identifier.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'elementor';
	}

	/**
	 * Display label.
	 *
	 * @return string
	 */
	public function get_name() {
		return __('Elementor Page Builder', 'ai-post-scheduler');
	}

	/**
	 * Check if post is currently managed by Elementor.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_active_for_post($post_id) {
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return false;
		}
		$edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
		return ('builder' === $edit_mode);
	}

	/**
	 * Extract textual and HTML content from Elementor JSON data tree.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function extract_content($post_id) {
		$post_id = absint($post_id);
		$meta    = get_post_meta($post_id, '_elementor_data', true);

		if (empty($meta)) {
			$post = get_post($post_id);
			return $post ? (string) $post->post_content : '';
		}

		$elements = is_array($meta) ? $meta : json_decode($meta, true);
		if (!is_array($elements) && is_string($meta)) {
			$elements = json_decode(wp_unslash($meta), true);
		}
		if (!is_array($elements)) {
			$post = get_post($post_id);
			return $post ? (string) $post->post_content : '';
		}

		$chunks = array();
		$this->collect_element_text($elements, $chunks);

		$extracted = implode("\n\n", array_filter($chunks));
		if (empty($extracted)) {
			$post = get_post($post_id);
			return $post ? (string) $post->post_content : '';
		}

		return $extracted;
	}

	/**
	 * Extract internal links from Elementor content and widget settings.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function extract_links($post_id) {
		$content   = $this->extract_content($post_id);
		$container = AIPS_Container::get_instance();
		$service   = $container->has(AIPS_Link_Graph_Service::class) ? $container->make(AIPS_Link_Graph_Service::class) : new AIPS_Link_Graph_Service();

		$links = $service->parse_content_for_internal_links($content, $post_id);

		// Also check structured link settings in _elementor_data
		$meta = get_post_meta($post_id, '_elementor_data', true);
		if (!empty($meta)) {
			$elements = is_array($meta) ? $meta : json_decode($meta, true);
			if (!is_array($elements) && is_string($meta)) {
				$elements = json_decode(wp_unslash($meta), true);
			}
			if (is_array($elements)) {
				$widget_links = array();
				$this->collect_widget_links($elements, $widget_links, $post_id);

				// Merge unique target IDs
				$existing_targets = array_column($links, 'target_id');
				foreach ($widget_links as $w_link) {
					if (!in_array($w_link['target_id'], $existing_targets, true)) {
						$links[] = $w_link;
						$existing_targets[] = $w_link['target_id'];
					}
				}
			}
		}

		return $links;
	}

	/**
	 * Enqueue assets for Elementor editor screen.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function enqueue_editor_assets($post_id) {
		// Elementor editor integration hooks
		if (did_action('elementor/loaded') && function_exists('add_action')) {
			add_action('elementor/editor/after_enqueue_scripts', function () use ($post_id) {
				// Future Phase: Elementor panel scripts
			});
		}
	}

	/**
	 * Supported feature flags.
	 *
	 * @return array
	 */
	public function get_supported_features() {
		return array(
			'content_indexing',
			'graph_badges',
			'json_data_extraction',
			'structured_widget_links',
		);
	}

	/**
	 * Recursively extract text from Elementor element hierarchy.
	 *
	 * @param array $elements Array of elements.
	 * @param array $chunks   Output chunks array.
	 * @return void
	 */
	protected function collect_element_text(array $elements, array &$chunks) {
		foreach ($elements as $element) {
			if (!is_array($element)) {
				continue;
			}

			// Extract settings text
			if (!empty($element['settings']) && is_array($element['settings'])) {
				$s = $element['settings'];
				$text_keys = array('editor', 'title', 'description', 'text', 'html', 'caption');

				foreach ($text_keys as $key) {
					if (!empty($s[$key]) && is_string($s[$key])) {
						$chunks[] = $s[$key];
					}
				}
			}

			// Traverse child elements
			if (!empty($element['elements']) && is_array($element['elements'])) {
				$this->collect_element_text($element['elements'], $chunks);
			}
		}
	}

	/**
	 * Recursively extract link settings from Elementor widgets.
	 *
	 * @param array $elements Output chunks array.
	 * @param array $links    Output links array.
	 * @param int   $post_id  Source post ID.
	 * @return void
	 */
	protected function collect_widget_links(array $elements, array &$links, $post_id) {
		$site_host = wp_parse_url(get_site_url(), PHP_URL_HOST);

		foreach ($elements as $element) {
			if (!is_array($element)) {
				continue;
			}

			if (!empty($element['settings']['link']['url'])) {
				$url = trim($element['settings']['link']['url']);
				$url_host = wp_parse_url($url, PHP_URL_HOST);

				if (empty($url_host) || strtolower($url_host) === strtolower($site_host)) {
					$target_id = url_to_postid($url);
					if ($target_id > 0 && $target_id !== $post_id) {
						$target_post = get_post($target_id);
						$links[] = array(
							'target_id'   => $target_id,
							'anchor_text' => sanitize_text_field($element['settings']['title'] ?? $element['settings']['text'] ?? ''),
							'link_url'    => get_permalink($target_id) ?: $url,
							'post_type'   => $target_post ? $target_post->post_type : 'post',
						);
					}
				}
			}

			if (!empty($element['elements']) && is_array($element['elements'])) {
				$this->collect_widget_links($element['elements'], $links, $post_id);
			}
		}
	}
}
