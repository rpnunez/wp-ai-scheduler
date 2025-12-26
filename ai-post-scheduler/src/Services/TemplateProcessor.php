<?php
/**
 * Template Variable Processor
 *
 * Handles the processing of template variables (e.g., {{date}}, {{topic}}, {{site_name}})
 * in prompt templates, separating this concern from content generation.
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
 * through WordPress filters.
 */
class AIPS_Template_Processor {
    
    /**
     * Process template variables in a given string.
     *
     * Replaces placeholders like {{date}}, {{topic}}, {{site_name}} with actual values.
     * Variables are extensible through the 'aips_template_variables' filter.
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
     * Checks for common issues like unclosed braces or invalid variable names.
     *
     * @param string $template The template string to validate.
     * @return bool|WP_Error True if valid, WP_Error with details if invalid.
     */
    public function validate_template($template) {
        // Check for unclosed braces
        $open_count = substr_count($template, '{{');
        $close_count = substr_count($template, '}}');
        
        if ($open_count !== $close_count) {
            return new WP_Error(
                'unclosed_braces',
                __('Template has unclosed variable braces. Each {{ must have a matching }}.', 'ai-post-scheduler')
            );
        }
        
        // Extract all variables used in the template
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
