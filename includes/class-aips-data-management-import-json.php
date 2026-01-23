<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * JSON import implementation (placeholder for future)
 */
class AIPS_Data_Management_Import_JSON extends AIPS_Data_Management_Import {
	
	/**
	 * Get the import format name
	 * 
	 * @return string
	 */
	public function get_format_name() {
		return 'JSON';
	}
	
	/**
	 * Get the file extension for this format
	 * 
	 * @return string
	 */
	public function get_file_extension() {
		return 'json';
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
			return new WP_Error('invalid_extension', __('Invalid file extension. Expected .json file.', 'ai-post-scheduler'));
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
	 * Import the data from JSON file
	 * 
	 * @param string $file_path Path to the uploaded file
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function import($file_path) {
		global $wpdb;
		
		// Read the JSON file
		$json_content = file_get_contents($file_path);
		
		if ($json_content === false) {
			return new WP_Error('read_error', __('Could not read the JSON file.', 'ai-post-scheduler'));
		}
		
		// Parse JSON
		$data = json_decode($json_content, true);
		
		if ($data === null) {
			return new WP_Error('parse_error', __('Invalid JSON format.', 'ai-post-scheduler'));
		}
		
		// Validate data structure
		if (!isset($data['tables']) || !is_array($data['tables'])) {
			return new WP_Error('invalid_structure', __('Invalid data structure in JSON file.', 'ai-post-scheduler'));
		}
		
		$tables = $this->get_tables();
		$success_count = 0;
		$error_count = 0;
		
		// Disable foreign key checks
		$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
		
		foreach ($data['tables'] as $table_name => $rows) {
			if (!isset($tables[$table_name])) {
				continue; // Skip unknown tables
			}
			
			$full_table_name = $tables[$table_name];
			
			// Truncate table first - table name is already validated from get_full_table_names()
			$wpdb->query("TRUNCATE TABLE `" . esc_sql($full_table_name) . "`");
			
			// Insert rows
			foreach ($rows as $row) {
				$result = $wpdb->insert($full_table_name, $row);
				if ($result === false) {
					$error_count++;
				} else {
					$success_count++;
				}
			}
		}
		
		$wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
		
		if ($error_count > 0) {
			return new WP_Error(
				'import_errors',
				sprintf(
					__('Import completed with %d successful inserts and %d errors.', 'ai-post-scheduler'),
					$success_count,
					$error_count
				)
			);
		}
		
		return true;
	}
}
