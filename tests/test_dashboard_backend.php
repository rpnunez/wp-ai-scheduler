<?php
/**
 * Tests for the AIPS_Dashboard backend data.
 *
 * @package AI_Post_Scheduler
 */

// Provide lightweight repository and logger stubs if the real classes are
// not already loaded by the plugin or test bootstrap.
if ( ! class_exists( 'AIPS_History_Repository' ) ) {
	class AIPS_History_Repository {
		/**
		 * Return fixed stats for testing.
		 *
		 * @return array
		 */
		public function get_stats() {
			return array(
				'total'        => 10,
				'completed'    => 8,
				'failed'       => 2,
				'processing'   => 0,
				'success_rate' => 80,
			);
		}
	}
}

if ( ! class_exists( 'AIPS_Template_Repository' ) ) {
	class AIPS_Template_Repository {
		/**
		 * Return an empty templates list for testing.
		 *
		 * @return array
		 */
		public function get_all() {
			return array();
		}
	}
}

if ( ! class_exists( 'AIPS_Logger' ) ) {
	class AIPS_Logger {
		/**
		 * Return an empty list of log files for testing.
		 *
		 * @return array
		 */
		public function get_log_files() {
			return array();
		}

		/**
		 * Return an empty list of logs for testing.
		 *
		 * @return array
		 */
		public function get_logs() {
			return array();
		}
	}
}

// Include the class to test. In the real test environment, the plugin
// bootstrap may already load this, but require_once keeps this idempotent.
require_once '/app/ai-post-scheduler/includes/class-aips-dashboard.php';

/**
 * Dashboard backend tests.
 */
class AIPS_Test_Dashboard_Backend extends WP_UnitTestCase {

	/**
	 * Ensure that get_dashboard_data returns expected stats and suggestions.
	 */
	public function test_get_dashboard_data_returns_expected_stats_and_suggestions() {
		$dashboard = new AIPS_Dashboard();

		$data = $dashboard->get_dashboard_data();

		// Basic structure checks.
		$this->assertIsArray( $data, 'Dashboard data should be an array.' );
		$this->assertArrayHasKey( 'stats', $data, 'Dashboard data should contain stats.' );
		$this->assertArrayHasKey( 'suggestions', $data, 'Dashboard data should contain suggestions.' );

		// Verify stats, mirroring the original script expectations.
		$this->assertSame(
			80,
			$data['stats']['success_rate'],
			'Success rate should be 80 as provided by the history repository stub.'
		);

		// Verify suggestions.
		$this->assertIsArray( $data['suggestions'], 'Suggestions should be an array.' );
		$this->assertNotEmpty( $data['suggestions'], 'Suggestions should not be empty.' );
	}
}
