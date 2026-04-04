<?php
/**
 * Research Controller
 *
 * Orchestrates the automated topic research workflow.
 * Handles research execution, result storage, and integration with scheduler.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Research_Controller
 *
 * Manages the complete research workflow from discovery to scheduling.
 */
class AIPS_Research_Controller {
    
    /**
     * @var AIPS_Research_Service Research service instance
     */
    private $research_service;
    
    /**
     * @var AIPS_Trending_Topics_Repository Repository instance
     */
    private $repository;
    
    /**
     * @var AIPS_Logger Logger instance
     */
    private $logger;

    /**
     * @var AIPS_History_Service History service instance
     */
    private $history_service;

    /**
     * @var AIPS_Content_Auditor Content Auditor instance
     */
    private $content_auditor;
    
    /**
     * Initialize the controller.
     */
    public function __construct() {
        $this->research_service = new AIPS_Research_Service();
        $this->repository = new AIPS_Trending_Topics_Repository();
        $this->logger = new AIPS_Logger();
        $this->history_service = new AIPS_History_Service();
        $this->content_auditor = new AIPS_Content_Auditor();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_aips_research_topics', array($this, 'ajax_research_topics'));
        add_action('wp_ajax_aips_get_trending_topics', array($this, 'ajax_get_trending_topics'));
        add_action('wp_ajax_aips_delete_trending_topic', array($this, 'ajax_delete_trending_topic'));
        add_action('wp_ajax_aips_delete_trending_topic_bulk', array($this, 'ajax_delete_trending_topic_bulk'));
        add_action('wp_ajax_aips_schedule_trending_topics', array($this, 'ajax_schedule_trending_topics'));
        add_action('wp_ajax_aips_generate_trending_topics_bulk', array($this, 'ajax_generate_trending_topics_bulk'));
        add_action('wp_ajax_aips_get_trending_topic_posts', array($this, 'ajax_get_trending_topic_posts'));
        add_action('wp_ajax_aips_perform_gap_analysis', array($this, 'ajax_perform_gap_analysis'));
        add_action('wp_ajax_aips_generate_topics_from_gap', array($this, 'ajax_generate_topics_from_gap'));

        // Scheduled research cron
        add_action('aips_scheduled_research', array($this, 'run_scheduled_research'));
    }
    
    /**
     * AJAX handler: Research trending topics.
     *
     * Executes AI research and stores results in database.
     */
    public function ajax_research_topics() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 10;
        $keywords = isset($_POST['keywords']) ? AIPS_Utilities::sanitize_string_array((array) wp_unslash($_POST['keywords'])) : array();
        
        if (empty($niche)) {
            wp_send_json_error(array('message' => __('Niche is required.', 'ai-post-scheduler')));
        }
        
        // Execute research
        $topics = $this->research_service->research_trending_topics($niche, $count, $keywords);
        
        if (is_wp_error($topics)) {
            wp_send_json_error(array('message' => $topics->get_error_message()));
        }
        
        // Save to database
        $saved_count = $this->repository->save_research_batch($topics, $niche);
        
        if ($saved_count === false) {
            wp_send_json_error(array('message' => __('Failed to save research results.', 'ai-post-scheduler')));
        }
        
        // Get top 5 for display
        $top_topics = $this->research_service->get_top_topics($topics, 5);
        
        wp_send_json_success(array(
            'topics' => $topics,
            'top_topics' => $top_topics,
            'saved_count' => $saved_count,
            'niche' => $niche,
        ));
    }
    
    /**
     * AJAX handler: Get trending topics from database.
     *
     * Retrieves previously researched topics with filtering.
     */
    public function ajax_get_trending_topics() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
        $min_score = isset($_POST['min_score']) ? absint($_POST['min_score']) : 0;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $fresh_only = isset($_POST['fresh_only']) && $_POST['fresh_only'] === 'true';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'new';

        if ($status === 'all') {
            $status = '';
        }
        
        $args = array(
            'limit' => $limit,
            'min_score' => $min_score,
            'fresh_only' => $fresh_only,
            'status' => $status,
        );
        
        if (!empty($niche)) {
            $args['niche'] = $niche;
        }
        
        $topics = $this->repository->get_all($args);

        $topic_ids = array_map('absint', wp_list_pluck($topics, 'id'));
        $post_counts = $this->repository->get_generated_post_counts($topic_ids);
        
        // Parse keywords from JSON and enrich each topic with generated-post counts.
        foreach ($topics as &$topic) {
            if (!empty($topic['keywords'])) {
                $topic['keywords'] = json_decode($topic['keywords'], true);
            }

            $topic_id = isset($topic['id']) ? absint($topic['id']) : 0;
            $topic['generated_post_count'] = isset($post_counts[$topic_id]) ? (int) $post_counts[$topic_id] : 0;
        }
        
        $stats = $this->repository->get_stats();
        $niches = $this->repository->get_niche_list();
        
        wp_send_json_success(array(
            'topics' => $topics,
            'stats' => $stats,
            'niches' => $niches,
        ));
    }
    
    /**
     * AJAX handler: Delete a trending topic.
     */
    public function ajax_delete_trending_topic() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
        
        if (empty($topic_id)) {
            wp_send_json_error(array('message' => __('Topic ID is required.', 'ai-post-scheduler')));
        }
        
        $result = $this->repository->delete($topic_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Topic deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete topic.', 'ai-post-scheduler')));
        }
    }

    /**
     * AJAX handler: Bulk delete trending topics.
     */
    public function ajax_delete_trending_topic_bulk() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $topic_ids = isset($_POST['topic_ids']) ? array_map('absint', (array) $_POST['topic_ids']) : array();
        
        // Filter out any IDs that are 0 or less (invalid)
        $topic_ids = array_filter($topic_ids, function($id) {
            return $id > 0;
        });

        if (empty($topic_ids)) {
            wp_send_json_error(array('message' => __('Topic IDs are required.', 'ai-post-scheduler')));
        }

        $result = $this->repository->delete_bulk($topic_ids);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d topics deleted successfully.', 'ai-post-scheduler'), $result),
                'count' => $result
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete topics.', 'ai-post-scheduler')));
        }
    }
    
    /**
     * AJAX handler: Schedule posts from trending topics.
     *
     * Creates schedules for selected trending topics.
     */
    public function ajax_schedule_trending_topics() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $topic_ids = isset($_POST['topic_ids']) ? array_map('absint', (array) $_POST['topic_ids']) : array();
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily';
        
        if (empty($topic_ids) || empty($template_id) || empty($start_date)) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'ai-post-scheduler')));
        }
        
        // Get topics from database
        $topics = array();
        $valid_topic_ids = array();
        foreach ($topic_ids as $topic_id) {
            $topic = $this->repository->get_by_id($topic_id);
            if ($topic) {
                $topics[] = $topic['topic'];
                $valid_topic_ids[] = $topic_id;
            }
        }
        
        if (empty($topics)) {
            wp_send_json_error(array('message' => __('No valid topics found.', 'ai-post-scheduler')));
        }

        $history = $this->history_service->create('bulk_schedule', array(
            'user_id' => get_current_user_id(),
            'source' => 'manual_ui',
            'trigger' => 'ajax_schedule_trending_topics',
            'entity_type' => 'trending_topic',
            'entity_count' => count($valid_topic_ids),
        ));

        $history->record_user_action(
            'bulk_schedule_trending_topics',
            sprintf(__('User scheduled %d trending topic(s)', 'ai-post-scheduler'), count($valid_topic_ids)),
            array(
                'topic_ids' => $valid_topic_ids,
                'template_id' => $template_id,
                'frequency' => $frequency,
                'start_date' => $start_date,
            )
        );
        
        // Use scheduler to create schedules
        $scheduler = new AIPS_Scheduler();
        $interval_calculator = new AIPS_Interval_Calculator();
        
        $base_time = strtotime($start_date);
        if ($base_time === false) {
             wp_send_json_error(array('message' => __('Invalid start date provided.', 'ai-post-scheduler')));
        }
        
        // Get interval duration and validate frequency
        $valid_intervals = $interval_calculator->get_intervals();
        if (!array_key_exists($frequency, $valid_intervals)) {
             wp_send_json_error(array('message' => __('Invalid frequency provided.', 'ai-post-scheduler')));
        }

        $count = 0;
        $interval_duration = $interval_calculator->get_interval_duration($frequency);

        $schedules_to_create = array();
        
        foreach ($topics as $index => $topic) {
            $next_run_time = $base_time + ($interval_duration * $index);
            
            $schedules_to_create[] = array(
                'template_id' => $template_id,
                'frequency' => $frequency,
                'next_run' => date('Y-m-d H:i:s', $next_run_time),
                'is_active' => 1,
                'topic' => $topic,
            );
        }
        
        $result = $scheduler->save_schedule_bulk($schedules_to_create);

        if ($result) {
            $status_updated = $this->repository->update_status_bulk($valid_topic_ids, 'scheduled');

            // Restore per-topic hook for backward compatibility.
            foreach ($schedules_to_create as $schedule_data) {
                /**
                 * Fires when a trending topic is scheduled for generation.
                 *
                 * This action is documented in HOOKS.md and is expected to run
                 * once per scheduled trending topic.
                 *
                 * @since 1.6.0
                 *
                 * @param array $schedule_data {
                 *     Data used to create the schedule, including:
                 *     @type int    $template_id Template ID used for generation.
                 *     @type string $frequency   Schedule frequency key.
                 *     @type string $next_run    Next run datetime (Y-m-d H:i:s).
                 *     @type int    $is_active   Active flag.
                 *     @type string $topic       Trending topic text.
                 * }
                 */
                do_action('aips_trending_topic_scheduled', $schedule_data);
            }
            
            $count = (int) $result;
            $this->logger->log("Scheduled {$count} trending topics for generation", 'info', array(
                'template_id' => $template_id,
                'frequency' => $frequency,
            ));

            $history->record(
                'activity',
                sprintf(__('Scheduled %d trending topic(s) and hid them from library view', 'ai-post-scheduler'), $count),
                null,
                null,
                array(
                    'scheduled_count' => $count,
                    'status_updated_count' => (int) $status_updated,
                    'updated_status' => 'scheduled',
                )
            );
            $history->complete_success(array(
                'scheduled_count' => $count,
                'status_updated_count' => (int) $status_updated,
            ));
            
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully scheduled %d topics.', 'ai-post-scheduler'), $count),
                'scheduled_count' => $count,
            ));
        } else {
            $history->complete_failure(
                __('Failed to create schedules for selected trending topics.', 'ai-post-scheduler'),
                array(
                    'topic_ids' => $valid_topic_ids,
                    'template_id' => $template_id,
                    'frequency' => $frequency,
                )
            );
            wp_send_json_error(array('message' => __('Failed to create schedules.', 'ai-post-scheduler')));
        }
    }

    /**
     * AJAX handler: Generate posts from trending topics immediately.
     *
     * Creates posts on-demand from selected trending topics.
     */
    public function ajax_generate_trending_topics_bulk() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $topic_ids = isset($_POST['topic_ids']) ? array_map('absint', (array) $_POST['topic_ids']) : array();

        if (empty($topic_ids)) {
            wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
        }

        // Get topics from database
        $topics = array();
        foreach ($topic_ids as $topic_id) {
            $topic = $this->repository->get_by_id($topic_id);
            if ($topic) {
                $topics[] = array(
                    'id' => $topic_id,
                    'topic' => $topic['topic']
                );
            }
        }

        if (empty($topics)) {
            wp_send_json_error(array('message' => __('No valid topics found.', 'ai-post-scheduler')));
        }

        // Get a default template to use for generation
        $template_repository = new AIPS_Template_Repository();
        $templates = $template_repository->get_all(true);

        if (empty($templates)) {
            wp_send_json_error(array('message' => __('No active templates found. Please create a template first.', 'ai-post-scheduler')));
        }

        // Use the first active template
        $template = $templates[0];

        $history = $this->history_service->create('bulk_generate', array(
            'user_id' => get_current_user_id(),
            'source' => 'manual_ui',
            'trigger' => 'ajax_generate_trending_topics_bulk',
            'entity_type' => 'trending_topic',
            'entity_count' => count($topics),
            'template_id' => isset($template->id) ? absint($template->id) : 0,
        ));

        $history->record_user_action(
            'bulk_generate_trending_topics',
            sprintf(__('User initiated generation for %d trending topic(s)', 'ai-post-scheduler'), count($topics)),
            array(
                'topic_ids' => wp_list_pluck($topics, 'id'),
                'template_id' => isset($template->id) ? absint($template->id) : 0,
            )
        );

        // Initialize the generator
        $generator = new AIPS_Generator();

        if (!$generator->is_available()) {
            $message = __('AI Engine is not available. Please install and configure Meow Apps AI Engine before generating posts.', 'ai-post-scheduler');

            $this->logger->log($message, 'error', array(
                'action' => 'ajax_generate_trending_topics_bulk',
                'topic_ids' => wp_list_pluck($topics, 'id'),
                'template_id' => isset($template->id) ? absint($template->id) : 0,
            ));

            $history->record_error(
                __('Bulk trending topic generation unavailable because AI Engine is not available', 'ai-post-scheduler'),
                array(
                    'error_code' => 'TRENDING_BULK_GENERATE_UNAVAILABLE',
                    'topic_ids' => wp_list_pluck($topics, 'id'),
                    'template_id' => isset($template->id) ? absint($template->id) : 0,
                ),
                new WP_Error('ai_engine_unavailable', $message)
            );

            wp_send_json_error(array('message' => $message));
        }
        $success_count = 0;
        $failed_topics = array();
        $generated_topic_ids = array();

        // Generate posts for each topic
        foreach ($topics as $topic_data) {
            $context = new AIPS_Template_Context($template, null, $topic_data['topic'], 'manual');

            $post_id = $generator->generate_post($context);

            if (is_wp_error($post_id)) {
                $failed_topics[] = $topic_data['topic'];
                $this->logger->log("Failed to generate post for topic: {$topic_data['topic']}", 'error', array(
                    'error' => $post_id->get_error_message()
                ));
                $history->record_error(
                    sprintf(__('Failed generating post for trending topic ID %d', 'ai-post-scheduler'), $topic_data['id']),
                    array(
                        'topic_id' => $topic_data['id'],
                        'topic' => $topic_data['topic'],
                        'error_code' => 'TRENDING_BULK_GENERATE_FAILED',
                    ),
                    $post_id
                );
            } else {
                $success_count++;
                $generated_topic_ids[] = $topic_data['id'];

                // Persist a durable post-to-trending-topic link so the Research UI
                // can show generated-post counts and drill into post lists later.
                update_post_meta($post_id, '_aips_trending_topic_id', absint($topic_data['id']));
                update_post_meta($post_id, '_aips_trending_topic_text', sanitize_text_field($topic_data['topic']));

                $this->logger->log("Generated post #{$post_id} from trending topic: {$topic_data['topic']}", 'info');
                $history->record(
                    'activity',
                    sprintf(__('Generated post from trending topic ID %d', 'ai-post-scheduler'), $topic_data['id']),
                    null,
                    null,
                    array(
                        'topic_id' => $topic_data['id'],
                        'topic' => $topic_data['topic'],
                        'post_id' => $post_id,
                    )
                );
            }
        }

        $status_updated = 0;
        if (!empty($generated_topic_ids)) {
            $status_updated = $this->repository->update_status_bulk($generated_topic_ids, 'generated');
        }

        if ($success_count > 0) {
            $message = sprintf(
                _n(
                    '%d post generated successfully.',
                    '%d posts generated successfully.',
                    $success_count,
                    'ai-post-scheduler'
                ),
                $success_count
            );

            if (!empty($failed_topics)) {
                $message .= ' ' . sprintf(
                    _n(
                        '%d topic failed.',
                        '%d topics failed.',
                        count($failed_topics),
                        'ai-post-scheduler'
                    ),
                    count($failed_topics)
                );

                $history->complete_failure(
                    sprintf(__('Generated %d trending topic posts with %d failures.', 'ai-post-scheduler'), $success_count, count($failed_topics)),
                    array(
                        'success_count' => $success_count,
                        'failed_count' => count($failed_topics),
                        'status_updated_count' => (int) $status_updated,
                        'updated_status' => 'generated',
                    )
                );
            } else {
                $history->complete_success(array(
                    'success_count' => $success_count,
                    'failed_count' => 0,
                    'status_updated_count' => (int) $status_updated,
                ));
            }

            wp_send_json_success(array(
                'message' => $message,
                'success_count' => $success_count,
                'failed_count' => count($failed_topics),
            ));
        } else {
            $history->complete_failure(
                __('Failed to generate posts from selected trending topics.', 'ai-post-scheduler'),
                array(
                    'failed_topics' => $failed_topics,
                    'status_updated_count' => 0,
                )
            );
            wp_send_json_error(array(
                'message' => __('Failed to generate posts from selected topics.', 'ai-post-scheduler'),
                'failed_topics' => $failed_topics
            ));
        }
    }

    /**
     * AJAX handler: Get generated posts linked to a trending topic.
     */
    public function ajax_get_trending_topic_posts() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

        if ($topic_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
        }

        $topic = $this->repository->get_by_id($topic_id);

        if (!$topic) {
            wp_send_json_error(array('message' => __('Topic not found.', 'ai-post-scheduler')));
        }

        $posts = $this->repository->get_generated_posts_by_topic_id($topic_id);
        $formatted_posts = array();

        foreach ($posts as $post_row) {
            $post_id = isset($post_row['post_id']) ? absint($post_row['post_id']) : 0;
            $post_status = isset($post_row['post_status']) ? $post_row['post_status'] : '';

            $formatted_posts[] = array(
                'post_id' => $post_id,
                'post_title' => isset($post_row['post_title']) ? $post_row['post_title'] : '',
                'post_status' => $post_status,
                'date_generated' => isset($post_row['post_date']) ? $post_row['post_date'] : '',
                'date_published' => $post_status === 'publish' && isset($post_row['post_date']) ? $post_row['post_date'] : '',
                'edit_url' => $post_id > 0 ? esc_url_raw(get_edit_post_link($post_id, '')) : '',
                'post_url' => $post_id > 0 ? esc_url_raw(get_permalink($post_id)) : '',
            );
        }

        wp_send_json_success(array(
            'topic' => array(
                'id' => isset($topic['id']) ? absint($topic['id']) : 0,
                'topic' => isset($topic['topic']) ? $topic['topic'] : '',
            ),
            'posts' => $formatted_posts,
        ));
    }

    /**
     * Run scheduled research automatically.
     *
     * Executed by WordPress cron based on configured schedule.
     */
    public function run_scheduled_research() {
        $this->logger->log("Starting scheduled research execution", 'info');
        
        // Get configured research niches from settings
        $niches = get_option('aips_research_niches', array());
        
        if (empty($niches)) {
            $this->logger->log("No research niches configured. Skipping scheduled research.", 'info');
            return;
        }
        
        $total_researched = 0;
        
        foreach ($niches as $niche_config) {
            $niche = isset($niche_config['niche']) ? $niche_config['niche'] : '';
            $count = isset($niche_config['count']) ? absint($niche_config['count']) : 10;
            $keywords = isset($niche_config['keywords']) ? $niche_config['keywords'] : array();
            
            if (empty($niche)) {
                continue;
            }
            
            $this->logger->log("Researching niche: {$niche}", 'info');
            
            // Execute research
            $topics = $this->research_service->research_trending_topics($niche, $count, $keywords);
            
            if (is_wp_error($topics)) {
                $this->logger->log("Research failed for {$niche}: " . $topics->get_error_message(), 'error');
                continue;
            }
            
            // Save results
            $saved_count = $this->repository->save_research_batch($topics, $niche);
            
            if ($saved_count) {
                $total_researched += $saved_count;
                $this->logger->log("Saved {$saved_count} topics for {$niche}", 'info');
                
                // Fire event for completed research
                do_action('aips_scheduled_research_completed', $niche, $saved_count, $topics);
            }
        }
        
        $this->logger->log("Scheduled research completed. Total topics: {$total_researched}", 'info');
    }
    
    /**
     * Get research statistics for admin dashboard.
     *
     * @return array Statistics data.
     */
    public function get_research_stats() {
        return $this->repository->get_stats();
    }
    
    /**
     * Get top trending topics for dashboard widget.
     *
     * @param int $count Number of topics to retrieve.
     * @return array Top topics.
     */
    public function get_dashboard_topics($count = 5) {
        $topics = $this->repository->get_top_topics($count, 7);
        
        // Parse keywords
        foreach ($topics as &$topic) {
            if (!empty($topic['keywords'])) {
                $topic['keywords'] = json_decode($topic['keywords'], true);
            }
        }
        
        return $topics;
    }

    /**
     * AJAX handler: Perform gap analysis.
     */
    public function ajax_perform_gap_analysis() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';

        if (empty($niche)) {
            wp_send_json_error(array('message' => __('Niche is required.', 'ai-post-scheduler')));
        }

        $gaps = $this->content_auditor->perform_gap_analysis($niche);

        if (is_wp_error($gaps)) {
            wp_send_json_error(array('message' => $gaps->get_error_message()));
        }

        wp_send_json_success(array(
            'gaps' => $gaps,
            'niche' => $niche
        ));
    }

    /**
     * AJAX handler: Generate topics from a gap.
     *
     * Uses the gap topic as a seed for the standard research service.
     */
    public function ajax_generate_topics_from_gap() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $gap_topic = isset($_POST['gap_topic']) ? sanitize_text_field(wp_unslash($_POST['gap_topic'])) : '';
        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';

        if (empty($gap_topic) || empty($niche)) {
            wp_send_json_error(array('message' => __('Gap topic and niche are required.', 'ai-post-scheduler')));
        }

        // Use the gap topic as a keyword for research
        $topics = $this->research_service->research_trending_topics($niche, 5, array($gap_topic));

        if (is_wp_error($topics)) {
            wp_send_json_error(array('message' => $topics->get_error_message()));
        }

        // Save to database
        $saved_count = $this->repository->save_research_batch($topics, $niche);

        wp_send_json_success(array(
            'message' => sprintf(__('Generated and saved %d topics based on "%s".', 'ai-post-scheduler'), count($topics), $gap_topic),
            'count' => count($topics)
        ));
    }
}
