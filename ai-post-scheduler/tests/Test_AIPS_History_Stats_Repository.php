<?php
/**
 * Tests for AIPS_History_Stats_Repository.
 */
require_once dirname(dirname(__FILE__)) . "/includes/class-aips-history-stats-repository.php";

// Simple mock for wpdb
if (!class_exists('wpdb')) {
	class wpdb {
		public $prefix = 'wp_';
		public $postmeta = 'wp_postmeta';
		public function prepare($query, ...$args) { return $query; }
		public function get_results($query, $output = 'OBJECT') { return []; }
		public function get_col($query, $x = 0) { return []; }
		public function get_row($query, $output = 'OBJECT', $y = 0) { return null; }
		public function get_var($query, $x = 0, $y = 0) { return null; }
	}
}

class Test_AIPS_History_Stats_Repository extends WP_UnitTestCase {

	private $wpdb_mock;
	private $repository;

	public function setUp(): void {
		parent::setUp();

		$this->wpdb_mock = $this->createMock(wpdb::class);
		$this->repository = new AIPS_History_Stats_Repository(
			$this->wpdb_mock,
			'wp_aips_history',
			'wp_aips_history_log'
		);
	}

	public function test_get_daily_success_failure_trend() {
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with('prepared_query', ARRAY_A)
			->willReturn([['metric_date' => '2023-01-01', 'success_count' => 10, 'failure_count' => 2]]);

		$result = $this->repository->get_daily_success_failure_trend(14);
		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertEquals(10, $result[0]['success_count']);
	}

	public function test_get_average_duration_by_flow() {
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with('prepared_query', ARRAY_A)
			->willReturn([['flow_type' => 'manual', 'avg_duration_seconds' => 30.5, 'sample_count' => 5]]);

		$result = $this->repository->get_average_duration_by_flow(14);
		$this->assertIsArray($result);
		$this->assertEquals('manual', $result[0]['flow_type']);
	}

	public function test_get_retry_counts_by_service() {
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with('prepared_query', ARRAY_A)
			->willReturn([['service_key' => 'openai', 'retry_count' => 3]]);

		$result = $this->repository->get_retry_counts_by_service(14);
		$this->assertIsArray($result);
		$this->assertEquals('openai', $result[0]['service_key']);
	}

	public function test_get_top_failure_reasons() {
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with('prepared_query', ARRAY_A)
			->willReturn([['reason' => 'Timeout', 'failure_count' => 10]]);

		$result = $this->repository->get_top_failure_reasons(14, 8);
		$this->assertIsArray($result);
		$this->assertEquals('Timeout', $result[0]['reason']);
	}

	public function test_get_estimated_generation_time_with_no_data() {
		// wpdb->postmeta property is accessed
		$this->wpdb_mock->postmeta = 'wp_postmeta';
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_col')
			->with('prepared_query')
			->willReturn([]);

		$result = $this->repository->get_estimated_generation_time(20);
		$this->assertIsArray($result);
		$this->assertEquals(30, $result['per_post_seconds']);
		$this->assertEquals(0, $result['sample_size']);
	}

	public function test_get_estimated_generation_time_with_data() {
		$this->wpdb_mock->postmeta = 'wp_postmeta';
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_col')
			->with('prepared_query')
			->willReturn(['15', '25', '20']);

		$result = $this->repository->get_estimated_generation_time(20);
		$this->assertIsArray($result);
		$this->assertEquals(20, $result['per_post_seconds']);
		$this->assertEquals(3, $result['sample_size']);
	}

	public function test_get_stats_without_cache() {
		$mock_row = (object)[
			'total' => 100,
			'completed' => 90,
			'failed' => 5,
			'processing' => 3,
			'partial' => 2
		];

		$this->wpdb_mock->expects($this->once())
			->method('get_row')
			->willReturn($mock_row);

		$result = $this->repository->get_stats();

		$this->assertIsArray($result);
		$this->assertEquals(100, $result['total']);
		$this->assertEquals(90.0, $result['success_rate']);
	}

	public function test_get_template_stats() {
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_var')
			->with('prepared_query')
			->willReturn(42);

		$result = $this->repository->get_template_stats(1);
		$this->assertEquals(42, $result);
	}

	public function test_get_all_template_stats() {
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->willReturn([
				(object)['template_id' => 1, 'count' => 10],
				(object)['template_id' => 2, 'count' => 20]
			]);

		$result = $this->repository->get_all_template_stats();
		$this->assertIsArray($result);
		$this->assertEquals(10, $result[1]);
		$this->assertEquals(20, $result[2]);
	}

	public function test_get_schedule_generated_post_counts_empty() {
		$result = $this->repository->get_schedule_generated_post_counts([]);
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	public function test_get_schedule_generated_post_counts_with_data() {
		$this->wpdb_mock->method('prepare')->willReturn('prepared_query');
		$this->wpdb_mock->expects($this->once())
			->method('get_results')
			->with('prepared_query')
			->willReturn([
				(object)['history_id' => 10, 'count' => 5],
				(object)['history_id' => 20, 'count' => 8]
			]);

		$result = $this->repository->get_schedule_generated_post_counts([10, 20]);
		$this->assertIsArray($result);
		$this->assertEquals(5, $result[10]);
		$this->assertEquals(8, $result[20]);
	}
}
