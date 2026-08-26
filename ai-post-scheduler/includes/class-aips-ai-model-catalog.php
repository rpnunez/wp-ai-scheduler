<?php
/**
 * Cached model catalog integration for the WordPress AI Client.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.4
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Model_Catalog {
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Return models supporting a requested capability.
	 *
	 * @param string $capability text or image.
	 * @param bool   $refresh Force a remote refresh.
	 * @return array
	 */
	public static function get($capability = 'text', $refresh = false) {
		$capability = $capability === 'image' ? 'image' : 'text';
		$key = 'aips_ai_model_catalog_' . $capability;
		if (!$refresh) {
			$cached = get_transient($key);
			if (is_array($cached)) {
				return $cached;
			}
		}

		$models = self::discover_wp_ai_client_models($capability);
		$models = (array) apply_filters('aips_ai_model_catalog', $models, $capability);
		set_transient($key, $models, self::CACHE_TTL);

		return $models;
	}

	/**
	 * Discover metadata through the SDK registry when available.
	 *
	 * @param string $capability text or image.
	 * @return array
	 */
	private static function discover_wp_ai_client_models($capability) {
		$client_class = 'WordPress\\AiClient\\AiClient';
		$requirements_class = 'WordPress\\AiClient\\Providers\\Models\\DTO\\ModelRequirements';
		$capability_class = 'WordPress\\AiClient\\Providers\\Models\\Enums\\CapabilityEnum';

		if (!class_exists($client_class) || !class_exists($requirements_class) || !class_exists($capability_class)) {
			return array();
		}

		try {
			$required_capability = $capability === 'image'
				? constant($capability_class . '::IMAGE_GENERATION')
				: constant($capability_class . '::TEXT_GENERATION');
			$requirements = new $requirements_class(array($required_capability));
			$registry = $client_class::defaultRegistry();
			$metadata = $registry->findModelsMetadataForSupport($requirements);
		} catch (Throwable $e) {
			return array();
		}

		$models = array();
		foreach ((array) $metadata as $provider_models) {
			if (!is_object($provider_models) || !method_exists($provider_models, 'getProvider') || !method_exists($provider_models, 'getModels')) {
				continue;
			}
			$provider = $provider_models->getProvider();
			$provider_id = is_object($provider) && method_exists($provider, 'getId') ? sanitize_key($provider->getId()) : '';
			$provider_name = is_object($provider) && method_exists($provider, 'getName') ? sanitize_text_field($provider->getName()) : $provider_id;

			foreach ((array) $provider_models->getModels() as $model) {
				if (!is_object($model) || !method_exists($model, 'getId')) {
					continue;
				}
				$model_id = sanitize_text_field($model->getId());
				if ($model_id === '') {
					continue;
				}
				$models[] = array(
					'id' => $model_id,
					'label' => method_exists($model, 'getName') ? sanitize_text_field($model->getName()) : $model_id,
					'provider' => $provider_id,
					'provider_label' => $provider_name,
					'capability' => $capability,
				);
			}
		}

		return $models;
	}
}
