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
     * @var bool Prevent duplicate hook registration when multiple instances are created.
     */
    private static $hooks_registered = false;
    
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
     * @var AIPS_Bulk_Generator_Service Shared bulk generation harness
     */
    private $bulk_generator_service;
    
    /**
     * Initialize the controller.
     */
    public function __construct() {
        $this->research_service       = new AIPS_Research_Service();
        $this->repository             = new AIPS_Trending_Topics_Repository();
        $this->logger                 = new AIPS_Logger();
        $this->history_service        = new AIPS_History_Service();
        $this->content_auditor        = new AIPS_Content_Auditor();
        $this->bulk_generator_service = new AIPS_Bulk_Generator_Service( $this->history_service );
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        if (self::$hooks_registered) {
            return;
        }

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
        self::$hooks_registered = true;
    }
    
    /**
     * AJAX handler: Research trending topics.
     *
     * Executes AI research and stores results in database.
     */
    public function ajax_research_topics() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 10;
        $keywords = isset($_POST['keywords']) ? AIPS_Utilities::sanitize_string_array((array) wp_unslash($_POST['keywords'])) : array();
        
        if (empty($niche)) {
            AIPS_Ajax_Response::error(__('Niche is required.', 'ai-post-scheduler'));
        }
        
        // Execute research
        $topics = $this->research_service->research_trending_topics($niche, $count, $keywords);
        
        if (is_wp_error($topics)) {
            AIPS_Ajax_Response::error(array('message' => $topics->get_error_message()));
        }
        
        // Save to database
        $saved_count = $this->repository->save_research_batch($topics, $niche);
        
        if ($saved_count === false) {
            AIPS_Ajax_Response::error(__('Failed to save research results.', 'ai-post-scheduler'));
        }
        
        // Get top 5 for display
        $top_topics = $this->research_service->get_top_topics($topics, 5);
        
        AIPS_Ajax_Response::success(array(
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
            AIPS_Ajax_Response::permission_denied();
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
        
        AIPS_Ajax_Response::success(array(
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
            AIPS_Ajax_Response::permission_denied();
        }
        
        $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
        
        if (empty($topic_id)) {
            AIPS_Ajax_Response::error(__('Topic ID is required.', 'ai-post-scheduler'));
        }
        
        $result = $this->repository->delete($topic_id);
        
        if ($result) {
            AIPS_Ajax_Response::success(array(), __('Topic deleted successfully.', 'ai-post-scheduler'));
        } else {
            AIPS_Ajax_Response::error(__('Failed to delete topic.', 'ai-post-scheduler'));
        }
    }

    /**
     * AJAX handler: Bulk delete trending topics.
     */
    public function ajax_delete_trending_topic_bulk() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $topic_ids = isset($_POST['topic_ids']) ? array_map('absint', (array) $_POST['topic_ids']) : array();
        
        // Filter out any IDs that are 0 or less (invalid)
        $topic_ids = array_filter($topic_ids, function($id) {
            return $id > 0;
        });

        if (empty($topic_ids)) {
            AIPS_Ajax_Response::error(__('Topic IDs are required.', 'ai-post-scheduler'));
        }

        $result = $this->repository->delete_bulk($topic_ids);

        if ($result !== false) {
            AIPS_Ajax_Response::success(array(
                'message' => sprintf(__('%d topics deleted successfully.', 'ai-post-scheduler'), $result),
                'count' => $result
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to delete topics.', 'ai-post-scheduler'));
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
            AIPS_Ajax_Response::permission_denied();
        }
        
        $topic_ids = isset($_POST['topic_ids']) ? array_map('absint', (array) $_POST['topic_ids']) : array();
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily';
        
        if (empty($topic_ids) || empty($template_id) || empty($start_date)) {
            AIPS_Ajax_Response::error(__('Missing required fields.', 'ai-post-scheduler'));
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
            AIPS_Ajax_Response::error(__('No valid topics found.', 'ai-post-scheduler'));
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
        
        // Use schedule repository to create schedules
        $schedule_repository = new AIPS_Schedule_Repository();
        $interval_calculator = new AIPS_Interval_Calculator();
        
        $base_time = strtotime($start_date);
        if ($base_time === false) {
             AIPS_Ajax_Response::error(__('Invalid start date provided.', 'ai-post-scheduler'));
        }
        
        // Get interval duration and validate frequency
        $valid_intervals = $interval_calculator->get_intervals();
        if (!array_key_exists($frequency, $valid_intervals)) {
             AIPS_Ajax_Response::error(__('Invalid frequency provided.', 'ai-post-scheduler'));
        }

        $count = 0;
        $next_run = date('Y-m-d H:i:s', $base_time);

        $schedules_to_create = array();

        foreach ($topics as $topic) {
            $schedules_to_create[] = array(
                'template_id' => $template_id,
                'frequency' => $frequency,
                'next_run' => $next_run,
                'is_active' => 1,
                'topic' => $topic,
            );
        }
        
        $result = $schedule_repository->create_bulk($schedules_to_create);

        if ($result) {
            $status_updated = $this->repository->update_status_bulk($valid_topic_ids, 'scheduled');

            if ($status_updated === false) {
                $this->logger->log('Failed to update topic status after scheduling.', 'warning', array(
                    'topic_ids' => $valid_topic_ids,
                    'template_id' => $template_id,
                ));

                $history->record(
                    'warning',
                    __('Schedules were created but topic statuses could not be updated. Topics may still appear in the library.', 'ai-post-scheduler'),
                    null,
                    null,
                    array(
                        'topic_ids' => $valid_topic_ids,
                        'status_update_failed' => true,
                    )
                );
                $history->complete_failure(
                    __('Schedules created but status update failed — topic library may be out of sync.', 'ai-post-scheduler'),
                    array(
                        'topic_ids' => $valid_topic_ids,
                        'template_id' => $template_id,
                        'frequency' => $frequency,
                    )
                );

                AIPS_Ajax_Response::error(array(
                    'message' => __('Schedules were created but topic statuses could not be updated. Please reload the library.', 'ai-post-scheduler'),
                ));
                return;
            }

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
            $status_updated_count = (int) $status_updated;

            $this->logger->log("Scheduled {$count} trending topics for generation", 'info', array(
                'template_id' => $template_id,
                'frequency' => $frequency,
            ));

            if ($status_updated_count === $count) {
                $activity_message = sprintf(
                    __('Scheduled %d trending topic(s) and hid them from library view', 'ai-post-scheduler'),
                    $count
                );
            } else {
                $activity_message = sprintf(
                    /* translators: 1: number of schedules created, 2: number of topics whose status was updated */
                    __('Scheduled %1$d trending topic(s), but only %2$d topic status(es) were updated in the library.', 'ai-post-scheduler'),
                    $count,
                    $status_updated_count
                );
                $this->logger->log('Scheduled/status-updated count mismatch after scheduling.', 'warning', array(
                    'scheduled_count' => $count,
                    'status_updated_count' => $status_updated_count,
                ));
            }

            $history->record(
                'activity',
                $activity_message,
                null,
                null,
                array(
                    'scheduled_count' => $count,
                    'status_updated_count' => $status_updated_count,
                    'updated_status' => 'scheduled',
                    'count_mismatch' => $status_updated_count !== $count,
                )
            );
            $history->complete_success(array(
                'scheduled_count' => $count,
                'status_updated_count' => $status_updated_count,
            ));
            
            AIPS_Ajax_Response::success(array(
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
            AIPS_Ajax_Response::error(__('Failed to create schedules.', 'ai-post-scheduler'));
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
            AIPS_Ajax_Response::permission_denied();
        }

        $topic_ids = isset($_POST['topic_ids']) ? array_map('absint', (array) $_POST['topic_ids']) : array();

        if (empty($topic_ids)) {
            AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
        }

        // Resolve topic rows from the database.
        $topics = array();
        foreach ($topic_ids as $topic_id) {
            $topic = $this->repository->get_by_id($topic_id);
            if ($topic) {
                $topics[] = array(
                    'id'    => $topic_id,
                    'topic' => $topic['topic'],
                );
            }
        }

        if (empty($topics)) {
            AIPS_Ajax_Response::error(__('No valid topics found.', 'ai-post-scheduler'));
        }

        // Resolve the first active template.
        $template_repository = new AIPS_Template_Repository();
        $templates           = $template_repository->get_all(true);

        if (empty($templates)) {
            AIPS_Ajax_Response::error(__('No active templates found. Please create a template first.', 'ai-post-scheduler'));
        }

        $template = $templates[0];

        // Check AI Engine availability before running the batch.
        $generator = new AIPS_Generator();

        if (!$generator->is_available()) {
            $message = __('AI Engine is not available. Please install and configure Meow Apps AI Engine before generating posts.', 'ai-post-scheduler');

            $this->logger->log($message, 'error', array(
                'action'      => 'ajax_generate_trending_topics_bulk',
                'topic_ids'   => wp_list_pluck($topics, 'id'),
                'template_id' => isset($template->id) ? absint($template->id) : 0,
            ));

            AIPS_Ajax_Response::error(array('message' => $message));
            return;
        }

        $total_requested     = count($topics);
        $generated_topic_ids = array();
        $logger              = $this->logger;

        $result = $this->bulk_generator_service->run(
            $topics,
            function ($topic_data) use ($generator, $template, &$generated_topic_ids, $logger) {
                $context = new AIPS_Template_Context($template, null, $topic_data['topic'], 'manual');
                $post_id = $generator->generate_post($context);

                if (is_wp_error($post_id)) {
                    $logger->log(
                        "Failed to generate post for topic: {$topic_data['topic']}",
                        'error',
                        array('error' => $post_id->get_error_message())
                    );
                    return $post_id;
                }

                // Persist a durable post-to-trending-topic link so the Research UI
                // can show generated-post counts and drill into post lists later.
                update_post_meta($post_id, '_aips_trending_topic_id', absint($topic_data['id']));
                update_post_meta($post_id, '_aips_trending_topic_text', sanitize_text_field($topic_data['topic']));

                $generated_topic_ids[] = $topic_data['id'];

                $logger->log("Generated post #{$post_id} from trending topic: {$topic_data['topic']}", 'info');

                return $post_id;
            },
            array(
                'limit_filter' => 'aips_trending_bulk_generate_max_batch',
                'limit_mode'   => 'soft',
                'history_type' => 'bulk_generate',
                'history_meta' => array(
                    'entity_type'  => 'trending_topic',
                    'entity_count' => $total_requested,
                    'template_id'  => isset($template->id) ? absint($template->id) : 0,
                ),
                'trigger_name' => 'ajax_generate_trending_topics_bulk',
                'user_action'  => 'bulk_generate_trending_topics',
                'user_message' => sprintf(
                    /* translators: %d: number of trending topics */
                    __('User initiated generation for %d trending topic(s)', 'ai-post-scheduler'),
                    $total_requested
                ),
                'error_formatter' => function ($topic_data, $msg) {
                    /* translators: 1: topic text, 2: error message */
                    return sprintf(__('Topic "%1$s": %2$s', 'ai-post-scheduler'), $topic_data['topic'], $msg);
                },
            )
        );

        // Update the status of all successfully generated topics in the repository.
        if (!empty($generated_topic_ids)) {
            $this->repository->update_status_bulk($generated_topic_ids, 'generated');
        }

        if ($result->success_count > 0) {
            $message = sprintf(
                _n(
                    '%d post generated successfully.',
                    '%d posts generated successfully.',
                    $result->success_count,
                    'ai-post-scheduler'
                ),
                $result->success_count
            );

            if ($result->was_limited) {
                $remaining = $total_requested - $result->max_bulk;
                $message .= ' ' . sprintf(
                    _n(
                        '%d topic remaining — please generate again to continue.',
                        '%d topics remaining — please generate again to continue.',
                        $remaining,
                        'ai-post-scheduler'
                    ),
                    $remaining
                );
            }

            if ($result->failed_count > 0) {
                $message .= ' ' . sprintf(
                    _n(
                        '%d topic failed.',
                        '%d topics failed.',
                        $result->failed_count,
                        'ai-post-scheduler'
                    ),
                    $result->failed_count
                );
            }

            AIPS_Ajax_Response::success(array(
                'message'         => $message,
                'success_count'   => $result->success_count,
                'failed_count'    => $result->failed_count,
                'batch_limited'   => $result->was_limited,
                'total_requested' => $total_requested,
                'processed_count' => $result->success_count + $result->failed_count,
            ));
        } else {
            AIPS_Ajax_Response::error(array(
                'message'      => __('Failed to generate posts from selected topics.', 'ai-post-scheduler'),
                'failed_topics' => $result->errors,
            ));
        }
    }

    /**
     * AJAX handler: Get generated posts linked to a trending topic.
     */
    public function ajax_get_trending_topic_posts() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

        if ($topic_id <= 0) {
            AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
        }

        $topic = $this->repository->get_by_id($topic_id);

        if (!$topic) {
            AIPS_Ajax_Response::error(__('Topic not found.', 'ai-post-scheduler'));
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

        AIPS_Ajax_Response::success(array(
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
        $niches = AIPS_Config::get_instance()->get_option('aips_research_niches');
        
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
     * AJAX handler: Perform gap analysis.
     */
    public function ajax_perform_gap_analysis() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';

        if (empty($niche)) {
            AIPS_Ajax_Response::error(__('Niche is required.', 'ai-post-scheduler'));
        }

        $gaps = $this->content_auditor->perform_gap_analysis($niche);

        if (is_wp_error($gaps)) {
            AIPS_Ajax_Response::error(array('message' => $gaps->get_error_message()));
        }

        AIPS_Ajax_Response::success(array(
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
            AIPS_Ajax_Response::permission_denied();
        }

        $gap_topic = isset($_POST['gap_topic']) ? sanitize_text_field(wp_unslash($_POST['gap_topic'])) : '';
        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';

        if (empty($gap_topic) || empty($niche)) {
            AIPS_Ajax_Response::error(__('Gap topic and niche are required.', 'ai-post-scheduler'));
        }

        // Use the gap topic as a keyword for research
        $topics = $this->research_service->research_trending_topics($niche, 5, array($gap_topic));

        if (is_wp_error($topics)) {
            AIPS_Ajax_Response::error(array('message' => $topics->get_error_message()));
        }

        // Save to database
        $saved_count = $this->repository->save_research_batch($topics, $niche);

        AIPS_Ajax_Response::success(array(
            'message' => sprintf(__('Generated and saved %d topics based on "%s".', 'ai-post-scheduler'), count($topics), $gap_topic),
            'count' => count($topics)
        ));
    }
}
