<?php
/**
 * Post Manager Service
 *
 * Handles the creation and configuration of WordPress posts from generated content.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Post_Manager
 *
 * Encapsulates WordPress post lifecycle operations including taxonomy assignment,
 * featured image attachment, and generation metadata state updates.
 */
class AIPS_Post_Manager {

    /**
     * Create a new post from generated content.
     *
     * @param array $data {
     *     Post data.
     *
     *     @type string $title    Post title.
     *     @type string $content  Post content.
     *     @type string $excerpt  Post excerpt.
     *     @type bool   $generation_incomplete Optional. Whether post generation is incomplete (e.g., featured image failed).
     *     @type array  $component_statuses Optional. Per-component generation status map.
     *                                   Supported keys: post_title, post_excerpt, featured_image, post_content (bool values).
     *     @type string $focus_keyword Optional. Primary SEO focus keyword/keyphrase.
     *     @type string $meta_description Optional. SEO meta description content.
     *     @type string $seo_title Optional. SEO title override for plugins.
     *     @type string $topic Optional. Topic used to infer focus keyword when none provided.
     *     @type object $template Optional. Template object containing settings (legacy).
     *     @type AIPS_Generation_Context $context Optional. Generation context (preferred).
     * }
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_post($data) {
        $title = isset($data['title']) ? $data['title'] : '';
        $content = isset($data['content']) ? $data['content'] : '';
        $excerpt = isset($data['excerpt']) ? $data['excerpt'] : '';
        
        // Support both legacy template and new context approaches
        $context = isset($data['context']) ? $data['context'] : null;
        $template = isset($data['template']) ? $data['template'] : null;

        // If we have a context, use it; otherwise fall back to template
        if ($context instanceof AIPS_Generation_Context) {
            $post_status = $context->get_post_status();
            $post_author = $context->get_post_author();
            $post_category = $context->get_post_category();
            $post_tags = $context->get_post_tags();
        } elseif ($template) {
            $post_status = !empty($template->post_status) ? $template->post_status : get_option('aips_default_post_status', 'draft');
            $post_author = !empty($template->post_author) ? $template->post_author : get_current_user_id();
            $post_category = !empty($template->post_category) ? $template->post_category : null;
            $post_tags = !empty($template->post_tags) ? $template->post_tags : '';
        } else {
            return new WP_Error(
                'missing_context',
                __('Either a template object or generation context is required for post creation.', 'ai-post-scheduler')
            );
        }

        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $post_status,
            'post_author' => $post_author,
            'post_type' => 'post',
        );

        if (!empty($post_category)) {
            $post_data['post_category'] = array($post_category);
        } elseif ($default_cat = get_option('aips_default_category')) {
            $post_data['post_category'] = array($default_cat);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (isset($data['generation_incomplete']) || isset($data['component_statuses'])) {
            $this->update_generation_status_meta(
                $post_id,
                isset($data['component_statuses']) && is_array($data['component_statuses']) ? $data['component_statuses'] : null,
                isset($data['generation_incomplete']) ? (bool) $data['generation_incomplete'] : null
            );
        }

        // Handle Tags
        if (!empty($post_tags)) {
            $tags = array_map('trim', explode(',', $post_tags));
            wp_set_post_tags($post_id, $tags);
        }

        $focus_keyword = $title;
        // Build initial meta description from provided meta_description or fallback to excerpt.
        $meta_description = '';
        if (isset($data['meta_description']) && $data['meta_description'] !== '') {
            $meta_description = $data['meta_description'];
        } elseif ($excerpt !== '') {
            $meta_description = $excerpt;
        }

        $seo_data = array(
            'focus_keyword'    => isset($data['focus_keyword']) ? $data['focus_keyword'] : (isset($data['topic']) ? $data['topic'] : $title),
            'meta_description' => wp_strip_all_tags($meta_description),
            'seo_title'        => isset($data['seo_title']) ? $data['seo_title'] : $title,
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

    /**
     * Update per-component generation status metadata on a post.
     *
     * @param int   $post_id Post ID.
     * @param array|null $component_statuses Optional component status map.
     * @param bool|null  $generation_incomplete Optional explicit flag; when null, inferred from component statuses.
     * @return void
     */
    public function update_generation_status_meta($post_id, $component_statuses = null, $generation_incomplete = null) {
        if (empty($post_id)) {
            return;
        }

        $normalized_statuses = null;
        if (is_array($component_statuses)) {
            $normalized_statuses = array(
                'post_title'     => !empty($component_statuses['post_title']),
                'post_excerpt'   => !empty($component_statuses['post_excerpt']),
                'featured_image' => !empty($component_statuses['featured_image']),
                'post_content'   => !empty($component_statuses['post_content']),
            );

            update_post_meta($post_id, 'aips_post_generation_component_statuses', wp_json_encode($normalized_statuses));
        }

        if ($generation_incomplete === null && is_array($normalized_statuses)) {
            $generation_incomplete = in_array(false, $normalized_statuses, true);
        }

        if ($generation_incomplete !== null) {
            update_post_meta($post_id, 'aips_post_generation_incomplete', $generation_incomplete ? 'true' : 'false');
        }

        $had_partial = ('true' === (string) $this->get_post_meta_value($post_id, 'aips_post_generation_had_partial'));
        if (!$had_partial) {
            $existing_incomplete = ('true' === (string) $this->get_post_meta_value($post_id, 'aips_post_generation_incomplete'));
            $had_partial = $existing_incomplete || (true === $generation_incomplete);
        }

        if ($had_partial) {
            update_post_meta($post_id, 'aips_post_generation_had_partial', 'true');
        }

        // Detect image-only recoverable failures: content and title succeeded, but featured
        // image was attempted (i.e. component_statuses includes featured_image = false) and
        // failed.  A featured_image = false entry only appears when generation was requested
        // and failed, because the generator pre-initialises it to true when not requested.
        if (is_array($normalized_statuses)) {
            $content_ok = !empty($normalized_statuses['post_content']) && !empty($normalized_statuses['post_title']);
            $image_attempted_and_failed = array_key_exists('featured_image', $normalized_statuses)
                && !$normalized_statuses['featured_image'];

            $image_recoverable = $content_ok && $image_attempted_and_failed;
            update_post_meta($post_id, 'aips_post_generation_image_recoverable', $image_recoverable ? 'true' : 'false');
        }
    }

    /**
     * Reconcile generation status metadata using current persisted post values.
     *
     * @param int   $post_id   Post ID.
     * @param array $overrides Optional component status overrides keyed by post_title, post_excerpt,
     *                         post_content, featured_image.
     * @return array|null Normalized component statuses or null when post not found.
     */
    public function reconcile_generation_status_meta_from_post($post_id, $overrides = array()) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return null;
        }

        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        $existing_statuses = json_decode((string) $this->get_post_meta_value($post_id, 'aips_post_generation_component_statuses'), true);
        $has_thumbnail = !empty(get_post_thumbnail_id($post_id));

        $component_statuses = array(
            'post_title' => '' !== trim(wp_strip_all_tags((string) $post->post_title)),
            'post_excerpt' => '' !== trim(wp_strip_all_tags((string) $post->post_excerpt)),
            'post_content' => '' !== trim(wp_strip_all_tags((string) $post->post_content)),
            'featured_image' => $has_thumbnail,
        );

        if (!$has_thumbnail && is_array($existing_statuses) && array_key_exists('featured_image', $existing_statuses)) {
            $component_statuses['featured_image'] = !empty($existing_statuses['featured_image']);
        }

        $allowed_keys = array('post_title', 'post_excerpt', 'post_content', 'featured_image');
        foreach ($allowed_keys as $key) {
            if (array_key_exists($key, $overrides)) {
                $component_statuses[$key] = (bool) $overrides[$key];
            }
        }

        $this->update_generation_status_meta($post_id, $component_statuses, null);

        return $component_statuses;
    }

    /**
     * Safely read a post meta value in both runtime and limited test environments.
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     * @return mixed
     */
    private function get_post_meta_value($post_id, $meta_key) {
        if (function_exists('get_post_meta')) {
            return get_post_meta($post_id, $meta_key, true);
        }

        global $aips_test_meta;
        if (isset($aips_test_meta[$post_id]) && array_key_exists($meta_key, $aips_test_meta[$post_id])) {
            return $aips_test_meta[$post_id][$meta_key];
        }

        return '';
    }
}