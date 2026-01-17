<?php
/**
 * Template Variable Processor
 *
 * Handles the processing of template variables (e.g., {{date}}, {{topic}}, {{site_name}})
 * in prompt templates, separating this concern from content generation.
 *
 * Also supports AI Variables - custom variables that are resolved dynamically by AI
 * during content generation. AI Variables are any {{VariableName}} that is not in the
 * predefined list of system variables.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Template_Processor
 *
 * Provides template variable replacement functionality with extensibility
 * through WordPress filters. Also supports AI Variables that are resolved
 * dynamically using AI during content generation.
 */
class AIPS_Template_Processor {
    
    /**
     * Process template variables in a given string.
     *
     * Replaces placeholders like {{date}}, {{topic}}, {{site_name}} with actual values.
     * Variables are extensible through the 'aips_template_variables' filter.
     * Note: AI Variables are NOT processed here - use process_with_ai_variables() instead.
     *
     * @param string      $template The template string containing variables to replace.
     * @param string|null $topic    Optional topic value for {{topic}} and {{title}} variables.
     * @return string The processed template with variables replaced.
     */
    public function process($template, $topic = null) {
        $variables = $this->get_variables($topic);
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    /**
     * Process template with AI variable resolution.
     *
     * First resolves AI variables (custom variables not in the predefined list) using
     * the AI service, then processes standard template variables.
     *
     * @param string             $template    The template string containing variables.
     * @param string|null        $topic       Optional topic value for {{topic}} variables.
     * @param array              $ai_values   Pre-resolved AI variable values (key => value).
     * @return string The processed template with all variables replaced.
     */
    public function process_with_ai_variables($template, $topic = null, $ai_values = array()) {
        // First replace AI variables with their resolved values
        if (!empty($ai_values)) {
            foreach ($ai_values as $var_name => $value) {
                $template = str_replace('{{' . $var_name . '}}', $value, $template);
            }
        }
        
        // Then process standard template variables
        return $this->process($template, $topic);
    }
    
    /**
     * Extract AI variables from a template string.
     *
     * AI Variables are any {{VariableName}} that is NOT in the predefined list
     * of system variables. These are intended to be resolved dynamically by AI.
     *
     * @param string $template The template string to extract AI variables from.
     * @return array Array of AI variable names (without braces).
     */
    public function extract_ai_variables($template) {
        $ai_variables = array();
        $system_variables = $this->get_variable_names();
        
        // Extract all variables from the template
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $var_name) {
                $var_name = trim($var_name);
                
                // If it's not a system variable, it's an AI variable
                if (!in_array($var_name, $system_variables)) {
                    $ai_variables[] = $var_name;
                }
            }
        }
        
        // Remove duplicates and re-index
        return array_values(array_unique($ai_variables));
    }
    
    /**
     * Check if a template contains AI variables.
     *
     * @param string $template The template string to check.
     * @return bool True if the template contains AI variables.
     */
    public function has_ai_variables($template) {
        return !empty($this->extract_ai_variables($template));
    }
    
    /**
     * Build a prompt for AI to resolve AI variables.
     *
     * Creates a structured prompt that asks the AI to provide values for
     * the specified AI variables based on the content context.
     *
     * @param array  $ai_variables Array of AI variable names to resolve.
     * @param string $context      The content context/prompt to help AI understand what values to generate.
     * @return string The prompt to send to AI for variable resolution.
     */
    public function build_ai_variables_prompt($ai_variables, $context) {
        if (empty($ai_variables)) {
            return '';
        }
        
        $variables_list = implode(', ', $ai_variables);
        
        $prompt = "Based on the following content context, provide creative and appropriate values for these variables: {$variables_list}\n\n";
        $prompt .= "Content Context:\n{$context}\n\n";
        $prompt .= "IMPORTANT: Respond ONLY with a JSON object containing the variable names as keys and their values. ";
        $prompt .= "Do not include any explanation or extra text. ";
        $prompt .= "Example format: {\"VariableName1\": \"Value1\", \"VariableName2\": \"Value2\"}\n\n";
        $prompt .= "Provide values that are specific, relevant, and would make sense in the context of the content. ";
        $prompt .= "For comparison articles, ensure the values are distinct from each other.";
        
        return $prompt;
    }
    
    /**
     * Parse AI response for variable values.
     *
     * Extracts variable values from the AI's JSON response.
     * Handles common AI response formats including raw JSON and markdown-wrapped code blocks.
     *
     * @param string $response      The AI response containing variable values.
     * @param array  $ai_variables  The expected AI variable names.
     * @return array Associative array of variable names and their values.
     */
    public function parse_ai_variables_response($response, $ai_variables) {
        $values = array();
        
        // Clean up the response - remove any markdown code block formatting
        $response = trim($response);
        $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);
        $response = trim($response);
        
        // Try to parse as JSON
        $decoded = json_decode($response, true);
        
        if (is_array($decoded)) {
            foreach ($ai_variables as $var_name) {
                if (isset($decoded[$var_name])) {
                    $values[$var_name] = $decoded[$var_name];
                }
            }
        }
        
        return $values;
    }
    
    /**
     * Get all available template variables and their values.
     *
     * Builds an associative array of variable placeholders and their replacement values.
     * Core variables include date/time values, site information, and dynamic content.
     *
     * @param string|null $topic Optional topic value for topic-related variables.
     * @return array Associative array of variable placeholders and their values.
     */
    public function get_variables($topic = null) {
        $variables = array(
            '{{date}}' => date('F j, Y'),
            '{{year}}' => date('Y'),
            '{{month}}' => date('F'),
            '{{day}}' => date('l'),
            '{{time}}' => current_time('H:i'),
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_description}}' => get_bloginfo('description'),
            '{{random_number}}' => rand(1, 1000),
            '{{topic}}' => $topic ? $topic : '',
            '{{title}}' => $topic ? $topic : '', // Alias for topic
        );
        
        /**
         * Filter template variables.
         *
         * Allows developers to add custom template variables or modify existing ones.
         *
         * @param array       $variables Associative array of variable placeholders and values.
         * @param string|null $topic     The current topic being processed, if any.
         */
        return apply_filters('aips_template_variables', $variables, $topic);
    }
    
    /**
     * Get a list of all available variable names (without braces).
     *
     * Useful for documentation and UI display purposes.
     *
     * @param string|null $topic Optional topic value to use when building the list.
     * @return array Array of variable names (e.g., ['date', 'year', 'month']).
     */
    public function get_variable_names($topic = null) {
        $variables = $this->get_variables($topic);
        $names = array();
        
        foreach (array_keys($variables) as $key) {
            // Remove {{ and }} from variable keys
            $names[] = str_replace(array('{{', '}}'), '', $key);
        }
        
        return $names;
    }
    
    /**
     * Validate that a template string has valid variable syntax.
     *
     * Checks for common issues like unclosed braces. AI Variables (custom variables
     * not in the predefined list) are now allowed and will be resolved by AI.
     *
     * @param string $template          The template string to validate.
     * @param bool   $allow_ai_variables Whether to allow AI variables. Default true.
     * @return bool|WP_Error True if valid, WP_Error with details if invalid.
     */
    public function validate_template($template, $allow_ai_variables = true) {
        // Check for unclosed braces
        $open_count = substr_count($template, '{{');
        $close_count = substr_count($template, '}}');
        
        if ($open_count !== $close_count) {
            return new WP_Error(
                'unclosed_braces',
                __('Template has unclosed variable braces. Each {{ must have a matching }}.', 'ai-post-scheduler')
            );
        }
        
        // If AI variables are allowed, we only check brace syntax, not variable names
        if ($allow_ai_variables) {
            return true;
        }
        
        // Legacy behavior: validate that all variables are in the predefined list
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        
        if (!empty($matches[1])) {
            $available_vars = $this->get_variable_names();
            
            foreach ($matches[1] as $var_name) {
                $var_name = trim($var_name);
                
                if (!in_array($var_name, $available_vars)) {
                    return new WP_Error(
                        'invalid_variable',
                        sprintf(
                            __('Unknown variable: {{%s}}. Available variables: %s', 'ai-post-scheduler'),
                            $var_name,
                            implode(', ', array_map(function($v) { return '{{' . $v . '}}'; }, $available_vars))
                        )
                    );
                }
            }
        }
        
        return true;
    }
}
