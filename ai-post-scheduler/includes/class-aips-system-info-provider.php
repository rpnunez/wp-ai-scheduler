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
	 * @var AIPS_DB_Prune_Service
	 */
	private $db_prune_service;

	/**
	 * @var AIPS_System_Totals_Repository
	 */
	private $totals_repository;

	/**
	 * @param AIPS_DB_Prune_Service|null         $db_prune_service  Optional service override.
	 * @param AIPS_System_Totals_Repository|null $totals_repository Optional repository override.
	 */
	public function __construct($db_prune_service = null, $totals_repository = null) {
		$container = AIPS_Container::get_instance();

		$this->db_prune_service = $db_prune_service ?: ($container->has(AIPS_DB_Prune_Service::class)
			? $container->make(AIPS_DB_Prune_Service::class)
			: AIPS_DB_Prune_Service::instance());

		$this->totals_repository = $totals_repository ?: ($container->has(AIPS_System_Totals_Repository::class)
			? $container->make(AIPS_System_Totals_Repository::class)
			: new AIPS_System_Totals_Repository());
	}

	/**
	 * Get all system info data organized by section.
	 *
	 * @return array<string, array<string, array{label:string, value:string, status:string, details?:array<string>}>>
	 */
	public function get_system_info(): array {
		return array(
			'environment'   => $this->get_environment_info(),
			'plugin'        => $this->get_plugin_info(),
			'totals_counts' => $this->get_totals_info(),
			'database'      => $this->get_database_info(),
			'filesystem'    => $this->get_filesystem_info(),
			'ai'            => $this->get_ai_info(),
			'data_storage'  => $this->get_data_storage_info(),
		);
	}

	/**
	 * Display title for a system info section key.
	 *
	 * @param string $section Section key from get_system_info().
	 * @return string
	 */
	public function get_section_title($section): string {
		$titles = array(
			'ai'            => __('AI', 'ai-post-scheduler'),
			'totals_counts' => __('Totals & Counts', 'ai-post-scheduler'),
		);

		$section = (string) $section;

		return isset($titles[$section]) ? $titles[$section] : ucwords(str_replace(array('_', '-'), ' ', $section));
	}

	/**
	 * Lifetime totals across content generation, authors, AI usage, embeddings,
	 * linking, and content setup.
	 *
	 * @return array<string, array{label:string, value:string, status:string, details?:array<string>}>
	 */
	private function get_totals_info(): array {
		$t = $this->totals_repository->get_totals();

		$history     = $t['history'];
		$gen_status  = $history['generation_by_status'];
		$completed   = isset($gen_status['completed']) ? (int) $gen_status['completed'] : 0;
		$partial     = isset($gen_status['partial']) ? (int) $gen_status['partial'] : 0;
		$failed      = isset($gen_status['failed']) ? (int) $gen_status['failed'] : 0;
		$finished    = $completed + $partial + $failed;
		$success_pct = $finished > 0 ? round((($completed + $partial) / $finished) * 100, 1) : 0;

		$rows = array(
			'generated_posts' => $this->total_row(__('Posts Generated', 'ai-post-scheduler'), $t['generated_posts']),
			'generation_runs' => array(
				'label'  => __('Generation Runs', 'ai-post-scheduler'),
				'value'  => sprintf(
					/* translators: 1: completed runs, 2: partial runs, 3: failed runs, 4: success rate percentage */
					__('%1$s completed · %2$s partial · %3$s failed (%4$s%% success)', 'ai-post-scheduler'),
					number_format_i18n($completed),
					number_format_i18n($partial),
					number_format_i18n($failed),
					number_format_i18n($success_pct, 1)
				),
				'status' => 'info',
			),
			'generation_methods' => $this->breakdown_row(
				__('Generations by Method', 'ai-post-scheduler'),
				array_sum($history['generation_by_method']),
				$history['generation_by_method']
			),
			'history_containers' => $this->breakdown_row(
				__('History Containers', 'ai-post-scheduler'),
				$history['total'],
				$history['by_kind']
			),
			'authors'       => $this->active_row(__('Authors', 'ai-post-scheduler'), $t['authors']),
			'author_topics' => $this->breakdown_row(
				__('Author Topics Generated', 'ai-post-scheduler'),
				$t['author_topics']['total'],
				$t['author_topics']['groups']
			),
			'author_posts'  => $this->total_row(__('Author Posts Generated', 'ai-post-scheduler'), $t['author_posts']),
			'ai_requests'   => $this->total_row(__('AI Requests (All-Time, Logged)', 'ai-post-scheduler'), $t['ai_requests']),
		);

		$rows = array_merge($rows, $this->get_ai_call_counter_rows());

		$rows['ai_assistance']       = $this->total_row(__('AI Assistance Requests', 'ai-post-scheduler'), $t['ai_assistance']);
		$rows['background_ai_calls'] = $this->total_row(__('AI Calls by Background Jobs', 'ai-post-scheduler'), $t['background_ai_calls']);
		$rows['embeddings']          = $this->breakdown_row(
			__('Embeddings', 'ai-post-scheduler'),
			$t['embeddings']['total'],
			$t['embeddings']['groups']
		);
		$rows['relationships']  = $this->total_row(__('Content Relationships', 'ai-post-scheduler'), $t['relationships']);
		$rows['internal_links'] = $this->breakdown_row(
			__('Internal Link Suggestions', 'ai-post-scheduler'),
			$t['internal_links']['total'],
			$t['internal_links']['groups']
		);
		$rows['link_index'] = array(
			'label'  => __('Links Indexed', 'ai-post-scheduler'),
			'value'  => sprintf(
				/* translators: 1: total links, 2: links per type, 3: links inserted by the plugin */
				__('%1$s (%2$s) · %3$s inserted by AIPS', 'ai-post-scheduler'),
				number_format_i18n($t['link_index']['total']),
				$this->format_breakdown($t['link_index']['by_type']),
				number_format_i18n($t['link_index']['inserted_by_aips'])
			),
			'status' => 'info',
		);
		$rows['templates']       = $this->active_row(__('Templates', 'ai-post-scheduler'), $t['templates']);
		$rows['schedules']       = $this->active_row(__('Schedules', 'ai-post-scheduler'), $t['schedules']);
		$rows['campaigns']       = $this->active_row(__('Campaigns', 'ai-post-scheduler'), $t['campaigns']);
		$rows['trending_topics'] = $this->total_row(__('Trending Topics Researched', 'ai-post-scheduler'), $t['trending_topics']);
		$rows['sources']         = $this->total_row(__('Sources', 'ai-post-scheduler'), $t['sources']);
		$rows['source_pages']    = array(
			'label'   => __('Source Pages Fetched', 'ai-post-scheduler'),
			'value'   => sprintf(
				/* translators: 1: fetched source pages, 2: times source content was used */
				__('%1$s pages · used %2$s times', 'ai-post-scheduler'),
				number_format_i18n($t['source_pages']['total']),
				number_format_i18n($t['source_pages']['times_used'])
			),
			'status'  => 'info',
			'details' => $this->breakdown_details($t['source_pages']['by_status']),
		);
		$rows['bulk_jobs'] = array(
			'label'  => __('Bulk Batch Jobs', 'ai-post-scheduler'),
			'value'  => sprintf(
				/* translators: 1: bulk job count, 2: items processed */
				__('%1$s jobs · %2$s items processed', 'ai-post-scheduler'),
				number_format_i18n($t['bulk_jobs']['total']),
				number_format_i18n($t['bulk_jobs']['processed'])
			),
			'status' => 'info',
		);

		return $rows;
	}

	/**
	 * Rows for the per-provider AI call counter (WP AI Connector / AI Engine).
	 *
	 * @return array<string, array{label:string, value:string, status:string, details?:array<string>}>
	 */
	private function get_ai_call_counter_rows(): array {
		$counts    = AIPS_AI_Call_Counter::get_counts();
		$providers = $counts['providers'];

		// Always list the two real providers, even before their first call.
		foreach (array('wp_ai_client', 'meow') as $provider_id) {
			if (!isset($providers[$provider_id])) {
				$providers[$provider_id] = array('total' => 0, 'failed' => 0, 'by_type' => array());
			}
		}

		$since = $counts['since'] > 0
			? sprintf(
				/* translators: %s: date the AI call counter started */
				__('since %s', 'ai-post-scheduler'),
				wp_date(get_option('date_format'), $counts['since'])
			)
			: __('counting starts with the next AI call', 'ai-post-scheduler');

		$rows    = array();
		$by_type = array();

		foreach ($providers as $provider_id => $entry) {
			$total  = isset($entry['total']) ? (int) $entry['total'] : 0;
			$failed = isset($entry['failed']) ? (int) $entry['failed'] : 0;
			$types  = isset($entry['by_type']) && is_array($entry['by_type']) ? $entry['by_type'] : array();

			foreach ($types as $type => $count) {
				$by_type[$type] = (isset($by_type[$type]) ? $by_type[$type] : 0) + (int) $count;
			}

			arsort($types);

			$rows['ai_calls_' . sanitize_key($provider_id)] = array(
				'label'   => sprintf(
					/* translators: %s: AI provider name */
					__('AI Calls (%s)', 'ai-post-scheduler'),
					$this->get_provider_label($provider_id)
				),
				'value'   => sprintf(
					/* translators: 1: total calls, 2: failed calls, 3: "since <date>" note */
					__('%1$s calls · %2$s failed (%3$s)', 'ai-post-scheduler'),
					number_format_i18n($total),
					number_format_i18n($failed),
					$since
				),
				'status'  => 'info',
				'details' => $this->breakdown_details($types),
			);
		}

		arsort($by_type);

		$rows['ai_calls_by_type'] = array(
			'label'  => __('AI Calls by Type', 'ai-post-scheduler'),
			'value'  => empty($by_type) ? __('None recorded yet', 'ai-post-scheduler') : $this->format_breakdown($by_type),
			'status' => 'info',
		);

		return $rows;
	}

	/**
	 * Human label for an AI provider id.
	 *
	 * @param string $provider_id Provider id.
	 * @return string
	 */
	private function get_provider_label($provider_id): string {
		$labels = array(
			'wp_ai_client' => __('WP AI Connector', 'ai-post-scheduler'),
			'meow'         => __('Meow AI Engine', 'ai-post-scheduler'),
		);

		return isset($labels[$provider_id]) ? $labels[$provider_id] : $this->humanize_key($provider_id);
	}

	/**
	 * A row showing a single total.
	 *
	 * @param string $label Row label.
	 * @param int    $total Total.
	 * @return array{label:string, value:string, status:string}
	 */
	private function total_row($label, $total): array {
		return array(
			'label'  => $label,
			'value'  => number_format_i18n((int) $total),
			'status' => 'info',
		);
	}

	/**
	 * A row showing "<active> active of <total>".
	 *
	 * @param string                       $label  Row label.
	 * @param array{total:int, active:int} $counts Counts.
	 * @return array{label:string, value:string, status:string}
	 */
	private function active_row($label, array $counts): array {
		return array(
			'label'  => $label,
			'value'  => sprintf(
				/* translators: 1: active count, 2: total count */
				__('%1$s active of %2$s', 'ai-post-scheduler'),
				number_format_i18n((int) $counts['active']),
				number_format_i18n((int) $counts['total'])
			),
			'status' => 'info',
		);
	}

	/**
	 * A row showing a total with its breakdown inline and in the details panel.
	 *
	 * @param string            $label  Row label.
	 * @param int               $total  Total.
	 * @param array<string,int> $groups Breakdown counts keyed by raw value.
	 * @return array{label:string, value:string, status:string}
	 */
	private function breakdown_row($label, $total, array $groups): array {
		$value = number_format_i18n((int) $total);

		if (!empty($groups)) {
			$value .= ' (' . $this->format_breakdown($groups) . ')';
		}

		return array(
			'label'  => $label,
			'value'  => $value,
			'status' => 'info',
		);
	}

	/**
	 * Format breakdown counts as "Completed 10 · Failed 2".
	 *
	 * @param array<string,int> $groups Breakdown counts keyed by raw value.
	 * @return string
	 */
	private function format_breakdown(array $groups): string {
		if (empty($groups)) {
			return __('none', 'ai-post-scheduler');
		}

		$parts = array();
		foreach ($groups as $key => $count) {
			$parts[] = $this->humanize_key($key) . ' ' . number_format_i18n((int) $count);
		}

		return implode(' · ', $parts);
	}

	/**
	 * Breakdown counts as one "Key: count" line each, for the details panel.
	 *
	 * @param array<string,int> $groups Breakdown counts keyed by raw value.
	 * @return array<string>
	 */
	private function breakdown_details(array $groups): array {
		$lines = array();
		foreach ($groups as $key => $count) {
			$lines[] = $this->humanize_key($key) . ': ' . number_format_i18n((int) $count);
		}

		return $lines;
	}

	/**
	 * Turn a raw slug ("bulk_generate") into a label ("Bulk Generate").
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private function humanize_key($key): string {
		return ucwords(str_replace(array('_', '-'), ' ', (string) $key));
	}

	/**
	 * Gather row count and storage size for every plugin database table, so an
	 * admin can see at a glance how much of each kind of data exists on the site.
	 *
	 * @return array<string, array{label:string, value:string, status:string}>
	 */
	private function get_data_storage_info(): array {
		$tables = $this->db_prune_service->get_table_status_summary();

		$checks       = array();
		$tot_records  = 0;
		$tot_data     = 0;
		$tot_index    = 0;

		foreach ($tables as $table) {
			$short_name = isset($table['short_name']) ? (string) $table['short_name'] : (string) $table['table'];
			$records    = isset($table['records']) ? (int) $table['records'] : 0;
			$data_size  = isset($table['data_size']) ? (int) $table['data_size'] : 0;
			$index_size = isset($table['index_size']) ? (int) $table['index_size'] : 0;
			$overhead   = isset($table['overhead']) ? (int) $table['overhead'] : 0;

			$tot_records += $records;
			$tot_data    += $data_size;
			$tot_index   += $index_size;

			$checks[$short_name] = array(
				'label'  => $short_name,
				'value'  => sprintf(
					/* translators: 1: formatted row count, 2: formatted data size, 3: formatted index size */
					__('%1$s rows · %2$s data · %3$s index', 'ai-post-scheduler'),
					isset($table['formatted_records']) ? $table['formatted_records'] : number_format_i18n($records),
					isset($table['formatted_data_size']) ? $table['formatted_data_size'] : size_format($data_size, 2),
					isset($table['formatted_index_size']) ? $table['formatted_index_size'] : size_format($index_size, 2)
				),
				'status' => ($overhead > 1048576) ? 'warning' : 'info',
			);
		}

		$checks['total'] = array(
			'label'  => __('Total Plugin Storage', 'ai-post-scheduler'),
			'value'  => sprintf(
				/* translators: 1: formatted total row count, 2: formatted total data size, 3: formatted total index size */
				__('%1$s rows · %2$s data · %3$s index', 'ai-post-scheduler'),
				number_format_i18n($tot_records),
				size_format($tot_data, 2),
				size_format($tot_index, 2)
			),
			'status' => 'info',
		);

		return $checks;
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
			$title = $this->get_section_title($section_key);
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
