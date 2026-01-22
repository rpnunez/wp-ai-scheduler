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
    public function render_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/dev-tools.php';
    }

    /**
     * Handle AJAX request to generate scaffold.
     *
     * @return void
     */
    public function ajax_generate_scaffold() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $include_voice = isset($_POST['include_voice']) && $_POST['include_voice'] === 'true';
        $include_structure = isset($_POST['include_structure']) && $_POST['include_structure'] === 'true';
        $include_title_prompt = isset($_POST['include_title_prompt']) && $_POST['include_title_prompt'] === 'true';
        $include_content_prompt = isset($_POST['include_content_prompt']) && $_POST['include_content_prompt'] === 'true';
        $include_image_prompt = isset($_POST['include_image_prompt']) && $_POST['include_image_prompt'] === 'true';
        $include_ai_variables = isset($_POST['include_ai_variables']) && $_POST['include_ai_variables'] === 'true';

        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Topic is required.', 'ai-post-scheduler')));
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

        $prompt .= "\nReturn ONLY the raw JSON object. No markdown formatting or explanation.";

        // Call AI Service
        $ai_service = new \AIPS\Service\AI();
        $response = $ai_service->generate_text($prompt, array('max_tokens' => 2500, 'temperature' => 0.7));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        // Parse JSON
        $json_str = trim($response);
        // Remove markdown code blocks if present
        $json_str = preg_replace('/^```(?:json)?\s*/i', '', $json_str);
        $json_str = preg_replace('/\s*```$/', '', $json_str);

        $data = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => __('Failed to parse AI response as JSON.', 'ai-post-scheduler'),
                'debug' => $json_str
            ));
        }

        $created_items = array();
        $voice_id = null;

        // 1. Create Voice
        if ($include_voice && isset($data['voice'])) {
            $voices_handler = new \AIPS\Controllers\Voices();
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
            $structure_repo = new \AIPS\Repository\ArticleStructure();
            $section_repo = new \AIPS\Repository\PromptSection();

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
                'is_default' => 0
            );

            $structure_id = $structure_repo->create($structure_db_data);
            if ($structure_id) {
                $created_items[] = sprintf(__('Article Structure created: %s', 'ai-post-scheduler'), $data['article_structure']['name']);
            }
        }

        // 3. Create Template
        if (isset($data['template'])) {
            $template_repo = new \AIPS\Repository\Template();

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

        wp_send_json_success(array(
            'message' => __('Scaffold generated successfully!', 'ai-post-scheduler'),
            'items' => $created_items
        ));
    }
}
