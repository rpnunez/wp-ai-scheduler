<?php
/**
 * Test case for Telemetry Repository performance behavior.
 */

class AIPS_Telemetry_Repository_Performance_Test extends WP_UnitTestCase {

	private $wpdb_backup;

	public function setUp(): void {
		parent::setUp();
		if (isset($GLOBALS['wpdb'])) {
			$this->wpdb_backup = $GLOBALS['wpdb'];
		}
	}

	public function tearDown(): void {
		if ($this->wpdb_backup) {
			$GLOBALS['wpdb'] = $this->wpdb_backup;
		}
		parent::tearDown();
	}

	/**
	 * Ensure rollups process rows in bounded batches instead of one unbounded hydration.
	 */
	public function test_get_daily_rollup_processes_large_result_set_in_batches() {
		$wpdb_mock = $this->getMockBuilder('stdClass')
			->addMethods(array('prepare', 'get_results', 'esc_like'))
			->getMock();

		$wpdb_mock->prefix = 'wp_';
		$wpdb_mock->method('prepare')
			->will($this->returnCallback(function($query, ...$args) {
				return $query;
			}));
		$wpdb_mock->method('esc_like')
			->will($this->returnCallback(function($text) {
				return $text;
			}));

		$base_timestamp = AIPS_DateTime::now()->timestamp();
		$first_batch = array();
		for ($i = 1; $i <= 1000; $i++) {
			$first_batch[] = array(
				'id' => $i,
				'inserted_at' => $base_timestamp,
				'num_queries' => 1,
				'peak_memory_bytes' => 100,
				'elapsed_ms' => 10.0,
			);
		}
		$second_batch = array(
			array(
				'id' => 1001,
				'inserted_at' => $base_timestamp,
				'num_queries' => 1,
				'peak_memory_bytes' => 200,
				'elapsed_ms' => 20.0,
			),
		);

		$wpdb_mock->expects($this->exactly(2))
			->method('get_results')
			->willReturnOnConsecutiveCalls($first_batch, $second_batch);

		$GLOBALS['wpdb'] = $wpdb_mock;
		$repo = new AIPS_Telemetry_Repository();

		$today = AIPS_DateTime::now()->toDisplay('Y-m-d');
		$rollup = $repo->get_daily_rollup($today, $today);

		$this->assertCount(1, $rollup);
		$this->assertSame(1001, $rollup[0]['request_count']);
		$this->assertSame(1001, $rollup[0]['total_queries']);
		$this->assertSame(200, $rollup[0]['peak_memory_bytes_max']);
		$this->assertEqualsWithDelta((1000 * 10 + 20) / 1001, $rollup[0]['avg_elapsed_ms'], 0.001);
	}
}

