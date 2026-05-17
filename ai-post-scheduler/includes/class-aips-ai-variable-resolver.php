<?php
if (!defined('ABSPATH')) {
    die;
}

/**
 * AIPS_AI_Variable_Resolver
 *
 * Extracted from AIPS_Generator to enforce Single Responsibility.
 * Responsible for parsing and resolving AI variables embedded in prompts.
 */
class AIPS_AI_Variable_Resolver {

    /**
     * @var AIPS_Template_Processor
     */
    private $template_processor;

    /**
     * @var AIPS_Generation_Logger
     */
    private $generation_logger;

    /**
     * @var callable Callback to invoke AI generation to avoid circular dependencies.
     */
    private $content_generator_callback;

    /**
     * Constructor.
     *
     * @param AIPS_Template_Processor $template_processor Template processor instance.
     * @param AIPS_Generation_Logger  $generation_logger  Logger instance.
     * @param callable                $content_generator_callback Callback to AIPS_Generator->generate_content().
     */
    public function __construct(
        $template_processor,
        $generation_logger,
        callable $content_generator_callback
    ) {
        $this->template_processor = $template_processor;
        $this->generation_logger = $generation_logger;
        $this->content_generator_callback = $content_generator_callback;
    }

    /**
     * Resolves AI variables from a template string using a voice and content context.
     *
     * @param string      $template The template string.
     * @param string      $content  The generated content context.
     * @param object|null $voice    Optional voice object.
     * @return array Resolved AI variables.
     */
    public function resolve_ai_variables($template, $content, $voice = null) {
        $context = new AIPS_Template_Context($template, $voice, null);
        return $this->resolve_ai_variables_from_context($context, $content);
    }

    /**
     * Resolve AI Variables from a generation context.
     *
     * Extracts AI Variables from the title prompt and uses AI to generate
     * appropriate values based on the content context.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @param string                  $content Generated article content for context.
     * @return array Associative array of resolved AI variable values.
     */
    public function resolve_ai_variables_from_context($context, $content) {
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
        $context_str .= "Generated Article Content:\n" . $this->smart_truncate_content($content, 2000);

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
                'component' => $log_type,
            ));
        } else {
            $this->generation_logger->log('Resolved AI variables', 'info', array(
                'variables' => $ai_variables,
                'resolved'   => $resolved_values,
                'component' => $log_type,
            ));
        }

        return $resolved_values;
    }

    /**
     * Build context text for featured image AI variable resolution.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @param string                  $content Generated content.
     * @param string                  $title   Generated title.
     * @return string
     */
    public function build_featured_image_variable_context($context, $content = '', $title = '') {
        $context_parts = array();

        $context_parts[] = "Content Prompt: " . $context->get_content_prompt();
        $context_parts[] = "Title Prompt: " . $context->get_title_prompt();

        if (!empty($title)) {
            $context_parts[] = "Generated Title: " . $title;
        }

        if (!empty($content)) {
            $context_parts[] = "Generated Article Content:\n" . $this->smart_truncate_content($content, 1600);
        }

        return implode("\n\n", $context_parts);
    }

    /**
     * Smart truncate content to preserve the beginning and the end.
     *
     * @param string $content    The content to truncate.
     * @param int    $max_length Maximum length allowed.
     * @return string Truncated content.
     */
    public function smart_truncate_content($content, $max_length = 2000) {
        $content_length = mb_strlen($content);

        if ($content_length <= $max_length) {
            return $content;
        }

        $separator = "\n\n[...]\n\n";
        $separator_length = mb_strlen($separator);

        $min_length = $separator_length + 40;
        if ($max_length < $min_length) {
            $max_length = $min_length;
        }

        $available_length = $max_length - $separator_length;
        $start_length = (int) ($available_length * 0.6);
        $end_length = $available_length - $start_length;

        $start_content = mb_substr($content, 0, $start_length);
        $end_content = mb_substr($content, -$end_length);

        return $start_content . $separator . $end_content;
    }
}
