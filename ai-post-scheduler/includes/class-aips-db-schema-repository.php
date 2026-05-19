<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_DB_Schema_Repository {
	/** @var wpdb */
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function column_exists($table, $column) {
		$exists = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SHOW COLUMNS FROM `{$table}` LIKE %s",
				$column
			)
		);
		return !empty($exists);
	}

	public function run_alter_statement($sql) {
		return $this->wpdb->query($sql);
	}
}
