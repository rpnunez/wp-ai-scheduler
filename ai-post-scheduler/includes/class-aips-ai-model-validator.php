<?php
/**
 * Validates configured model preferences against the optional cached catalog.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.5
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Model_Validator {
	/**
	 * Validate a comma-separated model preference list.
	 *
	 * @param string $model       Model preference list.
	 * @param string $capability  text or image.
	 * @param string $connector   Optional connector ID.
	 * @return array{valid:bool,known:bool,message:string}
	 */
	public static function validate($model, $capability = 'text', $connector = '') {
		$policy = (string) AIPS_Config::get_instance()->get_option('aips_ai_model_validation');
		if ($policy === 'off' || trim((string) $model) === '') {
			return array('valid' => true, 'known' => true, 'message' => '');
		}

		$catalog = AIPS_AI_Model_Catalog::get($capability);
		if (empty($catalog)) {
			return array(
				'valid' => true,
				'known' => false,
				'message' => __('The model catalog is unavailable; the configured model will be validated by the connector.', 'ai-post-scheduler'),
			);
		}

		$requested = array_filter(array_map('trim', explode(',', (string) $model)));
		$known_ids = array();
		foreach ($catalog as $entry) {
			if (!is_array($entry) || empty($entry['id'])) {
				continue;
			}
			if ($connector !== '' && !empty($entry['provider']) && $entry['provider'] !== $connector) {
				continue;
			}
			$known_ids[] = (string) $entry['id'];
		}

		$recognized = array_intersect($requested, $known_ids);
		if (!empty($recognized)) {
			return array('valid' => true, 'known' => true, 'message' => '');
		}

		$message = sprintf(
			__('None of the configured model preferences (%1$s) were found in the %2$s model catalog.', 'ai-post-scheduler'),
			implode(', ', $requested),
			$capability
		);

		return array(
			'valid' => $policy !== 'strict',
			'known' => true,
			'message' => $message,
		);
	}
}
