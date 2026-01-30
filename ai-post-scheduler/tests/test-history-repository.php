<?php
/**
 * Test case for History Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Repository extends WP_UnitTestCase {

    private $repository;
    private $mock_wpdb;

    public function setUp(): void {
        parent::setUp();

        // Mock $wpdb
        $this->mock_wpdb = $this->getMockBuilder('stdClass')
            ->addMethods(['get_results', 'get_row', 'get_var', 'insert', 'update', 'delete', 'prepare', 'query', 'esc_like'])
            ->getMock();

        $this->mock_wpdb->prefix = 'wp_';
        $this->mock_wpdb->insert_id = 123;

        // Default behaviors
        $this->mock_wpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            if (isset($args[0]) && is_array($args[0])) {
                $args = $args[0];
            }
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });

        $this->mock_wpdb->method('esc_like')->willReturnCallback(function($text) {
            return $text;
        });

        $GLOBALS['wpdb'] = $this->mock_wpdb;

        $this->repository = new AIPS_History_Repository();
    }

    public function test_get_history() {
        $expected_results = array(
            (object) array('id' => 1, 'generated_title' => 'Test Post 1', 'status' => 'completed'),
            (object) array('id' => 2, 'generated_title' => 'Test Post 2', 'status' => 'failed'),
        );

        $this->mock_wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn($expected_results);

        $this->mock_wpdb->expects($this->once())
            ->method('get_var')
            ->willReturn(2); // Total count

        $result = $this->repository->get_history();

        $this->assertIsArray($result);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['pages']);
    }

    public function test_get_by_id() {
        $expected_history = (object) array('id' => 1, 'generated_title' => 'Test Post');
        $expected_logs = array((object) array('id' => 1, 'message' => 'Log 1'));

        $this->mock_wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($expected_history);

        $this->mock_wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn($expected_logs);

        $result = $this->repository->get_by_id(1);

        $this->assertEquals($expected_history, $result);
        $this->assertEquals($expected_logs, $result->log);
    }

    public function test_create() {
        $data = array(
            'template_id' => 1,
            'status' => 'pending',
            'prompt' => 'Test Prompt',
            'generated_title' => 'Test Title',
        );

        $this->mock_wpdb->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('aips_history'),
                $this->callback(function($insert_data) {
                    return $insert_data['generated_title'] === 'Test Title';
                }),
                $this->anything()
            )
            ->willReturn(true);

        $result = $this->repository->create($data);

        $this->assertEquals(123, $result);
    }

    public function test_update() {
        $data = array(
            'status' => 'completed',
            'completed_at' => '2023-01-01 12:00:00'
        );

        $this->mock_wpdb->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('aips_history'),
                $this->callback(function($update_data) {
                    return $update_data['status'] === 'completed';
                }),
                array('id' => 1),
                $this->anything(),
                array('%d')
            )
            ->willReturn(true);

        $result = $this->repository->update(1, $data);

        $this->assertTrue($result);
    }

    public function test_delete() {
        $this->mock_wpdb->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('aips_history'),
                array('id' => 1),
                array('%d')
            )
            ->willReturn(1);

        $result = $this->repository->delete(1);

        $this->assertTrue($result);
    }

    public function test_delete_bulk() {
        $ids = array(1, 2, 3);

        $this->mock_wpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains("DELETE FROM wp_aips_history WHERE id IN (1,2,3)"))
            ->willReturn(3);

        $result = $this->repository->delete_bulk($ids);

        $this->assertEquals(3, $result);
    }

    public function test_get_stats() {
        $stats_obj = (object) array(
            'total' => 10,
            'completed' => 5,
            'failed' => 2,
            'processing' => 3
        );

        $this->mock_wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($stats_obj);

        // Mock get_transient to return false so we hit the DB
        $GLOBALS['aips_test_transients']['aips_history_stats'] = false;

        $result = $this->repository->get_stats();

        $this->assertEquals(10, $result['total']);
        $this->assertEquals(5, $result['completed']);
    }
}

// Helper functions for transient mocking if not present
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '') {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r = &$args;
        } else {
            wp_parse_str($args, $r);
        }

        if (is_array($defaults)) {
            return array_merge($defaults, $r);
        }
        return $r;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array) {
        parse_str($string, $array);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return isset($GLOBALS['aips_test_transients'][$transient]) ? $GLOBALS['aips_test_transients'][$transient] : false;
    }
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['aips_test_transients'][$transient] = $value;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        if (isset($GLOBALS['aips_test_transients'][$transient])) {
            unset($GLOBALS['aips_test_transients'][$transient]);
        }
        return true;
    }
}
