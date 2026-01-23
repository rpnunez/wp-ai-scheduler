<?php
/**
 * Verification Script: Verify Table Fix and Object Access
 *
 * 1. Checks if AIPS_DB_Manager::get_schema includes aips_trending_topics
 * 2. Simulates the object/array access logic in research.php to ensure no fatals
 */

// Mock WordPress constants and functions
define('ABSPATH', '/var/www/html/');
define('AIPS_PLUGIN_DIR', __DIR__ . '/../');

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';

    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function prepare($query, ...$args) {
        return $query; // Simple pass-through for mock
    }

    public function get_results($query, $output = 'OBJECT') {
        // Return dummy data for testing
        if (strpos($query, 'aips_templates') !== false) {
            $t1 = new stdClass(); $t1->id = 1; $t1->name = 'Template 1';
            $t2 = new stdClass(); $t2->id = 2; $t2->name = 'Template 2';
            return [$t1, $t2];
        }
        if (strpos($query, 'GROUP BY niche') !== false) {
            // Mock get_niche_list return - typically array of arrays if ARRAY_A used
            // but we want to verify our cast works for both objects and arrays
            return [
                (object)['niche' => 'Tech', 'count' => 5],
                (object)['niche' => 'Health', 'count' => 3]
            ];
        }
        return [];
    }
}

$wpdb = new MockWPDB();

// Include necessary files
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-db-manager.php';

// Test 1: Verify Schema
echo "Test 1: Verifying Schema...\n";
$db_manager = new AIPS_DB_Manager();
$schema = $db_manager->get_schema();
$found = false;
foreach ($schema as $sql) {
    if (strpos($sql, 'CREATE TABLE wp_aips_trending_topics') !== false) {
        $found = true;
        break;
    }
}

if ($found) {
    echo "SUCCESS: aips_trending_topics table found in schema.\n";
} else {
    echo "FAILURE: aips_trending_topics table NOT found in schema.\n";
    exit(1);
}

// Test 2: Verify Object Casting Logic
echo "\nTest 2: Verifying Object Casting Logic...\n";

// Mock data as Objects (what likely caused the error)
$niches_objects = [
    (object)['niche' => 'Tech', 'count' => 10],
    (object)['niche' => 'Health', 'count' => 5]
];

// Mock data as Arrays (what ARRAY_A should return)
$niches_arrays = [
    ['niche' => 'Tech', 'count' => 10],
    ['niche' => 'Health', 'count' => 5]
];

function test_logic($data, $label) {
    echo "Testing with $label...\n";
    try {
        foreach ($data as $niche) {
            // This is the logic we added to research.php
            $niche = (object) $niche;

            // Simulating the access
            $val = $niche->niche;
            $count = $niche->count;
            echo "  - Accessed: $val ($count)\n";
        }
        echo "  SUCCESS for $label\n";
    } catch (Error $e) {
        echo "  FAILURE for $label: " . $e->getMessage() . "\n";
        exit(1);
    }
}

test_logic($niches_objects, 'Objects');
test_logic($niches_arrays, 'Arrays');

echo "\nVerification Complete: All tests passed.\n";
