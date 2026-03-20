<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Upgrades {

	/**
	 * History container type identifier for database migration entries.
	 */
	const HISTORY_TYPE = 'db_migration';

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	public function __construct() {
		$this->logger = new AIPS_Logger();
		$this->history_service = new AIPS_History_Service();
	}

	public static function check_and_run() {
		$current_version = get_option('aips_db_version', '0');

		if (version_compare($current_version, AIPS_VERSION, '<')) {
			$instance = new self();
			$instance->run_upgrade($current_version);
		}
	}

	private function run_upgrade($from_version) {
		// Run dbDelta first so the history tables are guaranteed to exist
		// before we attempt to write any log entries into them.
		try {
			AIPS_DB_Manager::install_tables();
		} catch (Exception $e) {
			// If the schema update itself fails we cannot log to history either,
			// so fall back to the plain logger and bail out early.
			$this->logger->log(
				'Database upgrade failed (schema step): ' . $e->getMessage(),
				'error'
			);
			return;
		}

		$history = $this->history_service->create(
			self::HISTORY_TYPE,
			array(
				'creation_method' => 'system',
			)
		);

		$history->record(
			'activity',
			sprintf(
				/* translators: 1: from version, 2: to version */
				__('Database upgrade started from version %1$s to %2$s', 'ai-post-scheduler'),
				$from_version,
				AIPS_VERSION
			),
			array(
				'event_type'   => 'db_upgrade_started',
				'event_status' => 'processing',
				'from_version' => $from_version,
				'to_version'   => AIPS_VERSION,
			)
		);

		$history->record(
			'info',
			__('Database schema updated via dbDelta', 'ai-post-scheduler'),
			array(
				'event_type' => 'db_schema_updated',
				'tables'     => AIPS_DB_Manager::get_table_names(),
			)
		);

		// Persist the new version so subsequent requests skip the upgrade.
		$updated = update_option('aips_db_version', AIPS_VERSION);

		if ($updated) {
			$history->record(
				'activity',
				sprintf(
					/* translators: %s: new plugin version */
					__('Database version updated to %s', 'ai-post-scheduler'),
					AIPS_VERSION
				),
				array(
					'event_type'   => 'db_version_saved',
					'event_status' => 'success',
					'version'      => AIPS_VERSION,
				)
			);
		} else {
			// update_option() returns false when the stored value equals the new value.
			// Because we only run here when versions differ, treat this as an informational note.
			$history->record(
				'info',
				sprintf(
					/* translators: %s: plugin version */
					__('Database version option already set to %s — no change written', 'ai-post-scheduler'),
					AIPS_VERSION
				),
				array(
					'event_type' => 'db_version_unchanged',
					'version'    => AIPS_VERSION,
				)
			);
		}

		$history->complete_success(
			array(
				'from_version' => $from_version,
				'to_version'   => AIPS_VERSION,
			)
		);

		$this->logger->log(
			'Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION,
			'info'
		);
	}
}
?>
