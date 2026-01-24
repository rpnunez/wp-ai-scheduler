<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * MySQL dump import implementation
 * 
 * SECURITY CONSIDERATIONS:
 * This class executes raw SQL from uploaded files, which inherently carries security risks.
 * The design accepts these risks for the following reasons:
 * 
 * 1. RESTRICTED ACCESS: Only users with 'manage_options' capability (typically administrators) can access this
 * 2. INTENDED USE: This is specifically for restoring plugin backups, similar to phpMyAdmin or other DB tools
 * 3. VALIDATION LAYERS:
 *    - File extension validation (.sql only)
 *    - File size limit (50MB max)
 *    - Export header validation (must be AI Post Scheduler export)
 *    - Table name validation (must reference plugin tables)
 *    - User confirmation dialog with warnings
 * 4. ACCEPTABLE TRADE-OFF: The convenience of backup/restore for administrators outweighs the risk
 *    given the access restrictions
 * 
 * For higher security environments, consider:
 * - Using JSON import/export instead (uses parameterized queries)
 * - Implementing a custom SQL parser for stricter validation
 * - Creating database backups through hosting control panel instead
 */
class AIPS_Data_Management_Import_MySQL extends AIPS_Data_Management_Import {
	
	/**
	 * Get the import format name
	 * 
	 * @return string
	 */
	public function get_format_name() {
		return 'MySQL Dump';
	}
	
	/**
	 * Get the file extension for this format
	 * 
	 * @return string
	 */
	public function get_file_extension() {
		return 'sql';
	}
	
	/**
	 * Validate the uploaded file
	 * 
	 * @param array $file The uploaded file data from $_FILES
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function validate_file($file) {
		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return new WP_Error('upload_error', __('File upload failed.', 'ai-post-scheduler'));
		}
		
		// Check file extension
		if (!$this->check_file_extension($file['name'])) {
			return new WP_Error('invalid_extension', __('Invalid file extension. Expected .sql file.', 'ai-post-scheduler'));
		}
		
		// Check file size (limit to 50MB)
		$max_size = 50 * 1024 * 1024; // 50MB
		if ($file['size'] > $max_size) {
			return new WP_Error('file_too_large', __('File is too large. Maximum size is 50MB.', 'ai-post-scheduler'));
		}
		
		// Check if file is readable
		if (!is_readable($file['tmp_name'])) {
			return new WP_Error('file_not_readable', __('Cannot read uploaded file.', 'ai-post-scheduler'));
		}
		
		return true;
	}
	
	/**
	 * Import the data from SQL file
	 * 
	 * SECURITY NOTE: This method executes raw SQL from uploaded files. This is intended
	 * for restoring backups and is only available to users with 'manage_options' capability.
	 * The risk is acceptable given:
	 * 1. Only administrators can access this
	 * 2. User is warned about irreversibility
	 * 3. This is a standard backup/restore operation
	 * 
	 * @param string $file_path Path to the uploaded file
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function import($file_path) {
		global $wpdb;
		
		// Read the SQL file
		$sql_content = file_get_contents($file_path);
		
		if ($sql_content === false) {
			return new WP_Error('read_error', __('Could not read the SQL file.', 'ai-post-scheduler'));
		}
		
		// Validate that the SQL file appears to be from this plugin
		if (strpos($sql_content, 'AI Post Scheduler Data Export') === false) {
			return new WP_Error(
				'invalid_file',
				__('This does not appear to be a valid AI Post Scheduler export file.', 'ai-post-scheduler')
			);
		}
		
		// Split the SQL file into individual queries
		$queries = $this->split_sql_file($sql_content);
		
		if (empty($queries)) {
			return new WP_Error('no_queries', __('No valid SQL queries found in file.', 'ai-post-scheduler'));
		}
		
		// Validate queries only affect plugin tables
		$plugin_tables = AIPS_DB_Manager::get_full_table_names();
		$plugin_table_names = array_values($plugin_tables);
		
		foreach ($queries as $query) {
			$result = $this->is_valid_query($query, $plugin_table_names);
            if (is_wp_error($result)) {
                return $result;
            }
		}
		
		// Execute each query
		$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
		
		$success_count = 0;
		$error_count = 0;
		$last_error = '';
		
		foreach ($queries as $query) {
			$query = trim($query);
			
			// Skip empty queries
			if (empty($query)) {
				continue;
			}
			
			// Execute the query
			$result = $wpdb->query($query);
			
			if ($result === false) {
				$error_count++;
				$last_error = $wpdb->last_error;
			} else {
				$success_count++;
			}
		}
		
		$wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
		
		if ($error_count > 0) {
			return new WP_Error(
				'import_errors',
				sprintf(
					__('Import completed with %d successful queries and %d errors. Last error: %s', 'ai-post-scheduler'),
					$success_count,
					$error_count,
					$last_error
				)
			);
		}
		
		return true;
	}
	
	/**
	 * Validate a single SQL query
     *
     * @param string $query The SQL query
     * @param array $allowed_tables List of allowed table names
     * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
    private function is_valid_query($query, $allowed_tables) {
        $query = trim($query);
        if (empty($query)) {
            return true;
        }

        // Allowed commands
        $allowed_commands = array('INSERT', 'CREATE', 'DROP', 'LOCK', 'UNLOCK', 'SET', 'ALTER');
        $command = strtoupper(strtok($query, ' '));

        if (!in_array($command, $allowed_commands)) {
             return new WP_Error('invalid_command', sprintf(__('SQL command "%s" is not allowed.', 'ai-post-scheduler'), $command));
        }

        // Commands that don't need table validation
        if (in_array($command, array('UNLOCK', 'SET'))) {
            return true;
        }

        // Extract table name
        $table_name = '';
        $pattern = '';

        if ($command === 'INSERT') {
            // INSERT [IGNORE] [INTO] table ...
            $pattern = '/^INSERT\s+(?:IGNORE\s+)?(?:INTO\s+)?`?(\w+)`?/i';
        } elseif ($command === 'CREATE') {
            // CREATE TABLE [IF NOT EXISTS] table ...
            $pattern = '/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i';
        } elseif ($command === 'DROP') {
             // DROP TABLE [IF EXISTS] table ...
            $pattern = '/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i';
        } elseif ($command === 'LOCK') {
            // LOCK TABLES table ...
            $pattern = '/^LOCK\s+TABLES\s+`?(\w+)`?/i';
        } elseif ($command === 'ALTER') {
             // ALTER TABLE table ...
             $pattern = '/^ALTER\s+TABLE\s+`?(\w+)`?/i';
        }

        if (preg_match($pattern, $query, $matches)) {
            $table_name = $matches[1];
        } else {
             return new WP_Error('parse_error', __('Could not parse table name from query.', 'ai-post-scheduler'));
        }

        // Verify table is allowed
        // We check if the extracted table name matches one of the full plugin table names
        // Note: Export might include prefix or not depending on settings, but usually includes full prefix in dump.
        // Assuming dump includes full prefix.

        $is_allowed = false;
        foreach ($allowed_tables as $allowed) {
            // Precise match
            if ($table_name === $allowed) {
                $is_allowed = true;
                break;
            }
        }

        if (!$is_allowed) {
            return new WP_Error('forbidden_table', sprintf(__('Access to table "%s" is not allowed.', 'ai-post-scheduler'), $table_name));
        }

        return true;
    }

	/**
	 * Split SQL file into individual queries
	 * 
	 * @param string $sql_content
	 * @return array
	 */
	private function split_sql_file($sql_content) {
		// Remove comments
		$sql_content = preg_replace('/--[^\n]*\n/', "\n", $sql_content);
		$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
		
		// Split by semicolon
		$queries = explode(';', $sql_content);
		
		// Filter out empty queries
		$queries = array_filter($queries, function($query) {
			return !empty(trim($query));
		});
		
		return $queries;
	}
}
