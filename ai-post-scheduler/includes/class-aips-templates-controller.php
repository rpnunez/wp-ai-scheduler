<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Templates_Controller {

    private $templates;

    public function __construct($templates = null) {
        $this->templates = $templates ?: new AIPS_Templates();

        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_aips_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_aips_test_template', array($this, 'ajax_test_template'));
        add_action('wp_ajax_aips_clone_template', array($this, 'ajax_clone_template'));
        add_action('wp_ajax_aips_preview_template_prompts', array($this, 'ajax_preview_template_prompts'));
    }

    public function ajax_save_template() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $generate_featured_image = isset($_POST['generate_featured_image']) ? $_POST['generate_featured_image'] : 0;

        $data = array(
            'id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post(wp_unslash($_POST['prompt_template'])) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field(wp_unslash($_POST['title_prompt'])) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'post_quantity' => isset($_POST['post_quantity']) ? absint($_POST['post_quantity']) : 1,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post(wp_unslash($_POST['image_prompt'])) : '',
            'generate_featured_image' => $this->normalize_boolean_flag($generate_featured_image),
            'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field(wp_unslash($_POST['featured_image_source'])) : 'ai_prompt',
            'featured_image_unsplash_keywords' => isset($_POST['featured_image_unsplash_keywords']) ? sanitize_textarea_field(wp_unslash($_POST['featured_image_unsplash_keywords'])) : '',
            'featured_image_media_ids' => isset($_POST['featured_image_media_ids']) ? sanitize_text_field(wp_unslash($_POST['featured_image_media_ids'])) : '',
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'draft',
            'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : 0,
            'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field(wp_unslash($_POST['post_tags'])) : '',
            'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
            'include_sources' => isset($_POST['include_sources']) ? 1 : 0,
            'source_group_ids' => isset($_POST['source_group_ids']) && is_array($_POST['source_group_ids'])
                ? wp_json_encode(array_map('absint', $_POST['source_group_ids']))
                : wp_json_encode(array()),
            'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'en',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        if (empty(trim($data['name'])) || empty(trim($data['prompt_template']))) {
            AIPS_Ajax_Response::error(__('Name and prompt template are required.', 'ai-post-scheduler'));
        }

        if (mb_strlen($data['name']) > 255) {
            AIPS_Ajax_Response::error(__('Template name cannot exceed 255 characters.', 'ai-post-scheduler'));
        }

        if ($data['post_quantity'] < 1 || $data['post_quantity'] > 20) {
            $data['post_quantity'] = 1;
        }

        $id = $this->templates->save($data);

        if ($id) {
            do_action('aips_template_changed', array(
                'action'        => $data['id'] ? 'updated' : 'created',
                'template_id'   => absint($id),
                'template_name' => $data['name'],
                'user_id'       => get_current_user_id(),
            ));

            AIPS_Ajax_Response::success(array(
                'message' => __('Template saved successfully.', 'ai-post-scheduler'),
                'template_id' => $id
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to save template.', 'ai-post-scheduler'));
        }
    }

    public function ajax_delete_template() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if ($id <= 0) {
            AIPS_Ajax_Response::error(__('Invalid template ID.', 'ai-post-scheduler'));
        }

        $template = $this->templates->get($id);

        if ($this->templates->delete($id)) {
            do_action('aips_template_changed', array(
                'action'        => 'deleted',
                'template_id'   => $id,
                'template_name' => ($template && !empty($template->name)) ? $template->name : __('Template', 'ai-post-scheduler'),
                'user_id'       => get_current_user_id(),
            ));

            AIPS_Ajax_Response::success(array(), __('Template deleted successfully.', 'ai-post-scheduler'));
        } else {
            AIPS_Ajax_Response::error(__('Failed to delete template.', 'ai-post-scheduler'));
        }
    }

    public function ajax_get_template() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if ($id <= 0) {
            AIPS_Ajax_Response::error(__('Invalid template ID.', 'ai-post-scheduler'));
        }

        $template = $this->templates->get($id);

        if ($template) {
            AIPS_Ajax_Response::success(array('template' => $template));
        } else {
            AIPS_Ajax_Response::error(__('Template not found.', 'ai-post-scheduler'));
        }
    }

    public function ajax_clone_template() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if ($id <= 0) {
            AIPS_Ajax_Response::error(__('Invalid template ID.', 'ai-post-scheduler'));
        }

        $template = $this->templates->get($id);

        if (!$template) {
            AIPS_Ajax_Response::error(__('Template not found.', 'ai-post-scheduler'));
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
            'include_sources' => isset($template->include_sources) ? $template->include_sources : 0,
            'source_group_ids' => isset($template->source_group_ids) ? $template->source_group_ids : wp_json_encode(array()),
            'is_active' => $template->is_active,
        );

        $new_id = $this->templates->save($new_data);

        if ($new_id) {
            do_action('aips_template_changed', array(
                'action'        => 'cloned',
                'template_id'   => absint($new_id),
                'template_name' => $new_data['name'],
                'user_id'       => get_current_user_id(),
            ));

            AIPS_Ajax_Response::success(array(
                'message' => __('Template cloned successfully.', 'ai-post-scheduler'),
                'template_id' => $new_id
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to clone template.', 'ai-post-scheduler'));
        }
    }

    public function ajax_test_template() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $generate_featured_image = isset($_POST['generate_featured_image']) ? $_POST['generate_featured_image'] : 0;

        // Collect template data from POST
        $data = array(
            'id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : 'Test Template',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post(wp_unslash($_POST['prompt_template'])) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field(wp_unslash($_POST['title_prompt'])) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'article_structure_id' => isset($_POST['article_structure_id']) ? absint($_POST['article_structure_id']) : 0,
            'post_quantity' => 1,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post(wp_unslash($_POST['image_prompt'])) : '',
            'generate_featured_image' => $this->normalize_boolean_flag($generate_featured_image),
            'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field(wp_unslash($_POST['featured_image_source'])) : 'ai_prompt',
            'featured_image_unsplash_keywords' => isset($_POST['featured_image_unsplash_keywords']) ? sanitize_textarea_field(wp_unslash($_POST['featured_image_unsplash_keywords'])) : '',
            'featured_image_media_ids' => isset($_POST['featured_image_media_ids']) ? sanitize_text_field(wp_unslash($_POST['featured_image_media_ids'])) : '',
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'draft',
            'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : 0,
            'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field(wp_unslash($_POST['post_tags'])) : '',
            'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
        );

        if (empty(trim($data['prompt_template']))) {
            AIPS_Ajax_Response::error(__('Prompt template is required.', 'ai-post-scheduler'));
        }

        if (mb_strlen($data['name']) > 255) {
            AIPS_Ajax_Response::error(__('Template name cannot exceed 255 characters.', 'ai-post-scheduler'));
        }

        // Convert to object for context
        $template = (object) $data;

        // Get voice if selected
        $voice = null;
        if ($template->voice_id) {
            $prompt_builder = new AIPS_Prompt_Builder();
            $voice = $prompt_builder->get_voice($template->voice_id);
        }

        // Create context
        $context = new AIPS_Template_Context($template, $voice, null, 'preview');

        $generator = new AIPS_Generator();
        $result = $generator->generate_preview($context);

        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        AIPS_Ajax_Response::success(array(
            'result' => $result,
            'message' => __('Test generation successful.', 'ai-post-scheduler')
        ));
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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $generate_featured_image = isset($_POST['generate_featured_image']) ? $_POST['generate_featured_image'] : 0;

        // Collect template data from POST
        $template_data = (object) array(
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post(wp_unslash($_POST['prompt_template'])) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field(wp_unslash($_POST['title_prompt'])) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'article_structure_id' => isset($_POST['article_structure_id']) ? absint($_POST['article_structure_id']) : 0,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post(wp_unslash($_POST['image_prompt'])) : '',
            'generate_featured_image' => $this->normalize_boolean_flag($generate_featured_image),
            'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field(wp_unslash($_POST['featured_image_source'])) : 'ai_prompt',
            'include_sources' => isset($_POST['include_sources']) ? 1 : 0,
            'source_group_ids' => isset($_POST['source_group_ids']) && is_array($_POST['source_group_ids'])
                ? wp_json_encode(array_map('absint', $_POST['source_group_ids']))
                : wp_json_encode(array()),
        );

        if (empty(trim($template_data->prompt_template))) {
            AIPS_Ajax_Response::error(__('Please enter a content prompt to generate the preview.', 'ai-post-scheduler'));
        }

        // Use Prompt Builder to build all prompts
        $prompt_builder = new AIPS_Prompt_Builder();
        
        // Get voice if selected
        $voice = $prompt_builder->get_voice($template_data->voice_id);

        $sample_topic = 'Example Topic';

        // Build content prompt
        $content_prompt = $prompt_builder->get_post_content_builder()->build($template_data, $sample_topic, $voice);

        // Build title prompt
        $sample_content = '[Generated article content would appear here]';
        $title_prompt = $prompt_builder->get_post_title_builder()->build($template_data, $sample_topic, $voice, $sample_content);

        // Build excerpt prompt (requires title and content)
        $sample_title = '[Generated title would appear here]';
        $excerpt_prompt = $prompt_builder->get_post_excerpt_builder()->build($sample_title, $sample_content, $voice, $sample_topic);

        // Build image prompt if enabled
        $image_prompt_processed = $prompt_builder->get_post_featured_image_builder()->build($template_data, $sample_topic);

        // Get voice name if applicable
        $voice_name = '';
        if ($voice && isset($voice->name)) {
            $voice_name = $voice->name;
        }

        // Get article structure name if applicable
        $structure_name = '';
        if (isset($template_data->article_structure_id) && $template_data->article_structure_id > 0) {
            $structure_manager = new AIPS_Article_Structure_Manager();
            $structure = $structure_manager->get_structure($template_data->article_structure_id);
            if ($structure && !is_wp_error($structure) && isset($structure['name'])) {
                $structure_name = $structure['name'];
            }
        }

        $result = array(
            'prompts' => array(
                'content' => $content_prompt,
                'title' => $title_prompt,
                'excerpt' => $excerpt_prompt,
                'image' => $image_prompt_processed,
            ),
            'metadata' => array(
                'voice' => $voice_name,
                'article_structure' => $structure_name,
                'sample_topic' => $sample_topic,
                'include_sources' => !empty($template_data->include_sources),
            ),
        );

        AIPS_Ajax_Response::success($result);
    }

    /**
     * Normalize checkbox/radio-like values to an integer flag for persistence.
     *
     * @param mixed $value Raw request value.
     * @return int 1 when enabled, 0 when disabled.
     */
    private function normalize_boolean_flag($value) {
        if (is_array($value)) {
            $value = reset($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
}
