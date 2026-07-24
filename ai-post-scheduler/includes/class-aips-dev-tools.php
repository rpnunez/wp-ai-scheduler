<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Dev_Tools
 *
 * Handles the developer tools functionality for creating template scaffolds.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Dev_Tools {

    /**
     * Initialize the class.
     */
    public function __construct() {
        add_action('wp_ajax_aips_generate_scaffold', array($this, 'ajax_generate_scaffold'));
    }

    /**
     * Render the Dev Tools page.
     *
     * @return void
     */
    public function render_page($embedded = false) {
        $embedded = (bool) $embedded;

        include AIPS_PLUGIN_DIR . 'templates/admin/dev-tools.php';
    }

    /**
     * Handle AJAX request to generate scaffold.
     *
     * Includes defensive strict array checking after JSON decoding the AI response
     * to prevent scalar decoding errors.
     *
     * @return void
     */
    public function ajax_generate_scaffold() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::error(__('Unauthorized access.', 'ai-post-scheduler'));
        }

        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $include_voice = isset($_POST['include_voice']) && $_POST['include_voice'] === 'true';
        $include_structure = isset($_POST['include_structure']) && $_POST['include_structure'] === 'true';
        $include_title_prompt = isset($_POST['include_title_prompt']) && $_POST['include_title_prompt'] === 'true';
        $include_content_prompt = isset($_POST['include_content_prompt']) && $_POST['include_content_prompt'] === 'true';
        $include_image_prompt = isset($_POST['include_image_prompt']) && $_POST['include_image_prompt'] === 'true';
        $include_ai_variables = isset($_POST['include_ai_variables']) && $_POST['include_ai_variables'] === 'true';

        if (empty($topic)) {
            AIPS_Ajax_Response::error(__('Topic is required.', 'ai-post-scheduler'));
        }

        // Construct the prompt
        $prompt = "Act as a WordPress plugin configuration expert. I need you to generate a configuration for the 'AI Post Scheduler' plugin based on the topic: '{$topic}'.\n\n";

        $prompt .= "Please provide a valid JSON object with the following keys (only if requested):\n";

        if ($include_voice) {
            $prompt .= "- 'voice': Object with 'name', 'title_prompt' (instructions for generating titles), 'content_instructions' (style/tone guidelines), and 'excerpt_instructions'.\n";
        }

        if ($include_structure) {
            $prompt .= "- 'article_structure': Object with 'name', 'description', 'prompt_template' (the overall prompt combining sections), and 'sections' (Array of objects). \n";
            $prompt .= "  Each section object MUST have: 'key' (unique slug), 'name', 'description', and 'content' (the prompt for that specific section).\n";
            $prompt .= "  The 'prompt_template' should use {{section:key}} placeholders.\n";
        }

        $prompt .= "- 'template': Object with 'name'";
        if ($include_title_prompt) $prompt .= ", 'title_prompt'";
        if ($include_content_prompt) $prompt .= ", 'prompt_template' (main content prompt)";
        if ($include_image_prompt) $prompt .= ", 'image_prompt'";
        $prompt .= ".\n";

        if ($include_ai_variables) {
            $prompt .= "\nIMPORTANT: Use custom AI variables in your prompts using {{VariableName}} syntax (e.g., {{TargetAudience}}, {{KeyFeature}}). Do not use system variables like {{date}} or {{topic}} unless necessary.\n";
        }

        // Call AI Service
        $ai_service = new AIPS_AI_Service();
        $data = $ai_service->generate_json($prompt, array(
            'temperature'  => 0.7,
            'json_schema'  => $this->build_scaffold_json_schema(
                $include_voice,
                $include_structure,
                $include_title_prompt,
                $include_content_prompt,
                $include_image_prompt
            ),
        ));

        if (is_wp_error($data)) {
            AIPS_Ajax_Response::error(array('message' => $data->get_error_message()));
        }

        if (!is_array($data)) {
            AIPS_Ajax_Response::error(array('message' => __('Failed to parse AI response as JSON.', 'ai-post-scheduler')));
        }

        $created_items = array();
        $voice_id = null;

        // 1. Create Voice
        if ($include_voice && isset($data['voice'])) {
            $voices_handler = new AIPS_Voices();
            $voice_data = array(
                'name' => sanitize_text_field($data['voice']['name']),
                'title_prompt' => wp_kses_post($data['voice']['title_prompt']),
                'content_instructions' => wp_kses_post($data['voice']['content_instructions']),
                'excerpt_instructions' => isset($data['voice']['excerpt_instructions']) ? wp_kses_post($data['voice']['excerpt_instructions']) : '',
                'is_active' => 1
            );
            $voice_id = $voices_handler->save($voice_data);
            if ($voice_id) {
                $created_items[] = sprintf(__('Voice created: %s', 'ai-post-scheduler'), $data['voice']['name']);
            }
        }

        // 2. Create Structure
        if ($include_structure && isset($data['article_structure'])) {
            $structure_repo = new AIPS_Article_Structure_Repository();
            $section_repo = new AIPS_Prompt_Section_Repository();

            // Handle sections
            $sections_list = array();
            if (isset($data['article_structure']['sections']) && is_array($data['article_structure']['sections'])) {
                foreach ($data['article_structure']['sections'] as $section) {
                    $key = sanitize_key($section['key']);

                    // Check if section exists
                    $existing = $section_repo->get_by_key($key);

                    if (!$existing) {
                        // Create new section
                        $section_data = array(
                            'name' => sanitize_text_field($section['name']),
                            'section_key' => $key,
                            'description' => sanitize_textarea_field($section['description']),
                            'content' => wp_kses_post($section['content']),
                            'is_active' => 1
                        );
                        $section_repo->create($section_data);
                    }
                    $sections_list[] = $key;
                }
            }

            $structure_json_data = wp_json_encode(array(
                'sections' => $sections_list,
                'prompt_template' => wp_kses_post($data['article_structure']['prompt_template'])
            ));

            $structure_db_data = array(
                'name' => sanitize_text_field($data['article_structure']['name']),
                'description' => sanitize_textarea_field($data['article_structure']['description']),
                'structure_data' => $structure_json_data,
                'is_active' => 1,
            );

            $structure_id = $structure_repo->create($structure_db_data);
            if ($structure_id) {
                $created_items[] = sprintf(__('Article Structure created: %s', 'ai-post-scheduler'), $data['article_structure']['name']);
            }
        }

        // 3. Create Template
        if (isset($data['template'])) {
            $template_repo = new AIPS_Template_Repository();

            $template_data = array(
                'name' => sanitize_text_field($data['template']['name']),
                'prompt_template' => isset($data['template']['prompt_template']) ? wp_kses_post($data['template']['prompt_template']) : '',
                'title_prompt' => isset($data['template']['title_prompt']) ? sanitize_text_field($data['template']['title_prompt']) : '',
                'voice_id' => $voice_id,
                'post_quantity' => 1,
                'image_prompt' => isset($data['template']['image_prompt']) ? wp_kses_post($data['template']['image_prompt']) : '',
                'generate_featured_image' => isset($data['template']['image_prompt']) ? 1 : 0,
                'featured_image_source' => 'ai_prompt',
                'post_status' => 'draft',
                'post_category' => 0,
                'is_active' => 1
            );

            // If we created a structure but no prompt template was returned for the template (or specifically requested to rely on structure),
            // usually the template's prompt_template might be empty or generic.
            // But if the user asked for "Content Prompt", the AI should have provided it in `template.prompt_template`.

            $template_id = $template_repo->create($template_data);
            if ($template_id) {
                $created_items[] = sprintf(__('Template created: %s', 'ai-post-scheduler'), $data['template']['name']);
            }
        }

        AIPS_Ajax_Response::success(array(
            'message' => __('Scaffold generated successfully!', 'ai-post-scheduler'),
            'items' => $created_items
        ));
    }

    /**
     * Build the JSON schema for the scaffold response based on which components were requested.
     *
     * @param bool $include_voice
     * @param bool $include_structure
     * @param bool $include_title_prompt
     * @param bool $include_content_prompt
     * @param bool $include_image_prompt
     * @return array<string, mixed>
     */
    private function build_scaffold_json_schema(bool $include_voice, bool $include_structure, bool $include_title_prompt, bool $include_content_prompt, bool $include_image_prompt): array {
        $properties = array();

        if ($include_voice) {
            $properties['voice'] = array(
                'type'       => 'object',
                'properties' => array(
                    'name'                  => array('type' => 'string'),
                    'title_prompt'          => array('type' => 'string'),
                    'content_instructions'  => array('type' => 'string'),
                    'excerpt_instructions'  => array('type' => 'string'),
                ),
                'required' => array('name', 'title_prompt', 'content_instructions'),
            );
        }

        if ($include_structure) {
            $properties['article_structure'] = array(
                'type'       => 'object',
                'properties' => array(
                    'name'            => array('type' => 'string'),
                    'description'     => array('type' => 'string'),
                    'prompt_template' => array('type' => 'string'),
                    'sections'        => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'key'         => array('type' => 'string'),
                                'name'        => array('type' => 'string'),
                                'description' => array('type' => 'string'),
                                'content'     => array('type' => 'string'),
                            ),
                            'required' => array('key', 'name', 'description', 'content'),
                        ),
                    ),
                ),
                'required' => array('name', 'description', 'prompt_template', 'sections'),
            );
        }

        $template_props    = array('name' => array('type' => 'string'));
        $template_required = array('name');

        if ($include_title_prompt) {
            $template_props['title_prompt']  = array('type' => 'string');
            $template_required[]             = 'title_prompt';
        }
        if ($include_content_prompt) {
            $template_props['prompt_template'] = array('type' => 'string');
            $template_required[]               = 'prompt_template';
        }
        if ($include_image_prompt) {
            $template_props['image_prompt'] = array('type' => 'string');
            $template_required[]            = 'image_prompt';
        }

        $properties['template'] = array(
            'type'       => 'object',
            'properties' => $template_props,
            'required'   => $template_required,
        );

        return array(
            'type'       => 'object',
            'properties' => $properties,
        );
    }
}
