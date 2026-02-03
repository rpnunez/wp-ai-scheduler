<?php

class Test_Security_Import extends WP_UnitTestCase {
    private $import_mysql;
    private $original_wpdb;
    private $queries_executed = [];

    public function setUp(): void {
        parent::setUp();

        // Load required classes if not already loaded
        $includes_dir = dirname(__DIR__) . '/includes/';
        $files_to_load = [
            'class-aips-data-management.php',
            'class-aips-data-management-import.php',
            'class-aips-data-management-import-mysql.php',
            'class-aips-data-management-export.php',
            'class-aips-data-management-export-mysql.php',
            'class-aips-data-management-import-json.php',
            'class-aips-data-management-export-json.php'
        ];

        foreach ($files_to_load as $file) {
            if (file_exists($includes_dir . $file)) {
                require_once $includes_dir . $file;
            }
        }

        $this->import_mysql = new AIPS_Data_Management_Import_MySQL();

        // Mock wpdb to capture queries
        global $wpdb;
        $this->original_wpdb = $wpdb;

        $test_case = $this; // variable to pass to anonymous class

        $wpdb = new class($test_case) {
            private $test_case;
            public $prefix = 'wp_';
            public $last_error = '';

            public function __construct($test_case) {
                $this->test_case = $test_case;
            }

            public function query($query) {
                if (strpos($query, 'SET FOREIGN_KEY_CHECKS') !== false) {
                    return true;
                }
                $this->test_case->record_query($query);
                return true;
            }

            public function get_charset_collate() {
                return 'utf8mb4_unicode_ci';
            }
        };
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function record_query($query) {
        $this->queries_executed[] = $query;
    }

    public function test_vulnerability_arbitrary_insert_bypass() {
        // Construct a malicious SQL file content
        // The attacker wants to insert into 'wp_users' (not allowed)
        // But they include 'wp_aips_history' (allowed) in the values to bypass the check.
        // The regex check just looks for the table name ANYWHERE in the query.
        $malicious_sql = "INSERT INTO wp_users (user_login, user_pass) VALUES ('hacker', 'wp_aips_history');";

        $file_path = tempnam(sys_get_temp_dir(), 'test_vuln_');
        // Add -- prefix to make header a comment, as in real export
        file_put_contents($file_path, "-- AI Post Scheduler Data Export\n" . $malicious_sql);

        // Execute import
        $result = $this->import_mysql->import($file_path);

        unlink($file_path);

        // Security assertion: The import MUST fail.
        // Currently (before fix), this likely passes (returns true), causing the test to FAIL.
        $this->assertWPError($result, "Malicious import should be rejected");
        if (is_wp_error($result)) {
            // Either invalid_table (matched regex but table not allowed) or invalid_query (didn't match regex) is acceptable for security.
            // Ideally it should be invalid_table if our regex is good.
            $code = $result->get_error_code();
            $this->assertTrue(in_array($code, ['invalid_table', 'invalid_query']), "Should fail with invalid_table or invalid_query error. Got: " . $code . " Message: " . $result->get_error_message());
        }
    }

    public function test_vulnerability_semicolon_splitting() {
        // Construct SQL with semicolon in string
        $sql = "INSERT INTO wp_aips_history (generated_content) VALUES ('Hello; World');";

        $file_path = tempnam(sys_get_temp_dir(), 'test_semi_');
        // Add -- prefix to make header a comment
        file_put_contents($file_path, "-- AI Post Scheduler Data Export\n" . $sql);

        $this->queries_executed = [];
        $result = $this->import_mysql->import($file_path);

        unlink($file_path);

        if (is_wp_error($result)) {
            echo "Import failed with error: " . $result->get_error_message() . "\n";
        }

        // With unsafe splitting, this splits into 2 invalid queries.
        // We assert that we have exactly 1 query executed, and it is the full query.
        $this->assertCount(1, $this->queries_executed, "Should execute exactly 1 query (semicolon in string should not split)");
        if (count($this->queries_executed) > 0) {
            $this->assertStringContainsString("'Hello; World'", $this->queries_executed[0]);
        }
    }
}
