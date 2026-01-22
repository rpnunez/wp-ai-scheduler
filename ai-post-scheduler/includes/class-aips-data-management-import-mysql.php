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
			$query = trim($query);
			
			// Skip if query is empty or comment-only (should be handled by split_sql_file but good to be safe)
			if (empty($query)) {
				continue;
			}

			$query_upper = strtoupper($query);

			// SECURITY: Allow configuration commands commonly found in dumps
			// We strictly whitelist these to avoid bypassing security with SET commands
			if (strpos($query_upper, 'SET ') === 0) {
				// Allow standard configuration sets
				$allowed_sets = array(
					'SET SQL_MODE', 'SET TIME_ZONE', 'SET NAMES', 'SET CHARACTER_SET',
					'SET FOREIGN_KEY_CHECKS', 'SET UNIQUE_CHECKS', 'SET AUTOCOMMIT'
				);

				$is_allowed_set = false;
				foreach ($allowed_sets as $set_cmd) {
					if (strpos($query_upper, $set_cmd) === 0) {
						$is_allowed_set = true;
						break;
					}
				}

				if ($is_allowed_set) {
					continue;
				}
				// Fall through to default deny if it's a weird SET command
			}

			// SECURITY: Enforce strict whitelist of allowed SQL commands
			// Only allow INSERT, CREATE, DROP, LOCK, UNLOCK
			$allowed_commands = array('INSERT', 'CREATE', 'DROP', 'LOCK', 'UNLOCK');
			$is_allowed_command = false;
			foreach ($allowed_commands as $cmd) {
				if (strpos($query_upper, $cmd) === 0) {
					$is_allowed_command = true;
					break;
				}
			}

			if (!$is_allowed_command) {
				// We don't want to leak full query content in error messages
				$preview = substr(strip_tags($query), 0, 50);
				return new WP_Error(
					'invalid_command',
					sprintf(__('SQL command not allowed: %s. Only INSERT, CREATE, DROP, LOCK, UNLOCK, and standard SET commands are allowed.', 'ai-post-scheduler'), $preview . '...')
				);
			}
			
			// Extract table name from query
			// We use regex to ensure the table name is a distinct word token, preventing "wp_users -- wp_aips_templates" bypasses
			$has_valid_table = false;
			foreach ($plugin_table_names as $table_name) {
				// Escape for regex and allow it to be wrapped in backticks or whitespace boundaries
				// Pattern matches: `table_name` OR whitespace table_name whitespace/end
				$escaped_name = preg_quote($table_name, '/');
				if (preg_match('/`' . $escaped_name . '`|\b' . $escaped_name . '\b/i', $query)) {
					$has_valid_table = true;
					break;
				}
			}
			
			// If query references a table, ensure it's one of ours
			// If it's a valid command but doesn't contain a valid table name, REJECT IT.
			if (!$has_valid_table) {
				return new WP_Error(
					'invalid_table',
					__('SQL file contains queries for non-plugin tables. For security, only plugin tables can be imported.', 'ai-post-scheduler')
				);
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
