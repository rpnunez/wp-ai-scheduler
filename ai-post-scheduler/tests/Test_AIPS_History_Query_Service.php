<?php

/**
 * Class Test_AIPS_History_Query_Service
 *
 * Tests for the history query methods now hosted on AIPS_History_Repository.
 * The AIPS_History_Query_Service class remains as a BC shim; both entry points
 * are exercised here.
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/includes/class-aips-history-query-service.php';

class Test_AIPS_History_Query_Service extends WP_UnitTestCase {

    private $shim;
    private $repository;

    public function setUp(): void {
        parent::setUp();
        $this->shim = new AIPS_History_Query_Service();
        $this->repository = AIPS_History_Repository::instance();
    }

    public function test_get_auxiliary_creation_methods_is_private_on_repository() {
        $reflection = new ReflectionClass(AIPS_History_Repository::class);
        $method = $reflection->getMethod('get_auxiliary_creation_methods');
        $this->assertTrue($method->isPrivate());

        $method->setAccessible(true);
        $methods = $method->invoke($this->repository);

        $this->assertIsArray($methods);
        $this->assertContains('schedule_lifecycle', $methods);
        $this->assertContains('template_lifecycle', $methods);
    }

    public function test_get_history_returns_expected_structure() {
        $result = $this->shim->get_history();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
    }

    public function test_get_partial_generations_returns_expected_structure() {
        $result = $this->shim->get_partial_generations();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
    }

    public function test_shim_delegates_to_repository() {
        $direct = $this->repository->get_history();
        $through_shim = $this->shim->get_history();
        $this->assertSame(array_keys($direct), array_keys($through_shim));
    }

    public function test_extract_timeline_post_entity_data() {
        require_once dirname(__DIR__) . '/includes/class-aips-history.php';
        $history = new AIPS_History();
        $ref = new ReflectionClass($history);
        $method = $ref->getMethod('extract_timeline_post_entity_data');
        $method->setAccessible(true);

        $post_id = self::factory()->post->create(array('post_title' => 'Test Post', 'post_content' => 'Test content excerpt text for testing timeline helper.'));
        update_post_meta($post_id, 'aips_custom_meta', 'meta_value');
        update_post_meta($post_id, '_aips_generated_post', '1');

        $container = array('post_id' => $post_id);
        $history_item = (object) array(
            'post_id'         => $post_id,
            'creation_method' => 'post_generation',
        );

        $res = $method->invoke($history, $container, $history_item);
        $this->assertSame('Post', $res['post_type_label']);
        $this->assertSame('Test Post', $res['generated_title']);
        $this->assertTrue($res['has_content']);
        $this->assertTrue($res['show_milestones']);
        $this->assertNotEmpty($res['custom_fields']);

        $custom_field_names = wp_list_pluck($res['custom_fields'], 'name');
        $this->assertContains('aips_custom_meta', $custom_field_names);
        $this->assertNotContains('_aips_generated_post', $custom_field_names);
    }

    public function test_extract_timeline_post_entity_data_fallback_with_null_history_item() {
        require_once dirname(__DIR__) . '/includes/class-aips-history.php';
        $history = new AIPS_History();
        $ref = new ReflectionClass($history);
        $method = $ref->getMethod('extract_timeline_post_entity_data');
        $method->setAccessible(true);

        $container = array();
        $res = $method->invoke($history, $container, null);

        $this->assertSame('Post', $res['post_type_label']);
        $this->assertSame('', $res['generated_title']);
        $this->assertFalse($res['has_content']);
        $this->assertFalse($res['show_milestones']);
        $this->assertSame('none', $res['image_status']);
        $this->assertSame(array(), $res['custom_fields']);
    }

    public function test_format_timeline_chronological_events() {
        require_once dirname(__DIR__) . '/includes/class-aips-history.php';
        $history = new AIPS_History();
        $ref = new ReflectionClass($history);
        $method = $ref->getMethod('format_timeline_chronological_events');
        $method->setAccessible(true);

        $display_logs = array(
            array(
                'log_type' => 'info',
                'timestamp' => time(),
                'sections' => array(array('message_html' => 'Log message 1')),
                'type_ids' => array('info'),
                'type_class' => 'info-class',
            ),
            array(
                'log_type' => 'warning',
                'timestamp' => time(),
                'sections' => array(array('message_html' => 'Warning message alert')),
                'type_ids' => array('warning'),
                'type_class' => 'warning-class',
            ),
        );

        $res = $method->invoke($history, $display_logs);
        $this->assertSame('Warning message alert', $res['warning_text']);
        $this->assertCount(2, $res['formatted_events']);
    }
}
