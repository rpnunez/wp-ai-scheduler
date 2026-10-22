<?php
/**
 * Lock Repository
 *
 * The SQL behind AIPS_Lock. Locks are rows in the WordPress options table: the
 * option_name unique index makes the insert atomic, which a get-then-set
 * transient is not.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Lock_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Insert the lock row; fails when it already exists.
	 *
	 * @param string $name  Option name.
	 * @param string $value Lock value ("expiry|token").
	 * @return bool True if this call created the row.
	 */
	public function insert(string $name, string $value): bool {
		$rows = $this->wpdb->query($this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$name,
			$value
		));

		return 1 === (int) $rows;
	}

	/**
	 * Read the lock value straight from the database, bypassing the object cache.
	 *
	 * @param string $name Option name.
	 * @return string|null Null when there is no lock.
	 */
	public function read(string $name): ?string {
		$value = $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s LIMIT 1",
			$name
		));

		return is_string($value) ? $value : null;
	}

	/**
	 * Delete the lock row only if it still holds the given value.
	 *
	 * @param string $name  Option name.
	 * @param string $value Expected value.
	 * @return bool True if a row was deleted.
	 */
	public function delete_if_equals(string $name, string $value): bool {
		$rows = $this->wpdb->query($this->wpdb->prepare(
			"DELETE FROM {$this->wpdb->options} WHERE option_name = %s AND option_value = %s",
			$name,
			$value
		));

		return (int) $rows > 0;
	}

	/**
	 * Delete lock rows whose expiry has passed.
	 *
	 * @param string $prefix Option-name prefix of lock rows.
	 * @param int    $now    Current timestamp.
	 * @return int Rows deleted.
	 */
	public function delete_expired(string $prefix, int $now): int {
		$rows = $this->wpdb->query($this->wpdb->prepare(
			"DELETE FROM {$this->wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, '|', 1) AS UNSIGNED) <= %d",
			$this->wpdb->esc_like($prefix) . '%',
			$now
		));

		return max(0, (int) $rows);
	}
}
