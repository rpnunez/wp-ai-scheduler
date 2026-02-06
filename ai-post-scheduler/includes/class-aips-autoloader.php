<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Autoloader {

    public static function register() {
        spl_autoload_register(array(__CLASS__, 'load'));
    }

    public static function load($class_name) {
        // Check if class starts with AIPS_
        if (strpos($class_name, 'AIPS_') !== 0) {
            return;
        }

        // Convert AIPS_ClassName to aips-class-name
        $base_name = strtolower(str_replace('_', '-', $class_name));

        // Construct potential filenames
        $class_file = 'class-' . $base_name . '.php';
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
