<?php
/**
 * SEO Pattern Engine
 *
 * Evaluates dynamic tokens and pattern templates for SEO metadata fields.
 * Allows zero-token deterministic generation and prefix/suffix formatting.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Pattern_Engine {

	/**
	 * Replace pattern tokens with live post and site data.
	 *
	 * @param string      $pattern   Pattern string containing %%tokens%%.
	 * @param WP_Post|int $post      Post object or ID.
	 * @param array       $extra_data Optional contextual data (e.g., focus_keyword).
	 * @return string Evaluated string.
	 */
	public static function evaluate($pattern, $post, array $extra_data = array()) {
		if (!is_string($pattern) || trim($pattern) === '') {
			return '';
		}

		$post_obj = is_numeric($post) ? get_post($post) : $post;
		$post_id  = ($post_obj instanceof WP_Post) ? (int) $post_obj->ID : 0;

		$title     = ($post_obj instanceof WP_Post) ? $post_obj->post_title : '';
		$excerpt   = ($post_obj instanceof WP_Post) ? ($post_obj->post_excerpt ?: wp_trim_words($post_obj->post_content, 25, '')) : '';
		$sitename  = get_bloginfo('name');
		$sitedesc  = get_bloginfo('description');
		$focus_kw  = isset($extra_data['focus_keyword']) ? (string) $extra_data['focus_keyword'] : '';

		// Author name
		$author_name = '';
		if ($post_obj instanceof WP_Post && !empty($post_obj->post_author)) {
			$author = get_userdata($post_obj->post_author);
			$author_name = $author ? $author->display_name : '';
		}

		// Primary category
		$category_name = '';
		if ($post_id) {
			$categories = get_the_category($post_id);
			if (!empty($categories) && is_array($categories)) {
				$category_name = $categories[0]->name;
			}
		}

		$now = AIPS_DateTime::now();
		$year  = $now->format('Y');
		$month = $now->format('F');
		$day   = $now->format('j');
		$date  = $now->format(get_option('date_format', 'F j, Y'));

		$replacements = array(
			'%%title%%'         => $title,
			'%%sitename%%'      => $sitename,
			'%%sitedesc%%'      => $sitedesc,
			'%%focus_keyword%%' => $focus_kw,
			'%%year%%'          => $year,
			'%%month%%'         => $month,
			'%%day%%'           => $day,
			'%%date%%'          => $date,
			'%%category%%'      => $category_name,
			'%%author%%'        => $author_name,
			'%%excerpt%%'       => $excerpt,
		);

		/**
		 * Filters the pattern replacement token map.
		 *
		 * @param array       $replacements Token to value map.
		 * @param WP_Post|null $post_obj      Post object.
		 * @param array       $extra_data   Extra contextual data.
		 */
		$replacements = apply_filters('aips_seo_pattern_tokens', $replacements, $post_obj, $extra_data);

		$result = str_replace(array_keys($replacements), array_values($replacements), $pattern);

		// Clean up any double spaces or orphan separators
		$result = preg_replace('/\s+/', ' ', $result);
		$result = preg_replace('/(\s*\|\s*){2,}/', ' | ', $result);
		$result = preg_replace('/(\s*-\s*){2,}/', ' - ', $result);

		return trim($result);
	}

	/**
	 * Apply prefix and suffix formatting to a value.
	 *
	 * @param string      $value      Base value.
	 * @param string      $prefix     Prefix pattern (supports tokens).
	 * @param string      $suffix     Suffix pattern (supports tokens).
	 * @param WP_Post|int $post       Post object or ID.
	 * @param array       $extra_data Extra context.
	 * @return string Wrapped string.
	 */
	public static function wrap($value, $prefix = '', $suffix = '', $post = null, array $extra_data = array()) {
		$evaluated_prefix = !empty($prefix) ? self::evaluate($prefix, $post, $extra_data) : '';
		$evaluated_suffix = !empty($suffix) ? self::evaluate($suffix, $post, $extra_data) : '';

		$parts = array();
		if (!empty($evaluated_prefix)) {
			$parts[] = $evaluated_prefix;
		}
		if (!empty($value)) {
			$parts[] = $value;
		}
		if (!empty($evaluated_suffix)) {
			$parts[] = $evaluated_suffix;
		}

		return implode(' ', $parts);
	}
}
