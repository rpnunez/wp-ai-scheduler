<?php
/**
 * Test case for Schedule Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Repository extends WP_UnitTestCase {

    private $repository;
    private $mock_wpdb;

    public function setUp(): void {
        parent::setUp();

        // Mock $wpdb
        $this->mock_wpdb = $this->getMockBuilder('stdClass')
            ->addMethods(['get_results', 'get_row', 'get_var', 'insert', 'update', 'delete', 'prepare', 'query', 'esc_like'])
            ->getMock();

        $this->mock_wpdb->prefix = 'wp_';
        $this->mock_wpdb->insert_id = 456;

        // Default behaviors
        $this->mock_wpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            if (isset($args[0]) && is_array($args[0])) {
                $args = $args[0];
            }
            // Simple mock prepare to handle placeholders
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });

        $GLOBALS['wpdb'] = $this->mock_wpdb;

        $this->repository = new AIPS_Schedule_Repository();
    }

    public function test_get_all() {
        $expected_results = array(
            (object) array('id' => 1, 'template_id' => 1, 'next_run' => '2023-01-01 12:00:00'),
            (object) array('id' => 2, 'template_id' => 2, 'next_run' => '2023-01-02 12:00:00'),
        );

        $this->mock_wpdb->expects($this->once())
            ->method('get_results')
            ->with($this->stringContains("SELECT s.*, t.name as template_name"))
            ->willReturn($expected_results);

        $result = $this->repository->get_all();

        $this->assertEquals($expected_results, $result);
    }

    public function test_get_by_id() {
        $expected_schedule = (object) array('id' => 1, 'template_id' => 1);

        $this->mock_wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($expected_schedule);

        $result = $this->repository->get_by_id(1);

        $this->assertEquals($expected_schedule, $result);
    }

    public function test_get_upcoming() {
        $expected_results = array(
            (object) array('id' => 1, 'next_run' => '2023-01-01 12:00:00'),
        );

        $this->mock_wpdb->expects($this->once())
            ->method('get_results')
            ->with($this->stringContains("LIMIT 5"))
            ->willReturn($expected_results);

        $result = $this->repository->get_upcoming(5);

        $this->assertEquals($expected_results, $result);
    }

    public function test_create() {
        $data = array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2023-01-01 12:00:00',
            'is_active' => 1,
            'topic' => 'Test Topic'
        );

        $this->mock_wpdb->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('aips_schedule'),
                $this->callback(function($insert_data) {
                    return $insert_data['topic'] === 'Test Topic' && $insert_data['frequency'] === 'daily';
                }),
                $this->anything()
            )
            ->willReturn(true);

        $result = $this->repository->create($data);

        $this->assertEquals(456, $result);
    }

    public function test_update() {
        $data = array(
            'frequency' => 'weekly',
            'is_active' => 0
        );

        $this->mock_wpdb->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('aips_schedule'),
                $this->callback(function($update_data) {
                    return $update_data['frequency'] === 'weekly' && $update_data['is_active'] === 0;
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
                $this->stringContains('aips_schedule'),
                array('id' => 1),
                array('%d')
            )
            ->willReturn(1);

        $result = $this->repository->delete(1);

        $this->assertTrue($result);
    }

    public function test_create_bulk() {
        $schedules = array(
            array(
                'template_id' => 1,
                'frequency' => 'daily',
                'next_run' => '2023-01-01 12:00:00',
                'is_active' => 1
            ),
            array(
                'template_id' => 2,
                'frequency' => 'weekly',
                'next_run' => '2023-01-02 12:00:00',
                'is_active' => 0
            )
        );

        $this->mock_wpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains("INSERT INTO wp_aips_schedule"))
            ->willReturn(2);

        $result = $this->repository->create_bulk($schedules);

        $this->assertEquals(2, $result);
    }
}

// Helper functions for transient mocking if not present
if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return isset($GLOBALS['aips_test_transients'][$transient]) ? $GLOBALS['aips_test_transients'][$transient] : false;
    }
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
