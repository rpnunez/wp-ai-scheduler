<?php
/**
 * Test cases for AIPS_Embeddings_Rate_Limiter.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.6
 */

/**
 * @covers AIPS_Embeddings_Rate_Limiter
 */
class Test_AIPS_Embeddings_Rate_Limiter extends WP_UnitTestCase {

	/**
	 * @var AIPS_Embeddings_Rate_Limiter
	 */
	private $limiter;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	public function setUp(): void {
		parent::setUp();
		$this->config  = AIPS_Config::get_instance();
		$this->limiter = new AIPS_Embeddings_Rate_Limiter($this->config);
		$this->limiter->reset_history();

		// Configure test defaults
		update_option('aips_embeddings_rate_limits_enabled', true);
		update_option('aips_embeddings_daily_limit', 5);
		update_option('aips_embeddings_weekly_limit', 15);
		update_option('aips_embeddings_monthly_limit', 30);
	}

	public function tearDown(): void {
		$this->limiter->reset_history();
		delete_option('aips_embeddings_rate_limits_enabled');
		delete_option('aips_embeddings_daily_limit');
		delete_option('aips_embeddings_weekly_limit');
		delete_option('aips_embeddings_monthly_limit');
		parent::tearDown();
	}

	public function test_initial_state_empty() {
		$this->assertTrue($this->limiter->is_enabled());
		$stats = $this->limiter->get_usage_stats();

		$this->assertSame(0, $stats['daily_count']);
		$this->assertSame(0, $stats['weekly_count']);
		$this->assertSame(0, $stats['monthly_count']);
		$this->assertSame(5, $stats['daily_limit']);
		$this->assertSame(15, $stats['weekly_limit']);
		$this->assertSame(30, $stats['monthly_limit']);
		$this->assertFalse($stats['is_rate_limited']);
		$this->assertNull($stats['exceeded_limit']);
	}

	public function test_record_usage_increments_counts() {
		$this->limiter->record_usage(3);
		$stats = $this->limiter->get_usage_stats();

		$this->assertSame(3, $stats['daily_count']);
		$this->assertSame(3, $stats['weekly_count']);
		$this->assertSame(3, $stats['monthly_count']);
		$this->assertFalse($stats['is_rate_limited']);
	}

	public function test_check_limits_allows_within_budget() {
		$this->limiter->record_usage(4);
		$result = $this->limiter->check_limits(1);

		$this->assertTrue($result);
	}

	public function test_check_limits_blocks_when_daily_limit_exceeded() {
		$this->limiter->record_usage(5);
		$stats = $this->limiter->get_usage_stats();

		$this->assertTrue($stats['is_rate_limited']);
		$this->assertSame('daily', $stats['exceeded_limit']);

		$quota_alert_fired = false;
		add_action('aips_quota_alert', function ($payload) use (&$quota_alert_fired) {
			$quota_alert_fired = true;
			$this->assertSame('embedding', $payload['request_type']);
			$this->assertSame('rate_limit_exceeded', $payload['error_code']);
		});

		$result = $this->limiter->check_limits(1);

		$this->assertWPError($result);
		$this->assertSame('rate_limit_exceeded', $result->get_error_code());
		$this->assertTrue($quota_alert_fired);
	}

	public function test_check_limits_weekly_limit_enforcement() {
		update_option('aips_embeddings_daily_limit', 50);
		update_option('aips_embeddings_weekly_limit', 10);

		$this->limiter->record_usage(10);
		$stats = $this->limiter->get_usage_stats();

		$this->assertTrue($stats['is_rate_limited']);
		$this->assertSame('weekly', $stats['exceeded_limit']);

		$result = $this->limiter->check_limits(1);
		$this->assertWPError($result);
		$this->assertSame('rate_limit_exceeded', $result->get_error_code());
	}

	public function test_disabled_rate_limiting_allows_any_usage() {
		update_option('aips_embeddings_rate_limits_enabled', false);
		$this->assertFalse($this->limiter->is_enabled());

		$this->limiter->record_usage(100);
		$stats = $this->limiter->get_usage_stats();

		$this->assertFalse($stats['is_rate_limited']);
		$this->assertTrue($this->limiter->check_limits(50));
	}

	public function test_sliding_window_pruning() {
		$now = time();
		// Inject historical timestamps: 1 entry 35 days ago, 1 entry 10 days ago, 1 entry 2 days ago, 1 entry now
		$history = array(
			$now - (35 * DAY_IN_SECONDS),
			$now - (10 * DAY_IN_SECONDS),
			$now - (2 * DAY_IN_SECONDS),
			$now,
		);
		update_option(AIPS_Embeddings_Rate_Limiter::OPTION_NAME, $history, false);

		// Record a new entry, which triggers 30-day pruning
		$this->limiter->record_usage(1);

		$stored = $this->limiter->get_usage_history();
		$this->assertCount(4, $stored); // 35d old entry removed, 3 existing + 1 new = 4

		$stats = $this->limiter->get_usage_stats();
		$this->assertSame(2, $stats['daily_count']); // now + 1 new
		$this->assertSame(3, $stats['weekly_count']); // 2d + now + 1 new
		$this->assertSame(4, $stats['monthly_count']); // 10d + 2d + now + 1 new
	}

	public function test_reset_history_clears_all() {
		$this->limiter->record_usage(10);
		$this->assertNotEmpty($this->limiter->get_usage_history());

		$this->limiter->reset_history();
		$this->assertEmpty($this->limiter->get_usage_history());
		$stats = $this->limiter->get_usage_stats();
		$this->assertSame(0, $stats['daily_count']);
	}

	public function test_execute_success_calls_callbacks_and_records_usage() {
		$success_called = false;
		$failure_called = false;

		$result = $this->limiter->execute(
			function () {
				return array('vector' => array(0.1, 0.2, 0.3));
			},
			function ($res) use (&$success_called) {
				$success_called = true;
				$this->assertIsArray($res);
			},
			function ($err) use (&$failure_called) {
				$failure_called = true;
			},
			2
		);

		$this->assertIsArray($result);
		$this->assertTrue($success_called);
		$this->assertFalse($failure_called);

		$stats = $this->limiter->get_usage_stats(true);
		$this->assertSame(2, $stats['daily_count']);
	}

	public function test_execute_blocks_when_rate_limited() {
		$this->limiter->record_usage(5); // Reach daily limit of 5
		$failure_called = false;

		$result = $this->limiter->execute(
			function () {
				return 'should_not_run';
			},
			null,
			function ($err) use (&$failure_called) {
				$failure_called = true;
				$this->assertWPError($err);
				$this->assertSame('rate_limit_exceeded', $err->get_error_code());
			}
		);

		$this->assertWPError($result);
		$this->assertSame('rate_limit_exceeded', $result->get_error_code());
		$this->assertTrue($failure_called);
	}

	public function test_execute_blocks_when_in_cooldown() {
		update_option('aips_indexer_paused_until', AIPS_DateTime::now()->advance('+30 minutes')->timestamp(), false);
		update_option('aips_indexer_pause_reason', 'Testing cooldown guard', false);

		$failure_called = false;
		$result = $this->limiter->execute(
			function () {
				return 'should_not_run';
			},
			null,
			function ($err) use (&$failure_called) {
				$failure_called = true;
				$this->assertWPError($err);
				$this->assertSame('embeddings_cooldown_active', $err->get_error_code());
			}
		);

		$this->assertWPError($result);
		$this->assertSame('embeddings_cooldown_active', $result->get_error_code());
		$this->assertTrue($failure_called);

		$this->limiter->clear_cooldown();
		$this->assertFalse($this->limiter->is_in_cooldown());
	}

	public function test_execute_catches_exception() {
		$failure_called = false;

		$result = $this->limiter->execute(
			function () {
				throw new \RuntimeException('Remote API connection dropped');
			},
			null,
			function ($err) use (&$failure_called) {
				$failure_called = true;
				$this->assertWPError($err);
				$this->assertSame('embedding_execution_exception', $err->get_error_code());
			}
		);

		$this->assertWPError($result);
		$this->assertSame('embedding_execution_exception', $result->get_error_code());
		$this->assertTrue($failure_called);
	}

	public function test_execute_non_fault_error_does_not_increment_consecutive_errors() {
		delete_transient('aips_indexer_consecutive_errors');

		// Simulating an operation returning a non-fault code like 'embeddings_disabled'
		$result = $this->limiter->execute(
			function () {
				return new WP_Error('embeddings_disabled', 'System disabled');
			}
		);

		$this->assertWPError($result);
		$this->assertSame('embeddings_disabled', $result->get_error_code());
		$this->assertSame(0, (int) get_transient('aips_indexer_consecutive_errors'));
	}
}
