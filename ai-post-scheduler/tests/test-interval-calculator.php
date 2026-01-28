<?php
/**
 * Test case for Interval Calculator
 *
 * Tests the extraction and functionality of AIPS_Interval_Calculator class.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

class Test_AIPS_Interval_Calculator extends WP_UnitTestCase {

    private $calculator;

    public function setUp(): void {
        parent::setUp();
        $this->calculator = new AIPS_Interval_Calculator();
    }

    /**
     * Test get_intervals returns expected structure
     */
    public function test_get_intervals_structure() {
        $intervals = $this->calculator->get_intervals();
        
        $this->assertIsArray($intervals);
        $this->assertNotEmpty($intervals);
        
        // Check basic intervals exist
        $this->assertArrayHasKey('hourly', $intervals);
        $this->assertArrayHasKey('daily', $intervals);
        $this->assertArrayHasKey('weekly', $intervals);
        $this->assertArrayHasKey('monthly', $intervals);
        
        // Check each interval has required keys
        foreach ($intervals as $key => $data) {
            $this->assertArrayHasKey('interval', $data);
            $this->assertArrayHasKey('display', $data);
            $this->assertIsInt($data['interval']);
            $this->assertIsString($data['display']);
        }
    }

    /**
     * Test day-specific intervals are created
     */
    public function test_day_specific_intervals() {
        $intervals = $this->calculator->get_intervals();
        
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        
        foreach ($days as $day) {
            $key = 'every_' . $day;
            $this->assertArrayHasKey($key, $intervals);
            $this->assertEquals(604800, $intervals[$key]['interval']); // Weekly
        }
    }

    /**
     * Test calculate_next_run for hourly frequency
     */
    public function test_calculate_next_run_hourly() {
        // Use a future date to avoid catch-up logic
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('hourly', $start);
        
        $expected = date('Y-m-d 11:00:00', strtotime($start));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for daily frequency
     */
    public function test_calculate_next_run_daily() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('daily', $start);
        
        $expected = date('Y-m-d 10:00:00', strtotime('+1 day', strtotime($start)));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for weekly frequency
     */
    public function test_calculate_next_run_weekly() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('weekly', $start);
        
        $expected = date('Y-m-d 10:00:00', strtotime('+1 week', strtotime($start)));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for monthly frequency
     */
    public function test_calculate_next_run_monthly() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('monthly', $start);
        
        $expected = date('Y-m-d 10:00:00', strtotime('+1 month', strtotime($start)));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for every 4 hours
     */
    public function test_calculate_next_run_every_4_hours() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('every_4_hours', $start);
        
        $expected = date('Y-m-d 14:00:00', strtotime($start));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for every 6 hours
     */
    public function test_calculate_next_run_every_6_hours() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('every_6_hours', $start);
        
        $expected = date('Y-m-d 16:00:00', strtotime($start));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for every 12 hours
     */
    public function test_calculate_next_run_every_12_hours() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('every_12_hours', $start);
        
        $expected = date('Y-m-d 22:00:00', strtotime($start));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for bi-weekly frequency
     */
    public function test_calculate_next_run_bi_weekly() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('bi_weekly', $start);
        
        $expected = date('Y-m-d 10:00:00', strtotime('+2 weeks', strtotime($start)));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for day-specific frequency
     */
    public function test_calculate_next_run_day_specific() {
        // Start from next Monday, +1 year to be safe in future
        $start_timestamp = strtotime('next Monday 10:00:00', strtotime('+1 year'));
        $start = date('Y-m-d H:i:s', $start_timestamp);

        $next = $this->calculator->calculate_next_run('every_monday', $start);
        
        // Next Monday should be +1 week
        $expected = date('Y-m-d H:i:s', strtotime('+1 week', $start_timestamp));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run preserves time for day-specific
     */
    public function test_calculate_next_run_preserves_time() {
        // Start from next Monday, +1 year to be safe in future
        $start_timestamp = strtotime('next Monday 14:30:00', strtotime('+1 year'));
        $start = date('Y-m-d H:i:s', $start_timestamp);

        // Calculate for next Wednesday
        $next = $this->calculator->calculate_next_run('every_wednesday', $start);
        
        // Next Wednesday should be the Wednesday after the start date
        $expected = date('Y-m-d H:i:s', strtotime('next Wednesday 14:30:00', $start_timestamp));
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run without start time uses current time
     */
    public function test_calculate_next_run_without_start_time() {
        $next = $this->calculator->calculate_next_run('daily');
        
        // Should return a datetime string
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $next);
        
        // Should be in the future
        $this->assertGreaterThan(current_time('mysql'), $next);
    }

    /**
     * Test calculate_next_run with past time uses current time
     */
    public function test_calculate_next_run_with_past_time() {
        $start = '2020-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('daily', $start);
        
        // Should be in the future, not based on 2020
        $next_timestamp = strtotime($next);
        $current_timestamp = current_time('timestamp');
        
        $this->assertGreaterThan($current_timestamp, $next_timestamp);
    }

    /**
     * Test get_interval_duration returns correct seconds
     */
    public function test_get_interval_duration() {
        $this->assertEquals(3600, $this->calculator->get_interval_duration('hourly'));
        $this->assertEquals(86400, $this->calculator->get_interval_duration('daily'));
        $this->assertEquals(604800, $this->calculator->get_interval_duration('weekly'));
        $this->assertEquals(2592000, $this->calculator->get_interval_duration('monthly'));
    }

    /**
     * Test get_interval_duration with invalid frequency
     */
    public function test_get_interval_duration_invalid() {
        $this->assertEquals(0, $this->calculator->get_interval_duration('invalid_frequency'));
    }

    /**
     * Test get_interval_display returns human-readable name
     */
    public function test_get_interval_display() {
        $display = $this->calculator->get_interval_display('daily');
        
        $this->assertIsString($display);
        $this->assertNotEmpty($display);
        $this->assertStringContainsString('Daily', $display);
    }

    /**
     * Test get_interval_display with invalid frequency
     */
    public function test_get_interval_display_invalid() {
        $frequency = 'invalid_frequency';
        $display = $this->calculator->get_interval_display($frequency);
        
        // Should return the frequency itself
        $this->assertEquals($frequency, $display);
    }

    /**
     * Test is_valid_frequency with valid frequencies
     */
    public function test_is_valid_frequency_valid() {
        $this->assertTrue($this->calculator->is_valid_frequency('hourly'));
        $this->assertTrue($this->calculator->is_valid_frequency('daily'));
        $this->assertTrue($this->calculator->is_valid_frequency('weekly'));
        $this->assertTrue($this->calculator->is_valid_frequency('every_monday'));
    }

    /**
     * Test is_valid_frequency with invalid frequency
     */
    public function test_is_valid_frequency_invalid() {
        $this->assertFalse($this->calculator->is_valid_frequency('invalid'));
        $this->assertFalse($this->calculator->is_valid_frequency('every_someday'));
        $this->assertFalse($this->calculator->is_valid_frequency(''));
    }

    /**
     * Test merge_with_wp_schedules adds intervals
     */
    public function test_merge_with_wp_schedules() {
        $existing_schedules = array(
            'hourly' => array('interval' => 3600, 'display' => 'Once Hourly'),
        );
        
        $merged = $this->calculator->merge_with_wp_schedules($existing_schedules);
        
        $this->assertIsArray($merged);
        $this->assertArrayHasKey('hourly', $merged);
        $this->assertArrayHasKey('daily', $merged);
        $this->assertArrayHasKey('every_4_hours', $merged);
        
        // Should not override existing schedules
        $this->assertEquals('Once Hourly', $merged['hourly']['display']);
    }

    /**
     * Test calculate_next_run with invalid frequency defaults to daily
     */
    public function test_calculate_next_run_invalid_defaults_to_daily() {
        $start = date('Y-m-d 10:00:00', strtotime('+1 year'));
        $next = $this->calculator->calculate_next_run('invalid_frequency', $start);
        
        // Should default to +1 day
        $expected = date('Y-m-d 10:00:00', strtotime('+1 day', strtotime($start)));
        $this->assertEquals($expected, $next);
    }
}
