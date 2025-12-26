<?php
/**
 * Post Creator Service
 *
 * Handles the creation and configuration of WordPress posts from generated content.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Post_Creator
 *
 * Encapsulates WordPress post creation logic including taxonomy assignment
 * and featured image attachment.
 */
class AIPS_Post_Creator {

    /**
     * Create a new post from generated content.
     *
     * @param array $data {
     *     Post data.
     *
     *     @type string $title    Post title.
     *     @type string $content  Post content.
     *     @type string $excerpt  Post excerpt.
     *     @type object $template Template object containing settings.
     * }
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_post($data) {
        $title = isset($data['title']) ? $data['title'] : '';
        $content = isset($data['content']) ? $data['content'] : '';
        $excerpt = isset($data['excerpt']) ? $data['excerpt'] : '';
        $template = isset($data['template']) ? $data['template'] : null;

        if (!$template) {
            return new WP_Error('missing_template', 'Template data is required for post creation.');
        }

        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => !empty($template->post_status) ? $template->post_status : get_option('aips_default_post_status', 'draft'),
            'post_author' => !empty($template->post_author) ? $template->post_author : get_current_user_id(),
            'post_type' => 'post',
        );

        if (!empty($template->post_category)) {
            $post_data['post_category'] = array($template->post_category);
        } elseif ($default_cat = get_option('aips_default_category')) {
            $post_data['post_category'] = array($default_cat);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Handle Tags
        if (!empty($template->post_tags)) {
            $tags = array_map('trim', explode(',', $template->post_tags));
            wp_set_post_tags($post_id, $tags);
        }

        return $post_id;
    }

    /**
     * Set the featured image for a post.
     *
     * @param int $post_id         Post ID.
     * @param int $attachment_id   Attachment ID of the image.
     * @return bool|WP_Error True on success, false or WP_Error on failure.
     */
    public function set_featured_image($post_id, $attachment_id) {
        if (empty($post_id) || empty($attachment_id)) {
            return false;
        }
        return set_post_thumbnail($post_id, $attachment_id);
    }
}
