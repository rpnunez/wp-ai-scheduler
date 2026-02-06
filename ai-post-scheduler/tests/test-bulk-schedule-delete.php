<?php
/**
 * Test Bulk Schedule Delete
 *
 * @package AI_Post_Scheduler
 */

class Test_Bulk_Schedule_Delete extends WP_UnitTestCase {

    public function test_repository_delete_bulk() {
        global $wpdb;

        // Mock wpdb->query to return true
        // In the bootstrap mock, query returns true by default.
        // We can't easily spy on it without a better mock library like Mockery.
        // But we can check if it runs without error.

        $repo = new AIPS_Schedule_Repository();

        // Assert the method exists
        $this->assertTrue(method_exists($repo, 'delete_bulk'), 'delete_bulk method should exist in AIPS_Schedule_Repository');

        $ids = array(1, 2, 3);
        $result = $repo->delete_bulk($ids);

        // If it returns a number (rows affected) or true, it passed the SQL generation check
        $this->assertTrue($result !== false);
    }

    public function test_scheduler_delete_bulk() {
        $scheduler = new AIPS_Scheduler();

        // Use reflection to mock the repository
        $mock_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
                          ->onlyMethods(array('delete_bulk'))
                          ->getMock();

        $mock_repo->expects($this->once())
                  ->method('delete_bulk')
                  ->with($this->equalTo(array(1, 2)))
                  ->willReturn(2);

        $reflection = new ReflectionClass($scheduler);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($scheduler, $mock_repo);

        // Assert the method exists
        $this->assertTrue(method_exists($scheduler, 'delete_schedule_bulk'), 'delete_schedule_bulk method should exist in AIPS_Scheduler');

        $count = $scheduler->delete_schedule_bulk(array(1, 2));
        $this->assertEquals(2, $count);
    }
}
