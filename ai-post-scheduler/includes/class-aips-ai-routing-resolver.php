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

		$result = array(
			'connector' => $connector,
			'model' => $model,
			'fallback_enabled' => isset($template_policy['fallback_enabled']) ? (bool) $template_policy['fallback_enabled'] : (array_key_exists('fallback_enabled', $profile) ? (bool) $profile['fallback_enabled'] : true),
			'source' => $model !== '' ? ($overrides !== array() ? 'template_override_or_profile' : 'profile_or_global') : 'provider_default',
		);

		if (!empty($overrides['connector'])) {
			$result['connector'] = sanitize_key((string) $overrides['connector']);
			$result['source'] = 'template_override_or_profile';
		}

		// Explicit call options stay authoritative and are therefore applied last.
		// 'connector_id' is the canonical key used by AIPS_AI_Service.
		$explicit_keys = array('connector' => array('connector', 'connector_id'), 'model' => array('model'));
		foreach ($explicit_keys as $result_key => $option_keys) {
			foreach ($option_keys as $option_key) {
				if (isset($explicit[$option_key]) && (string) $explicit[$option_key] !== '') {
					$result[$result_key] = sanitize_text_field((string) $explicit[$option_key]);
					$result['source'] = 'explicit_request';
					break;
				}
			}
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
			// site_default must come first: the template form's profile <select>
			// falls back to its first option after a native form reset.
			$profiles = array_merge(
				array(
					'site_default' => array(
						'label' => __('Site Default', 'ai-post-scheduler'),
						'provider' => '',
						'fallback_enabled' => true,
					),
				),
				$profiles
			);
		}

		return $profiles;
	}

	private static function value_for_request(array $source, $request_type) {
		// Profiles use '<type>_model'; AIPS_Config::get_ai_config() uses 'model_<type>'
		// (and 'image_model'). Both shapes are accepted so the same lookup can read
		// either source.
		$keys = array(
			$request_type . '_model',
			'model_' . $request_type,
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
