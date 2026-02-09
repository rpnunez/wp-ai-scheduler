<?php
/**
 * Activity Controller
 *
 * Handles the Activity page logic and AJAX requests.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Activity_Controller
 *
 * Manages the Activity feed display and data retrieval.
 */
class AIPS_Activity_Controller {

    /**
     * Initialize the controller.
     */
    public function __construct() {
        add_action('wp_ajax_aips_get_activity', array($this, 'ajax_get_activity'));
        add_action('wp_ajax_aips_get_activity_detail', array($this, 'ajax_get_activity_detail'));
        add_action('wp_ajax_aips_publish_draft', array($this, 'ajax_publish_draft'));
    }

    /**
     * Render the Activity page.
     *
     * Includes the activity template file.
     *
     * @return void
     */
    public function render_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/activity.php';
    }

    /**
     * AJAX handler to get activity feed.
     */
    public function ajax_get_activity() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;

        $history_service = new AIPS_History_Service();

        // Build filters
        $filters = array();
        if ($search) {
            $filters['search'] = $search;
        }

        // Map filter to event types or statuses
        if ($filter === 'published') {
            $filters['event_type'] = 'post_published';
        } elseif ($filter === 'drafts') {
            $filters['event_type'] = 'post_generated';
        } elseif ($filter === 'failed') {
            $filters['event_status'] = 'failed';
        }

        $activity_logs = $history_service->get_activity_feed($limit, 0, $filters);

        // Format activities for the frontend
        $activities = array();
        foreach ($activity_logs as $log) {
            $details = json_decode($log->details, true);

            $activity = array(
                'id' => $log->id,
                'message' => $log->log_type,
                'type' => isset($details['event_type']) ? $details['event_type'] : 'info',
                'status' => isset($details['event_status']) ? $details['event_status'] : 'info',
                'date_formatted' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->timestamp),
                'post' => null
            );

            // Get post data if available
            if ($log->post_id) {
                $post = get_post($log->post_id);
                if ($post) {
                    $activity['post'] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'status' => $post->post_status,
                        'edit_url' => get_edit_post_link($post->ID, 'raw')
                    );
                }
            }

            $activities[] = $activity;
        }

        wp_send_json_success(array('activities' => $activities));
    }

    /**
     * AJAX handler to get activity detail for a specific post.
     */
    public function ajax_get_activity_detail() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'ai-post-scheduler')));
        }

        $detail = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'date' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $post->post_date),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'edit_url' => get_edit_post_link($post->ID, 'raw'),
            'view_url' => get_permalink($post->ID),
            'featured_image_url' => get_the_post_thumbnail_url($post->ID, 'large'),
            'categories' => array(),
            'tags' => array()
        );

        // Get categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            $detail['categories'][] = $category->name;
        }

        // Get tags
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $detail['tags'][] = $tag->name;
            }
        }

        wp_send_json_success(array('post' => $detail));
    }

    /**
     * AJAX handler to publish a draft post.
     */
    public function ajax_publish_draft() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'draft') {
            wp_send_json_error(array('message' => __('Post not found or not a draft.', 'ai-post-scheduler')));
        }

        // Publish the post
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish',
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1)
        ), true);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Refresh post data after update
        $post = get_post($post_id);

        // Log the activity
        $history_service = new AIPS_History_Service();
        $history = $history_service->create('post_published', array('post_id' => $post_id));
        $history->record(
            'activity',
            sprintf(__('Post published: %s', 'ai-post-scheduler'), $post->post_title),
            array('event_type' => 'post_published', 'event_status' => 'success'),
            $post_id,
            array()
        );

        wp_send_json_success(array('message' => __('Post published successfully.', 'ai-post-scheduler')));
    }
}
