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
				"SHOW COLUMNS FROM `{$table}` WHERE Field = %s",
				$column
			)
		);
		return !empty($exists);
	}

	public function run_alter_statement($table, $alter_clause) {
		$table = (string) $table;
		$alter_clause = trim((string) $alter_clause);
		$expected_prefix = (string) $this->wpdb->prefix . 'aips_';

		if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
			return false;
		}

		if (0 !== strpos($table, $expected_prefix)) {
			return false;
		}

		if ('' === $alter_clause) {
			return false;
		}

		if (!preg_match('/^(ADD|CHANGE|MODIFY|DROP)\s+/i', $alter_clause)) {
			return false;
		}

		if (preg_match('/;|--|#|\/\*/', $alter_clause)) {
			return false;
		}

		return $this->wpdb->query("ALTER TABLE `{$table}` {$alter_clause}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}
}
