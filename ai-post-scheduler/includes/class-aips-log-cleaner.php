<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Log_Cleaner
 *
 * Automated maintenance worker that prunes system log files and log entries
 * older than the configured retention threshold (aips_log_retention_days).
 * Runs on a recurring daily cron schedule or via manual admin trigger.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */
class AIPS_Log_Cleaner {

	/**
	 * Daily cron hook name.
	 */
	const CRON_HOOK = 'aips_daily_log_cleanup';

	/**
	 * Prune system log files and database log rows older than the retention period.
	 *
	 * @param int|null $retention_days Optional override. Null = read from AIPS_Config.
	 * @return array{files_deleted: int, db_rows_deleted: int, deleted_files_count: int, deleted_db_rows: int, cutoff: int, cutoff_date: string}
	 */
	public static function prune_logs( ?int $retention_days = null ): array {
		if ( null === $retention_days ) {
			$retention_days = (int) AIPS_Config::get_instance()->get_option( 'aips_log_retention_days', 30 );
		}

		// 0 or negative means keep indefinitely.
		if ( $retention_days <= 0 ) {
			return array(
				'files_deleted'       => 0,
				'db_rows_deleted'     => 0,
				'deleted_files_count' => 0,
				'deleted_db_rows'     => 0,
				'cutoff'              => 0,
				'cutoff_date'         => '',
			);
		}

		$cutoff        = AIPS_DateTime::now()->timestamp() - ( $retention_days * DAY_IN_SECONDS );
		$files_deleted = self::prune_disk_log_files( $cutoff );
		$rows_deleted  = self::prune_db_log_records( $cutoff );

		AIPS_Logger::instance()->log(
			sprintf( 'Log cleanup completed: %d files and %d database rows purged (cutoff: %s).', $files_deleted, $rows_deleted, date( 'Y-m-d H:i:s', $cutoff ) ),
			'info'
		);

		return array(
			'files_deleted'       => $files_deleted,
			'db_rows_deleted'     => $rows_deleted,
			'deleted_files_count' => $files_deleted,
			'deleted_db_rows'     => $rows_deleted,
			'cutoff'              => $cutoff,
			'cutoff_date'         => date( 'Y-m-d H:i:s', $cutoff ),
		);
	}

	/**
	 * Prune log files on disk older than cutoff timestamp.
	 *
	 * @param int $cutoff
	 * @return int Number of files deleted.
	 */
	private static function prune_disk_log_files( int $cutoff ): int {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/aips-logs';

		if ( ! is_dir( $log_dir ) || ! is_readable( $log_dir ) ) {
			return 0;
		}

		$files = glob( $log_dir . '/aips-*.log' );
		if ( false === $files ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$mtime = filemtime( $file );
			if ( false !== $mtime && $mtime < $cutoff ) {
				if ( @unlink( $file ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Prune any database log rows older than cutoff timestamp in chunked batches.
	 *
	 * AIPS_Logger only writes to files under uploads/aips-logs/; there is no
	 * `aips_logs` database table in AIPS_DB_Manager::get_schema(), so this is
	 * currently always a no-op. Kept as an explicit extension point (and to
	 * preserve the db_rows_deleted/deleted_db_rows keys in prune_logs()'s
	 * return shape) for if/when a DB-backed log store is introduced, at which
	 * point the deletion belongs in that table's repository class, not here.
	 *
	 * @param int $cutoff
	 * @return int Number of rows deleted.
	 */
	private static function prune_db_log_records( int $cutoff ): int {
		return 0;
	}
}
