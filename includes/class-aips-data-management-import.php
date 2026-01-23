<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Base class for data import functionality
 */
abstract class AIPS_Data_Management_Import {
	
	/**
	 * Get the import format name
	 * 
	 * @return string
	 */
	abstract public function get_format_name();
	
	/**
	 * Get the file extension for this format
	 * 
	 * @return string
	 */
	abstract public function get_file_extension();
	
	/**
	 * Validate the uploaded file
	 * 
	 * @param array $file The uploaded file data from $_FILES
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	abstract public function validate_file($file);
	
	/**
	 * Import the data from file
	 * 
	 * @param string $file_path Path to the uploaded file
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	abstract public function import($file_path);
	
	/**
	 * Get all plugin table names
	 * 
	 * @return array
	 */
	protected function get_tables() {
		return AIPS_DB_Manager::get_full_table_names();
	}
	
	/**
	 * Check if file extension is valid
	 * 
	 * @param string $filename
	 * @return bool
	 */
	protected function check_file_extension($filename) {
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return $extension === $this->get_file_extension();
	}
}
