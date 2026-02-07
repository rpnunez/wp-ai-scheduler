<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_Generator
 *
 * Responsible for orchestrating the AI content generation pipeline:
 * - Building prompts
 * - Calling the AI service for text and images
 * - Creating posts and updating history
 * - Tracking a per-request generation session for observability
 */
class AIPS_Generator {
    
    private $ai_service;
    private $logger;
    
    /**
     * @var AIPS_History_Service History service for unified logging
     */
    private $history_service;
    
    /**
     * @var AIPS_History_Container|null Current history container
     */
    private $current_history;
    
    /**
     * @var AIPS_Generation_Logger Handles logging logic.
     */
    private $generation_logger;

    private $template_processor;
    private $image_service;
    private $structure_manager;
    private $post_creator;
    private $prompt_builder;
    
    /**
     * Constructor.
     *
     * Accepts dependencies for easier testing; falls back to concrete
     * implementations when not provided.
     *
     * @param object|null $logger
     * @param object|null $ai_service
     * @param object|null $template_processor
     * @param object|null $image_service
     * @param object|null $structure_manager
     * @param object|null $post_creator
     * @param object|null $history_service
     * @param object|null $prompt_builder
     */
    public function __construct(
        $logger = null,
        $ai_service = null,
        $template_processor = null,
        $image_service = null,
        $structure_manager = null,
        $post_creator = null,
        $history_service = null,
        $prompt_builder = null
    ) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->ai_service = $ai_service ?: new AIPS_AI_Service();
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->image_service = $image_service ?: new AIPS_Image_Service($this->ai_service);
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
        $this->post_creator = $post_creator ?: new AIPS_Post_Creator();
        $this->history_service = $history_service ?: new AIPS_History_Service();
        $this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);

        // Initialize session tracker
        $this->current_session = new AIPS_Generation_Session();

        // Initialize logger wrapper (no longer using history_repository directly)
        $this->generation_logger = new AIPS_Generation_Logger($this->logger, null, $this->current_session);
    }
    
    /**
     * Check if AI is available in the configured AI service.
     *
     * @return bool True if AI Engine is available, false otherwise.
     */
    public function is_available() {
        return $this->ai_service->is_available();
    }
    
    /**
     * Generate content using AI.
     *
     * Wrapper method that uses the AI Service to generate text content and
     * records the call in the session log. Returns generated text or WP_Error.
     *
     * @param string $prompt   The prompt to send to AI.
     * @param array  $options  Optional AI generation options.
     * @param string $log_type Optional type label for logging.
     * @return string|WP_Error The generated content or WP_Error on failure.
     */
    public function generate_content($prompt, $options = array(), $log_type = 'content') {
        // Log AI request before making the call
        if ($this->current_history) {
            $this->current_history->record(
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
            if ($this->current_history) {
                $this->current_history->record(
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
            if ($this->current_history) {
                $this->current_history->record(
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
    
    /**
     * Resolve AI Variables for a template.
     *
     * Extracts AI Variables from the title prompt and uses AI to generate
     * appropriate values based on the content context.
     *
     * @param object      $template Template object containing prompts.
     * @param string      $content  Generated article content for context.
     * @param object|null $voice    Optional voice object with title prompt.
     * @return array Associative array of resolved AI variable values.
     */
    public function resolve_ai_variables($template, $content, $voice = null) {
        // For backward compatibility, convert to context and delegate
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
    private function resolve_ai_variables_from_context($context, $content) {
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
        $result = $this->generate_content($resolve_prompt, $options, 'ai_variables');
        
        if (is_wp_error($result)) {
            $this->generation_logger->log('Failed to resolve AI variables: ' . $result->get_error_message(), 'warning');
            return array();
        }
        
        // Parse the AI response to extract variable values
        $resolved_values = $this->template_processor->parse_ai_variables_response($result, $ai_variables);
        
        if (empty($resolved_values)) {
            // AI call succeeded but we could not extract any variable values.
            // This usually indicates invalid JSON or an unexpected response format.
            $this->generation_logger->log('AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.', 'warning', array(
                'variables' => $ai_variables,
                'raw_response' => $result,
            ));
        } else {
            $this->generation_logger->log('Resolved AI variables', 'info', array(
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
    private function smart_truncate_content($content, $max_length = 2000) {
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
     * Generate a post title based on the generated content, template, and optional voice/topic.
     *
     * Delegates title prompt construction to AIPS_Prompt_Builder for consistency
     * and to follow the Single Responsibility Principle. The Prompt Builder handles
     * all the logic for building prompts (title, excerpt, content).
     *
     * @param object      $template Template object containing prompts and settings.
     * @param object|null $voice    Optional voice object with overrides.
     * @param string|null $topic    Optional topic to be injected into prompts.
     * @param string      $content  Generated article content used as context.
     * @param array       $options  AI options (e.g., model, max_tokens override).
     * @param array       $ai_variables Optional resolved AI variables.
     * @return string|WP_Error      Generated title string or WP_Error on failure.
     */
    public function generate_title($template, $voice = null, $topic = null, $content = '', $options = array(), $ai_variables = array()) {
        // For backward compatibility, convert to context and delegate
        $context = new AIPS_Template_Context($template, $voice, $topic);
        return $this->generate_title_from_context($context, $content, $ai_variables, $options);
    }
    
    /**
     * Generate a post title from a generation context.
     *
     * @param AIPS_Generation_Context $context      Generation context.
     * @param string                  $content      Generated article content used as context.
     * @param array                   $ai_variables Optional resolved AI variables.
     * @param array                   $options      AI options (e.g., model, max_tokens override).
     * @return string|WP_Error Generated title string or WP_Error on failure.
     */
    private function generate_title_from_context($context, $content = '', $ai_variables = array(), $options = array()) {
        // Delegate prompt building to Prompt Builder
        $prompt = $this->prompt_builder->build_title_prompt($context, null, null, $content);

        // Set token limit for title generation
        $options['max_tokens'] = 100;

        // Request title from AI service
        $result = $this->generate_content($prompt, $options, 'title');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $title = trim($result);

        // Strip surrounding quotes from AI responses
        $title = preg_replace('/^["\']|["\']$/', '', $title);

        return $title;
    }
    
    /**
     * Generate an excerpt (short summary) for a post.
     *
     * Delegates excerpt prompt construction to AIPS_Prompt_Builder for consistency.
     * Ensures the excerpt length is within a reasonable limit and removes
     * surrounding quotes from the AI output.
     *
     * @param string      $title   Title of the generated article.
     * @param string      $content The article content to summarize.
     * @param object|null $voice   Optional voice object with excerpt instructions.
     * @param string|null $topic   Optional topic to be injected into prompts.
     * @param array       $options AI options.
     * @return string Short excerpt string (max 160 chars). Empty string on failure.
     */
    public function generate_excerpt($title, $content, $voice = null, $topic = null, $options = array()) {
        // Delegate prompt building to Prompt Builder
        $excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($title, $content, $voice, $topic);
        
        // Set token limit for excerpt generation
        $options['max_tokens'] = 150;
        
        // Request excerpt from AI service
        $result = $this->generate_content($excerpt_prompt, $options, 'excerpt');
        
        if (is_wp_error($result)) {
            // Return a safe empty excerpt when excerpt generation fails
            return '';
        }
        
        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

        return substr($excerpt, 0, 160);
    }
    
    /**
     * Generate an excerpt from a generation context.
     *
     * @param string                  $title   Title of the generated article.
     * @param string                  $content The article content to summarize.
     * @param AIPS_Generation_Context $context Generation context.
     * @param array                   $options AI options.
     * @return string Short excerpt string (max 160 chars). Empty string on failure.
     */
    private function generate_excerpt_from_context($title, $content, $context, $options = array()) {
        // For template contexts with voice, pass voice object to prompt builder
        $voice_obj = null;
        if ($context->get_type() === 'template' && $context->get_voice_id()) {
            $voice_obj = $context->get_voice();
        }
        
        $topic_str = $context->get_topic();
        
        // Delegate prompt building to Prompt Builder
        $excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($title, $content, $voice_obj, $topic_str);
        
        // Set token limit for excerpt generation
        $options['max_tokens'] = 150;
        
        // Request excerpt from AI service
        $result = $this->generate_content($excerpt_prompt, $options, 'excerpt');
        
        if (is_wp_error($result)) {
            // Return a safe empty excerpt when excerpt generation fails
            return '';
        }
        
        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

        return substr($excerpt, 0, 160);
    }
    
    /**
     * Main entry point to generate a post from a context (template, topic, etc.).
     *
     * Supports both legacy template-based calls and new context-based calls.
     *
     * Steps performed:
     * 1. Start a generation session and create a history record
     * 2. Build content prompt and request body content from AI
     * 3. Generate title and excerpt
     * 4. Create the WordPress post and optionally the featured image
     * 5. Complete session, update history, and dispatch hooks
     *
     * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
     * @param object|null $voice Optional voice object with overrides (legacy).
     * @param string|null $topic Optional topic to be injected into prompts (legacy).
     * @return int|WP_Error ID of created post or WP_Error on failure.
     */
    public function generate_post($template_or_context, $voice = null, $topic = null) {
        // Check if we're using the new context-based approach
        if ($template_or_context instanceof AIPS_Generation_Context) {
            return $this->generate_post_from_context($template_or_context);
        }
        
        // Legacy template-based approach - convert to context and delegate
        $template = $template_or_context;
        $context = new AIPS_Template_Context($template, $voice, $topic);
        return $this->generate_post_from_context($context);
    }
    
    /**
     * Generate post components (content, title, excerpt) using chatbot for better coherence.
     *
     * Uses the AI Engine's chatbot feature to maintain conversational context across
     * all component generation. This ensures that the title and excerpt are closely
     * linked to the content, as the AI "remembers" what it generated previously.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @return array|WP_Error Array with 'content', 'title', 'excerpt', 'chatId' keys on success, or WP_Error on failure.
     */
    private function generate_post_components_with_chatbot($context) {
        // Get the chatbot ID from settings, default to 'default'
        $chatbot_id = get_option('aips_chatbot_id', 'default');
        
        // Step 1: Generate the content using the chatbot (first message)
        $content_prompt = $this->prompt_builder->build_content_prompt($context);
        $content_context = $this->prompt_builder->build_content_context($context);
        
        // Log AI request for content
        if ($this->current_history) {
            $this->current_history->record(
                'ai_request',
                "Requesting AI generation for content (chatbot)",
                array(
                    'prompt' => $content_prompt,
                    'chatbot_id' => $chatbot_id,
                ),
                null,
                array('component' => 'content', 'method' => 'chatbot')
            );
        }
        
        // Prepare chatbot options for content generation
        $chatbot_options = array();
        if (!empty($content_context)) {
            // Context/instructions can be passed as system message or context
            $chatbot_options['context'] = $content_context;
        }
        
        $content_response = $this->ai_service->generate_with_chatbot($chatbot_id, $content_prompt, $chatbot_options, 'content');
        
        if (is_wp_error($content_response)) {
            if ($this->current_history) {
                $this->current_history->record(
                    'error',
                    "Content generation failed (chatbot): " . $content_response->get_error_message(),
                    array('prompt' => $content_prompt),
                    null,
                    array('component' => 'content', 'method' => 'chatbot')
                );
            }
            return $content_response;
        }
        
        $content = $content_response['reply'];
        $chat_id = $content_response['chatId'];
        
        // Log successful content generation
        if ($this->current_history) {
            $this->current_history->record(
                'ai_response',
                "Content generated successfully (chatbot)",
                null,
                $content,
                array('component' => 'content', 'method' => 'chatbot', 'chatId' => $chat_id)
            );
        }
        
        $this->generation_logger->log('Content generated successfully using chatbot', 'info', array(
            'chatId' => $chat_id,
            'content_length' => strlen($content)
        ));
        
        // Step 2: Generate the title using the same chatbot session (maintains content context)
        $title_prompt = $this->prompt_builder->build_title_prompt($context, null, null, '');
        
        // Modify title prompt to reference the content just generated
        $title_message = "Based on the article content you just generated, please create a compelling title. ";
        $title_message .= $title_prompt;
        
        // Log AI request for title
        if ($this->current_history) {
            $this->current_history->record(
                'ai_request',
                "Requesting AI generation for title (chatbot with context)",
                array(
                    'prompt' => $title_message,
                    'chatId' => $chat_id,
                ),
                null,
                array('component' => 'title', 'method' => 'chatbot')
            );
        }
        
        $title_response = $this->ai_service->generate_with_chatbot($chatbot_id, $title_message, array('chatId' => $chat_id), 'title');
        
        if (is_wp_error($title_response)) {
            // If title generation fails, fall back to a safe default
            $this->generation_logger->log('Title generation failed, using fallback: ' . $title_response->get_error_message(), 'warning');
            
            $base_title = __('AI Generated Post', 'ai-post-scheduler');
            $topic_str = $context->get_topic();
            if (!empty($topic_str)) {
                $base_title .= ': ' . mb_substr($topic_str, 0, 50) . (mb_strlen($topic_str) > 50 ? '...' : '');
            }
            $title = $base_title . ' - ' . date('Y-m-d H:i:s');
        } else {
            $title = trim($title_response['reply']);
            $title = preg_replace('/^["\']|["\']$/', '', $title);
            
            // Log successful title generation
            if ($this->current_history) {
                $this->current_history->record(
                    'ai_response',
                    "Title generated successfully (chatbot)",
                    null,
                    $title,
                    array('component' => 'title', 'method' => 'chatbot')
                );
            }
            
            $this->generation_logger->log('Title generated successfully using chatbot', 'info', array(
                'chatId' => $chat_id,
                'title' => $title
            ));
        }
        
        // Step 3: Generate the excerpt using the same chatbot session (maintains content + title context)
        $voice_obj = null;
        if ($context->get_type() === 'template' && $context->get_voice_id()) {
            $voice_obj = $context->get_voice();
        }
        
        $topic_str = $context->get_topic();
        $excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($title, '', $voice_obj, $topic_str);
        
        // Modify excerpt prompt to reference the content and title
        $excerpt_message = "Based on the article content and title you just created, please write a short excerpt (max 160 characters). ";
        $excerpt_message .= $excerpt_prompt;
        
        // Log AI request for excerpt
        if ($this->current_history) {
            $this->current_history->record(
                'ai_request',
                "Requesting AI generation for excerpt (chatbot with context)",
                array(
                    'prompt' => $excerpt_message,
                    'chatId' => $chat_id,
                ),
                null,
                array('component' => 'excerpt', 'method' => 'chatbot')
            );
        }
        
        $excerpt_response = $this->ai_service->generate_with_chatbot($chatbot_id, $excerpt_message, array('chatId' => $chat_id), 'excerpt');
        
        if (is_wp_error($excerpt_response)) {
            // If excerpt generation fails, return empty string
            $this->generation_logger->log('Excerpt generation failed, using empty excerpt: ' . $excerpt_response->get_error_message(), 'warning');
            $excerpt = '';
        } else {
            $excerpt = trim($excerpt_response['reply']);
            $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);
            $excerpt = substr($excerpt, 0, 160);
            
            // Log successful excerpt generation
            if ($this->current_history) {
                $this->current_history->record(
                    'ai_response',
                    "Excerpt generated successfully (chatbot)",
                    null,
                    $excerpt,
                    array('component' => 'excerpt', 'method' => 'chatbot')
                );
            }
            
            $this->generation_logger->log('Excerpt generated successfully using chatbot', 'info', array(
                'chatId' => $chat_id,
                'excerpt' => $excerpt
            ));
        }
        
        return array(
            'content' => $content,
            'title' => $title,
            'excerpt' => $excerpt,
            'chatId' => $chat_id,
        );
    }
    
    /**
     * Generate a post from a Generation Context.
     *
     * This is the core implementation that works with any context type.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @return int|WP_Error ID of created post or WP_Error on failure.
     */
    private function generate_post_from_context($context) {
        // Dispatch post generation started event
        do_action('aips_post_generation_started', $context->get_id(), $context->get_topic() ? $context->get_topic() : '');
        
        // Create new history container using new API
        $template_id = $context->get_type() === 'template' ? $context->get_id() : null;
        $this->current_history = $this->history_service->create('post_generation', array(
            'template_id' => $template_id,
        ))->with_session($context);
        
        if (!$this->current_history->get_id()) {
            // Fallback if history creation fails (though unlikely)
            $this->logger->log('Failed to create history record', 'error');
        }
        
        // Use chatbot-based generation for better coherence between components
        $generation_result = $this->generate_post_components_with_chatbot($context);
        
        if (is_wp_error($generation_result)) {
            // Use new history API to complete with failure
            $this->current_history->complete_failure($generation_result->get_error_message(), array(
                'component' => 'chatbot_generation',
            ));
            
            // Dispatch post generation failed event
            do_action('aips_post_generation_failed', $context->get_id(), $generation_result->get_error_message(), $context->get_topic());
            
            return $generation_result;
        }
        
        // Extract generated components
        $content = $generation_result['content'];
        $title = $generation_result['title'];
        $excerpt = $generation_result['excerpt'];
        
        // Use Post Creator Service to save the generated post in WP
        $post_creation_data = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'context' => $context,
            // Provide SEO context for downstream plugins.
            'focus_keyword' => $context->get_topic() ? $context->get_topic() : $title,
            'meta_description' => $excerpt,
            'seo_title' => $title,
        );
        
        // For backward compatibility, include template if this is a template context
        if ($context->get_type() === 'template') {
            $post_creation_data['template'] = $context->get_template();
        }

        // Allow integrations to hook before the post is created.
        do_action('aips_post_generation_before_post_create', $post_creation_data);

        $post_id = $this->post_creator->create_post($post_creation_data);
        
        if (is_wp_error($post_id)) {
            // Use new history API to complete with failure
            $this->current_history->complete_failure($post_id->get_error_message(), array(
                'component' => 'post_creation',
                'title' => $title,
                'content_length' => strlen($content),
            ));
            
            return $post_id;
        }
        
        // Handle featured image generation/selection.
        $featured_image_id = $this->set_featured_image_from_context($context, $post_id, $title);
        
        // Use new history API to complete with success
        $this->current_history->complete_success(array(
            'post_id' => $post_id,
            'generated_title' => $title,
            'generated_content' => $content,
        ));
        
        // Log activity
        $this->current_history->record(
            'activity',
            sprintf('Post "%s" generated successfully', $title),
            null,
            null,
            array(
                'post_id' => $post_id,
                'context_type' => $context->get_type(),
                'context_id' => $context->get_id(),
            )
        );
        
        $this->generation_logger->log('Post generated successfully', 'info', array(
            'post_id' => $post_id,
            'context_type' => $context->get_type(),
            'context_id' => $context->get_id(),
            'title' => $title
        ));
        
        // Trigger hook for other systems to respond to the new post
        // For backward compatibility, extract template if it's a template context
        if ($context->get_type() === 'template') {
            $template_obj = $context->get_template();
            do_action('aips_post_generated', $post_id, $template_obj, $this->current_history->get_id());
        } else {
            do_action('aips_post_generated', $post_id, $context, $this->current_history->get_id());
        }
        
        $this->history_id = null;
        $this->generation_logger->set_history_id(null);
      
        return $post_id;
    }

    /**
     * Generate or select and set the featured image for a post from a context.
     *
     * Uses the context configuration to decide the source (AI prompt,
     * Unsplash, or media library). Logs any errors into the current
     * generation session.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @param int                     $post_id ID of the post to attach the image to.
     * @param string                  $title   Title of the generated post, used as image alt text/context.
     * @return int|null ID of the featured image attachment or null on failure/disabled.
     */
    private function set_featured_image_from_context($context, $post_id, $title) {
        $featured_image_id = null;

        if (!$context->should_generate_featured_image()) {
            return null;
        }

        $featured_image_source = $context->get_featured_image_source();
        $allowed_sources = array('ai_prompt', 'unsplash', 'media_library');

        if (!in_array($featured_image_source, $allowed_sources, true)) {
            $featured_image_source = 'ai_prompt';
        }

        $featured_image_result = null;

        if ($featured_image_source === 'unsplash') {
            $keywords = $context->get_unsplash_keywords();
            $topic_str = $context->get_topic();
            
            $processed_keywords = $this->template_processor->process($keywords, $topic_str);
            $featured_image_result = $this->image_service->fetch_and_upload_unsplash_image($processed_keywords, $title);

            if (!is_wp_error($featured_image_result)) {
                $featured_image_id = $featured_image_result;

                $this->post_creator->set_featured_image($post_id, $featured_image_id);
            }
        } elseif ($featured_image_source === 'media_library') {
            $media_ids = $context->get_media_library_ids();
            $featured_image_result = $this->image_service->select_media_library_image($media_ids);

            if (!is_wp_error($featured_image_result)) {
                $this->post_creator->set_featured_image($post_id, $featured_image_result);

                $featured_image_id = $featured_image_result;
            }
        } elseif ($context->get_image_prompt()) {
            $image_prompt = $context->get_image_prompt();
            $topic_str = $context->get_topic();
            $processed_image_prompt = $this->template_processor->process($image_prompt, $topic_str);
            
            // Log AI request for featured image
            if ($this->current_history) {
                $this->current_history->record(
                    'ai_request',
                    "Requesting AI generation for featured image",
                    array(
                        'prompt' => $processed_image_prompt,
                        'title' => $title,
                    ),
                    null,
                    array('component' => 'featured_image')
                );
            }
            
            $featured_image_result = $this->image_service->generate_and_upload_featured_image($processed_image_prompt, $title);

            if (!is_wp_error($featured_image_result)) {
                $featured_image_id = $featured_image_result;

                $this->post_creator->set_featured_image($post_id, $featured_image_id);

                // Log successful featured image generation
                if ($this->current_history) {
                    $this->current_history->record(
                        'ai_response',
                        "Featured image generated successfully",
                        null,
                        array('featured_image_id' => $featured_image_id),
                        array('component' => 'featured_image')
                    );
                }
            }
        } else {
            $featured_image_result = new WP_Error('missing_image_prompt', __('Image prompt is required to generate a featured image.', 'ai-post-scheduler'));
        }

        if (is_wp_error($featured_image_result)) {
            $this->generation_logger->log('Featured image handling failed: ' . $featured_image_result->get_error_message(), 'error');

            // Log featured image generation error
            if ($this->current_history) {
                $this->current_history->record(
                    'error',
                    "Featured image generation failed: " . $featured_image_result->get_error_message(),
                    array('prompt' => isset($processed_image_prompt) ? $processed_image_prompt : ''),
                    null,
                    array('component' => 'featured_image', 'error' => $featured_image_result->get_error_message())
                );
            }
        }

        return $featured_image_id;
    }
}
