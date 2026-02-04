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
			$query_upper = strtoupper(trim($query));
			
			// Skip if query is empty
			if (empty($query_upper)) {
				continue;
			}
			
			// Extract table name from query
			$has_valid_table = false;
			foreach ($plugin_table_names as $table_name) {
				if (stripos($query, $table_name) !== false) {
					$has_valid_table = true;
					break;
				}
			}
			
			// If query references a table, ensure it's one of ours
			if (!$has_valid_table && 
				(strpos($query_upper, 'TABLE') !== false || 
				 strpos($query_upper, 'INSERT') !== false)) {
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
	 * Split SQL file into individual queries using a character-based tokenizer.
	 * 
	 * @param string $sql_content
	 * @return array
	 */
	private function split_sql_file($sql_content) {
		$queries = array();
		$buffer = '';
		$in_string = false;
		$string_char = ''; // ' or " or `
		$in_comment = false; // -- style
		$in_block_comment = false; // /* style */
		$escaped = false;
		
		$len = strlen($sql_content);
		
		for ($i = 0; $i < $len; $i++) {
			$char = $sql_content[$i];
			$next_char = ($i + 1 < $len) ? $sql_content[$i + 1] : '';

			// Handle escapes in strings
			if ($in_string) {
				if ($escaped) {
					$escaped = false;
				} elseif ($char === '\\') {
					$escaped = true;
				} elseif ($char === $string_char) {
					// Check for double escape (e.g. 'Don''t') - mostly for single quotes
					// MySQL dumps usually use backslash escapes, but handling double quotes is safer
					if ($char === "'" && $next_char === "'") {
						$buffer .= $char; // consume first quote
						$i++; // skip next quote
						// effectively we consumed one quote and we are still in string, but $buffer logic below adds current char
						// wait, if I consume here, I should continue loop
						$buffer .= $next_char;
						continue;
					}
					$in_string = false;
				}
				$buffer .= $char;
				continue;
			}

			// Handle block comments
			if ($in_block_comment) {
				if ($char === '*' && $next_char === '/') {
					$in_block_comment = false;
					$i++; // Skip /
					// We don't append comments to buffer
				}
				continue;
			}

			// Handle line comments
			if ($in_comment) {
				if ($char === "\n") {
					$in_comment = false;
				}
				continue;
			}

			// Check for start of string or identifier
			if ($char === "'" || $char === '"' || $char === '`') {
				$in_string = true;
				$string_char = $char;
				$buffer .= $char;
				continue;
			}

			// Check for start of comments
			if ($char === '-' && $next_char === '-') {
				$in_comment = true;
				$i++; // Skip next -
				continue;
			}

			if ($char === '#' ) {
				$in_comment = true;
				continue;
			}

			if ($char === '/' && $next_char === '*') {
				$in_block_comment = true;
				$i++; // Skip *
				continue;
			}

			// Check for statement terminator
			if ($char === ';') {
				if (!empty(trim($buffer))) {
					$queries[] = trim($buffer);
				}
				$buffer = '';
				continue;
			}

			$buffer .= $char;
		}

		if (!empty(trim($buffer))) {
			$queries[] = trim($buffer);
		}
		
		return $queries;
	}
}
