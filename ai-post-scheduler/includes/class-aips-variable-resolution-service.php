<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_Variable_Resolution_Service
 *
 * Handles AI Variable resolution and content truncation logic.
 * Extracted from AIPS_Generator to separate concerns.
 */
class AIPS_Variable_Resolution_Service {

    private $template_processor;
    private $ai_service;
    private $logger;

    /**
     * Constructor.
     *
     * @param AIPS_Template_Processor $template_processor
     * @param AIPS_AI_Service         $ai_service
     * @param AIPS_Logger             $logger
     */
    public function __construct($template_processor, $ai_service, $logger) {
        $this->template_processor = $template_processor;
        $this->ai_service = $ai_service;
        $this->logger = $logger;
    }

    /**
     * Resolve AI Variables for a template.
     *
     * Extracts AI Variables from the title prompt and uses AI to generate
     * appropriate values based on the content context.
     *
     * @param object                    $template Template object containing prompts.
     * @param string                    $content  Generated article content for context.
     * @param object|null               $voice    Optional voice object with title prompt.
     * @param AIPS_History_Container|null $history_container Optional history container for logging.
     * @return array Associative array of resolved AI variable values.
     */
    public function resolve_ai_variables($template, $content, $voice = null, $history_container = null) {
        // For backward compatibility, convert to context and delegate
        $context = new AIPS_Template_Context($template, $voice, null);
        return $this->resolve_ai_variables_from_context($context, $content, $history_container);
    }

    /**
     * Resolve AI Variables from a generation context.
     *
     * Extracts AI Variables from the title prompt and uses AI to generate
     * appropriate values based on the content context.
     *
     * @param AIPS_Generation_Context   $context Generation context.
     * @param string                    $content Generated article content for context.
     * @param AIPS_History_Container|null $history_container Optional history container for logging.
     * @return array Associative array of resolved AI variable values.
     */
    public function resolve_ai_variables_from_context($context, $content, $history_container = null) {
        // Get the title prompt from context
        $title_prompt = $context->get_title_prompt();

        // For template contexts with voice, voice takes precedence
        if ($context->get_type() === 'template' && $context->get_voice_id()) {
            $voice_obj = $context->get_voice();
            if ($voice_obj && !empty($voice_obj->title_prompt)) {
                $title_prompt = $voice_obj->title_prompt;
            }
        }

        // Extract AI variables from the title prompt
        $ai_variables = $this->template_processor->extract_ai_variables($title_prompt);

        if (empty($ai_variables)) {
            return array();
        }

        // Build context from content prompt and generated content.
        // Use smart truncation to preserve context from both beginning and end of content.
        $context_str = "Content Prompt: " . $context->get_content_prompt() . "\n\n";
        $context_str .= "Generated Article Content:\n" . $this->smart_truncate_content($content, 2000);

        // Build the prompt to resolve AI variables
        $resolve_prompt = $this->template_processor->build_ai_variables_prompt($ai_variables, $context_str);

        // Call AI to resolve the variables.
        // Max tokens of 200 is sufficient for JSON responses with typical variable values.
        $options = array('max_tokens' => 200);

        // Use local helper to handle AI call + optional history logging
        $result = $this->generate_content_with_logging($resolve_prompt, $options, 'ai_variables', $history_container);

        if (is_wp_error($result)) {
            $this->logger->log('Failed to resolve AI variables: ' . $result->get_error_message(), 'warning');
            return array();
        }

        // Parse the AI response to extract variable values
        $resolved_values = $this->template_processor->parse_ai_variables_response($result, $ai_variables);

        if (empty($resolved_values)) {
            // AI call succeeded but we could not extract any variable values.
            // This usually indicates invalid JSON or an unexpected response format.
            $this->logger->log('AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.', 'warning', array(
                'variables' => $ai_variables,
                'raw_response' => $result,
            ));
        } else {
            $this->logger->log('Resolved AI variables', 'info', array(
                'variables' => $ai_variables,
                'resolved'   => $resolved_values,
            ));
        }

        return $resolved_values;
    }

    /**
     * Smart truncate content to preserve key information from both beginning and end.
     *
     * Instead of simply truncating from the beginning, this method takes content
     * from both the start and end of the text to provide better context for AI
     * variable resolution. Articles often have introductions at the start and
     * conclusions/summaries at the end, both of which are valuable for context.
     *
     * @param string $content    The content to truncate.
     * @param int    $max_length Maximum total length of the result. Minimum of 100 chars.
     * @return string Truncated content with beginning and end preserved.
     */
    public function smart_truncate_content($content, $max_length = 2000) {
        $content_length = mb_strlen($content);

        // If content fits within limit, return as-is
        if ($content_length <= $max_length) {
            return $content;
        }

        // Define separator and calculate its length
        $separator = "\n\n[...]\n\n";
        $separator_length = mb_strlen($separator);

        // Ensure minimum length to avoid negative values
        $min_length = $separator_length + 40; // At least 20 chars on each end
        if ($max_length < $min_length) {
            $max_length = $min_length;
        }

        // Calculate how much to take from each end
        // Take 60% from the beginning (introductions, key points) and 40% from the end (conclusions)
        $available_length = $max_length - $separator_length;
        $start_length = (int) ($available_length * 0.6);
        $end_length = $available_length - $start_length;

        $start_content = mb_substr($content, 0, $start_length);
        $end_content = mb_substr($content, -$end_length);

        return $start_content . $separator . $end_content;
    }

    /**
     * Generate content using AI with optional history logging.
     *
     * @param string $prompt
     * @param array $options
     * @param string $log_type
     * @param AIPS_History_Container|null $history
     * @return string|WP_Error
     */
    private function generate_content_with_logging($prompt, $options, $log_type, $history) {
        // Log AI request before making the call
        if ($history) {
            $history->record(
                'ai_request',
                "Requesting AI generation for {$log_type}",
                array(
                    'prompt' => $prompt,
                    'options' => $options,
                ),
                null,
                array('component' => $log_type)
            );
        }

        $result = $this->ai_service->generate_text($prompt, $options);

        if (is_wp_error($result)) {
            // Log the error
            if ($history) {
                $history->record(
                    'error',
                    "AI generation failed for {$log_type}: " . $result->get_error_message(),
                    array(
                        'prompt' => $prompt,
                        'options' => $options,
                    ),
                    null,
                    array('component' => $log_type, 'error' => $result->get_error_message())
                );
            }

            $this->logger->log($result->get_error_message(), 'error', array(
                'component' => $log_type,
                'prompt_length' => strlen($prompt)
            ));
        } else {
            // Log successful AI response
            if ($history) {
                $history->record(
                    'ai_response',
                    "AI generation successful for {$log_type}",
                    null,
                    $result,
                    array('component' => $log_type)
                );
            }

            $this->logger->log('Content generated successfully', 'info', array(
                'component' => $log_type,
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($result)
            ));
        }

        return $result;
    }
}
