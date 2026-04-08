<?php
/**
 * Tests that admin handlers do not register duplicate AJAX callbacks when instantiated multiple times.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Duplicate_Ajax_Registration extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        $GLOBALS['aips_test_hooks']['actions'] = array();
        $GLOBALS['aips_test_hooks']['filters'] = array();
    }

    public function test_dev_tools_does_not_register_duplicate_ajax_hooks() {
        new AIPS_Dev_Tools();
        $before = $this->count_action_callbacks('wp_ajax_aips_generate_scaffold');

        new AIPS_Dev_Tools();
        $after = $this->count_action_callbacks('wp_ajax_aips_generate_scaffold');

        $this->assertSame($before, $after);
    }

    public function test_voices_does_not_register_duplicate_ajax_hooks() {
        new AIPS_Voices();
        $before = array(
            'save' => $this->count_action_callbacks('wp_ajax_aips_save_voice'),
            'delete' => $this->count_action_callbacks('wp_ajax_aips_delete_voice'),
            'get' => $this->count_action_callbacks('wp_ajax_aips_get_voice'),
            'search' => $this->count_action_callbacks('wp_ajax_aips_search_voices'),
        );

        new AIPS_Voices();
        $after = array(
            'save' => $this->count_action_callbacks('wp_ajax_aips_save_voice'),
            'delete' => $this->count_action_callbacks('wp_ajax_aips_delete_voice'),
            'get' => $this->count_action_callbacks('wp_ajax_aips_get_voice'),
            'search' => $this->count_action_callbacks('wp_ajax_aips_search_voices'),
        );

        $this->assertSame($before, $after);
    }

    public function test_history_does_not_register_duplicate_ajax_hooks() {
        new AIPS_History();
        $before = array(
            'bulk_delete' => $this->count_action_callbacks('wp_ajax_aips_bulk_delete_history'),
            'clear' => $this->count_action_callbacks('wp_ajax_aips_clear_history'),
            'export' => $this->count_action_callbacks('wp_ajax_aips_export_history'),
            'details' => $this->count_action_callbacks('wp_ajax_aips_get_history_details'),
            'logs' => $this->count_action_callbacks('wp_ajax_aips_get_history_logs'),
            'reload' => $this->count_action_callbacks('wp_ajax_aips_reload_history'),
            'retry' => $this->count_action_callbacks('wp_ajax_aips_retry_generation'),
        );

        new AIPS_History();
        $after = array(
            'bulk_delete' => $this->count_action_callbacks('wp_ajax_aips_bulk_delete_history'),
            'clear' => $this->count_action_callbacks('wp_ajax_aips_clear_history'),
            'export' => $this->count_action_callbacks('wp_ajax_aips_export_history'),
            'details' => $this->count_action_callbacks('wp_ajax_aips_get_history_details'),
            'logs' => $this->count_action_callbacks('wp_ajax_aips_get_history_logs'),
            'reload' => $this->count_action_callbacks('wp_ajax_aips_reload_history'),
            'retry' => $this->count_action_callbacks('wp_ajax_aips_retry_generation'),
        );

        $this->assertSame($before, $after);
    }

    public function test_generated_posts_controller_does_not_register_duplicate_ajax_hooks() {
        new AIPS_Generated_Posts_Controller();
        $before = array(
            'session' => $this->count_action_callbacks('wp_ajax_aips_get_post_session'),
            'json' => $this->count_action_callbacks('wp_ajax_aips_get_session_json'),
            'download' => $this->count_action_callbacks('wp_ajax_aips_download_session_json'),
        );

        new AIPS_Generated_Posts_Controller();
        $after = array(
            'session' => $this->count_action_callbacks('wp_ajax_aips_get_post_session'),
            'json' => $this->count_action_callbacks('wp_ajax_aips_get_session_json'),
            'download' => $this->count_action_callbacks('wp_ajax_aips_download_session_json'),
        );

        $this->assertSame($before, $after);
    }

    public function test_research_controller_does_not_register_duplicate_hooks() {
        new AIPS_Research_Controller();
        $before = array(
            'research_topics' => $this->count_action_callbacks('wp_ajax_aips_research_topics'),
            'get_topics' => $this->count_action_callbacks('wp_ajax_aips_get_trending_topics'),
            'delete_topic' => $this->count_action_callbacks('wp_ajax_aips_delete_trending_topic'),
            'bulk_delete' => $this->count_action_callbacks('wp_ajax_aips_delete_trending_topic_bulk'),
            'schedule_topics' => $this->count_action_callbacks('wp_ajax_aips_schedule_trending_topics'),
            'gap_analysis' => $this->count_action_callbacks('wp_ajax_aips_perform_gap_analysis'),
            'generate_gap_topics' => $this->count_action_callbacks('wp_ajax_aips_generate_topics_from_gap'),
            'cron' => $this->count_action_callbacks('aips_scheduled_research'),
        );

        new AIPS_Research_Controller();
        $after = array(
            'research_topics' => $this->count_action_callbacks('wp_ajax_aips_research_topics'),
            'get_topics' => $this->count_action_callbacks('wp_ajax_aips_get_trending_topics'),
            'delete_topic' => $this->count_action_callbacks('wp_ajax_aips_delete_trending_topic'),
            'bulk_delete' => $this->count_action_callbacks('wp_ajax_aips_delete_trending_topic_bulk'),
            'schedule_topics' => $this->count_action_callbacks('wp_ajax_aips_schedule_trending_topics'),
            'gap_analysis' => $this->count_action_callbacks('wp_ajax_aips_perform_gap_analysis'),
            'generate_gap_topics' => $this->count_action_callbacks('wp_ajax_aips_generate_topics_from_gap'),
            'cron' => $this->count_action_callbacks('aips_scheduled_research'),
        );

        $this->assertSame($before, $after);
    }

    private function count_action_callbacks($hook_name) {
        if (!isset($GLOBALS['aips_test_hooks']['actions'][$hook_name])) {
            return 0;
        }

        $count = 0;
        foreach ($GLOBALS['aips_test_hooks']['actions'][$hook_name] as $callbacks) {
            $count += count($callbacks);
        }

        return $count;
    }
}
