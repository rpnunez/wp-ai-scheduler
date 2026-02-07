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
     * @param bool        $use_conversation_context Whether chatbot conversation context is available (chatId exists).
     * @return string The complete title generation prompt.
     */
    public function build_title_prompt($template_or_context, $topic = null, $voice = null, $content = '', $use_conversation_context = false) {
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
            
            // Build the title generation prompt
            // If using conversation context (chatId), reference the article just generated
            // Otherwise, include the full content in the prompt
            if ($use_conversation_context) {
                $prompt = "Based on the article content you just generated, please create a compelling title. Respond with ONLY the most relevant title, nothing else.";
            } else {
                $prompt = "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.";
            }
            
            if (!empty($title_instructions)) {
                $prompt .= " Here are your instructions:\n\n" . $title_instructions;
            }
            
            // Only include content if not using conversation context
            if (!$use_conversation_context && !empty($content)) {
                $prompt .= "\n\nHere is the content:\n\n" . $content;
            }

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

        // Build the title generation prompt
        // If using conversation context (chatId), reference the article just generated
        // Otherwise, include the full content in the prompt
        if ($use_conversation_context) {
            $prompt = "Based on the article content you just generated, please create a compelling title. Respond with ONLY the most relevant title, nothing else.";
        } else {
            $prompt = "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.";
        }

        if (!empty($title_instructions)) {
            $prompt .= " Here are your instructions:\n\n" . $title_instructions;
        }

        // Only include content if not using conversation context
        if (!$use_conversation_context && !empty($content)) {
            $prompt .= "\n\nHere is the content:\n\n" . $content;
        }

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
     * @param bool        $use_conversation_context Whether chatbot conversation context is available (chatId exists).
     * @return string The complete excerpt generation prompt.
     */
    public function build_excerpt_prompt($title, $content, $voice = null, $topic = null, $use_conversation_context = false) {
        // Build the excerpt prompt
        // If using conversation context (chatId), reference the article just created
        // Otherwise, include the full title and content in the prompt
        if ($use_conversation_context) {
            $excerpt_prompt = "Based on the article content and title you just created, please write a short excerpt between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";
        } else {
            $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";
        }
        
        // Add voice-specific excerpt instructions if provided
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
            $excerpt_prompt .= $voice_instructions . "\n\n";
        }
        
        // Only include title and body if not using conversation context
        if (!$use_conversation_context) {
            $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
            $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
            $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";
        }
        
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
     * @return string
     */
    private function get_output_instructions() {
        return 'Output the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.';
    }

    /**
     * Build all prompts for a template configuration.
     *
     * Generates content, title, excerpt, and image prompts for a given template
     * configuration, ensuring consistency across preview and generation flows.
     *
     * @since 1.7.0
     * @param object      $template_data Template data object with configuration.
     * @param string|null $sample_topic  Sample topic to use (default: 'Example Topic').
     * @param object|null $voice         Optional voice object.
     * @return array Array containing 'prompts' and 'metadata' keys.
     */
    public function build_prompts($template_data, $sample_topic = null, $voice = null) {
        if (empty($sample_topic)) {
            $sample_topic = 'Example Topic';
        }

        // Build content prompt
        $content_prompt = $this->build_content_prompt($template_data, $sample_topic, $voice);

        // Build title prompt
        $sample_content = '[Generated article content would appear here]';
        $title_prompt = $this->build_title_prompt($template_data, $sample_topic, $voice, $sample_content);

        // Build excerpt prompt (requires title and content)
        $sample_title = '[Generated title would appear here]';
        $excerpt_prompt = $this->build_excerpt_prompt($sample_title, $sample_content, $voice, $sample_topic);

        // Build image prompt if enabled
        $image_prompt_processed = '';
        if (isset($template_data->generate_featured_image) && $template_data->generate_featured_image 
            && isset($template_data->featured_image_source) && $template_data->featured_image_source === 'ai_prompt' 
            && !empty($template_data->image_prompt)) {
            $image_prompt_processed = $this->template_processor->process($template_data->image_prompt, $sample_topic);
        }

        // Get voice name if applicable
        $voice_name = '';
        if ($voice && isset($voice->name)) {
            $voice_name = $voice->name;
        }

        // Get article structure name if applicable
        $structure_name = '';
        if (isset($template_data->article_structure_id) && $template_data->article_structure_id > 0) {
            $structure = $this->structure_manager->get_structure($template_data->article_structure_id);
            if ($structure && !is_wp_error($structure) && isset($structure['name'])) {
                $structure_name = $structure['name'];
            }
        }

        return array(
            'prompts' => array(
                'content' => $content_prompt,
                'title' => $title_prompt,
                'excerpt' => $excerpt_prompt,
                'image' => $image_prompt_processed,
            ),
            'metadata' => array(
                'voice' => $voice_name,
                'article_structure' => $structure_name,
                'sample_topic' => $sample_topic,
            ),
        );
    }

    /**
     * Get voice object by ID.
     *
     * Helper method to retrieve voice configuration.
     *
     * @since 1.7.0
     * @param int $voice_id Voice ID.
     * @return object|null Voice object or null if not found.
     */
    public function get_voice($voice_id) {
        if ($voice_id <= 0) {
            return null;
        }

        $voice_service = new AIPS_Voices();
        return $voice_service->get($voice_id);
    }
}
