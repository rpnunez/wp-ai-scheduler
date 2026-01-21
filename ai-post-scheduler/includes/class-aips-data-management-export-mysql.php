<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * MySQL dump export implementation
 */
class AIPS_Data_Management_Export_MySQL extends AIPS_Data_Management_Export {
	
	/**
	 * Get the export format name
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
	 * Get the MIME type for this format
	 * 
	 * @return string
	 */
	public function get_mime_type() {
		return 'application/sql';
	}
	
	/**
	 * Export the data as MySQL dump
	 * 
	 * @return string The exported SQL dump
	 */
	public function export() {
		global $wpdb;
		
		$sql_dump = '';
		
		// Add header comment
		$sql_dump .= "-- AI Post Scheduler Data Export\n";
		$sql_dump .= "-- Generated on: " . gmdate('Y-m-d H:i:s') . " GMT\n";
		$sql_dump .= "-- WordPress Version: " . get_bloginfo('version') . "\n";
		$sql_dump .= "-- Plugin Version: " . AIPS_VERSION . "\n\n";
		
		$sql_dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
		$sql_dump .= "SET time_zone = \"+00:00\";\n\n";
		
		$tables = $this->get_tables();
		
		foreach ($tables as $table_name => $full_table_name) {
			// Check if table exists
			$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
			
			if ($table_exists !== $full_table_name) {
				$sql_dump .= "-- Table $full_table_name does not exist\n\n";
				continue;
			}
			
			$sql_dump .= "-- --------------------------------------------------------\n";
			$sql_dump .= "-- Table structure for table `$full_table_name`\n";
			$sql_dump .= "-- --------------------------------------------------------\n\n";
			
			// Get CREATE TABLE statement - table name is already validated from get_full_table_names()
			$create_table = $wpdb->get_row("SHOW CREATE TABLE `" . esc_sql($full_table_name) . "`", ARRAY_N);
			if ($create_table) {
				$sql_dump .= "DROP TABLE IF EXISTS `" . esc_sql($full_table_name) . "`;\n";
				$sql_dump .= $create_table[1] . ";\n\n";
			}
			
			// Get table data
			$sql_dump .= "-- Dumping data for table `$full_table_name`\n\n";
			
			$rows = $wpdb->get_results("SELECT * FROM `" . esc_sql($full_table_name) . "`", ARRAY_A);
			
			if (!empty($rows)) {
				foreach ($rows as $row) {
					$sql_dump .= $this->generate_insert_statement($full_table_name, $row);
				}
				$sql_dump .= "\n";
			} else {
				$sql_dump .= "-- No data in table\n\n";
			}
		}
		
		return $sql_dump;
	}
	
	/**
	 * Generate an INSERT statement for a row
	 * 
	 * @param string $table_name
	 * @param array $row
	 * @return string
	 */
	private function generate_insert_statement($table_name, $row) {
		global $wpdb;
		
		$columns = array_keys($row);
		$values = array();
		
		foreach ($row as $value) {
			if ($value === null) {
				$values[] = 'NULL';
			} else {
				// Escape the value properly using esc_sql
				$values[] = "'" . esc_sql($value) . "'";
			}
		}
		
		$columns_str = '`' . implode('`, `', array_map('esc_sql', $columns)) . '`';
		$values_str = implode(', ', $values);
		
		return "INSERT INTO `$table_name` ($columns_str) VALUES ($values_str);\n";
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
