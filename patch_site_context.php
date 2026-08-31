<<<<<<< SEARCH
	public static function get_setting($key, $default = null) {
		$options = AIPS_Settings::get_content_strategy_options();
		$config  = AIPS_Config::get_instance();

		foreach ($options as $option_name => $meta) {
			if ($meta['key'] === $key) {
				return $config->get_option($option_name, $default);
			}
		}

		return $default !== null ? $default : '';
	}
=======
	public static function get_setting($key, $default = null) {
		$options = AIPS_Settings::get_content_strategy_options();
		$config  = AIPS_Config::get_instance();

		// Create an inverted index by key to avoid O(n) loops.
		static $key_to_option = array();
		if (empty($key_to_option)) {
			foreach ($options as $option_name => $meta) {
				if (isset($meta['key'])) {
					$key_to_option[$meta['key']] = $option_name;
				}
			}
		}

		if (isset($key_to_option[$key])) {
			return $config->get_option($key_to_option[$key], $default);
		}

		return $default !== null ? $default : '';
	}
>>>>>>> REPLACE
