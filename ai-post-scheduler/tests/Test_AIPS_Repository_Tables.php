<?php
/**
 * Tests for the shared table-name accessor (AIPS_DB_Manager::get_table) and the
 * AIPS_Repository_Tables trait.
 *
 * These are the Phase 0 foundation for issue #1944 (repository caching trait +
 * table() standardization). They assert behavioral equivalence between the new
 * accessor and the previously hardcoded `$wpdb->prefix . 'aips_x'` strings.
 *
 * @package AI_Post_Scheduler
 */

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once dirname(__DIR__) . '/includes/trait-aips-repository-tables.php';
}

/**
 * Minimal consumer of the trait so its protected helpers can be exercised.
 */
class AIPS_Test_Repository_Tables_Subject {
	use AIPS_Repository_Tables;

	public function public_table(string $key): string {
		return $this->table($key);
	}

	public function public_db() {
		return $this->db();
	}
}

class Test_AIPS_Repository_Tables extends WP_UnitTestCase {

	/**
	 * get_table() must return the same value as get_full_table_names() for every key.
	 */
	public function test_get_table_matches_full_table_names_map() {
		$map = AIPS_DB_Manager::get_full_table_names();

		$this->assertNotEmpty($map, 'Expected a non-empty table map.');

		foreach ($map as $key => $full_name) {
			$this->assertSame(
				$full_name,
				AIPS_DB_Manager::get_table($key),
				sprintf('get_table(%s) should equal the get_full_table_names() entry.', $key)
			);
		}
	}

	/**
	 * get_table() must equal the legacy hardcoded string form.
	 */
	public function test_get_table_matches_legacy_prefixed_string() {
		global $wpdb;

		foreach (AIPS_DB_Manager::get_table_names() as $key) {
			$this->assertSame(
				$wpdb->prefix . $key,
				AIPS_DB_Manager::get_table($key),
				sprintf('get_table(%s) should equal $wpdb->prefix . key.', $key)
			);
		}
	}

	/**
	 * An unknown key triggers _doing_it_wrong but still degrades to a prefixed name.
	 */
	public function test_get_table_unknown_key_flags_incorrect_usage() {
		global $wpdb;

		$this->setExpectedIncorrectUsage('AIPS_DB_Manager::get_table');

		$this->assertSame(
			$wpdb->prefix . 'aips_not_a_real_table',
			AIPS_DB_Manager::get_table('aips_not_a_real_table')
		);
	}

	/**
	 * The trait's table() helper delegates to the DB manager accessor.
	 */
	public function test_trait_table_delegates_to_db_manager() {
		$subject = new AIPS_Test_Repository_Tables_Subject();

		foreach (AIPS_DB_Manager::get_table_names() as $key) {
			$this->assertSame(
				AIPS_DB_Manager::get_table($key),
				$subject->public_table($key)
			);
		}
	}

	/**
	 * The trait's db() helper returns the global wpdb instance.
	 */
	public function test_trait_db_returns_global_wpdb() {
		global $wpdb;

		$subject = new AIPS_Test_Repository_Tables_Subject();

		$this->assertSame($wpdb, $subject->public_db());
	}
}
