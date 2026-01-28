<?php
/**
 * Test System Status DB Check
 *
 * @package AI_Post_Scheduler
 */

class Test_System_Status_DB extends WP_UnitTestCase {

    private $original_wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_check_database_returns_table_sizes() {
        global $wpdb;

        // Mock $wpdb to return table status
        $wpdb = $this->getMockBuilder(stdClass::class)
                     ->addMethods(['get_results', 'get_row', 'prepare', 'get_var', 'db_version', 'get_charset_collate'])
                     ->getMock();

        $wpdb->prefix = 'wp_';

        $wpdb->method('db_version')->willReturn('8.0.0');
        $wpdb->method('get_charset_collate')->willReturn('utf8mb4_unicode_ci');

        // Better prepare mock that handles the %s replacement
        $wpdb->method('prepare')->will($this->returnCallback(function($query, ...$args) {
            foreach ($args as $arg) {
                // Determine if arg should be quoted
                $val = is_numeric($arg) ? $arg : "'$arg'";
                // Replace first occurrence of %s or %d
                $query = preg_replace('/%[sd]/', $val, $query, 1);
            }
            return $query;
        }));

        $wpdb->method('get_var')->will($this->returnCallback(function($query) {
             // Mock 'SHOW TABLES LIKE'
             if (strpos($query, 'SHOW TABLES LIKE') !== false) {
                 // Extract table name (simplified)
                 if (preg_match("/LIKE '(.+)'/", $query, $matches)) {
                     return $matches[1];
                 }
             }
             return null;
        }));

        $wpdb->method('get_row')->will($this->returnCallback(function($query) {
            if (strpos($query, 'SHOW TABLE STATUS') !== false) {
                return (object) [
                    'Name' => 'wp_aips_schedule',
                    'Data_length' => 1024 * 1024, // 1 MB
                    'Index_length' => 512 * 1024, // 0.5 MB
                ];
            }
            return null;
        }));

        $wpdb->method('get_results')->will($this->returnCallback(function($query) {
            if (strpos($query, 'SHOW COLUMNS') !== false) {
                 // Mock columns for aips_schedule
                 return [
                     (object) ['Field' => 'id'],
                     (object) ['Field' => 'template_id'],
                     (object) ['Field' => 'frequency'],
                     (object) ['Field' => 'next_run'],
                     (object) ['Field' => 'is_active'],
                     (object) ['Field' => 'topic'],
                     (object) ['Field' => 'article_structure_id'],
                     (object) ['Field' => 'rotation_pattern'],
                     (object) ['Field' => 'last_run'],
                     (object) ['Field' => 'status'],
                     (object) ['Field' => 'created_at'],
                 ];
            }
            if (strpos($query, 'SHOW TABLE STATUS') !== false) {
                return [
                    (object) [
                        'Name' => 'wp_aips_schedule',
                        'Data_length' => 1024 * 1024, // 1 MB
                        'Index_length' => 512 * 1024, // 0.5 MB
                    ]
                ];
            }
            return [];
        }));

        $system_status = new AIPS_System_Status();
        $info = $system_status->get_system_info();

        $this->assertArrayHasKey('database', $info);
        $db_info = $info['database'];

        // We expect 'aips_schedule' to have size info now
        $this->assertArrayHasKey('aips_schedule', $db_info);

        // Assert the value contains the calculated size (1.5 MB)
        // Since we haven't implemented it yet, this test is expected to fail or just return "OK"
        // We want to assert that it eventually contains "Size: 1.50 MB"
        $this->assertStringContainsString('Size: 1.50 MB', $db_info['aips_schedule']['value']);
    }
}
