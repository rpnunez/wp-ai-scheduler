<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Prompt_Builder {

    private $template_processor;
    private $structure_manager;

    public function __construct($template_processor = null, $structure_manager = null) {
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
    }

    /**
     * Builds the complete content prompt based on context.
     *
     * Supports both legacy template-based approach and new context-based approach.
     *
     * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
     * @param string|null $topic    The topic for the post (legacy, may be null if using context).
     * @param object|null $voice    Optional voice object (legacy, may be null if using context).
     * @return string The constructed prompt.
     */
    public function build_content_prompt($template_or_context, $topic = null, $voice = null) {
        // Check if we're using the new context-based approach
        if ($template_or_context instanceof AIPS_Generation_Context) {
            $context = $template_or_context;
            
            do_action('aips_before_build_content_prompt', $context, null);
            
            // Get the base content prompt from context
            $processed_prompt = $context->get_content_prompt();
            
            // Check if article_structure_id is provided
            $article_structure_id = $context->get_article_structure_id();
            $topic_str = $context->get_topic();
            
            if ($article_structure_id && $topic_str) {
                // Use article structure to build prompt
                $structured_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic_str);
                
                if (!is_wp_error($structured_prompt)) {
                    $processed_prompt = $structured_prompt;
                } else {
                    // Fall back to processing the base prompt with topic
                    $processed_prompt = $this->template_processor->process($processed_prompt, $topic_str);
                }
            } elseif ($topic_str) {
                // Process template variables in the prompt
                $processed_prompt = $this->template_processor->process($processed_prompt, $topic_str);
            }
            
            // For template contexts with voice, add voice instructions
            if ($context->get_type() === 'template' && $context->get_voice_id()) {
                $voice_obj = $context->get_voice();
                if ($voice_obj && !empty($voice_obj->content_instructions)) {
                    $voice_instructions = $this->template_processor->process($voice_obj->content_instructions, $topic_str);
                    $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
                }
            }
            
            $content_prompt = apply_filters('aips_content_prompt', $processed_prompt, $context, $topic_str);
            
            return $content_prompt;
        }
        
        // Legacy template-based approach
        $template = $template_or_context;
        
        do_action('aips_before_build_content_prompt', $template, $topic);

        // Check if article_structure_id is provided, build prompt with structure
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        if ($article_structure_id) {
            // Use article structure to build prompt
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);

            if (is_wp_error($processed_prompt)) {
                // Fall back to regular template processing
                $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
            }
        } else {
            // Use traditional template processing
            $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
        }

        if ($voice) {
            $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
            $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
        }

        $content_prompt = $processed_prompt;

        $content_prompt = apply_filters('aips_content_prompt', $content_prompt, $template, $topic);

        return $content_prompt;
    }

    /**
     * Builds an auxiliary context string for AI Engine queries.
     *
     * This keeps instructions (voice, formatting, safety) out of the main message
     * while still providing them through the AI Engine's context channel.
     *
     * Supports both legacy template-based approach and new context-based approach.
     *
     * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
     * @param string|null $topic    The topic for the post (legacy).
     * @param object|null $voice    Optional voice object (legacy).
     * @return string Context string (may be empty).
     */
    public function build_content_context($template_or_context, $topic = null, $voice = null) {
        $context_parts = array();
        
        // Check if we're using the new context-based approach
        if ($template_or_context instanceof AIPS_Generation_Context) {
            $context = $template_or_context;
            $topic_str = $context->get_topic();
            
            // For template contexts with voice, add voice content instructions
            if ($context->get_type() === 'template' && $context->get_voice_id()) {
                $voice_obj = $context->get_voice();
                if ($voice_obj && !empty($voice_obj->content_instructions)) {
                    $context_parts[] = $this->template_processor->process($voice_obj->content_instructions, $topic_str);
                }
            }
            
            $context_parts[] = $this->get_output_instructions();
            
            /**
             * Filter the context sent to AI Engine for content generation.
             *
             * @since 1.6.0
             *
             * @param array  $context_parts Array of context fragments.
             * @param AIPS_Generation_Context $context Generation context object.
             * @param string $topic_str     Topic string.
             * @param object|null $voice_obj Optional voice object.
             */
            $context_parts = apply_filters('aips_content_context_parts', $context_parts, $context, $topic_str, null);
        } else {
            // Legacy template-based approach
            $template = $template_or_context;
            
            if ($voice && !empty($voice->content_instructions)) {
                $context_parts[] = $this->template_processor->process($voice->content_instructions, $topic);
            }

            $context_parts[] = $this->get_output_instructions();

            /**
             * Filter the context sent to AI Engine for content generation.
             *
             * @since 1.6.0
             *
             * @param array  $context_parts Array of context fragments.
             * @param object $template      Template object.
             * @param string $topic         Topic string.
             * @param object $voice         Optional voice object.
             */
            $context_parts = apply_filters('aips_content_context_parts', $context_parts, $template, $topic, $voice);
        }

        $context_parts = array_filter(
            array_map('trim', $context_parts),
            function($part) {
                return !empty($part);
            }
        );

        return implode("\n\n", $context_parts);
    }

    /**
     * Builds the complete prompt for title generation.
     *
     * This method encapsulates all title prompt construction logic. It uses the
     * generated article content as primary context, and applies the following
     * precedence for title instructions:
     *   1. Voice title prompt (if provided)
     *   2. Template/Context title prompt (if provided)
     *
     * The final prompt structure sent to AI:
     *   "Generate a title for a blog post, based on the content below. Here are your instructions:\n\n"
     *   (Voice Title Prompt OR Template Title Prompt)
     *   "\n\nHere is the content:\n\n"
     *   (Generated Post Content)
     *
     * Supports both legacy template-based approach and new context-based approach.
     *
     * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
     * @param string|null $topic    Optional topic to be injected into prompts (legacy).
     * @param object|null $voice    Optional voice object with overrides (legacy).
     * @param string      $content  Generated article content used as context.
     * @return string The complete title generation prompt.
     */
    public function build_title_prompt($template_or_context, $topic = null, $voice = null, $content = '') {
        // Build title instructions based on voice or template configuration.
        // Voice title prompt takes precedence over template title prompt.
        $title_instructions = '';
        
        // Check if we're using the new context-based approach
        if ($template_or_context instanceof AIPS_Generation_Context) {
            $context = $template_or_context;
            $topic_str = $context->get_topic();
            
            // For template contexts with voice, check voice title prompt first
            if ($context->get_type() === 'template' && $context->get_voice_id()) {
                $voice_obj = $context->get_voice();
                if ($voice_obj && !empty($voice_obj->title_prompt)) {
                    $title_instructions = $this->template_processor->process($voice_obj->title_prompt, $topic_str);
                }
            }
            
            // If no voice title prompt, use context title prompt
            if (empty($title_instructions)) {
                $title_prompt = $context->get_title_prompt();
                if (!empty($title_prompt)) {
                    $title_instructions = $this->template_processor->process($title_prompt, $topic_str);
                }
            }
            
            // Build the title generation prompt using the generated content as context.
            $prompt = "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else. Here are your instructions:\n\n";

            if (!empty($title_instructions)) {
                $prompt .= $title_instructions . "\n\n";
            }

            $prompt .= "Here is the content:\n\n" . $content;

            // Allow filtering of title prompt
            $prompt = apply_filters('aips_title_prompt', $prompt, $context, $topic_str, null, $content);

            return $prompt;
        }
        
        // Legacy template-based approach
        $template = $template_or_context;

        if ($voice && !empty($voice->title_prompt)) {
            $title_instructions = $this->template_processor->process($voice->title_prompt, $topic);
        } elseif (!empty($template->title_prompt)) {
            $title_instructions = $this->template_processor->process($template->title_prompt, $topic);
        }

        // Build the title generation prompt using the generated content as context.
        $prompt = "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else. Here are your instructions:\n\n";

        if (!empty($title_instructions)) {
            $prompt .= $title_instructions . "\n\n";
        }

        $prompt .= "Here is the content:\n\n" . $content;

        // Allow filtering of title prompt
        $prompt = apply_filters('aips_title_prompt', $prompt, $template, $topic, $voice, $content);

        return $prompt;
    }

    /**
     * Builds the complete prompt for excerpt generation.
     *
     * Constructs a prompt that instructs the AI to create a short, compelling
     * excerpt for the article. Includes voice-specific instructions if provided.
     *
     * Supports both legacy template-based approach and new context-based approach.
     *
     * @param string      $title   Title of the generated article.
     * @param string      $content The article content to summarize.
     * @param object|null $voice   Optional voice object with excerpt instructions (legacy).
     * @param string|null $topic   Optional topic to be injected into prompts (legacy).
     * @return string The complete excerpt generation prompt.
     */
    public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
        $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";
        
        // Add voice-specific excerpt instructions if provided
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
            $excerpt_prompt .= $voice_instructions . "\n\n";
        }
        
        $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";
        
        // Allow filtering of excerpt prompt
        $excerpt_prompt = apply_filters('aips_excerpt_prompt', $excerpt_prompt, $title, $content, $voice, $topic);
        
        return $excerpt_prompt;
    }

    /**
     * Builds voice-specific excerpt instructions (legacy method for backward compatibility).
     *
     * This method is maintained for backward compatibility but the new
     * build_excerpt_prompt() should be preferred for full excerpt generation.
     *
     * @deprecated Use build_excerpt_prompt() instead
     * @param object|null $voice
     * @param string|null $topic
     * @return string|null
     */
    public function build_excerpt_instructions($voice, $topic) {
        if ($voice && !empty($voice->excerpt_instructions)) {
            return $this->template_processor->process($voice->excerpt_instructions, $topic);
        }
        return null;
    }

    /**
     * Standard output instructions for article formatting.
     *
     * Provides explicit instructions to prevent markdown formatting and ensure
     * clean HTML output suitable for WordPress posts.
     *
     * @return string
     */
    private function get_output_instructions() {
        return 'CRITICAL INSTRUCTIONS:
- Output ONLY the article content, nothing else
- Do NOT include any preamble, thinking text, or commentary like "Let\'s create..." or "Here\'s..."
- Do NOT use markdown formatting (no ```, no **, no __)
- Use proper HTML tags: <h2> for section titles, <p> for paragraphs
- For code samples: wrap code in <pre><code> tags with HTML entities (use &lt; for <, &gt; for >, &amp; for &)
- Example code format: <pre><code>&lt;div class="example"&gt;content&lt;/div&gt;</code></pre>
- Do NOT include markdown code fences like ```html or ```
- Start directly with the article content (typically an opening paragraph or <h2> heading)
- End with a concise summary paragraph';
    }
}
