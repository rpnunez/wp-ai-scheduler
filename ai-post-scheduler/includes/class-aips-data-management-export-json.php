<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * JSON export implementation (placeholder for future)
 */
class AIPS_Data_Management_Export_JSON extends AIPS_Data_Management_Export {
	
	/**
	 * Get the export format name
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
	 * Get the MIME type for this format
	 * 
	 * @return string
	 */
	public function get_mime_type() {
		return 'application/json';
	}
	
	/**
	 * Export the data as JSON
	 * 
	 * @return string The exported JSON data
	 */
	public function export() {
		global $wpdb;
		
		$data = array(
			'version' => AIPS_VERSION,
			'exported_at' => gmdate('Y-m-d H:i:s'),
			'tables' => array(),
		);
		
		$tables = $this->get_tables();
		
		foreach ($tables as $table_name => $full_table_name) {
			// Check if table exists
			$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
			
			if ($table_exists !== $full_table_name) {
				continue;
			}
			
			// Get table data
			$rows = $wpdb->get_results("SELECT * FROM `$full_table_name`", ARRAY_A);
			$data['tables'][$table_name] = $rows;
		}
		
		return wp_json_encode($data, JSON_PRETTY_PRINT);
	}
	
	/**
	 * Perform the export and send to browser
	 */
	public function do_export() {
		$data = $this->export();
		$filename = $this->generate_filename();
		$this->send_download($data, $filename);
	}
}
