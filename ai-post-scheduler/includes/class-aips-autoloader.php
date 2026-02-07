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

    public static function load($class_name) {
        // Check if class starts with AIPS_
        if (strpos($class_name, 'AIPS_') !== 0) {
            return;
        }

        // Convert class name to file names using helper methods
        $class_file = self::convert_class_name_to_filename($class_name);
        $base_name = self::convert_class_name_to_base($class_name);
        $interface_file = 'interface-' . $base_name . '.php';

        $path = AIPS_PLUGIN_DIR . 'includes/';

        if (file_exists($path . $class_file)) {
            require_once $path . $class_file;
            return;
        }

        if (file_exists($path . $interface_file)) {
            require_once $path . $interface_file;
            return;
        }
    }
}
