<?php
/**
 * Database Prune Service
 *
 * Coordinates database retention policies, automated background cleanups,
 * and manual maintenance actions across plugin tables.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_DB_Prune_Service
 */
class AIPS_DB_Prune_Service {

	/**
	 * @var AIPS_DB_Prune_Service|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var AIPS_DB_Prune_Repository
	 */
	private $prune_repository;

	/**
	 * @var AIPS_Telemetry_Repository
	 */
	private $telemetry_repository;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param AIPS_DB_Prune_Repository|null  $prune_repository     Optional repository dependency.
	 * @param AIPS_Telemetry_Repository|null $telemetry_repository Optional telemetry repository.
	 * @param AIPS_Config|null               $config               Optional config instance.
	 */
	public function __construct(
		?AIPS_DB_Prune_Repository $prune_repository = null,
		?AIPS_Telemetry_Repository $telemetry_repository = null,
		?AIPS_Config $config = null
	) {
		$this->prune_repository     = $prune_repository ?: AIPS_DB_Prune_Repository::instance();
		$this->telemetry_repository = $telemetry_repository ?: AIPS_Telemetry_Repository::instance();
		$this->config               = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return AIPS_DB_Prune_Service
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Convert an integer value and time unit into a UTC Unix timestamp cutoff using AIPS_DateTime.
	 *
	 * @param int    $value Retention count (e.g. 30).
	 * @param string $unit  Time unit ('days', 'weeks', 'months').
	 * @return int UTC cutoff timestamp.
	 */
	public function calculate_cutoff($value, $unit) {
		$value = max(1, (int) $value);
		$unit  = sanitize_key((string) $unit);

		switch ($unit) {
			case 'weeks':
				$modifier = "-{$value} weeks";
				break;
			case 'months':
				$modifier = "-{$value} months";
				break;
			case 'days':
			default:
				$modifier = "-{$value} days";
				break;
		}

		return AIPS_DateTime::now()->advance($modifier)->timestamp();
	}

	/**
	 * Get the configured telemetry cutoff timestamp.
	 *
	 * @return int UTC cutoff timestamp.
	 */
	public function get_telemetry_cutoff() {
		$value = (int) $this->config->get_option('aips_telemetry_retention_value', 30);
		$unit  = (string) $this->config->get_option('aips_telemetry_retention_unit', 'days');

		return $this->calculate_cutoff($value, $unit);
	}

	/**
	 * Get the configured history log cutoff timestamp.
	 *
	 * @return int UTC cutoff timestamp.
	 */
	public function get_history_log_cutoff() {
		$value = (int) $this->config->get_option('aips_history_log_retention_value', 60);
		$unit  = (string) $this->config->get_option('aips_history_log_retention_unit', 'days');

		return $this->calculate_cutoff($value, $unit);
	}

	/**
	 * Prune telemetry records older than the cutoff timestamp.
	 *
	 * When more than 500 records are removed, automatically runs OPTIMIZE TABLE
	 * on the telemetry table to reclaim InnoDB tablespace.
	 *
	 * @param int|null $cutoff Optional explicit cutoff timestamp. If null, uses configured retention.
	 * @return array<string, mixed> Operation outcome details.
	 */
	public function prune_telemetry($cutoff = null) {
		if (null === $cutoff) {
			$cutoff = $this->get_telemetry_cutoff();
		}

		$deleted   = $this->telemetry_repository->prune_older_than($cutoff);
		$optimized = false;

		if ($deleted > 500) {
			$optimized = $this->telemetry_repository->optimize();
		}

		return array(
			'deleted'   => $deleted,
			'optimized' => $optimized,
			'cutoff'    => $cutoff,
		);
	}

	/**
	 * Purge all records from the telemetry table using TRUNCATE TABLE.
	 *
	 * Immediately releases allocated disk space back to MySQL.
	 *
	 * @return array<string, mixed> Operation outcome.
	 */
	public function purge_all_telemetry() {
		$success = $this->telemetry_repository->truncate();

		return array(
			'success' => $success,
			'message' => $success
				? __('All telemetry records permanently purged and table reset.', 'ai-post-scheduler')
				: __('Failed to truncate telemetry table.', 'ai-post-scheduler'),
		);
	}

	/**
	 * Prune generation event logs older than the cutoff timestamp.
	 *
	 * @param int|null $cutoff Optional explicit cutoff timestamp. If null, uses configured retention.
	 * @return array<string, mixed> Operation outcome details.
	 */
	public function prune_history_logs($cutoff = null) {
		if (null === $cutoff) {
			$cutoff = $this->get_history_log_cutoff();
		}

		$deleted   = $this->prune_repository->prune_history_logs($cutoff);
		$optimized = false;

		if ($deleted > 500) {
			$optimized = $this->prune_repository->optimize_table('aips_history_log');
		}

		return array(
			'deleted'   => $deleted,
			'optimized' => $optimized,
			'cutoff'    => $cutoff,
		);
	}

	/**
	 * Clean orphaned embeddings associated with deleted WordPress posts.
	 *
	 * @return int Number of deleted orphaned embedding records.
	 */
	public function clean_orphaned_embeddings() {
		return $this->prune_repository->clean_orphaned_embeddings();
	}

	/**
	 * Clean rejected or expired author topics older than the cutoff timestamp.
	 *
	 * @param int|null $cutoff Optional cutoff timestamp. If null, defaults to 30 days.
	 * @return int Number of deleted expired topics.
	 */
	public function clean_expired_topics($cutoff = null) {
		if (null === $cutoff) {
			$cutoff = $this->calculate_cutoff(30, 'days');
		}

		return $this->prune_repository->clean_expired_topics($cutoff);
	}

	/**
	 * Optimize a single plugin database table.
	 *
	 * @param string $table_name Full or short table name.
	 * @return bool True on success, false on failure.
	 */
	public function optimize_table($table_name) {
		return $this->prune_repository->optimize_table($table_name);
	}

	/**
	 * Retrieve disk space, row count, and overhead statistics for all plugin tables.
	 *
	 * @return array<int, array<string, mixed>> Table status records.
	 */
	public function get_table_status_summary() {
		return $this->prune_repository->get_tables_status();
	}

	/**
	 * Execute all automated pruning tasks if automatic database pruning is enabled.
	 *
	 * Called via the aips_database_prune_cleanup WP-Cron hook.
	 *
	 * @return array<string, mixed> Prune run results.
	 */
	public function run_automated_prune() {
		if (!(bool) $this->config->get_option('aips_auto_prune_enabled')) {
			return array(
				'skipped' => true,
				'reason'  => 'Automated pruning is disabled.',
			);
		}

		$results = array(
			'telemetry'    => $this->prune_telemetry(),
			'history_logs' => $this->prune_history_logs(),
		);

		if ((bool) $this->config->get_option('aips_clean_orphaned_embeddings', true)) {
			$results['orphaned_embeddings'] = $this->clean_orphaned_embeddings();
		}

		if ((bool) $this->config->get_option('aips_clean_expired_topics', true)) {
			$results['expired_topics'] = $this->clean_expired_topics();
		}

		return $results;
	}
}
