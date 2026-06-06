<?php
/**
 * Test case for Manual Schedule Execution
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Manual_Schedule_Execution extends WP_UnitTestCase {

    private $scheduler;

    public function setUp(): void {
        parent::setUp();
        $this->scheduler = new AIPS_Scheduler();
    }

    /**
     * Helper to build a configured scheduler with mocked dependencies.
     *
     * @param object   $schedule         The schedule object returned by the repository.
     * @param object   $template         The template object returned by the repository.
     * @param int      $expected_post_id The post ID the mock generator should return.
     * @param int|null $expected_qty     The post_quantity expected inside the AIPS_Template_Context.
     * @return AIPS_Scheduler
     */
    private function build_scheduler_with_mocks($schedule, $template, $expected_post_id, $expected_qty = null) {
        // Schedule repository mock
        $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_schedule_repo->method('get_by_id')
            ->with($schedule->id)
            ->willReturn($schedule);

        $this->scheduler->set_repository($mock_schedule_repo);

        // Template repository mock
        $mock_template_repo = $this->getMockBuilder('AIPS_Template_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_template_repo->method('get_by_id')
            ->with($template->id)
            ->willReturn($template);

        $this->scheduler->set_template_repository($mock_template_repo);

        // Generator mock — generate_post receives a single AIPS_Template_Context argument.
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        $expected_call_count = $expected_qty ? $expected_qty : 1;
        $mock_generator->expects($this->exactly($expected_call_count))
            ->method('generate_post')
            ->with(
                $this->callback(function($context) use ($expected_qty) {
                    if (!($context instanceof AIPS_Template_Context)) {
                        return false;
                    }
                    if ($expected_qty === null) {
                        return true;
                    }
                    return $context->get_template()->post_quantity === $expected_qty;
                })
            )
            ->willReturn($expected_post_id);

        $this->scheduler->set_generator($mock_generator);

        return $this->scheduler;
    }

    /**
     * Verifies that run_schedule_now() uses the template's post_quantity (not a hard-coded 1).
     */
    public function test_run_schedule_now_uses_template_post_quantity() {
        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Manual Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1
        );

        $template = (object) array(
            'id' => 456,
            'name' => 'Manual Test Template',
            'post_quantity' => 5, // Should now be honoured, not overridden to 1
            'is_active' => 1
        );

        $this->build_scheduler_with_mocks($schedule, $template, 123, 5);

        $result = $this->scheduler->run_schedule_now(123);

        if (is_wp_error($result)) {
            $this->fail('Unexpected WP_Error: ' . $result->get_error_message());
        }
        $this->assertEquals(array(123, 123, 123, 123, 123), $result);
    }

    /**
     * Verifies that a caller-supplied quantity_override takes precedence over the template's post_quantity.
     */
    public function test_run_schedule_now_respects_quantity_override() {
        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Override Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1
        );

        $template = (object) array(
            'id' => 456,
            'name' => 'Override Test Template',
            'post_quantity' => 5, // Template says 5; override should win
            'is_active' => 1
        );

        $this->build_scheduler_with_mocks($schedule, $template, 123, 3);

        // Pass an explicit override of 3
        $result = $this->scheduler->run_schedule_now(123, 3);

        if (is_wp_error($result)) {
            $this->fail('Unexpected WP_Error: ' . $result->get_error_message());
        }
        $this->assertEquals(array(123, 123, 123), $result);
    }

    /**
     * Verifies that a schedule_not_found error is returned when the schedule does not exist.
     */
    public function test_run_schedule_now_not_found() {
         $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

         $mock_schedule_repo->expects($this->once())
            ->method('get_by_id')
            ->willReturn(null);

         $this->scheduler->set_repository($mock_schedule_repo);

         $result = $this->scheduler->run_schedule_now(9999);
         $this->assertWPError($result);
         $this->assertEquals('schedule_not_found', $result->get_error_code());
    }

    public function test_run_schedule_now_applies_blueprint_preset_voice_structure_and_slices() {
       global $wpdb;

       $template_table = $wpdb->prefix . 'aips_templates';
       $schedule_table = $wpdb->prefix . 'aips_schedule';
       $voice_table = $wpdb->prefix . 'aips_voices';
       $structure_table = $wpdb->prefix . 'aips_article_structures';
       $slice_table = $wpdb->prefix . 'aips_post_slices';
       $preset_table = $wpdb->prefix . 'aips_blueprint_presets';

       $wpdb->insert($voice_table, array(
           'name' => 'Preset Voice',
           'title_prompt' => 'Preset title prompt',
           'content_instructions' => 'Preset content instructions',
           'excerpt_instructions' => 'Preset excerpt instructions',
           'is_active' => 1,
           'created_at' => time(),
       ));
       $voice_id = (int) $wpdb->insert_id;

       $wpdb->insert($structure_table, array(
           'name' => 'Preset Structure',
           'description' => 'Preset structure description',
           'structure_data' => wp_json_encode(array(
               'sections' => array(),
               'prompt_template' => 'Structured prompt',
           )),
           'is_active' => 1,
           'created_at' => time(),
           'updated_at' => time(),
       ));
       $structure_id = (int) $wpdb->insert_id;

       $wpdb->insert($slice_table, array(
           'name' => 'Preset Slice One',
           'description' => 'Slice one',
           'sort_order' => 1,
           'is_active' => 1,
           'created_at' => time(),
           'updated_at' => time(),
       ));
       $slice_one_id = (int) $wpdb->insert_id;

       $wpdb->insert($slice_table, array(
           'name' => 'Preset Slice Two',
           'description' => 'Slice two',
           'sort_order' => 2,
           'is_active' => 1,
           'created_at' => time(),
           'updated_at' => time(),
       ));
       $slice_two_id = (int) $wpdb->insert_id;

       $wpdb->insert($preset_table, array(
           'name' => 'Runtime Preset',
           'description' => 'Preset used by test',
           'structure_id' => $structure_id,
           'voice_id' => $voice_id,
           'slice_ids' => wp_json_encode(array($slice_one_id, $slice_two_id)),
           'section_overrides' => null,
           'is_active' => 1,
           'is_default' => 0,
           'created_at' => time(),
           'updated_at' => time(),
       ));
       $preset_id = (int) $wpdb->insert_id;

       $wpdb->insert($template_table, array(
           'name' => 'Preset Template',
           'prompt_template' => 'Write about {{topic}}',
           'title_prompt' => 'Template title',
           'post_quantity' => 1,
           'post_status' => 'draft',
           'post_type' => 'post',
           'post_category' => 1,
           'post_tags' => 'preset',
           'post_author' => 1,
           'is_active' => 1,
           'created_at' => time(),
           'updated_at' => time(),
       ));
       $template_id = (int) $wpdb->insert_id;

       $wpdb->insert($schedule_table, array(
           'template_id' => $template_id,
           'title' => 'Preset Schedule',
           'blueprint_preset_id' => $preset_id,
           'frequency' => 'daily',
           'topic' => 'Preset Topic',
           'next_run' => time(),
           'is_active' => 1,
           'created_at' => time(),
       ));
       $schedule_id = (int) $wpdb->insert_id;

       $mock_generator = $this->getMockBuilder('AIPS_Generator')
           ->disableOriginalConstructor()
           ->onlyMethods(array('generate_post'))
           ->getMock();

       $mock_generator->expects($this->once())
           ->method('generate_post')
           ->with($this->callback(function($context) use ($voice_id, $structure_id) {
               return $context instanceof AIPS_Template_Context
                   && $context->get_voice_id() === $voice_id
                   && $context->get_article_structure_id() === $structure_id
                   && $context->get_post_slice_names() === array('Preset Slice One', 'Preset Slice Two')
                   && $context->get_blueprint_preset_id() !== null;
           }))
           ->willReturn(321);

       $this->scheduler->set_generator($mock_generator);

       $result = $this->scheduler->run_schedule_now($schedule_id);

       $this->assertSame(array(321), $result);

       $wpdb->delete($schedule_table, array('id' => $schedule_id), array('%d'));
       $wpdb->delete($template_table, array('id' => $template_id), array('%d'));
       $wpdb->delete($preset_table, array('id' => $preset_id), array('%d'));
       $wpdb->delete($slice_table, array('id' => $slice_one_id), array('%d'));
       $wpdb->delete($slice_table, array('id' => $slice_two_id), array('%d'));
       $wpdb->delete($structure_table, array('id' => $structure_id), array('%d'));
       $wpdb->delete($voice_table, array('id' => $voice_id), array('%d'));
    }
}
