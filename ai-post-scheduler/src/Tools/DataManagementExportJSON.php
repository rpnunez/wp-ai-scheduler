<?php
namespace AIPS\Tools;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * JSON export implementation (placeholder for future)
 */
class DataManagementExportJSON extends AbstractDataManagementExport {

	public function get_format_name() {
		return 'JSON';
	}

	public function get_file_extension() {
		return 'json';
	}

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
			$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));

			if ($table_exists !== $full_table_name) {
				continue;
			}

			$rows = $wpdb->get_results("SELECT * FROM `" . esc_sql($full_table_name) . "`", ARRAY_A);
			$data['tables'][$table_name] = $rows;
		}

		return wp_json_encode($data, JSON_PRETTY_PRINT);
	}
}
