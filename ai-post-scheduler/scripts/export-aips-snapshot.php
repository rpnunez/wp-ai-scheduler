<?php
/**
 * AIPS Configuration Snapshot Export
 *
 * Exports the AIPS configuration (settings, templates, schedules, authors,
 * voices, structures, sections, slices, sources, campaigns, image configs,
 * affiliate rules), the categories and users they reference, and 90-day
 * generation statistics, into one zip file. It is the input for rebuilding
 * scripts/setup-devstacktips-content.php against the live site.
 *
 * Read-only: runs SELECT queries only and changes nothing on the site.
 *
 * Left out on purpose: post content, generation logs, embeddings, cache,
 * password hashes, emails, and credentials. Settings and fields whose names
 * look like credentials (key, secret, token, password, ...) are replaced
 * with "[REDACTED]", including inside serialized/JSON values.
 *
 * Usage (CLI only):
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/export-aips-snapshot.php
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/export-aips-snapshot.php /path/to/output-dir
 *
 * The default output directory is the home directory of the user running WP-CLI
 * (falling back to the system temp directory), never inside the web root.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	die('Direct access not permitted.');
}

if (!defined('WP_CLI') || !WP_CLI) {
	die('This script must be run with WP-CLI: wp eval-file <path>' . PHP_EOL);
}

/**
 * Class AIPS_Snapshot_Exporter
 */
class AIPS_Snapshot_Exporter {

	/**
	 * Replacement for redacted values.
	 */
	private const REDACTED = '[REDACTED]';

	/**
	 * Name fragments that mark a setting or field as a credential.
	 */
	private const SECRET_PATTERN = '/(api[_-]?key|access[_-]?key|secret|token(?!s)|password|passwd|credential|service[_-]?account|private[_-]?key|auth[_-]?key|bearer|license)/i';

	/**
	 * Settings left out entirely (large state blobs or credentials).
	 */
	private const EXCLUDED_OPTIONS = array(
		'aips_log_secret',
		'aips_gsc_service_account',
		'aips_unsplash_access_key',
		'aips_post_clusters',
		'aips_duplicate_dismissed',
		'aips_duplicate_excluded_post_ids',
	);

	/**
	 * Config tables exported in full (table suffix => columns to drop).
	 */
	private const CONFIG_TABLES = array(
		'aips_campaigns'             => array(),
		'aips_templates'             => array(),
		'aips_schedule'              => array('run_state', 'batch_progress'),
		'aips_authors'               => array(),
		'aips_voices'                => array(),
		'aips_article_structures'    => array(),
		'aips_prompt_sections'       => array(),
		'aips_post_slices'           => array(),
		'aips_sources'               => array(),
		'aips_source_group_terms'    => array(),
		'aips_post_images'           => array(),
		'aips_affiliate_links'       => array('cta_html'),
		'aips_taxonomy'              => array(),
	);

	/**
	 * Number of seconds covered by the statistics queries.
	 */
	private const STATS_WINDOW = 90 * DAY_IN_SECONDS;

	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $work_dir;

	/** @var string */
	private $output_dir;

	/** @var array Summary lines for README / console. */
	private $summary = array();

	/** @var int */
	private $redaction_count = 0;

	/**
	 * @param string $output_dir Directory the zip is written to.
	 */
	public function __construct($output_dir) {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->output_dir = rtrim($output_dir, '/\\');
	}

	/**
	 * Run the export.
	 *
	 * @return void
	 */
	public function run() {
		if (!is_dir($this->output_dir) || !is_writable($this->output_dir)) {
			WP_CLI::error("Output directory is not writable: {$this->output_dir}");
		}

		if ($this->is_inside_web_root($this->output_dir)) {
			WP_CLI::error("Refusing to write inside the WordPress directory ({$this->output_dir}). Pass a directory outside it.");
		}

		$stamp = gmdate('Ymd-His');
		$this->work_dir = $this->output_dir . DIRECTORY_SEPARATOR . 'aips-snapshot-' . $stamp;

		if (!wp_mkdir_p($this->work_dir)) {
			WP_CLI::error("Could not create {$this->work_dir}");
		}

		WP_CLI::log('Exporting AIPS snapshot to ' . $this->work_dir);

		$this->export_environment();
		$this->export_settings();
		$this->export_config_tables();
		$this->export_categories();
		$this->export_users();
		$this->export_topic_pipeline();
		$this->export_history_stats();
		$this->export_post_status_counts();
		$this->export_ai_edit_usage();
		$this->export_table_sizes();
		$this->write_readme();

		$zip_path = $this->zip_work_dir();

		WP_CLI::log('');
		foreach ($this->summary as $line) {
			WP_CLI::log('  ' . $line);
		}
		WP_CLI::log('');
		WP_CLI::log("Redacted values: {$this->redaction_count}");

		if ($zip_path) {
			WP_CLI::success("Snapshot written to: {$zip_path}");
			WP_CLI::log('Download that zip and send it over. You can delete it from the server afterwards.');
		} else {
			WP_CLI::success("Snapshot written to folder: {$this->work_dir}");
			WP_CLI::log('ZipArchive is not available, so zip that folder yourself (e.g. tar czf aips-snapshot.tgz <folder>) and send it over.');
		}
	}

	/* ------------------------------------------------------------------ */
	/* Sections                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Site, WordPress, PHP and plugin version fingerprint.
	 */
	private function export_environment() {
		$plugin_version = defined('AIPS_VERSION') ? AIPS_VERSION : null;

		if (!$plugin_version && function_exists('get_plugins')) {
			foreach (get_plugins() as $file => $data) {
				if (strpos($file, 'ai-post-scheduler') === 0) {
					$plugin_version = $data['Version'];
					break;
				}
			}
		}

		$data = array(
			'exported_at_utc' => gmdate('c'),
			'site_url'        => home_url(),
			'timezone'        => wp_timezone_string(),
			'wp_version'      => get_bloginfo('version'),
			'php_version'     => PHP_VERSION,
			'mysql_version'   => $this->wpdb->db_version(),
			'table_prefix'    => $this->wpdb->prefix,
			'aips_version'    => $plugin_version,
			'aips_db_version' => get_option('aips_db_version'),
			'active_theme'    => wp_get_theme()->get('Name'),
			'stats_window_days' => self::STATS_WINDOW / DAY_IN_SECONDS,
		);

		$this->write_json('00_environment.json', $data);
		$this->summary[] = sprintf('AIPS version: %s (DB %s)', $plugin_version ?: 'unknown', $data['aips_db_version'] ?: 'unknown');
	}

	/**
	 * Every aips_* option, unserialized, with credentials redacted.
	 */
	private function export_settings() {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$this->wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
				$this->wpdb->esc_like('aips_') . '%'
			),
			ARRAY_A
		);

		$settings = array();
		$skipped = array();

		foreach ((array) $rows as $row) {
			$name = $row['option_name'];

			if (in_array($name, self::EXCLUDED_OPTIONS, true)) {
				$skipped[] = $name;
				continue;
			}

			$value = maybe_unserialize($row['option_value']);

			if (preg_match(self::SECRET_PATTERN, $name)) {
				$value = ($value === '' || $value === null || $value === false) ? $value : self::REDACTED;
				if ($value === self::REDACTED) {
					$this->redaction_count++;
				}
			} else {
				$value = $this->redact_value($value);
			}

			$settings[$name] = array(
				'value'    => $value,
				'autoload' => $row['autoload'],
			);
		}

		$this->write_json('01_settings.json', array(
			'excluded_options' => $skipped,
			'settings'         => $settings,
		));

		$this->summary[] = sprintf('Settings: %d (%d excluded)', count($settings), count($skipped));
	}

	/**
	 * Full rows of every config table that exists.
	 */
	private function export_config_tables() {
		$index = 10;

		foreach (self::CONFIG_TABLES as $suffix => $drop_columns) {
			$table = $this->wpdb->prefix . $suffix;

			if (!$this->table_exists($table)) {
				$this->summary[] = sprintf('%s: table missing (skipped)', $suffix);
				continue;
			}

			$rows = $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY 1", ARRAY_A);
			$rows = is_array($rows) ? $rows : array();

			foreach ($rows as &$row) {
				foreach ($drop_columns as $column) {
					unset($row[$column]);
				}
				$row = $this->redact_row($row);
			}
			unset($row);

			$this->write_json(sprintf('%02d_%s.json', $index, $suffix), $rows);
			$this->summary[] = sprintf('%s: %d rows', $suffix, count($rows));
			$index++;
		}
	}

	/**
	 * Categories (and tags in use) so IDs on templates/authors resolve to names.
	 */
	private function export_categories() {
		$rows = $this->wpdb->get_results(
			"SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.count
			 FROM {$this->wpdb->terms} t
			 JOIN {$this->wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			 WHERE tt.taxonomy = 'category' OR (tt.taxonomy = 'post_tag' AND tt.count > 0)
			 ORDER BY tt.taxonomy, tt.count DESC",
			ARRAY_A
		);

		$this->write_json('30_wp_terms.json', (array) $rows);
		$this->summary[] = sprintf('Categories/tags: %d', count((array) $rows));
	}

	/**
	 * Users referenced as post authors (ID, login, display name only).
	 */
	private function export_users() {
		$ids = array();

		foreach (array('aips_templates', 'aips_authors') as $suffix) {
			$table = $this->wpdb->prefix . $suffix;
			if ($this->table_exists($table)) {
				$ids = array_merge($ids, array_map('intval', (array) $this->wpdb->get_col("SELECT DISTINCT post_author FROM `{$table}` WHERE post_author IS NOT NULL")));
			}
		}

		$ids[] = (int) get_option('aips_default_post_author');
		$ids = array_values(array_filter(array_unique($ids)));

		$users = array();
		foreach ($ids as $id) {
			$user = get_userdata($id);
			$users[] = array(
				'ID'           => $id,
				'user_login'   => $user ? $user->user_login : null,
				'display_name' => $user ? $user->display_name : null,
				'roles'        => $user ? array_values((array) $user->roles) : array(),
			);
		}

		$this->write_json('31_wp_users.json', $users);
	}

	/**
	 * Topic backlog per author by status, plus the latest titles per author.
	 */
	private function export_topic_pipeline() {
		$table = $this->wpdb->prefix . 'aips_author_topics';
		if (!$this->table_exists($table)) {
			return;
		}

		$by_status = $this->wpdb->get_results(
			"SELECT author_id, status, COUNT(*) AS n, ROUND(AVG(score), 1) AS avg_score, MIN(score) AS min_score, MAX(score) AS max_score
			 FROM `{$table}` GROUP BY author_id, status ORDER BY author_id, status",
			ARRAY_A
		);

		$recent = array();
		$author_ids = $this->wpdb->get_col("SELECT DISTINCT author_id FROM `{$table}`");
		foreach ((array) $author_ids as $author_id) {
			$recent[$author_id] = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT id, topic_title, status, score FROM `{$table}` WHERE author_id = %d ORDER BY id DESC LIMIT 25",
					$author_id
				),
				ARRAY_A
			);
		}

		$this->write_json('40_author_topics.json', array(
			'by_author_status'      => (array) $by_status,
			'recent_titles_by_author' => $recent,
		));
	}

	/**
	 * Generation output and failures per template/author, and top errors.
	 */
	private function export_history_stats() {
		$table = $this->wpdb->prefix . 'aips_history';
		if (!$this->table_exists($table)) {
			return;
		}

		$since = time() - self::STATS_WINDOW;

		$by_source = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT template_id, author_id, campaign_id, creation_method, status, COUNT(*) AS n
				 FROM `{$table}`
				 WHERE record_kind = 'generation' AND created_at > %d
				 GROUP BY template_id, author_id, campaign_id, creation_method, status
				 ORDER BY template_id, author_id, status",
				$since
			),
			ARRAY_A
		);

		$by_week = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT FROM_UNIXTIME(created_at, '%%x-W%%v') AS iso_week, status, COUNT(*) AS n
				 FROM `{$table}`
				 WHERE record_kind = 'generation' AND created_at > %d
				 GROUP BY iso_week, status
				 ORDER BY iso_week, status",
				$since
			),
			ARRAY_A
		);

		$errors = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT LEFT(error_message, 240) AS error_head, COUNT(*) AS n, MAX(created_at) AS last_seen
				 FROM `{$table}`
				 WHERE error_message IS NOT NULL AND error_message <> '' AND created_at > %d
				 GROUP BY error_head
				 ORDER BY n DESC
				 LIMIT 30",
				$since
			),
			ARRAY_A
		);

		$this->write_json('41_history_stats.json', array(
			'window_days'           => self::STATS_WINDOW / DAY_IN_SECONDS,
			'by_template_author'    => (array) $by_source,
			'by_week'               => (array) $by_week,
			'top_errors'            => (array) $errors,
		));

		$this->summary[] = sprintf('History stats: %d groups, %d error kinds', count((array) $by_source), count((array) $errors));
	}

	/**
	 * Draft vs published counts of AIPS-generated posts.
	 */
	private function export_post_status_counts() {
		$table = $this->wpdb->prefix . 'aips_history';
		if (!$this->table_exists($table)) {
			return;
		}

		$rows = $this->wpdb->get_results(
			"SELECT p.post_status, COUNT(DISTINCT p.ID) AS n
			 FROM {$this->wpdb->posts} p
			 JOIN `{$table}` h ON h.post_id = p.ID AND h.record_kind = 'generation'
			 GROUP BY p.post_status",
			ARRAY_A
		);

		$this->write_json('42_aips_post_status.json', (array) $rows);
	}

	/**
	 * Which AI assistance / AI Edit contexts are used (counts only).
	 */
	private function export_ai_edit_usage() {
		$table = $this->wpdb->prefix . 'aips_ai_assistance';
		if (!$this->table_exists($table)) {
			return;
		}

		$rows = $this->wpdb->get_results(
			"SELECT form_context, field_key, COUNT(*) AS n, MAX(created_at) AS last_used
			 FROM `{$table}` GROUP BY form_context, field_key ORDER BY n DESC",
			ARRAY_A
		);

		$this->write_json('43_ai_assistance_usage.json', (array) $rows);
	}

	/**
	 * Row counts for every AIPS table (shows which features hold data).
	 */
	private function export_table_sizes() {
		$tables = $this->wpdb->get_col(
			$this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($this->wpdb->prefix . 'aips_') . '%')
		);

		$sizes = array();
		foreach ((array) $tables as $table) {
			$sizes[$table] = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
		}

		$this->write_json('44_table_row_counts.json', $sizes);
	}

	/**
	 * Short README describing the files.
	 */
	private function write_readme() {
		$lines = array(
			'AIPS configuration snapshot',
			'===========================',
			'',
			'Generated by ai-post-scheduler/scripts/export-aips-snapshot.php (read-only).',
			'Credentials are redacted; post content, logs, embeddings, emails and password hashes are not included.',
			'',
			'Summary:',
		);

		foreach ($this->summary as $line) {
			$lines[] = '- ' . $line;
		}

		$lines[] = '- Redacted values: ' . $this->redaction_count;

		file_put_contents($this->work_dir . DIRECTORY_SEPARATOR . 'README.txt', implode(PHP_EOL, $lines) . PHP_EOL);
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Redact credential-looking columns of a row, and decode JSON/serialized text.
	 *
	 * @param array $row
	 * @return array
	 */
	private function redact_row(array $row) {
		foreach ($row as $column => $value) {
			if (preg_match(self::SECRET_PATTERN, (string) $column) && $value !== null && $value !== '') {
				$row[$column] = self::REDACTED;
				$this->redaction_count++;
				continue;
			}

			if (is_string($value) && $value !== '') {
				$decoded = $this->decode_structured($value);
				if ($decoded !== null) {
					$row[$column] = $this->redact_value($decoded);
				}
			}
		}

		return $row;
	}

	/**
	 * Recursively redact credential-looking keys inside arrays/objects.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function redact_value($value) {
		if (is_object($value)) {
			$value = get_object_vars($value);
		}

		if (!is_array($value)) {
			return $value;
		}

		foreach ($value as $key => $item) {
			if (is_string($key) && preg_match(self::SECRET_PATTERN, $key) && $item !== '' && $item !== null && !is_bool($item)) {
				$value[$key] = self::REDACTED;
				$this->redaction_count++;
				continue;
			}

			$value[$key] = $this->redact_value($item);
		}

		return $value;
	}

	/**
	 * Decode a JSON or PHP-serialized string into an array, or return null.
	 *
	 * @param string $value
	 * @return array|null
	 */
	private function decode_structured($value) {
		$first = $value[0];

		if ($first === '{' || $first === '[') {
			$decoded = json_decode($value, true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}

		if (is_serialized($value)) {
			$decoded = @unserialize($value, array('allowed_classes' => false));
			if (is_array($decoded)) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * @param string $table Full table name.
	 * @return bool
	 */
	private function table_exists($table) {
		return $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))) === $table;
	}

	/**
	 * @param string $file
	 * @param mixed  $data
	 */
	private function write_json($file, $data) {
		$json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

		if ($json === false) {
			WP_CLI::warning("Could not encode {$file}");
			return;
		}

		file_put_contents($this->work_dir . DIRECTORY_SEPARATOR . $file, $json . PHP_EOL);
	}

	/**
	 * Zip the work directory and remove the loose files.
	 *
	 * @return string|null Zip path, or null when ZipArchive is unavailable.
	 */
	private function zip_work_dir() {
		if (!class_exists('ZipArchive')) {
			return null;
		}

		$zip_path = $this->work_dir . '.zip';
		$zip = new ZipArchive();

		if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			WP_CLI::warning('Could not create the zip; leaving the folder in place.');
			return null;
		}

		$base = basename($this->work_dir);
		$files = glob($this->work_dir . DIRECTORY_SEPARATOR . '*');
		foreach ((array) $files as $file) {
			$zip->addFile($file, $base . '/' . basename($file));
		}
		$zip->close();

		foreach ((array) $files as $file) {
			@unlink($file);
		}
		@rmdir($this->work_dir);

		return $zip_path;
	}

	/**
	 * @param string $dir
	 * @return bool
	 */
	private function is_inside_web_root($dir) {
		$real_dir = realpath($dir);
		$real_root = realpath(ABSPATH);

		if (!$real_dir || !$real_root) {
			return false;
		}

		$real_dir = rtrim(str_replace('\\', '/', $real_dir), '/') . '/';
		$real_root = rtrim(str_replace('\\', '/', $real_root), '/') . '/';

		return strpos($real_dir, $real_root) === 0;
	}
}

/**
 * Resolve the output directory: first CLI argument, else $HOME, else system temp.
 *
 * @param array $args
 * @return string
 */
function aips_snapshot_output_dir($args) {
	if (!empty($args[0])) {
		return $args[0];
	}

	$home = getenv('HOME');
	if ($home && is_dir($home) && is_writable($home)) {
		return $home;
	}

	return sys_get_temp_dir();
}

$aips_snapshot_args = isset($args) && is_array($args) ? $args : array();
(new AIPS_Snapshot_Exporter(aips_snapshot_output_dir($aips_snapshot_args)))->run();
