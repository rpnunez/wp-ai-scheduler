<?php
/**
 * Tests for repository boundary lint guardrails.
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/tools/check-repository-boundary.php';

class Test_AIPS_Repository_Boundary_Check extends WP_UnitTestCase {

	public function test_repository_boundary_check_accepts_current_legacy_cache_baseline() {
		$result = aips_run_repository_boundary_check(dirname(__DIR__));

		$this->assertSame(array(), $result['wpdb_violations']);
		$this->assertSame(array(), $result['legacy_cache_result']['violations']);
		$this->assertSame(array(), $result['legacy_cache_result']['stale_entries']);
	}

	public function test_repository_legacy_cache_scan_flags_unapproved_repository_usage() {
		$root          = dirname(__DIR__);
		$temp_file     = $root . '/includes/class-aips-temp-legacy-repository.php';
		$temp_relative = 'includes/class-aips-temp-legacy-repository.php';

		file_put_contents( $temp_file, "<?php\nclass AIPS_Temp_Legacy_Repository { public function legacy() { AIPS_Cache_Policy::key( 'x', 'y' ); } }\n" );

		try {
			$result = aips_scan_repository_legacy_cache_usage( $root, array() );
			$this->assertSame( array( $temp_relative ), $result['violations'] );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_repository_legacy_cache_scan_flags_stale_baseline_entries() {
		$root            = dirname(__DIR__);
		$legacy_file     = $root . '/includes/class-aips-temp-legacy-repository.php';
		$legacy_relative = 'includes/class-aips-temp-legacy-repository.php';
		$clean_file      = $root . '/includes/class-aips-temp-clean-repository.php';
		$clean_relative  = 'includes/class-aips-temp-clean-repository.php';

		file_put_contents( $legacy_file, "<?php\nclass AIPS_Temp_Legacy_Repository { public function legacy() { AIPS_Cache_Policy::key( 'x', 'y' ); } }\n" );
		file_put_contents( $clean_file, "<?php\nclass AIPS_Temp_Clean_Repository { public function ok() { return true; } }\n" );

		try {
			$result = aips_scan_repository_legacy_cache_usage(
				$root,
				array(
					$legacy_relative => true,
					$clean_relative  => true,
				)
			);

			$this->assertSame( array(), $result['violations'] );
			$this->assertSame( array( $clean_relative ), $result['stale_entries'] );
		} finally {
			@unlink( $legacy_file );
			@unlink( $clean_file );
		}
	}
}
