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
		$root = dirname(__DIR__);

		$result = aips_scan_repository_legacy_cache_usage(
			$root,
			array(
				'includes/class-aips-article-structure-repository.php' => true,
				'includes/class-aips-prompt-section-repository.php'    => true,
				'includes/class-aips-schedule-repository.php'          => true,
			)
		);

		$this->assertSame(
			array( 'includes/class-aips-post-slices-repository.php' ),
			$result['violations']
		);
	}

	public function test_repository_legacy_cache_scan_flags_stale_baseline_entries() {
		$root = dirname(__DIR__);

		$result = aips_scan_repository_legacy_cache_usage(
			$root,
			array(
				'includes/class-aips-article-structure-repository.php' => true,
				'includes/class-aips-post-slices-repository.php'       => true,
				'includes/class-aips-prompt-section-repository.php'    => true,
				'includes/class-aips-schedule-repository.php'          => true,
				'includes/class-aips-template-repository.php'          => true,
			)
		);

		$this->assertSame(
			array( 'includes/class-aips-template-repository.php' ),
			$result['stale_entries']
		);
	}
}
