<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * Processes featured image prompts and resolves variables.
 *
 * Extracted from AIPS_Generator to adhere to Single Responsibility Principle.
 */
class AIPS_Image_Prompt_Processor {

    /**
     * @var mixed Template processor instance.
     */
    private $template_processor;

    /**
     * @var AIPS_AI_Variable_Resolver AI variable resolver instance.
     */
    private $ai_variable_resolver;

    /**
     * @var callable Callback to invoke smart_truncate_content.
     */
    private $smart_truncate_callback;

    /**
     * Constructor for AIPS_Image_Prompt_Processor.
     *
     * @param mixed                     $template_processor      Processor for template variables.
     * @param AIPS_AI_Variable_Resolver $ai_variable_resolver    Resolver for AI-specific variables.
     * @param callable                  $smart_truncate_callback Callback for content truncation.
     */
    public function __construct($template_processor, $ai_variable_resolver, callable $smart_truncate_callback) {
        $this->template_processor = $template_processor;
        $this->ai_variable_resolver = $ai_variable_resolver;
        $this->smart_truncate_callback = $smart_truncate_callback;
    }

    /**
     * Build context text for featured image AI variable resolution.
     *
     * @param mixed  $context Generation context.
     * @param string $content Generated content.
     * @param string $title   Generated title.
     * @return string Formatted context string for image prompts.
     */
    public function build_featured_image_variable_context($context, $content = '', $title = '') {
        $context_parts = array();

        if (!empty($context->get_content_prompt())) {
            $context_parts[] = 'Content Prompt: ' . $context->get_content_prompt();
        }

        if (!empty($title)) {
            $context_parts[] = 'Generated Post Title: ' . $title;
        }

        if (!empty($content)) {
            $context_parts[] = "Generated Article Content:\n" . call_user_func($this->smart_truncate_callback, $content, 1600);
        }

        if (!empty($context->get_topic())) {
            $context_parts[] = 'Topic: ' . $context->get_topic();
        }

        return implode("\n\n", $context_parts);
    }

    /**
     * Process featured image prompt with basic template variables and AI variables.
     *
     * Resolves any AI variables (custom {{VariableName}} placeholders not in the
     * system variable list) using the generated content and title as context,
     * then processes standard template variables such as {{topic}}.
     *
     * @param mixed  $context Generation context.
     * @param string $content Generated article content.
     * @param string $title   Generated post title.
     * @return string Processed image prompt with all variables replaced.
     */
    public function process_featured_image_prompt($context, $content = '', $title = '') {
        $image_prompt = $context->get_image_prompt();
        if (empty($image_prompt)) {
            return '';
        }

        $topic_str = $context->get_topic();
        $resolved_ai_variables = array();

        if (method_exists($this->template_processor, 'has_ai_variables') && $this->template_processor->has_ai_variables($image_prompt)) {
            $image_context = $this->build_featured_image_variable_context($context, $content, $title);
            $resolved_ai_variables = $this->ai_variable_resolver->resolve_ai_variables_for_template_string($image_prompt, $image_context, 'ai_variables_featured_image');
        }

        if (method_exists($this->template_processor, 'process_with_ai_variables')) {
            $processed_prompt = $this->template_processor->process_with_ai_variables($image_prompt, $topic_str, $resolved_ai_variables);
        } else {
            $processed_prompt = $this->template_processor->process($image_prompt, $topic_str);
        }

        return $this->remove_unresolved_template_placeholders($processed_prompt);
    }

    /**
     * Remove any unresolved template placeholders from a processed prompt.
     *
     * This is a defensive cleanup step for public featured image prompt
     * processing so downstream preview and generation paths never receive raw
     * {{Variable}} tokens when AI-variable resolution is partial.
     *
     * @param string $prompt Processed prompt text.
     * @return string Prompt with unresolved placeholders removed.
     */
    public function remove_unresolved_template_placeholders($prompt) {
        $prompt = (string) $prompt;
        $prompt = preg_replace('/\{\{[^{}]+\}\}/', '', $prompt);
        if (!is_string($prompt)) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', $prompt));
    }
}
