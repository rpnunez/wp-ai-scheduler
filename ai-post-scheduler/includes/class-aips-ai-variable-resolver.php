<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * Resolves AI Variables from generation contexts and templates.
 *
 * Extracted from AIPS_Generator to adhere to Single Responsibility Principle.
 */
class AIPS_AI_Variable_Resolver {

    /**
     * @var mixed Template processor instance.
     */
    private $template_processor;

    /**
     * @var mixed Generation logger instance.
     */
    private $generation_logger;

    /**
     * @var callable Callback to invoke generate_content method without circular dependency.
     */
    private $content_generator_callback;

    /**
     * Constructor for AIPS_AI_Variable_Resolver.
     *
     * @param mixed    $template_processor         Processor for template variables.
     * @param mixed    $generation_logger          Logger for generation flow.
     * @param callable $content_generator_callback Callback representing generate_content.
     */
    public function __construct($template_processor, $generation_logger, callable $content_generator_callback) {
        $this->template_processor = $template_processor;
        $this->generation_logger = $generation_logger;
        $this->content_generator_callback = $content_generator_callback;
    }

    /**
     * Resolve AI Variables from a generation context.
     *
     * Extracts AI Variables from the title prompt and uses AI to generate
     * appropriate values based on the content context.
     *
     * @param mixed    $context                 Generation context.
     * @param string   $content                 Generated article content for context.
     * @param callable $smart_truncate_callback Callback for truncating content.
     * @return array Associative array of resolved AI variable values.
     */
    public function resolve_ai_variables_from_context($context, $content, callable $smart_truncate_callback) {
        $title_prompt = $context->get_title_prompt();

        if ($context->get_type() === 'template' && $context->get_voice_id()) {
            $voice_obj = $context->get_voice();
            if ($voice_obj && !empty($voice_obj->title_prompt)) {
                $title_prompt = $voice_obj->title_prompt;
            }
        }

        if (!method_exists($this->template_processor, 'extract_ai_variables')) {
            return array();
        }

        $ai_variables = $this->template_processor->extract_ai_variables($title_prompt);
        if (empty($ai_variables)) {
            return array();
        }

        $context_str = "Content Prompt: " . $context->get_content_prompt() . "\n\n";
        $context_str .= "Generated Article Content:\n" . call_user_func($smart_truncate_callback, $content, 2000);

        return $this->resolve_ai_variables_for_template_string($title_prompt, $context_str, 'ai_variables');
    }

    /**
     * Resolve AI variables for a template string using context text.
     *
     * @param string $template_string Template that may include AI variables.
     * @param string $context_str     Context used to resolve variable values.
     * @param string $log_type        Log component label for observability.
     * @return array Associative array of resolved AI variable values.
     */
    public function resolve_ai_variables_for_template_string($template_string, $context_str, $log_type = 'ai_variables') {
        if (!method_exists($this->template_processor, 'extract_ai_variables')) {
            return array();
        }

        $ai_variables = $this->template_processor->extract_ai_variables($template_string);
        if (empty($ai_variables)) {
            return array();
        }

        $resolve_prompt = $this->template_processor->build_ai_variables_prompt($ai_variables, $context_str);
        $options = array('max_tokens' => 200);
        $result = call_user_func($this->content_generator_callback, $resolve_prompt, $options, $log_type);

        if (is_wp_error($result)) {
            $this->generation_logger->log('Failed to resolve AI variables: ' . $result->get_error_message(), 'warning');
            return array();
        }

        $resolved_values = $this->template_processor->parse_ai_variables_response($result, $ai_variables);

        if (empty($resolved_values)) {
            $this->generation_logger->log('AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.', 'warning', array(
                'variables' => $ai_variables,
                'raw_response' => $result,
                'component' => $log_type
            ));
        } else {
            $this->generation_logger->log('Resolved AI variables', 'info', array(
                'variables' => $ai_variables,
                'resolved' => $resolved_values,
                'component' => $log_type
            ));
        }

        return $resolved_values;
    }
}
