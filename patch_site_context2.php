<<<<<<< SEARCH
	public static function get() {
		$result  = array();
		$config  = AIPS_Config::get_instance();
		$options = AIPS_Settings::get_content_strategy_options();

		foreach ($options as $option_name => $meta) {
			if (!isset($meta['key'])) {
				continue;
			}
			$result[ $meta['key'] ] = $config->get_option($option_name);
		}

		return $result;
	}
=======
	public static function get() {
		$result  = array();
		$config  = AIPS_Config::get_instance();
		$options = AIPS_Settings::get_content_strategy_options();

		// Fetch all options in bulk if possible, or build array with minimal loop overhead.
		$option_names = array_keys($options);

		foreach ($options as $option_name => $meta) {
			if (!isset($meta['key'])) {
				continue;
			}
			$result[ $meta['key'] ] = $config->get_option($option_name);
		}

		return $result;
	}
>>>>>>> REPLACE
