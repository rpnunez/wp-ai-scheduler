<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_System_Info_Provider
 *
 * Gathers WordPress environment, server settings, PHP extensions, database schemas,
 * filesystem permissions, and AI Engine plugin configurations for the System Info
 * diagnostics tab, and compiles clean Markdown reports for technical support.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.1
 */
class AIPS_System_Info_Provider {

	/**
	 * Get all system info data organized by section.
	 *
	 * @return array<string, array<string, array{label:string, value:string, status:string, details?:array<string>}>>
	 */
	public function get_system_info(): array {
		return array(
			'environment' => $this->get_environment_info(),
			'plugin'      => $this->get_plugin_info(),
			'database'    => $this->get_database_info(),
			'filesystem'  => $this->get_filesystem_info(),
			'ai'          => $this->get_ai_info(),
		);
	}

	/**
	 * Gather WordPress and Server environment specifications.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_environment_info(): array {
		global $wp_version;

		$php_version = phpversion();
		$php_status  = version_compare($php_version, '8.2', '>=') ? 'ok' : 'warning';

		$mem_limit = ini_get('memory_limit');
		$max_exec  = ini_get('max_execution_time');

		$curl_info    = function_exists('curl_version') ? curl_version() : array();
		$curl_version = !empty($curl_info['version']) ? $curl_info['version'] : __('Not Available', 'ai-post-scheduler');
		$ssl_version  = !empty($curl_info['ssl_version']) ? $curl_info['ssl_version'] : (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : __('Unknown', 'ai-post-scheduler'));

		return array(
			'wp_version' => array(
				'label'  => __('WordPress Version', 'ai-post-scheduler'),
				'value'  => $wp_version . (is_multisite() ? ' (Multisite)' : ''),
				'status' => 'ok',
			),
			'site_url' => array(
				'label'  => __('Site URL', 'ai-post-scheduler'),
				'value'  => site_url(),
				'status' => 'info',
			),
			'home_url' => array(
				'label'  => __('Home URL', 'ai-post-scheduler'),
				'value'  => home_url(),
				'status' => 'info',
			),
			'php_version' => array(
				'label'  => __('PHP Version', 'ai-post-scheduler'),
				'value'  => $php_version . ' (' . (PHP_INT_SIZE * 8) . '-bit)',
				'status' => $php_status,
			),
			'web_server' => array(
				'label'  => __('Web Server', 'ai-post-scheduler'),
				'value'  => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : __('Unknown', 'ai-post-scheduler'),
				'status' => 'info',
			),
			'php_memory_limit' => array(
				'label'  => __('PHP Memory Limit', 'ai-post-scheduler'),
				'value'  => $mem_limit ? $mem_limit : __('Unlimited', 'ai-post-scheduler'),
				'status' => 'info',
			),
			'wp_memory_limit' => array(
				'label'  => __('WP Memory Limit', 'ai-post-scheduler'),
				'value'  => WP_MEMORY_LIMIT,
				'status' => 'info',
			),
			'max_execution_time' => array(
				'label'  => __('PHP Max Execution Time', 'ai-post-scheduler'),
				'value'  => ($max_exec ? $max_exec . 's' : __('Unlimited', 'ai-post-scheduler')),
				'status' => 'info',
			),
			'curl_version' => array(
				'label'  => __('cURL Version', 'ai-post-scheduler'),
				'value'  => $curl_version,
				'status' => function_exists('curl_version') ? 'ok' : 'error',
			),
			'ssl_version' => array(
				'label'  => __('OpenSSL / SSL', 'ai-post-scheduler'),
				'value'  => $ssl_version,
				'status' => 'ok',
			),
		);
	}

	/**
	 * Gather plugin configuration and DB version info.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_plugin_info(): array {
		$config          = AIPS_Config::get_instance();
		$db_version_raw  = $config->get_option('aips_db_version');
		$db_version      = is_scalar($db_version_raw) ? trim((string) $db_version_raw) : 'Unknown';
		$db_match        = version_compare($db_version, AIPS_VERSION, '==');

		$dev_mode        = (bool) $config->get_option('aips_developer_mode');
		$telemetry       = (bool) $config->get_option('aips_enable_telemetry');
		$cache_enabled   = $config->is_cache_system_enabled();

		return array(
			'version' => array(
				'label'  => __('Plugin Version', 'ai-post-scheduler'),
				'value'  => AIPS_VERSION,
				'status' => 'ok',
			),
			'db_version' => array(
				'label'   => __('Database Version', 'ai-post-scheduler'),
				'value'   => empty($db_version) ? __('Unknown', 'ai-post-scheduler') : $db_version,
				'status'  => $db_match ? 'ok' : 'warning',
				'details' => $db_match ? array() : array(
					sprintf(__('Stored: %s', 'ai-post-scheduler'), $db_version),
					sprintf(__('Expected: %s', 'ai-post-scheduler'), AIPS_VERSION),
				),
			),
			'cache_system' => array(
				'label'  => __('Cache Subsystem', 'ai-post-scheduler'),
				'value'  => $cache_enabled ? __('Enabled', 'ai-post-scheduler') : __('Disabled', 'ai-post-scheduler'),
				'status' => 'info',
			),
			'telemetry' => array(
				'label'  => __('Telemetry Collection', 'ai-post-scheduler'),
				'value'  => $telemetry ? __('Enabled', 'ai-post-scheduler') : __('Disabled', 'ai-post-scheduler'),
				'status' => 'info',
			),
			'dev_mode' => array(
				'label'  => __('Developer Mode', 'ai-post-scheduler'),
				'value'  => $dev_mode ? __('Active', 'ai-post-scheduler') : __('Inactive', 'ai-post-scheduler'),
				'status' => 'info',
			),
		);
	}

	/**
	 * Gather database tables status and counts.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_database_info(): array {
		global $wpdb;

		$tables          = AIPS_DB_Manager::get_expected_columns();
		$database_tables = $wpdb->get_col('SHOW TABLES');
		$table_lookup    = array_fill_keys(array_map('strtolower', (array) $database_tables), true);

		$total_tables   = count($tables);
		$ok_count       = 0;
		$missing_tables = array();
		$missing_cols   = array();

		foreach ($tables as $table_name => $columns) {
			$full_table_name = $wpdb->prefix . $table_name;
			$table_exists    = isset($table_lookup[strtolower($full_table_name)]);

			if (!$table_exists) {
				$missing_tables[] = $table_name;
				continue;
			}

			$escaped_table   = str_replace('`', '``', $full_table_name);
			$db_columns      = $wpdb->get_results("SHOW COLUMNS FROM `{$escaped_table}`", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$db_column_names = is_array($db_columns) ? array_column($db_columns, 'Field') : array();

			$col_diff = array_diff($columns, $db_column_names);
			if (!empty($col_diff)) {
				$missing_cols[$table_name] = $col_diff;
			} else {
				$ok_count++;
			}
		}

		$db_status = ($ok_count === $total_tables) ? 'ok' : 'error';
		$db_details = array();
		if (!empty($missing_tables)) {
			$db_details[] = sprintf(__('Missing tables: %s', 'ai-post-scheduler'), implode(', ', $missing_tables));
		}
		if (!empty($missing_cols)) {
			foreach ($missing_cols as $t => $cols) {
				$db_details[] = sprintf(__('Table %1$s missing: %2$s', 'ai-post-scheduler'), $t, implode(', ', $cols));
			}
		}

		return array(
			'mysql_version' => array(
				'label'  => __('MySQL / MariaDB Version', 'ai-post-scheduler'),
				'value'  => $wpdb->db_version(),
				'status' => 'ok',
			),
			'db_prefix' => array(
				'label'  => __('Table Prefix', 'ai-post-scheduler'),
				'value'  => $wpdb->prefix,
				'status' => 'info',
			),
			'db_charset' => array(
				'label'  => __('Database Charset / Collate', 'ai-post-scheduler'),
				'value'  => ($wpdb->charset ? $wpdb->charset : 'utf8') . ' / ' . ($wpdb->collate ? $wpdb->collate : 'default'),
				'status' => 'info',
			),
			'plugin_tables' => array(
				'label'   => __('Plugin Database Schema', 'ai-post-scheduler'),
				'value'   => sprintf(__('%1$d of %2$d tables verified OK', 'ai-post-scheduler'), $ok_count, $total_tables),
				'status'  => $db_status,
				'details' => $db_details,
			),
		);
	}

	/**
	 * Gather filesystem and permission info.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_filesystem_info(): array {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/aips-logs';

		$upload_writable = wp_is_writable($upload_dir['basedir']);
		$log_exists      = file_exists($log_dir);
		$log_writable    = $log_exists && wp_is_writable($log_dir);

		return array(
			'upload_dir' => array(
				'label'  => __('Upload Directory', 'ai-post-scheduler'),
				'value'  => $upload_dir['basedir'] . ($upload_writable ? ' (Writable)' : ' (Not Writable)'),
				'status' => $upload_writable ? 'ok' : 'error',
			),
			'log_dir' => array(
				'label'  => __('Plugin Log Directory', 'ai-post-scheduler'),
				'value'  => $log_exists ? ($log_writable ? __('Writable', 'ai-post-scheduler') : __('Not Writable', 'ai-post-scheduler')) : __('Created on first log entry', 'ai-post-scheduler'),
				'status' => (!$log_exists || $log_writable) ? 'ok' : 'error',
			),
		);
	}

	/**
	 * Gather AI engine and provider specs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_ai_info(): array {
		$ai_engine_active = class_exists('Meow_MWAI_Core');
		$active_provider  = AIPS_AI_Provider_Factory::create();
		$provider_ready   = $active_provider->is_available();

		return array(
			'ai_provider' => array(
				'label'   => __('Active AI Provider', 'ai-post-scheduler'),
				'value'   => $active_provider->get_label(),
				'status'  => $provider_ready ? 'ok' : 'error',
				'details' => $provider_ready ? array() : array($active_provider->get_unavailable_reason()),
			),
			'ai_engine_plugin' => array(
				'label'  => __('Meow Apps AI Engine Plugin', 'ai-post-scheduler'),
				'value'  => $ai_engine_active ? __('Installed & Active', 'ai-post-scheduler') : __('Not Active', 'ai-post-scheduler'),
				'status' => $ai_engine_active ? 'ok' : 'info',
			),
		);
	}

	/**
	 * Generate a formatted Markdown text report suitable for copy-pasting to support or GitHub.
	 *
	 * @return string
	 */
	public function get_markdown_report(): string {
		$data = $this->get_system_info();

		$output = "### AI Post Scheduler — System Info Report\n\n";
		$output .= "Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

		foreach ($data as $section_key => $section_data) {
			$title = ucwords(str_replace(array('_', '-'), ' ', $section_key));
			$output .= "#### " . $title . "\n";
			$output .= "| Setting | Value | Status |\n";
			$output .= "| :--- | :--- | :--- |\n";

			foreach ($section_data as $item) {
				$label = str_replace('|', '\|', $item['label']);
				$value = str_replace('|', '\|', $item['value']);
				$status = strtoupper($item['status']);
				$output .= "| {$label} | {$value} | {$status} |\n";
			}
			$output .= "\n";
		}

		return trim($output);
	}
}
