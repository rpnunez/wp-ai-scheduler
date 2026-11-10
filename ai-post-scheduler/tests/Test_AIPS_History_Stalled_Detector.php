<?php
/**
 * Tests for AIPS_History_Stalled_Detector
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Stalled_Detector extends WP_UnitTestCase {

	/**
	 * @var AIPS_History_Stalled_Detector
	 */
	private $detector;

	/**
	 * @var int[]
	 */
	private $inserted_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->detector     = new AIPS_History_Stalled_Detector();
		$this->inserted_ids = array();
	}

	public function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';
		foreach ($this->inserted_ids as $id) {
			$wpdb->delete($table, array('id' => $id), array('%d'));
		}
		parent::tearDown();
	}

	public function test_threshold_seconds_defaults() {
		// Non-chain methods default to 900 seconds
		$this->assertSame(900, AIPS_History_Stalled_Detector::stalled_threshold_seconds('manual'));
		$this->assertSame(900, AIPS_History_Stalled_Detector::stalled_threshold_seconds(''));

		// Chain methods default to 21600 seconds
		$this->assertSame(21600, AIPS_History_Stalled_Detector::stalled_threshold_seconds('bulk_generator'));
		$this->assertSame(21600, AIPS_History_Stalled_Detector::stalled_threshold_seconds('content_indexer'));
	}

	public function test_threshold_seconds_filter_override() {
		add_filter('aips_history_stalled_after_seconds', function($seconds, $method) {
			if ($method === 'custom_method') {
				return 120;
			}
			return $seconds;
		}, 10, 2);

		$this->assertSame(120, AIPS_History_Stalled_Detector::stalled_threshold_seconds('custom_method'));

		// Filter cannot set below 60
		add_filter('aips_history_stalled_after_seconds', function() {
			return 10;
		});

		$this->assertSame(60, AIPS_History_Stalled_Detector::stalled_threshold_seconds('custom_method'));
	}

	public function test_resolve_effective_status() {
		$now = 1000000;

		// Non-processing statuses remain unchanged
		$this->assertSame('completed', AIPS_History_Stalled_Detector::resolve_effective_status('completed', 'manual', $now - 5000, $now));
		$this->assertSame('failed', AIPS_History_Stalled_Detector::resolve_effective_status('failed', 'manual', $now - 5000, $now));

		// Active processing run remains 'processing'
		$this->assertSame('processing', AIPS_History_Stalled_Detector::resolve_effective_status('processing', 'manual', $now - 100, $now));

		// Idle processing run past threshold resolves to 'stalled'
		$this->assertSame('stalled', AIPS_History_Stalled_Detector::resolve_effective_status('processing', 'manual', $now - 1000, $now));
	}

	public function test_stalled_condition_structure() {
		list($sql, $args) = $this->detector->stalled_condition('h');

		$this->assertStringContainsString("h.status = 'processing'", $sql);
		$this->assertIsArray($args);
		$this->assertNotEmpty($args);
	}

	public function test_stalled_summary_and_close() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		$now = AIPS_DateTime::now()->timestamp();
		$idle_created = $now - 2000; // Past 900s threshold

		// Insert a stalled processing row
		$wpdb->insert($table, array(
			'generated_title' => 'Stalled Run',
			'record_kind'     => 'generation',
			'status'          => 'processing',
			'creation_method' => 'manual',
			'created_at'      => $idle_created,
		));
		$history_id           = (int) $wpdb->insert_id;
		$this->inserted_ids[] = $history_id;

		$summary = $this->detector->get_stalled_summary(AIPS_History_Record_Kind::GENERATION);
		$this->assertGreaterThanOrEqual(1, $summary['count']);
		$this->assertNotNull($summary['oldest_idle_seconds']);

		// Close stalled rows
		$closed = $this->detector->close_stalled_processing_rows(AIPS_History_Record_Kind::GENERATION);
		$this->assertGreaterThanOrEqual(1, $closed);

		// Verify record is terminated
		$updated_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $history_id));
		$this->assertSame('terminated', $updated_status);
	}
}
