<?php
/**
 * Security tests for Data Management Import
 */
class Test_AIPS_Security_Import extends WP_UnitTestCase {

	private $import_mysql;
	private $original_wpdb;

	public function setUp(): void {
		parent::setUp();

		$this->original_wpdb = isset($GLOBALS['wpdb']) ? $GLOBALS['wpdb'] : null;

		// Ensure classes are loaded
		$includes_dir = dirname(__DIR__) . '/includes/';
		if (!class_exists('AIPS_Data_Management_Import')) {
			require_once $includes_dir . 'class-aips-data-management-import.php';
		}
		if (!class_exists('AIPS_Data_Management_Import_MySQL')) {
			require_once $includes_dir . 'class-aips-data-management-import-mysql.php';
		}

        // Replace global wpdb with our spy
        $GLOBALS['wpdb'] = new class {
             public $queries_executed = [];
             public $prefix = 'wp_';
             public $last_error = '';

             public function query($query) {
                 $this->queries_executed[] = $query;
                 return true;
             }

             // Add other methods if needed by the class under test
        };

		$this->import_mysql = new AIPS_Data_Management_Import_MySQL();
	}

	public function tearDown(): void {
		if ($this->original_wpdb) {
			$GLOBALS['wpdb'] = $this->original_wpdb;
		} else {
			unset($GLOBALS['wpdb']);
		}
		parent::tearDown();
	}

	public function test_import_security_bypass_delete() {
		global $wpdb;
        $wpdb->queries_executed = [];

		// Create a malicious SQL file content
		// This uses the bypass: command is DELETE (not filtered) and no plugin table name is present
		$malicious_sql = "
			-- AI Post Scheduler Data Export
			DELETE FROM wp_options WHERE option_name = 'siteurl';
		";

		$file_path = sys_get_temp_dir() . '/malicious.sql';
		file_put_contents($file_path, $malicious_sql);

		// Run import
		$result = $this->import_mysql->import($file_path);

		// Cleanup
		unlink($file_path);

		$executed = false;
		foreach ($wpdb->queries_executed as $q) {
			if (strpos($q, "DELETE FROM wp_options") !== false) {
				$executed = true;
				break;
			}
		}

		if ($executed) {
			$this->fail('VULNERABILITY CONFIRMED: Malicious DELETE query was executed.');
		}

        // Assert that we got an error (blocked)
        $this->assertTrue(is_wp_error($result), 'Should return error for blocked query');
        $this->assertEquals('invalid_command', $result->get_error_code(), 'Should be blocked as invalid command');
	}

    public function test_import_security_bypass_drop_view() {
		global $wpdb;
        $wpdb->queries_executed = [];

		// DROP VIEW does not contain TABLE or INSERT
		$malicious_sql = "
			-- AI Post Scheduler Data Export
			DROP VIEW IF EXISTS wp_secret_view;
		";

		$file_path = sys_get_temp_dir() . '/malicious_view.sql';
		file_put_contents($file_path, $malicious_sql);

		// Run import
		$result = $this->import_mysql->import($file_path);

		// Cleanup
		unlink($file_path);

		$executed = false;
		foreach ($wpdb->queries_executed as $q) {
			if (strpos($q, "DROP VIEW") !== false) {
				$executed = true;
				break;
			}
		}

		if ($executed) {
			$this->fail('VULNERABILITY CONFIRMED: Malicious DROP VIEW query was executed.');
		}

        $this->assertTrue(is_wp_error($result), 'Should return error for blocked query');
        $this->assertEquals('invalid_command', $result->get_error_code(), 'Should be blocked as invalid command');
    }

    public function test_import_valid_dump() {
        global $wpdb;
        $wpdb->queries_executed = [];

        // A valid dump sequence
        $valid_sql = "
-- AI Post Scheduler Data Export
--
-- Table structure for table `wp_aips_history`
--

DROP TABLE IF EXISTS `wp_aips_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_aips_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_aips_history`
--

LOCK TABLES `wp_aips_history` WRITE;
/*!40000 ALTER TABLE `wp_aips_history` DISABLE KEYS */;
INSERT INTO `wp_aips_history` VALUES (1,'uuid',123,1,'completed','prompt','title','content','log','', '2023-01-01 00:00:00', '2023-01-01 00:01:00');
/*!40000 ALTER TABLE `wp_aips_history` ENABLE KEYS */;
UNLOCK TABLES;
        ";

        $file_path = sys_get_temp_dir() . '/valid.sql';
        file_put_contents($file_path, $valid_sql);

        // Run import
        $result = $this->import_mysql->import($file_path);

        // Cleanup
        unlink($file_path);

        if (is_wp_error($result)) {
            $this->fail('Valid import failed: ' . $result->get_error_message() . ' (' . $result->get_error_code() . ')');
        }

        $this->assertTrue($result, 'Import should return true on success');

        // Verify queries were executed
        // Note: split_sql_file removes /*! ... */ comments so the SET commands inside might disappear
        // But DROP, CREATE, LOCK, INSERT, UNLOCK should be there.

        $queries = $wpdb->queries_executed;
        $this->assertNotEmpty($queries);

        // Check for specific commands
        $found_drop = false;
        $found_create = false;
        $found_insert = false;
        $found_lock = false;
        $found_unlock = false;

        foreach ($queries as $q) {
            if (strpos($q, 'DROP TABLE') !== false) $found_drop = true;
            if (strpos($q, 'CREATE TABLE') !== false) $found_create = true;
            if (strpos($q, 'INSERT INTO') !== false) $found_insert = true;
            if (strpos($q, 'LOCK TABLES') !== false) $found_lock = true;
            if (strpos($q, 'UNLOCK TABLES') !== false) $found_unlock = true;
        }

        $this->assertTrue($found_drop, 'DROP TABLE should be executed');
        $this->assertTrue($found_create, 'CREATE TABLE should be executed');
        $this->assertTrue($found_insert, 'INSERT INTO should be executed');
        $this->assertTrue($found_lock, 'LOCK TABLES should be executed');
        $this->assertTrue($found_unlock, 'UNLOCK TABLES should be executed');
    }
}
