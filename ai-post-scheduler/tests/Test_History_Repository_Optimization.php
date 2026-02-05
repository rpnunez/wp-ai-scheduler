<?php
/**
 * Test History Repository Performance Optimization
 *
 * @package AI_Post_Scheduler
 */

class Test_History_Repository_Optimization extends WP_UnitTestCase {
    private $repository;
    private $original_wpdb;

    public function setUp(): void {
        parent::setUp();

        // Save original wpdb
        global $wpdb;
        $this->original_wpdb = $wpdb;

        // Mock wpdb for this test
        $wpdb = $this->getMockBuilder('stdClass')
            ->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like'))
            ->getMock();

        $wpdb->prefix = 'wp_';

        // Mock prepare to return the query with placeholders (simplified)
        $wpdb->method('prepare')->willReturnCallback(function($query, $args) {
            return $query; // In this test we just want to inspect the query structure
        });

        $wpdb->method('esc_like')->willReturnCallback(function($text) {
            return $text;
        });

        $this->repository = new AIPS_History_Repository();
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_get_history_default_selects_all() {
        global $wpdb;

        $wpdb->expects($this->once())
            ->method('get_results')
            ->with($this->callback(function($query) {
                // Should contain h.* by default or if specified
                return strpos($query, 'SELECT h.*') !== false;
            }))
            ->willReturn(array());

        $this->repository->get_history(array());
    }

    public function test_get_history_list_fields_optimization() {
        global $wpdb;

        $wpdb->expects($this->once())
            ->method('get_results')
            ->with($this->callback(function($query) {
                // Should NOT contain h.*
                if (strpos($query, 'SELECT h.*') !== false) {
                    return false;
                }
                // Should contain specific columns
                return strpos($query, 'h.id') !== false &&
                       strpos($query, 'h.generated_title') !== false &&
                       strpos($query, 'h.status') !== false;
            }))
            ->willReturn(array());

        $this->repository->get_history(array('fields' => 'list'));
    }
}
