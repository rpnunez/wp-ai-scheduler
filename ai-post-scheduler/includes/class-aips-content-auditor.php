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
     * @var AIPS_AI_Service AI service for making API calls
     */
    private $ai_service;

    /**
     * @var AIPS_Logger Logger instance
     */
    private $logger;

    /**
     * Initialize the auditor.
     *
     * @param object|null $ai_service AI service instance.
     * @param object|null $logger Logger instance.
     */
    public function __construct($ai_service = null, $logger = null) {
        $this->ai_service = $ai_service ?: new AIPS_AI_Service();
        $this->logger = $logger ?: new AIPS_Logger();
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
     * @param string                       $niche            The target niche to analyze.
     * @param AIPS_History_Container|null  $history_container Optional history container for logging.
     * @return array|WP_Error Array of gap opportunities or WP_Error on failure.
     */
    public function perform_gap_analysis($niche, $history_container = null) {
        $this->logger->log("Starting gap analysis for niche: {$niche}", 'info');

        // 1. Ingest existing content
        $existing_content = $this->get_site_content_summary(100);
        
        if (empty($existing_content)) {
            // If no content exists, we can still run analysis but context is different
            $this->logger->log("No existing content found for gap analysis.", 'info');
        }

        // 2. Construct the prompt
        $prompt = $this->generate_gap_analysis_prompt($niche, $existing_content);

        // 3. Call AI Service
        $options = array(
            'max_tokens' => 2000,
            'temperature' => 0.7
        );

        // Check if a specific model is configured in settings, if it's "gpt-5-mini" (which doesn't exist publicly yet), override it.
        $configured_model = get_option('aips_ai_model', '');
        if ($configured_model === 'gpt-5-mini') {
             $options['model'] = ''; // Clear model to use AI Engine default (e.g. Gemini)
        }

        if ($history_container) {
            $history_container->record('ai_request', __('Sending gap analysis prompt to AI', 'ai-post-scheduler'), array(
                'niche'              => $niche,
                'existing_posts'     => count($existing_content),
                'prompt_preview'     => substr($prompt, 0, 200),
            ));
        }

        $response = $this->ai_service->generate_json($prompt, $options);

        if (is_wp_error($response)) {
            $this->logger->log("Gap analysis AI call failed: " . $response->get_error_message(), 'error');
            if ($history_container) {
                $history_container->record_error(
                    sprintf(__('Gap analysis AI call failed: %s', 'ai-post-scheduler'), $response->get_error_message()),
                    array(),
                    $response
                );
            }
            return $response;
        }

        // 4. Validate and return results
        if (!is_array($response)) {
            $this->logger->log("Gap analysis returned invalid JSON format.", 'error');
            if ($history_container) {
                $history_container->record_error(__('Gap analysis returned invalid JSON format.', 'ai-post-scheduler'));
            }
            return new WP_Error('invalid_response', 'AI returned invalid data format.');
        }

        $this->logger->log("Gap analysis completed successfully. Found " . count($response) . " gaps.", 'info');

        if ($history_container) {
            $history_container->record(
                'ai_response',
                sprintf(
                    /* translators: %d: number of content gaps found */
                    __('Gap analysis completed. Found %d content gaps.', 'ai-post-scheduler'),
                    count($response)
                ),
                null,
                array('gaps_count' => count($response))
            );

            // Log each individual gap as a separate activity entry
            foreach ($response as $gap) {
                $missing_topic = isset($gap['missing_topic']) ? $gap['missing_topic'] : __('Unknown gap', 'ai-post-scheduler');
                $priority      = isset($gap['priority']) ? $gap['priority'] : '';
                $reason        = isset($gap['reason']) ? $gap['reason'] : '';

                $history_container->record(
                    'activity',
                    sprintf(
                        /* translators: 1: missing topic 2: priority level */
                        __('Gap identified: %1$s [Priority: %2$s]', 'ai-post-scheduler'),
                        $missing_topic,
                        $priority
                    ),
                    null,
                    null,
                    array(
                        'missing_topic'  => $missing_topic,
                        'priority'       => $priority,
                        'reason'         => $reason,
                        'search_intent'  => isset($gap['search_intent']) ? $gap['search_intent'] : '',
                    )
                );
            }
        }
        
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
        $prompt = $this->generate_gap_analysis_prompt($niche, $existing_content);

        // 3. Call AI Service
        $options = array(
            'temperature' => 0.7,
            'max_tokens' => 2000,
        );
        
        // Override potentially bad model setting
        $configured_model = get_option('aips_ai_model', '');
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

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return null;
    }

    /**
     * Generate the prompt for the AI.
     *
     * @param string $niche The target niche.
     * @param array $existing_content List of existing content summaries.
     * @return string The constructed prompt.
     */
    private function generate_gap_analysis_prompt($niche, $existing_content) {
        $content_list = "";
        if (!empty($existing_content)) {
            foreach ($existing_content as $item) {
                $content_list .= "- {$item['title']} (Category: {$item['categories']})\n";
            }
        } else {
            $content_list = "(No existing content found)";
        }

        $prompt = "You are an SEO Content Strategist. The website's core niche is: {$niche}.\n\n";
        $prompt .= "Here is a list of the last " . count($existing_content) . " published articles on the site:\n";
        $prompt .= $content_list . "\n\n";
        
        $prompt .= "Task: Analyze the existing content coverage against the target niche. Identify 5-7 major sub-topics, 'pillar' pages, or content clusters that are MISSING or under-represented.\n\n";
        
        $prompt .= "Return a JSON array of objects. Each object must have:\n";
        $prompt .= "- \"missing_topic\": The title of the missing topic or cluster (string)\n";
        $prompt .= "- \"priority\": \"High\" or \"Medium\" (string)\n";
        $prompt .= "- \"reason\": A brief explanation of why this is a gap and why it's needed (string)\n";
        $prompt .= "- \"search_intent\": The primary user intent (e.g., Informational, Transactional) (string)\n\n";
        
        $prompt .= "Example format:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"missing_topic\": \"Advanced Composting Techniques\",\n";
        $prompt .= "    \"priority\": \"High\",\n";
        $prompt .= "    \"reason\": \"You have basic gardening tips but lack technical soil health content which establishes authority.\",\n";
        $prompt .= "    \"search_intent\": \"Informational\"\n";
        $prompt .= "  }\n";
        $prompt .= "]";

        return $prompt;
    }
}
