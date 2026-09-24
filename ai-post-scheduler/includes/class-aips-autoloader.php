<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Autoloader {

	public static function register() {
		spl_autoload_register(array(__CLASS__, 'load'));
	}

	/**
	 * Convert class name to base name (lowercase with hyphens)
	 *
	 * @param string $class_name The class name to convert
	 * @return string The base name (e.g., "aips-history-repository")
	 */
	public static function convert_class_name_to_base($class_name) {
		return strtolower(str_replace('_', '-', $class_name));
	}

	/**
	 * Convert class name to class file name
	 *
	 * @param string $class_name The class name to convert
	 * @return string The converted file name (e.g., "class-aips-history-repository.php")
	 */
	public static function convert_class_name_to_filename($class_name) {
		$base_name = self::convert_class_name_to_base($class_name);
		return 'class-' . $base_name . '.php';
	}

	/**
	 * Convert class/interface name to interface file name
	 *
	 * @param string $class_name The interface name to convert
	 * @return string The converted file name (e.g., "interface-aips-history-service.php")
	 */
	public static function convert_class_name_to_interface_filename($class_name) {
		$base_name = self::convert_class_name_to_base($class_name);
		return 'interface-' . $base_name . '.php';
	}

	/**
	 * Convert class/trait name to trait file name
	 *
	 * @param string $class_name The trait name to convert
	 * @return string The converted file name (e.g., "trait-aips-ajax-guard.php")
	 */
	public static function convert_class_name_to_trait_filename($class_name) {
		$base_name = self::convert_class_name_to_base($class_name);
		return 'trait-' . $base_name . '.php';
	}

	public static function load($class_name) {
		// Check if class starts with AIPS_
		if (strpos($class_name, 'AIPS_') !== 0) {
			return;
		}

		// Convert class name to file names using helper methods
		$class_file     = self::convert_class_name_to_filename($class_name);
		$interface_file = self::convert_class_name_to_interface_filename($class_name);
		$trait_file     = self::convert_class_name_to_trait_filename($class_name);

		$paths = array(
			AIPS_PLUGIN_DIR . 'includes/',
			AIPS_PLUGIN_DIR . 'includes/providers/',
			AIPS_PLUGIN_DIR . 'includes/diagnostics/',
			AIPS_PLUGIN_DIR . 'includes/job/',
		);

		foreach ($paths as $path) {
			if (file_exists($path . $class_file)) {
				require_once $path . $class_file;
				return;
			}

			if (file_exists($path . $interface_file)) {
				require_once $path . $interface_file;
				return;
			}

			if (file_exists($path . $trait_file)) {
				require_once $path . $trait_file;
				return;
			}
		}
	}
}
