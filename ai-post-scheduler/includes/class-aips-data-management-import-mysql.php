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
		
		// Validate queries
		$plugin_tables = AIPS_DB_Manager::get_full_table_names();
		$plugin_table_names = array_values($plugin_tables);
		
		// Create regex for allowed table names (exact matches only)
		$tables_regex_parts = array();
		foreach ($plugin_table_names as $name) {
			$quoted = preg_quote($name, '/');
			$tables_regex_parts[] = $quoted;
			$tables_regex_parts[] = '`' . $quoted . '`';
		}
		$allowed_tables_regex = implode('|', $tables_regex_parts);

		foreach ($queries as $query) {
			$query = trim($query);
			
			if (empty($query)) {
				continue;
			}
			
			$is_valid = false;

			// SET command (mostly safe, used for timezone/encoding)
			if (preg_match('/^SET\s+/i', $query)) {
				$is_valid = true;
			}
			// UNLOCK TABLES
			elseif (preg_match('/^UNLOCK\s+TABLES/i', $query)) {
				$is_valid = true;
			}
			// DROP TABLE
			// Ensure strict match: DROP TABLE [IF EXISTS] table_name;
			elseif (preg_match('/^DROP\s+TABLE\s+(IF\s+EXISTS\s+)?(' . $allowed_tables_regex . ')\s*;?$/i', $query)) {
				$is_valid = true;
			}
			// CREATE TABLE
			// Must be followed by (
			elseif (preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(' . $allowed_tables_regex . ')\s*\(/i', $query)) {
				$is_valid = true;
			}
			// INSERT INTO
			// Must use VALUES syntax
			elseif (preg_match('/^INSERT\s+INTO\s+(' . $allowed_tables_regex . ')\s+.*VALUES\s*\(/is', $query)) {
				$is_valid = true;
			}
			
			if (!$is_valid) {
				return new WP_Error(
					'invalid_query',
					sprintf(__('Invalid query or unauthorized table access: %s', 'ai-post-scheduler'), substr($query, 0, 100))
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
		$queries = array();
		$buffer = '';
		$in_string = false;
		$string_char = '';
		$in_comment_line = false;
		$in_comment_block = false;
		$len = strlen($sql_content);
		
		for ($i = 0; $i < $len; $i++) {
			$char = $sql_content[$i];
			$next_char = ($i + 1 < $len) ? $sql_content[$i + 1] : '';

			// Handle comments
			if (!$in_string && !$in_comment_block && !$in_comment_line) {
				if ($char === '-' && $next_char === '-') {
					$in_comment_line = true;
					$i++; // Skip next char
					continue;
				}
				if ($char === '/' && $next_char === '*') {
					$in_comment_block = true;
					$i++; // Skip next char
					continue;
				}
			}

			if ($in_comment_line) {
				if ($char === "\n") {
					$in_comment_line = false;
					// Don't add newline to buffer to keep queries clean
				}
				continue;
			}

			if ($in_comment_block) {
				if ($char === '*' && $next_char === '/') {
					$in_comment_block = false;
					$i++;
				}
				continue;
			}

			// Handle strings
			if ($in_string) {
				// Handle escaping with backslash (standard for WP/MySQL dump)
				if ($char === '\\') {
					$buffer .= $char;
					$i++;
					if ($i < $len) {
						$buffer .= $sql_content[$i];
					}
					continue;
				}

				if ($char === $string_char) {
					$in_string = false;
				}
			} elseif ($char === "'" || $char === '"' || $char === '`') {
				$in_string = true;
				$string_char = $char;
			}

			// Handle statement end
			if ($char === ';' && !$in_string) {
				if (trim($buffer) !== '') {
					$queries[] = trim($buffer);
				}
				$buffer = '';
			} else {
				$buffer .= $char;
			}
		}
		
		if (trim($buffer) !== '') {
			$queries[] = trim($buffer);
		}
		
		return $queries;
	}
}
