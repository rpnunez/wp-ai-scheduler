<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Shared sanitization and rendering metadata for scoped feedback overrides. */
class AIPS_Post_Feedback_Settings {
	public static function fields() {
		return array(
			'like_weight'           => array(0.0, 10.0),
			'dislike_weight'        => array(0.0, 10.0),
			'similarity_weight'     => array(0.0, 10.0),
			'recency_weight'        => array(0.0, 10.0),
			'author_match_weight'   => array(0.0, 10.0),
			'template_match_weight' => array(0.0, 10.0),
			'global_pool_weight'    => array(0.0, 10.0),
			'max_examples'          => array(1, 20),
			'min_similarity'        => array(0.0, 1.0),
			'min_samples'           => array(1, 1000),
			'prompt_budget_chars'   => array(300, 20000),
			'edited_content_weight' => array(0.0, 1.0),
		);
	}

	public static function sanitize_enabled($value) {
		if ('enabled' === $value || 1 === $value || '1' === $value) {
			return 1;
		}
		if ('disabled' === $value || 0 === $value || '0' === $value) {
			return 0;
		}
		return null;
	}

	public static function sanitize_config($raw) {
		if (is_string($raw)) {
			$decoded = json_decode(wp_unslash($raw), true);
			$raw = is_array($decoded) ? $decoded : array();
		}
		if (!is_array($raw)) {
			return array();
		}
		$clean = array();
		foreach (self::fields() as $key => $bounds) {
			if (!array_key_exists($key, $raw) || '' === $raw[$key] || !is_numeric($raw[$key])) {
				continue;
			}
			$value = max($bounds[0], min($bounds[1], (float) $raw[$key]));
			if (in_array($key, array('max_examples', 'min_samples', 'prompt_budget_chars'), true)) {
				$value = (int) $value;
			}
			$clean[$key] = $value;
		}
		return $clean;
	}
}
