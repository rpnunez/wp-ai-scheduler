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
     *     @type string $focus_keyword Optional. Primary SEO focus keyword/keyphrase.
     *     @type string $meta_description Optional. SEO meta description content.
     *     @type string $seo_title Optional. SEO title override for plugins.
     *     @type string $topic Optional. Topic used to infer focus keyword when none provided.
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

        $focus_keyword = $title;
        if (isset($data['topic'])) {
            $focus_keyword = $data['topic'];
        }
        if (isset($data['focus_keyword'])) {
            $focus_keyword = $data['focus_keyword'];
        }

        $meta_description = $excerpt;
        if (isset($data['meta_description'])) {
            $meta_description = $data['meta_description'];
        }

        $seo_title = $title;
        if (isset($data['seo_title'])) {
            $seo_title = $data['seo_title'];
        }

        // Build initial meta description from provided meta_description or excerpt.
        $meta_description = '';
        if (isset($data['meta_description']) && $data['meta_description'] !== '') {
            $meta_description = $data['meta_description'];
        } elseif ($excerpt !== '') {
            $meta_description = $excerpt;
        }

        $seo_data = array(
            'focus_keyword'   => isset($data['focus_keyword']) ? $data['focus_keyword'] : (isset($data['topic']) ? $data['topic'] : $title),
            'meta_description' => wp_strip_all_tags($meta_description),
            'seo_title'       => isset($data['seo_title']) ? $data['seo_title'] : $title,
        );

        /**
         * Filter hook: allow third-parties to modify SEO metadata before it is saved.
         *
         * The following parameters are passed to callbacks attached to the
         * {@see 'aips_post_seo_metadata'} filter.
         *
         * @param array  $seo_data  SEO metadata array passed to the filter callback.
         * @param int    $post_id   The post ID passed to the filter callback.
         * @param object $template  Template object passed to the filter callback.
         */
        $seo_data = apply_filters('aips_post_seo_metadata', $seo_data, $post_id, $template);

        $this->apply_seo_metadata($post_id, $seo_data);

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

    /**
     * Apply SEO metadata (Yoast and RankMath) to the created post.
     *
     * @param int   $post_id  Post ID.
     * @param array $seo_data {
     *     SEO data used to populate plugin-specific meta fields.
     *
     *     @type string $focus_keyword    Primary keyword/keyphrase.
     *     @type string $meta_description Meta description text.
     *     @type string $seo_title        SEO title override.
     * }
     * @return void
     */
    private function apply_seo_metadata($post_id, $seo_data) {
        if (empty($post_id)) {
            return;
        }

        $yoast_active = $this->is_yoast_active();
        $rank_math_active = $this->is_rank_math_active();

        if (!$yoast_active && !$rank_math_active) {
            return;
        }

        $focus_keyword = isset($seo_data['focus_keyword']) ? sanitize_text_field($seo_data['focus_keyword']) : '';
        $seo_title = isset($seo_data['seo_title']) ? sanitize_text_field($seo_data['seo_title']) : '';
        $meta_description = isset($seo_data['meta_description']) ? $this->sanitize_meta_description($seo_data['meta_description']) : '';

        if (!empty($focus_keyword)) {
            // Yoast SEO focus keyword support (legacy and current keyphrase fields).
            if ($yoast_active) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
                update_post_meta($post_id, '_yoast_wpseo_focuskeyphrase', $focus_keyword);
            }
            // RankMath focus keyword support.
            if ($rank_math_active) {
                update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
            }
        }

        if (!empty($meta_description)) {
            // Populate meta description for Yoast and RankMath for better SERP snippets.
            if ($yoast_active) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
            }
            if ($rank_math_active) {
                update_post_meta($post_id, 'rank_math_description', $meta_description);
            }
        }

        if (!empty($seo_title)) {
            // Set SEO title overrides when available.
            if ($yoast_active) {
                update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
            }
            if ($rank_math_active) {
                update_post_meta($post_id, 'rank_math_title', $seo_title);
            }
        }
    }

    /**
     * Determine if Yoast SEO is active.
     *
     * @return bool True when Yoast SEO is installed and active.
     */
    private function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Meta');
    }

    /**
     * Determine if Rank Math is active.
     *
     * @return bool True when Rank Math is installed and active.
     */
    private function is_rank_math_active() {
        return defined('RANK_MATH_VERSION') || class_exists('RankMath');
    }

    /**
     * Normalize and trim a meta description string for SEO plugins.
     *
     * @param string $description Raw description text.
     * @return string Sanitized description trimmed to 160 characters.
     */
    private function sanitize_meta_description($description) {
        $clean_description = sanitize_text_field($description);

        if (function_exists('mb_substr')) {
            return mb_substr($clean_description, 0, 160);
        }

        return substr($clean_description, 0, 160);
    }
}
