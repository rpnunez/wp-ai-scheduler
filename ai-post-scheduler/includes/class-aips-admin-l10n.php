<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_L10n
 *
 * Provides localized key/value arrays for admin scripts.
 * Delegates to AIPS_Language_Store for language-specific string retrieval.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_L10n {

	/**
	 * Get localized data array by key.
	 *
	 * Reads the current language setting and delegates to the language store.
	 *
	 * @param string $key Localization group key.
	 * @return array
	 */
	public static function get($key) {
		$language = AIPS_Config::get_instance()->get_option('aips_language');

		if (!is_string($language)) {
			$language = 'en';
		} else {
			$language = trim($language);

			if ('' === $language) {
				$language = 'en';
			}
		}

		return AIPS_Language_Store::get($language, $key);
	}
}
