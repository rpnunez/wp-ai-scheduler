<?php
// Mock upgrade.php
if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        return array();
    }
}
