<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Base class for data export functionality
 */
abstract class AIPS_Data_Management_Export {
	
	/**
	 * Get the export format name
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
	 * Get the MIME type for this format
	 * 
	 * @return string
	 */
	abstract public function get_mime_type();
	
	/**
	 * Export the data
	 * 
	 * @return string The exported data as a string
	 */
	abstract public function export();
	
	/**
	 * Get all plugin table names
	 * 
	 * @return array
	 */
	protected function get_tables() {
		return AIPS_DB_Manager::get_full_table_names();
	}
	
	/**
	 * Send the export file to the browser for download
	 * 
	 * @param string $data The export data
	 * @param string $filename The filename for the download
	 */
	protected function send_download($data, $filename) {
		// Set headers for file download
		header('Content-Type: ' . $this->get_mime_type());
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($data));
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');
		
		// Output the data
		echo $data;
		exit;
	}
	
	/**
	 * Generate a filename for the export
	 * 
	 * @return string
	 */
	protected function generate_filename() {
		$timestamp = gmdate('Y-m-d_H-i-s');
		return 'aips-export_' . $timestamp . '.' . $this->get_file_extension();
	}
}
