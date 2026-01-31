<?php
/**
 * Security Tests for Import Functionality
 */
class Test_Security_Import extends WP_UnitTestCase {

    public function test_import_mysql_blocks_unsafe_queries() {
        // Mock $wpdb to capture queries
        global $wpdb;

        // Save original wpdb
        $original_wpdb = $wpdb;

        // Create mock
        $wpdb = $this->getMockBuilder('stdClass')
                     ->addMethods(['query', 'prepare', 'get_results', 'get_row', 'get_var', 'insert', 'update', 'delete'])
                     ->getMock();

        $wpdb->prefix = 'wp_';

        $queries_executed = [];
        $wpdb->method('query')
             ->will($this->returnCallback(function($query) use (&$queries_executed) {
                 $queries_executed[] = trim($query);
                 return true;
             }));

        $importer = new AIPS_Data_Management_Import_MySQL();

        $malicious_query = "DELETE FROM wp_users WHERE ID = 1";

        // Create a temporary file with malicious SQL
        $tmp_file = tempnam(sys_get_temp_dir(), 'aips_test_sql');
        $content = "-- AI Post Scheduler Data Export\n" . $malicious_query . ";";
        file_put_contents($tmp_file, $content);

        // Attempt import
        $result = $importer->import($tmp_file);

        // Clean up
        unlink($tmp_file);
        $wpdb = $original_wpdb;

        // Verify the result is WP_Error (meaning it WAS blocked)
        $this->assertTrue(is_wp_error($result), 'Malicious query should be blocked and return WP_Error');

        if (is_wp_error($result)) {
            $this->assertEquals('invalid_query', $result->get_error_code());
        }

        // Check if malicious query was executed
        $executed = false;
        foreach ($queries_executed as $q) {
            if ($q === $malicious_query) {
                $executed = true;
                break;
            }
        }

        $this->assertFalse($executed, 'Malicious query should NOT be executed');
    }

    public function test_import_mysql_allows_valid_queries() {
        // Mock $wpdb to capture queries
        global $wpdb;
        $original_wpdb = $wpdb;

        $wpdb = $this->getMockBuilder('stdClass')
                     ->addMethods(['query', 'prepare', 'get_results', 'get_row', 'get_var', 'insert', 'update', 'delete'])
                     ->getMock();
        $wpdb->prefix = 'wp_';

        $queries_executed = [];
        $wpdb->method('query')
             ->will($this->returnCallback(function($query) use (&$queries_executed) {
                 $queries_executed[] = trim($query);
                 return true;
             }));

        $importer = new AIPS_Data_Management_Import_MySQL();

        // A valid import containing allowed commands on allowed tables
        // Note: We use wp_aips_history which corresponds to $wpdb->prefix . 'aips_history'
        $valid_sql = "-- AI Post Scheduler Data Export\n";
        $valid_sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $valid_sql .= "DROP TABLE IF EXISTS `wp_aips_history`;\n";
        $valid_sql .= "CREATE TABLE `wp_aips_history` ( id int );\n";
        $valid_sql .= "LOCK TABLES `wp_aips_history` WRITE;\n";
        $valid_sql .= "INSERT INTO `wp_aips_history` VALUES (1);\n";
        $valid_sql .= "UNLOCK TABLES;\n";

        $tmp_file = tempnam(sys_get_temp_dir(), 'aips_test_valid_sql');
        file_put_contents($tmp_file, $valid_sql);

        $result = $importer->import($tmp_file);

        unlink($tmp_file);
        $wpdb = $original_wpdb;

        if (is_wp_error($result)) {
            $this->fail('Valid import failed with error: ' . $result->get_error_message());
        }

        $this->assertTrue($result === true, 'Valid import should succeed');

        // Check if queries were executed
        $this->assertContains('DROP TABLE IF EXISTS `wp_aips_history`', $queries_executed);
        $this->assertContains('INSERT INTO `wp_aips_history` VALUES (1)', $queries_executed);
    }
}
