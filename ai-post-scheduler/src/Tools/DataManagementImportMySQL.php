<?php
namespace AIPS\Tools;

use AIPS\Helpers\DBHelper;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * MySQL dump import implementation
 */
class DataManagementImportMySQL extends AbstractDataManagementImport {

	public function get_format_name() {
		return 'MySQL Dump';
	}

	public function get_file_extension() {
		return 'sql';
	}

	public function validate_file($file) {
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return new \WP_Error('upload_error', __('File upload failed.', 'ai-post-scheduler'));
		}

		if (!$this->check_file_extension($file['name'])) {
			return new \WP_Error('invalid_extension', __('Invalid file extension. Expected .sql file.', 'ai-post-scheduler'));
		}

		$max_size = 50 * 1024 * 1024;
		if ($file['size'] > $max_size) {
			return new \WP_Error('file_too_large', __('File is too large. Maximum size is 50MB.', 'ai-post-scheduler'));
		}

		if (!is_readable($file['tmp_name'])) {
			return new \WP_Error('file_not_readable', __('Cannot read uploaded file.', 'ai-post-scheduler'));
		}

		return true;
	}

	/**
	 * Import the data from SQL file
	 *
	 * @param string $file_path Path to the uploaded file
	 * @return bool|\WP_Error True on success, WP_Error on failure
	 */
	public function import($file_path) {
		global $wpdb;

		$sql_content = file_get_contents($file_path);

		if ($sql_content === false) {
			return new \WP_Error('read_error', __('Could not read the SQL file.', 'ai-post-scheduler'));
		}

		if (strpos($sql_content, 'AI Post Scheduler Data Export') === false) {
			return new \WP_Error(
				'invalid_file',
				__('This does not appear to be a valid AI Post Scheduler export file.', 'ai-post-scheduler')
			);
		}

		$queries = $this->split_sql_file($sql_content);

		if (empty($queries)) {
			return new \WP_Error('no_queries', __('No valid SQL queries found in file.', 'ai-post-scheduler'));
		}

		$plugin_tables = DBHelper::get_full_table_names();
		$plugin_table_names = array_values($plugin_tables);

		foreach ($queries as $query) {
			$query_upper = strtoupper(trim($query));

			if (empty($query_upper)) {
				continue;
			}

			$has_valid_table = false;
			foreach ($plugin_table_names as $table_name) {
				if (stripos($query, $table_name) !== false) {
					$has_valid_table = true;
					break;
				}
			}

			if (!$has_valid_table &&
				(strpos($query_upper, 'TABLE') !== false ||
				 strpos($query_upper, 'INSERT') !== false)) {
				return new \WP_Error(
					'invalid_table',
					__('SQL file contains queries for non-plugin tables. For security, only plugin tables can be imported.', 'ai-post-scheduler')
				);
			}
		}

		$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

		$success_count = 0;
		$error_count = 0;
		$last_error = '';

		foreach ($queries as $query) {
			$query = trim($query);

			if (empty($query)) {
				continue;
			}

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
			return new \WP_Error(
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

		$sql_content = preg_replace('/^\s*--.*$/m', '', $sql_content);
		$sql_content = preg_replace('/^\s*#.*$/m', '', $sql_content);
		$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

		$delimiter = ';';
		$tokens = explode($delimiter, $sql_content);

		foreach ($tokens as $token) {
			$token = trim($token);
			if (!empty($token)) {
				$queries[] = $token;
			}
		}

		return $queries;
	}
}
