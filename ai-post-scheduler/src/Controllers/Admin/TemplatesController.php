<?php
namespace AIPS\Controllers\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class TemplatesController {

    private $templates;

    public function __construct($templates = null) {
        $this->templates = $templates ?: new \AIPS_Templates();

        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_aips_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_aips_test_template', array($this, 'ajax_test_template'));
        add_action('wp_ajax_aips_get_template_posts', array($this, 'ajax_get_template_posts'));
        add_action('wp_ajax_aips_clone_template', array($this, 'ajax_clone_template'));
        add_action('wp_ajax_aips_preview_template_prompts', array($this, 'ajax_preview_template_prompts'));
    }

    public function ajax_save_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $data = array(
            'id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field($_POST['title_prompt']) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'post_quantity' => isset($_POST['post_quantity']) ? absint($_POST['post_quantity']) : 1,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post($_POST['image_prompt']) : '',
            'generate_featured_image' => isset($_POST['generate_featured_image']) ? 1 : 0,
            'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field($_POST['featured_image_source']) : 'ai_prompt',
            'featured_image_unsplash_keywords' => isset($_POST['featured_image_unsplash_keywords']) ? sanitize_textarea_field($_POST['featured_image_unsplash_keywords']) : '',
            'featured_image_media_ids' => isset($_POST['featured_image_media_ids']) ? sanitize_text_field($_POST['featured_image_media_ids']) : '',
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft',
            'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : 0,
            'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field($_POST['post_tags']) : '',
            'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        if (empty($data['name']) || empty($data['prompt_template'])) {
            wp_send_json_error(array('message' => __('Name and prompt template are required.', 'ai-post-scheduler')));
        }

        if ($data['post_quantity'] < 1 || $data['post_quantity'] > 20) {
            $data['post_quantity'] = 1;
        }

        $id = $this->templates->save($data);

        if ($id) {
            wp_send_json_success(array(
                'message' => __('Template saved successfully.', 'ai-post-scheduler'),
                'template_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save template.', 'ai-post-scheduler')));
        }
    }

    public function ajax_delete_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        if ($this->templates->delete($id)) {
            wp_send_json_success(array('message' => __('Template deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete template.', 'ai-post-scheduler')));
        }
    }

    public function ajax_get_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $template = $this->templates->get($id);

        if ($template) {
            wp_send_json_success(array('template' => $template));
        } else {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }
    }

    public function ajax_clone_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $template = $this->templates->get($id);

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }

        $new_data = array(
            'name' => $template->name . ' ' . __('(Copy)', 'ai-post-scheduler'),
            'description' => isset($template->description) ? $template->description : '',
            'prompt_template' => $template->prompt_template,
            'title_prompt' => $template->title_prompt,
            'voice_id' => $template->voice_id,
            'post_quantity' => $template->post_quantity,
            'image_prompt' => $template->image_prompt,
            'generate_featured_image' => $template->generate_featured_image,
            'featured_image_source' => $template->featured_image_source,
            'featured_image_unsplash_keywords' => $template->featured_image_unsplash_keywords,
            'featured_image_media_ids' => $template->featured_image_media_ids,
            'post_status' => $template->post_status,
            'post_category' => $template->post_category,
            'post_tags' => $template->post_tags,
            'post_author' => $template->post_author,
            'is_active' => $template->is_active,
        );

        $new_id = $this->templates->save($new_data);

        if ($new_id) {
            wp_send_json_success(array(
                'message' => __('Template cloned successfully.', 'ai-post-scheduler'),
                'template_id' => $new_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to clone template.', 'ai-post-scheduler')));
        }
    }

    public function ajax_test_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $prompt = isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '';

        if (empty($prompt)) {
            wp_send_json_error(array('message' => __('Prompt template is required.', 'ai-post-scheduler')));
        }

        $generator = new \AIPS_Generator();
        $result = $generator->generate_content($prompt);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'content' => $result,
            'message' => __('Test generation successful.', 'ai-post-scheduler')
        ));
    }

    public function ajax_get_template_posts() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $history = new \AIPS_History();
        $data = $history->get_history(array(
            'template_id' => $template_id,
            'page' => $page,
            'per_page' => 10,
            'status' => 'completed'
        ));

        ob_start();
        if (!empty($data['items'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Date', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['items'] as $item): ?>
                    <tr>
                        <td>
                            <?php if ($item->post_id): ?>
                                <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" target="_blank">
                                    <?php echo esc_html($item->generated_title); ?>
                                </a>
                            <?php else: ?>
                                <?php echo esc_html($item->generated_title); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item->created_at); ?></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="button button-small" target="_blank">
                                <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($data['pages'] > 1): ?>
            <div class="aips-pagination" style="margin-top: 10px; text-align: right;">
                <?php
                $current = $data['current_page'];
                $total = $data['pages'];

                if ($current > 1) {
                    echo '<button type="button" class="button aips-modal-page" data-page="' . ($current - 1) . '">&laquo; ' . esc_html__('Prev', 'ai-post-scheduler') . '</button> ';
                }

                printf(
                    '<span class="paging-input">%s %d %s %d</span> ',
                    esc_html__('Page', 'ai-post-scheduler'),
                    $current,
                    esc_html__('of', 'ai-post-scheduler'),
                    $total
                );

                if ($current < $total) {
                    echo '<button type="button" class="button aips-modal-page" data-page="' . ($current + 1) . '">' . esc_html__('Next', 'ai-post-scheduler') . ' &raquo;</button>';
                }
                ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <p><?php esc_html_e('No posts generated yet.', 'ai-post-scheduler'); ?></p>
        <?php endif;
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Preview the prompts that will be generated for a template.
     *
     * This endpoint processes the template configuration and returns the actual prompts
     * that would be sent to the AI service, including voice and article structure integration.
     *
     * Uses AIPS_Prompt_Builder to ensure consistency with actual generation.
     *
     * @since 1.7.0
     */
    public function ajax_preview_template_prompts() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        // Collect template data from POST
        $template_data = (object) array(
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field($_POST['title_prompt']) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'article_structure_id' => isset($_POST['article_structure_id']) ? absint($_POST['article_structure_id']) : 0,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post($_POST['image_prompt']) : '',
            'generate_featured_image' => isset($_POST['generate_featured_image']) ? 1 : 0,
            'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field($_POST['featured_image_source']) : 'ai_prompt',
        );

        if (empty($template_data->prompt_template)) {
            wp_send_json_error(array('message' => __('Please enter a content prompt to generate the preview.', 'ai-post-scheduler')));
        }

        // Use Prompt Builder to build all prompts
        $prompt_builder = new \AIPS_Prompt_Builder();
        
        // Get voice if selected
        $voice = $prompt_builder->get_voice($template_data->voice_id);

        // Build prompts using the centralized method
        $result = $prompt_builder->build_prompts($template_data, null, $voice);

        wp_send_json_success($result);
    }
}
