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
     * Initialize the controller.
     */
    public function __construct() {
        $this->research_service = new AIPS_Research_Service();
        $this->repository = new AIPS_Trending_Topics_Repository();
        $this->logger = new AIPS_Logger();
        
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
        
        $niche = isset($_POST['niche']) ? sanitize_text_field($_POST['niche']) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 10;
        $keywords = isset($_POST['keywords']) ? array_map('sanitize_text_field', (array) $_POST['keywords']) : array();
        
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
        
        $niche = isset($_POST['niche']) ? sanitize_text_field($_POST['niche']) : '';
        $min_score = isset($_POST['min_score']) ? absint($_POST['min_score']) : 0;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $fresh_only = isset($_POST['fresh_only']) && $_POST['fresh_only'] === 'true';
        
        $args = array(
            'limit' => $limit,
            'min_score' => $min_score,
            'fresh_only' => $fresh_only,
        );
        
        if (!empty($niche)) {
            $args['niche'] = $niche;
        }
        
        $topics = $this->repository->get_all($args);
        
        // Parse keywords from JSON
        foreach ($topics as &$topic) {
            if (!empty($topic['keywords'])) {
                $topic['keywords'] = json_decode($topic['keywords'], true);
            }
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
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';
        
        if (empty($topic_ids) || empty($template_id) || empty($start_date)) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'ai-post-scheduler')));
        }
        
        // Get topics from database
        $topics = array();
        foreach ($topic_ids as $topic_id) {
            $topic = $this->repository->get_by_id($topic_id);
            if ($topic) {
                $topics[] = $topic['topic'];
            }
        }
        
        if (empty($topics)) {
            wp_send_json_error(array('message' => __('No valid topics found.', 'ai-post-scheduler')));
        }
        
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
            $count = count($schedules_to_create);
            $this->logger->log("Scheduled {$count} trending topics for generation", 'info', array(
                'template_id' => $template_id,
                'frequency' => $frequency,
            ));
            
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully scheduled %d topics.', 'ai-post-scheduler'), $count),
                'scheduled_count' => $count,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create schedules.', 'ai-post-scheduler')));
        }
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
}
