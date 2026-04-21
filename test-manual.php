<?php
echo "Starting test\n";
require 'ai-post-scheduler/vendor/autoload.php';
echo "Autoloaded\n";

try {
    if (class_exists('AIPS_Generator')) {
        echo "AIPS_Generator exists.\n";
    } else {
        echo "AIPS_Generator does NOT exist.\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
