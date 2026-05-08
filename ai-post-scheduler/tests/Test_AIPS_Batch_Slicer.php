<?php
/**
 * Tests for AIPS_Batch_Slicer
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Batch_Slicer extends WP_UnitTestCase {

	private $slicer;

	public function setUp(): void {
		parent::setUp();
		$this->slicer = new AIPS_Batch_Slicer();
	}

	public function test_needs_batching_returns_false_below_threshold() {
		$this->assertFalse($this->slicer->needs_batching(3));
		$this->assertFalse($this->slicer->needs_batching(4));
	}

	public function test_needs_batching_returns_true_at_threshold() {
		// Default threshold is 5
		$this->assertTrue($this->slicer->needs_batching(5));
		$this->assertTrue($this->slicer->needs_batching(10));
		$this->assertTrue($this->slicer->needs_batching(100));
	}

	public function test_needs_batching_respects_context_filter() {
		// Add filter for custom context
		add_filter('aips_batch_threshold_custom', function() {
			return 10;
		});

		$this->assertFalse($this->slicer->needs_batching(8, 'custom'));
		$this->assertTrue($this->slicer->needs_batching(10, 'custom'));

		remove_all_filters('aips_batch_threshold_custom');
	}

	public function test_calculate_slices_basic_division() {
		$config = $this->slicer->calculate_slices(10);

		$this->assertInstanceOf('AIPS_Slice_Configuration', $config);
		$this->assertEquals(5, $config->get_num_slices()); // aim for ~2 per slice
		$this->assertEquals(2, $config->get_items_per_slice());
		$this->assertEquals(10, $config->get_total_items());
	}

	public function test_calculate_slices_respects_max_slices() {
		// Large item count should be capped at max_slices
		$config = $this->slicer->calculate_slices(100);

		$this->assertEquals(10, $config->get_num_slices()); // default max
		$this->assertEquals(10, $config->get_items_per_slice()); // 100 / 10
	}

	public function test_calculate_slices_with_custom_max_slices() {
		$config = $this->slicer->calculate_slices(50, array(
			'max_slices' => 5,
		));

		$this->assertEquals(5, $config->get_num_slices());
		$this->assertEquals(10, $config->get_items_per_slice()); // 50 / 5
	}

	public function test_calculate_slices_with_custom_window() {
		$config = $this->slicer->calculate_slices(10, array(
			'window_seconds' => 1200, // 20 minutes
		));

		$this->assertEquals(1200, $config->get_window_seconds());
		// interval = window / (num_slices - 1)
		// with 5 slices: 1200 / 4 = 300 seconds
		$this->assertEquals(300.0, $config->get_interval_seconds());
	}

	public function test_calculate_slices_single_slice_for_one_item() {
		$config = $this->slicer->calculate_slices(1);

		$this->assertEquals(1, $config->get_num_slices());
		$this->assertEquals(1, $config->get_items_per_slice());
		$this->assertEquals(0.0, $config->get_interval_seconds()); // single slice = no interval
	}

	public function test_calculate_slices_respects_context_filters() {
		// Add filters for custom context
		add_filter('aips_batch_max_slices_test', function() {
			return 3;
		});
		add_filter('aips_batch_window_seconds_test', function() {
			return 300;
		});

		$config = $this->slicer->calculate_slices(20, array(
			'context' => 'test',
		));

		$this->assertEquals(3, $config->get_num_slices());
		$this->assertEquals(300, $config->get_window_seconds());

		remove_all_filters('aips_batch_max_slices_test');
		remove_all_filters('aips_batch_window_seconds_test');
	}

	public function test_get_threshold_default() {
		// Use reflection to test protected method
		$reflection = new ReflectionClass($this->slicer);
		$method = $reflection->getMethod('get_threshold');
		$method->setAccessible(true);

		$this->assertEquals(5, $method->invoke($this->slicer, 'default'));
	}

	public function test_get_max_slices_default() {
		$reflection = new ReflectionClass($this->slicer);
		$method = $reflection->getMethod('get_max_slices');
		$method->setAccessible(true);

		$this->assertEquals(10, $method->invoke($this->slicer, 'default'));
	}

	public function test_get_window_seconds_default() {
		$reflection = new ReflectionClass($this->slicer);
		$method = $reflection->getMethod('get_window_seconds');
		$method->setAccessible(true);

		$this->assertEquals(600, $method->invoke($this->slicer, 'default'));
	}
}
