<?php
/**
 * Tests for Security Import functionality
 */

// Manually load required classes if not already loaded
if (!class_exists('AIPS_Data_Management_Import')) {
    require_once dirname(dirname(__FILE__)) . '/includes/class-aips-data-management-import.php';
}
if (!class_exists('AIPS_Data_Management_Import_MySQL')) {
    require_once dirname(dirname(__FILE__)) . '/includes/class-aips-data-management-import-mysql.php';
}

class Test_Security_Import extends WP_UnitTestCase {

    private $importer;

    public function setUp(): void {
        parent::setUp();
        $this->importer = new AIPS_Data_Management_Import_MySQL();
    }

    // Helper for environment where assertWPError is missing
    private function assertIsWPError($thing, $message = '') {
        $this->assertInstanceOf('WP_Error', $thing, $message);
    }

    public function test_import_blocks_insert_select() {
        // Create a temporary file with malicious SQL using a valid plugin table (to pass table check)
        // but using SELECT to steal data.
        $file = tempnam(sys_get_temp_dir(), 'test_sql_');
        $table = 'wp_aips_history';
        file_put_contents($file, "-- AI Post Scheduler Data Export\nINSERT INTO $table (generated_title) SELECT user_pass FROM wp_users;");

        $result = $this->importer->import($file);

        unlink($file);

        $this->assertIsWPError($result, 'Should return WP_Error for INSERT ... SELECT');
    }

    public function test_import_blocks_update() {
        // Create a temporary file with malicious SQL
        $file = tempnam(sys_get_temp_dir(), 'test_sql_');
        file_put_contents($file, "-- AI Post Scheduler Data Export\nUPDATE wp_options SET option_value = 'hacked' WHERE option_name = 'siteurl';");

        $result = $this->importer->import($file);

        unlink($file);

        $this->assertIsWPError($result, 'Should return WP_Error for UPDATE queries');
    }

    public function test_import_allows_valid_insert() {
        global $wpdb;

        // Mock wpdb query to return true (success)
        $original_wpdb = $wpdb;
        $wpdb = $this->getMockBuilder('stdClass')
                     ->setMethods(['query', 'prepare', 'insert'])
                     ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->method('query')->willReturn(true);
        $wpdb->method('prepare')->willReturnArgument(0); // Simple passthrough

        // Create a temporary file with valid SQL targeting a plugin table
        $file = tempnam(sys_get_temp_dir(), 'test_sql_');
        $table = 'wp_aips_history'; // Must be a valid plugin table
        file_put_contents($file, "-- AI Post Scheduler Data Export\nINSERT INTO $table (id, title) VALUES (1, 'Test');");

        // We need to ensure AIPS_DB_Manager returns this table as valid.
        // The importer calls AIPS_DB_Manager::get_full_table_names().
        // This static method uses $wpdb->prefix. Since we mocked $wpdb global, it should work.

        $result = $this->importer->import($file);

        unlink($file);
        $wpdb = $original_wpdb; // Restore

        $this->assertTrue($result, 'Valid INSERT should be allowed');
    }

    public function test_import_enforces_whitelist() {
         // Create a temporary file with disallowed command
         $file = tempnam(sys_get_temp_dir(), 'test_sql_');
         file_put_contents($file, "-- AI Post Scheduler Data Export\nGRANT ALL PRIVILEGES ON *.* TO 'hacker'@'localhost';");

         $result = $this->importer->import($file);

         unlink($file);

         $this->assertIsWPError($result, 'Should return WP_Error for GRANT queries');
    }

    public function test_import_allows_insert_with_select_text() {
        global $wpdb;

        // Mock wpdb
        $original_wpdb = $wpdb;
        $wpdb = $this->getMockBuilder('stdClass')
                     ->setMethods(['query', 'prepare', 'insert'])
                     ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->method('query')->willReturn(true);
        $wpdb->method('prepare')->willReturnArgument(0);

        // Create a temporary file with valid SQL containing "select" in text
        $file = tempnam(sys_get_temp_dir(), 'test_sql_');
        $table = 'wp_aips_history';
        file_put_contents($file, "-- AI Post Scheduler Data Export\nINSERT INTO $table (title) VALUES ('Please select an option');");

        $result = $this->importer->import($file);

        unlink($file);
        $wpdb = $original_wpdb;

        $this->assertTrue($result, 'Should allow INSERT with "select" in content');
    }
}
