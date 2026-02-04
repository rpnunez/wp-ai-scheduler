<?php
/**
 * Security Reproduction Test
 */
class Test_Security_Repro extends WP_UnitTestCase {

    private $import_mysql;
    private $mock_wpdb;

    public function setUp(): void {
        parent::setUp();

        // Manual require if not autoloaded
        $includes_dir = dirname(__DIR__) . '/includes/';
        require_once $includes_dir . 'class-aips-data-management-import.php';
        require_once $includes_dir . 'class-aips-data-management-import-mysql.php';
        require_once $includes_dir . 'class-aips-db-manager.php';

        $this->import_mysql = new AIPS_Data_Management_Import_MySQL();

        // Mock $wpdb to capture queries
        $this->mock_wpdb = new class {
            public $prefix = 'wp_';
            public $queries = [];
            public $last_error = '';

            public function query($query) {
                $this->queries[] = trim($query);
                // Simulate DB error if query is "BAD QUERY"
                if ($query === "BAD QUERY") {
                    return false;
                }
                return true;
            }
        };

        $GLOBALS['wpdb'] = $this->mock_wpdb;
    }

    public function test_vulnerability_hash_comment_bypass() {
        // Payload: A query that drops a table, but uses a # comment to trick the validator
        // into thinking it references a valid plugin table (wp_aips_history).
        // The comment must be INSIDE the query string processed by explode(';').
        // So we put the comment before the semicolon.
        $malicious_sql = "DROP TABLE wp_users # wp_aips_history ;";

        $temp_file = tempnam(sys_get_temp_dir(), 'sql_import_test');
        $content = "-- AI Post Scheduler Data Export\n" . $malicious_sql;
        file_put_contents($temp_file, $content);

        $result = $this->import_mysql->import($temp_file);

        unlink($temp_file);

        $executed = false;
        foreach ($this->mock_wpdb->queries as $q) {
            // Check if the sensitive table drop was executed
            if (strpos($q, 'DROP TABLE wp_users') !== false) {
                $executed = true;
                break;
            }
        }

        // After the fix, the comment "# wp_aips_history" should be stripped.
        // Therefore, the query "DROP TABLE wp_users  ;" will remain.
        // This query does NOT contain a valid plugin table name.
        // So validation should fail, and the query should NOT be executed.
        $this->assertFalse($executed, 'SECURITY FIX FAILED: Malicious DROP TABLE query was executed! It should have been blocked.');
    }

    public function test_fragility_semicolon_in_string() {
        $sql = "INSERT INTO wp_aips_history (details) VALUES ('Hello; World');";

        $temp_file = tempnam(sys_get_temp_dir(), 'sql_import_test_2');
        $content = "-- AI Post Scheduler Data Export\n" . $sql;
        file_put_contents($temp_file, $content);

        $result = $this->import_mysql->import($temp_file);
        unlink($temp_file);

        // It should have executed the INSERT query correctly as one query (plus foreign key checks).
        // Debug output
        if (count($this->mock_wpdb->queries) !== 3) {
            echo "\nQueries executed:\n";
            print_r($this->mock_wpdb->queries);
        }

        // Find the INSERT query
        $insert_query = '';
        foreach ($this->mock_wpdb->queries as $q) {
            if (strpos($q, 'INSERT INTO') !== false) {
                $insert_query = $q;
                break;
            }
        }

        $this->assertNotEmpty($insert_query, 'INSERT query should be executed');
        $this->assertEquals("INSERT INTO wp_aips_history (details) VALUES ('Hello; World')", $insert_query, 'Query should be preserved exactly');
    }
}
