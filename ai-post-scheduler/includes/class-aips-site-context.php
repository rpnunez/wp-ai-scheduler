<?php
/**
 * Site Context Service
 *
 * Provides a centralised access layer for site-wide content strategy settings.
 * These settings define the overall identity of the website — its niche, target
 * audience, brand voice, and content goals — and are used by multiple features
 * (Author Suggestions, topic generation, post generation) so they share a
 * consistent picture of what the site is about.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Site_Context
 *
 * Singleton-friendly utility that reads and caches the site-wide content
 * strategy options. Individual settings fall back to sensible defaults if
 * not yet configured by the admin.
 */
class AIPS_Site_Context {

	/**
	 * Option keys managed by this service.
	 *
	 * @var string[]
	 */
	const OPTIONS = array(
		'aips_site_niche',
		'aips_site_target_audience',
		'aips_site_content_goals',
		'aips_site_brand_voice',
		'aips_site_content_language',
		'aips_site_content_guidelines',
		'aips_site_excluded_topics',
	);

	/**
	 * Return all site-wide content settings as an associative array.
	 *
	 * Keys match the raw option names without the `aips_site_` prefix for
	 * convenience (e.g. `'niche'`, `'target_audience'`).
	 *
	 * @return array {
	 *     @type string $niche              Primary niche / topic of the website.
	 *     @type string $target_audience    Who the website writes for.
	 *     @type string $content_goals      What the content aims to achieve.
	 *     @type string $brand_voice        Brand voice and tone.
	 *     @type string $content_language   Language code for generated content (e.g. 'en').
	 *     @type string $content_guidelines General content guidelines and restrictions.
	 *     @type string $excluded_topics    Comma-separated topics to avoid globally.
	 * }
	 */
	public static function get() {
		return array(
			'niche'              => get_option('aips_site_niche', ''),
			'target_audience'    => get_option('aips_site_target_audience', ''),
			'content_goals'      => get_option('aips_site_content_goals', ''),
			'brand_voice'        => get_option('aips_site_brand_voice', ''),
			'content_language'   => get_option('aips_site_content_language', 'en'),
			'content_guidelines' => get_option('aips_site_content_guidelines', ''),
			'excluded_topics'    => get_option('aips_site_excluded_topics', ''),
		);
	}

	/**
	 * Return a single site-wide setting.
	 *
	 * @param string $key     Setting key without the `aips_site_` prefix (e.g. `'niche'`).
	 * @param mixed  $default Default value if the setting has not been configured.
	 * @return mixed
	 */
	public static function get_setting($key, $default = '') {
		return get_option('aips_site_' . $key, $default);
	}

	/**
	 * Build a compact context string suitable for inclusion in AI prompts.
	 *
	 * Only non-empty fields are included so the prompt is not padded with
	 * empty placeholder lines.
	 *
	 * @return string Formatted context block, or empty string if no settings are configured.
	 */
	public static function build_prompt_context() {
		$ctx = self::get();

		$lines = array();

		if (!empty($ctx['niche'])) {
			$lines[] = 'Site niche: ' . $ctx['niche'];
		}

		if (!empty($ctx['target_audience'])) {
			$lines[] = 'Target audience: ' . $ctx['target_audience'];
		}

		if (!empty($ctx['content_goals'])) {
			$lines[] = 'Content goals: ' . $ctx['content_goals'];
		}

		if (!empty($ctx['brand_voice'])) {
			$lines[] = 'Brand voice/tone: ' . $ctx['brand_voice'];
		}

		if (!empty($ctx['content_language']) && $ctx['content_language'] !== 'en') {
			$lines[] = 'Language: ' . $ctx['content_language'];
		}

		if (!empty($ctx['content_guidelines'])) {
			$lines[] = 'Content guidelines: ' . $ctx['content_guidelines'];
		}

		if (!empty($ctx['excluded_topics'])) {
			$lines[] = 'Topics to avoid globally: ' . $ctx['excluded_topics'];
		}

		if (empty($lines)) {
			return '';
		}

		return "Site-wide content context:\n" . implode("\n", $lines) . "\n\n";
	}

	/**
	 * Check whether the site context has been configured (at minimum the niche).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return !empty(get_option('aips_site_niche', ''));
	}
}
