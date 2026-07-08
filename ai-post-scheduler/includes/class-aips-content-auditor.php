<?php
/**
 * Content Auditor Service
 *
 * Analyzes existing content to identify gaps and opportunities.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Content_Auditor
 *
 * Scans local content and interfaces with AI to find content gaps.
 */
class AIPS_Content_Auditor {

    /**
     * @var AIPS_AI_Service_Interface AI service for making API calls
     */
    private $ai_service;

    /**
     * @var AIPS_Logger_Interface Logger instance
     */
    private $logger;

    /**
     * @var AIPS_Prompt_Builder_Content_Audit Prompt builder.
     */
    private $prompt_builder;

    /**
     * Initialize the auditor.
     *
     * @param AIPS_AI_Service_Interface|null $ai_service AI service instance.
     * @param AIPS_Logger_Interface|null     $logger Logger instance.
     * @param AIPS_Prompt_Builder_Content_Audit|null $prompt_builder Prompt builder.
     */
    public function __construct(?AIPS_AI_Service_Interface $ai_service = null, ?AIPS_Logger_Interface $logger = null, ?AIPS_Prompt_Builder_Content_Audit $prompt_builder = null) {
        $container = AIPS_Container::get_instance();
        $this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
        $this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
        $this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Content_Audit();
    }

    /**
     * Get a summary of existing site content.
     *
     * Fetches recent published posts to provide context for the AI.
     *
     * @param int $limit Number of posts to fetch.
     * @return array Array of post summaries (title, categories).
     */
    public function get_site_content_summary($limit = 100) {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids', // Fetch IDs first to be lighter
        );

        $query = new WP_Query($args);
        $summary = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $title = get_the_title($post_id);
                $categories = get_the_category($post_id);
                $cat_names = array();
                
                if ($categories) {
                    foreach ($categories as $cat) {
                        $cat_names[] = $cat->name;
                    }
                }

                $summary[] = array(
                    'title' => $title,
                    'categories' => implode(', ', $cat_names)
                );
            }
        }

        return $summary;
    }

    /**
     * Perform a gap analysis for a specific niche.
     *
     * @param string $niche The target niche to analyze.
     * @return array|WP_Error Array of gap opportunities or WP_Error on failure.
     */
    public function perform_gap_analysis($niche) {
        $this->logger->log("Starting gap analysis for niche: {$niche}", 'info');

        // 1. Ingest existing content
        $existing_content = $this->get_site_content_summary(100);
        
        if (empty($existing_content)) {
            // If no content exists, we can still run analysis but context is different
            $this->logger->log("No existing content found for gap analysis.", 'info');
        }

        // 2. Construct the prompt
        $prompt = $this->prompt_builder->build_gap_analysis_prompt($niche, $existing_content);

        // 3. Call AI Service
        $options = array(
            'temperature' => 0.7,
        );

        // Check if a specific model is configured in settings, if it's "gpt-5-mini" (which doesn't exist publicly yet), override it.
        $configured_model = AIPS_Config::get_instance()->get_option('aips_ai_model');
        if ($configured_model === 'gpt-5-mini') {
             $options['model'] = ''; // Clear model to use AI Engine default (e.g. Gemini)
        }

        $response = $this->ai_service->generate_json($prompt, $options);

        if (is_wp_error($response)) {
            $this->logger->log("Gap analysis AI call failed: " . $response->get_error_message(), 'error');
            return $response;
        }

        // 4. Validate and return results
        if (!is_array($response)) {
            $this->logger->log("Gap analysis returned invalid JSON format.", 'error');
            return new WP_Error('invalid_response', 'AI returned invalid data format.');
        }

        $this->logger->log("Gap analysis completed successfully. Found " . count($response) . " gaps.", 'info');
        
        return $response;
    }

    /**
     * Perform a gap analysis for a specific niche (fallback method).
     *
     * Uses generate_text and manual JSON parsing.
     *
     * @param string $niche The target niche to analyze.
     * @return array|WP_Error Array of gap opportunities or WP_Error on failure.
     */
    public function perform_gap_analysis_fallback($niche) {
        $this->logger->log("Starting gap analysis (fallback) for niche: {$niche}", 'info');

        // 1. Ingest existing content
        $existing_content = $this->get_site_content_summary(100);
        
        if (empty($existing_content)) {
            // If no content exists, we can still run analysis but context is different
            $this->logger->log("No existing content found for gap analysis.", 'info');
        }

        // 2. Construct the prompt
        $prompt = $this->prompt_builder->build_gap_analysis_prompt($niche, $existing_content);

        // 3. Call AI Service
        $options = array(
            'temperature' => 0.7,
        );
        
        // Override potentially bad model setting
        $configured_model = AIPS_Config::get_instance()->get_option('aips_ai_model');
        if ($configured_model === 'gpt-5-mini') {
             $options['model'] = ''; // Clear model to use AI Engine default (e.g. Gemini)
        }

        $response = $this->ai_service->generate_text($prompt, $options);

        if (is_wp_error($response)) {
            $this->logger->log("Gap analysis AI call failed: " . $response->get_error_message(), 'error');
            return $response;
        }

        // 4. Parse JSON from text response
        $parsed_response = $this->parse_json_response($response);

        // 5. Validate and return results
        if (!is_array($parsed_response)) {
            $this->logger->log("Gap analysis returned invalid JSON format.", 'error');
            return new WP_Error('invalid_response', 'AI returned invalid data format.');
        }

        $this->logger->log("Gap analysis completed successfully. Found " . count($parsed_response) . " gaps.", 'info');
        
        return $parsed_response;
    }

    /**
     * Parse JSON from AI text response.
     * Handles markdown code blocks and raw JSON.
     *
     * Applies defensive strict type checking to ensure the parsed JSON is actually
     * an array, mitigating fatal scalar type errors.
     *
     * @param string $response The raw text response from AI.
     * @return array|null Parsed array or null on failure.
     */
    private function parse_json_response($response) {
        // Remove markdown code blocks if present
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

}
