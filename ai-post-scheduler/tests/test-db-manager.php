<?php
/**
 * Test case for DB Manager
 *
 * Tests the AIPS_DB_Manager class methods.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_DB_Manager extends WP_UnitTestCase {

    private $original_prefix;

    public function setUp() {
        parent::setUp();
        global $wpdb;
        // Store original prefix
        $this->original_prefix = $wpdb->prefix;
        // Set standard test prefix
        $wpdb->prefix = 'wp_';
    }

    public function tearDown() {
        global $wpdb;
        // Restore original prefix
        $wpdb->prefix = $this->original_prefix;
        parent::tearDown();
    }

    /**
     * Test get_table_name with valid table key
     */
    public function test_get_table_name_with_valid_table() {
        $result = AIPS_DB_Manager::get_table_name('history');
        
        $this->assertEquals('wp_aips_history', $result);
    }

    /**
     * Test get_table_name with templates table key
     */
    public function test_get_table_name_with_templates_table() {
        $result = AIPS_DB_Manager::get_table_name('templates');
        
        $this->assertEquals('wp_aips_templates', $result);
    }

    /**
     * Test get_table_name with schedule table key
     */
    public function test_get_table_name_with_schedule_table() {
        $result = AIPS_DB_Manager::get_table_name('schedule');
        
        $this->assertEquals('wp_aips_schedule', $result);
    }

    /**
     * Test get_table_name with voices table key
     */
    public function test_get_table_name_with_voices_table() {
        $result = AIPS_DB_Manager::get_table_name('voices');
        
        $this->assertEquals('wp_aips_voices', $result);
    }

    /**
     * Test get_table_name with invalid table key
     */
    public function test_get_table_name_with_invalid_table() {
        $result = AIPS_DB_Manager::get_table_name('nonexistent');
        
        $this->assertNull($result);
    }

    /**
     * Test get_table_name with empty string
     */
    public function test_get_table_name_with_empty_string() {
        $result = AIPS_DB_Manager::get_table_name('');
        
        $this->assertNull($result);
    }

    /**
     * Test get_table_name with non-string input (integer)
     */
    public function test_get_table_name_with_integer_input() {
        $result = AIPS_DB_Manager::get_table_name(123);
        
        $this->assertNull($result);
    }

    /**
     * Test get_table_name with non-string input (array)
     */
    public function test_get_table_name_with_array_input() {
        $result = AIPS_DB_Manager::get_table_name(array('aips_history'));
        
        $this->assertNull($result);
    }

    /**
     * Test get_table_name with null input
     */
    public function test_get_table_name_with_null_input() {
        $result = AIPS_DB_Manager::get_table_name(null);
        
        $this->assertNull($result);
    }

    /**
     * Test get_table_name with different prefix
     */
    public function test_get_table_name_with_custom_prefix() {
        global $wpdb;
        $wpdb->prefix = 'custom_';
        
        $result = AIPS_DB_Manager::get_table_name('history');
        
        $this->assertEquals('custom_aips_history', $result);
    }

    /**
     * Test that all tables from get_table_names work with get_table_name
     */
    public function test_get_table_name_with_all_tables() {
        $table_names = AIPS_DB_Manager::get_table_names();
        
        // Verify we can retrieve each table by its key
        $result = AIPS_DB_Manager::get_table_name('history');
        $this->assertNotNull($result);
        $this->assertEquals('wp_aips_history', $result);
        
        $result = AIPS_DB_Manager::get_table_name('templates');
        $this->assertNotNull($result);
        $this->assertEquals('wp_aips_templates', $result);
        
        $result = AIPS_DB_Manager::get_table_name('schedule');
        $this->assertNotNull($result);
        $this->assertEquals('wp_aips_schedule', $result);
        
        $result = AIPS_DB_Manager::get_table_name('voices');
        $this->assertNotNull($result);
        $this->assertEquals('wp_aips_voices', $result);
        
        // Verify get_table_names returns the table names (not keys)
        $this->assertContains('aips_history', $table_names);
        $this->assertContains('aips_templates', $table_names);
        $this->assertContains('aips_schedule', $table_names);
        $this->assertContains('aips_voices', $table_names);
    }
}
