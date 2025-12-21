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

    public function setUp() {
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
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('hourly', $start);
        
        $expected = '2024-01-01 11:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for daily frequency
     */
    public function test_calculate_next_run_daily() {
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('daily', $start);
        
        $expected = '2024-01-02 10:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for weekly frequency
     */
    public function test_calculate_next_run_weekly() {
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('weekly', $start);
        
        $expected = '2024-01-08 10:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for monthly frequency
     */
    public function test_calculate_next_run_monthly() {
        $start = '2024-01-15 10:00:00';
        $next = $this->calculator->calculate_next_run('monthly', $start);
        
        $expected = '2024-02-15 10:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for every 4 hours
     */
    public function test_calculate_next_run_every_4_hours() {
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('every_4_hours', $start);
        
        $expected = '2024-01-01 14:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for every 6 hours
     */
    public function test_calculate_next_run_every_6_hours() {
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('every_6_hours', $start);
        
        $expected = '2024-01-01 16:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for every 12 hours
     */
    public function test_calculate_next_run_every_12_hours() {
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('every_12_hours', $start);
        
        $expected = '2024-01-01 22:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for bi-weekly frequency
     */
    public function test_calculate_next_run_bi_weekly() {
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('bi_weekly', $start);
        
        $expected = '2024-01-15 10:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run for day-specific frequency
     */
    public function test_calculate_next_run_day_specific() {
        // January 1, 2024 is a Monday
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('every_monday', $start);
        
        // Next Monday should be January 8, 2024
        $expected = '2024-01-08 10:00:00';
        $this->assertEquals($expected, $next);
    }

    /**
     * Test calculate_next_run preserves time for day-specific
     */
    public function test_calculate_next_run_preserves_time() {
        // January 1, 2024 is a Monday, set time to 14:30
        $start = '2024-01-01 14:30:00';
        $next = $this->calculator->calculate_next_run('every_wednesday', $start);
        
        // Next Wednesday should be January 3, 2024 at 14:30
        $expected = '2024-01-03 14:30:00';
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
        $start = '2024-01-01 10:00:00';
        $next = $this->calculator->calculate_next_run('invalid_frequency', $start);
        
        // Should default to +1 day
        $expected = '2024-01-02 10:00:00';
        $this->assertEquals($expected, $next);
    }
}
