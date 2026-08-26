<?php
/**
 * Resolves AI model/provider policy across global, profile, template, and call scopes.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.3
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Routing_Resolver {

	private const REQUEST_KEYS = array('title', 'excerpt', 'content', 'image');

	/**
	 * Resolve a request policy. Explicit call options remain authoritative.
	 *
	 * @param array  $template_policy Template policy JSON decoded as an array.
	 * @param string $request_type    title, excerpt, content, or image.
	 * @param array  $explicit        Explicit provider/model options.
	 * @return array
	 */
	public static function resolve(array $template_policy = array(), $request_type = 'content', array $explicit = array()) {
		$request_type = in_array($request_type, self::REQUEST_KEYS, true) ? $request_type : 'content';
		$config       = AIPS_Config::get_instance();
		$ai_config    = $config->get_ai_config();
		$profiles     = self::get_profiles();
		$profile_id   = !empty($template_policy['profile']) ? sanitize_key($template_policy['profile']) : 'site_default';
		$profile      = isset($profiles[$profile_id]) && is_array($profiles[$profile_id]) ? $profiles[$profile_id] : array();
		$overrides    = isset($template_policy['overrides']) && is_array($template_policy['overrides']) ? $template_policy['overrides'] : array();

		$model = self::value_for_request($profile, $request_type);
		if ($model === '') {
			$model = self::value_for_request($ai_config, $request_type);
		}
		if (isset($overrides[$request_type . '_model'])) {
			$model = sanitize_text_field((string) $overrides[$request_type . '_model']);
		}

		$connector = !empty($profile['connector'])
			? sanitize_key($profile['connector'])
			: (!empty($profile['provider']) && !in_array($profile['provider'], array('meow', 'wp_ai_client'), true) ? sanitize_key($profile['provider']) : '');

		if ($connector === '' && !empty($ai_config['connector'])) {
			$connector = sanitize_key($ai_config['connector']);
		}

		$result = array(
			'connector' => $connector,
			'model' => $model,
			'fallback_enabled' => isset($template_policy['fallback_enabled']) ? (bool) $template_policy['fallback_enabled'] : (array_key_exists('fallback_enabled', $profile) ? (bool) $profile['fallback_enabled'] : true),
			'source' => $model !== '' ? ($overrides !== array() ? 'template_override_or_profile' : 'profile_or_global') : 'provider_default',
		);

		foreach (array('connector', 'model') as $key) {
			if (isset($explicit[$key]) && (string) $explicit[$key] !== '') {
				$result[$key] = sanitize_text_field((string) $explicit[$key]);
				$result['source'] = 'explicit_request';
			}
		}
		if (!empty($overrides['connector'])) {
			$result['connector'] = sanitize_key((string) $overrides['connector']);
			$result['source'] = 'explicit_request';
		}

		return $result;
	}

	/**
	 * Get normalized routing profiles from the site option.
	 *
	 * @return array
	 */
	public static function get_profiles() {
		$stored = AIPS_Config::get_instance()->get_option('aips_ai_routing_profiles');
		$profiles = is_array($stored) ? $stored : array();

		if (!isset($profiles['site_default'])) {
			$profiles['site_default'] = array(
				'label' => __('Site Default', 'ai-post-scheduler'),
				'provider' => '',
				'fallback_enabled' => true,
			);
		}

		return $profiles;
	}

	private static function value_for_request(array $source, $request_type) {
		$keys = array(
			$request_type . '_model',
			$request_type === 'image' ? 'image_model' : 'model',
		);

		foreach ($keys as $key) {
			if (!empty($source[$key])) {
				return sanitize_text_field((string) $source[$key]);
			}
		}

		return '';
	}
}
