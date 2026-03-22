<?php
/**
 * Compatibility tests for migrated HistoryContainer class.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Container_Compat extends WP_UnitTestCase {

    public function test_namespaced_class_exists() {
        $this->assertTrue(class_exists('AIPS\\History\\HistoryContainer'));
    }

    public function test_legacy_class_alias_maps_to_namespaced_class() {
        $repository = $this->getMockBuilder('AIPS_History_Repository')
            ->disableOriginalConstructor()
            ->onlyMethods(array('create', 'get_by_id', 'add_log_entry', 'update', 'get_by_post_id'))
            ->getMock();

        $repository->method('create')->willReturn(101);
        $repository->method('get_by_id')->willReturn(null);
        $repository->method('add_log_entry')->willReturn(202);
        $repository->method('update')->willReturn(true);
        $repository->method('get_by_post_id')->willReturn(null);

        $container = new AIPS_History_Container($repository, 'post_generation', array('post_id' => 55));

        $this->assertInstanceOf('AIPS\\History\\HistoryContainer', $container);
        $this->assertSame(101, $container->get_id());
        $this->assertSame('post_generation', $container->get_type());
        $this->assertNotEmpty($container->get_uuid());
    }
}


