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

    /**
     * Upper bound for a stored post excerpt, in characters.
     *
     * The excerpt prompt targets 40-60 words; this leaves headroom above that so
     * a normal response is never trimmed, while still bounding a model that
     * ignores the instruction. The SEO meta description is capped separately at
     * 160 characters by AIPS_Post_Manager.
     */
    const EXCERPT_MAX_CHARS = 400;

    private $ai_service;
    private $logger;

    /**
     * @var AIPS_History_Service_Interface History service for unified logging
     */
    private $history_service;

    /**
     * @var AIPS_History_Container|null Current history container
     */
    private $current_history;

    private $template_processor;
    private $image_service;
    private $structure_manager;
    private $post_manager;
    private $prompt_builder;
    private $post_content_prompt_builder;
    private $post_title_prompt_builder;
    private $post_excerpt_prompt_builder;
    private $post_featured_image_prompt_builder;
    private $post_metadata_prompt_builder;

    /**
     * @var AIPS_AI_Conversation|null Transcript for the current generation run.
     *
     * Non-null only while a conversational run is in progress. When null, every
     * prompt is built self-contained exactly as before.
     */
    private $conversation = null;

    /**
     * @var AIPS_Markdown_Parser Markdown parser
     */
    private $markdown_parser;

    /**
     * Constructor.
     *
     * Accepts dependencies for easier testing; falls back to concrete
     * implementations when not provided.
     *
    * @param AIPS_Logger_Interface|null $logger
    * @param AIPS_AI_Service_Interface|null $ai_service
     * @param object|null $template_processor
     * @param object|null $image_service
     * @param object|null $structure_manager
     * @param object|null $post_manager
    * @param AIPS_History_Service_Interface|null $history_service
     * @param object|null $prompt_builder
     * @param object|null $markdown_parser
     */
    public function __construct(
        ?AIPS_Logger_Interface $logger = null,
        ?AIPS_AI_Service_Interface $ai_service = null,
        $template_processor = null,
        $image_service = null,
        $structure_manager = null,
        $post_manager = null,
        ?AIPS_History_Service_Interface $history_service = null,
        $prompt_builder = null,
        $markdown_parser = null
    ) {
        $container = AIPS_Container::get_instance();
        $this->logger             = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
        $this->ai_service         = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->image_service      = $image_service ?: new AIPS_Image_Service( $this->ai_service );
        $this->structure_manager  = $structure_manager ?: new AIPS_Article_Structure_Manager();
        $this->post_manager       = $post_manager ?: new AIPS_Post_Manager();
        $this->history_service    = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
        $this->prompt_builder     = $prompt_builder ?: new AIPS_Prompt_Builder( $this->template_processor, $this->structure_manager );
        $this->post_content_prompt_builder = $this->prompt_builder->get_post_content_builder();
        $this->post_title_prompt_builder = $this->prompt_builder->get_post_title_builder();
        $this->post_excerpt_prompt_builder = $this->prompt_builder->get_post_excerpt_builder();
        $this->post_featured_image_prompt_builder = $this->prompt_builder->get_post_featured_image_builder();

        if ( $markdown_parser ) {
            $this->markdown_parser = $markdown_parser;
        } elseif ( class_exists( 'AIPS_Markdown_Parser' ) ) {
            $this->markdown_parser = new AIPS_Markdown_Parser();
        } else {
            $this->markdown_parser = null;
        }

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
        // Snapshot the caller's options for history records. The request options are
        // mutated below to carry the conversation transcript, which holds the whole
        // article and must never be serialized into a history row.
        $loggable_options = $options;

        // Log AI request before making the call
        if ($this->current_history) {
            $this->current_history->record(
                'ai_request',
                "Requesting AI generation for {$log_type}",
                array(
                    'prompt' => $prompt,
                    'options' => $loggable_options,
                ),
                null,
                array('component' => $log_type)
            );
        }

        // Forward the request type so AIPS_AI_Service can calculate maxTokens correctly.
        // Only set it when the caller has not already provided an explicit token override.
        if (!isset($options['maxTokens']) && !isset($options['max_tokens'])) {
            $options['request_type'] = $log_type;
        }

        // Replay the run's transcript so the model can refer back to text it has
        // already produced instead of receiving another copy of it in the prompt.
        if ($this->conversation !== null && !$this->conversation->is_empty()) {
            $options['conversation'] = $this->conversation;
        }

        $result = $this->ai_service->generate_text($prompt, $options);

        // Record the exchange only on success; a failed call contributed no model
        // turn, and a half-exchange would break the transcript's alternation.
        // Auxiliary calls (AI variable resolution) read the transcript but pass
        // 'conversation_turn' => false so their bookkeeping output does not end up
        // between the article and the follow-up that should refer to it.
        $records_turn = !isset($options['conversation_turn']) || $options['conversation_turn'] !== false;

        if ($this->conversation !== null && $records_turn && !is_wp_error($result)) {
            $this->conversation->add_exchange($prompt, (string) $result);
        }

        // Normalize values for logging to avoid deprecation warnings when null.
        $prompt_for_length  = (string) $prompt;
        $result_for_length  = is_string($result) ? $result : '';

        if (is_wp_error($result)) {
            // Log the error
            if ($this->current_history) {
                $this->current_history->record(
                    'error',
                    "AI generation failed for {$log_type}: " . $result->get_error_message(),
                    array(
                        'prompt' => $prompt,
                        'options' => $loggable_options,
                    ),
                    null,
                    array('component' => $log_type, 'error' => $result->get_error_message())
                );
            }

            $this->logger->log($result->get_error_message(), 'error', array(
                'component'      => $log_type,
                'prompt_length'  => strlen($prompt_for_length),
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
                'component'       => $log_type,
                'prompt_length'   => strlen($prompt_for_length),
                'response_length' => strlen($result_for_length),
            ));
        }

        return $result;
    }

    /**
     * Whether this run should generate components as one conversation.
     *
     * Requires both the site setting and a provider that can replay history —
     * without provider support the transcript would be silently dropped and the
     * follow-up prompts, which omit the article, would have no context at all.
     *
     * @return bool
     */
    private function use_conversation() {
        if (!AIPS_Config::get_instance()->get_option('aips_conversational_generation')) {
            return false;
        }

        if (!method_exists($this->ai_service, 'supports_conversation')) {
            return false;
        }

        return (bool) $this->ai_service->supports_conversation();
    }

    /**
     * Lazily build the combined metadata prompt builder.
     *
     * Only constructed when the metadata turn actually runs, matching the lazy
     * getters on AIPS_Prompt_Builder — every generation instantiates the
     * generator, but almost none of them need this builder.
     *
     * @return AIPS_Prompt_Builder_Post_Metadata
     */
    private function get_post_metadata_prompt_builder() {
        if (null === $this->post_metadata_prompt_builder) {
            $this->post_metadata_prompt_builder = new AIPS_Prompt_Builder_Post_Metadata($this->template_processor);
        }

        return $this->post_metadata_prompt_builder;
    }

    /**
     * Whether the remaining components should be requested in one structured turn.
     *
     * @return bool
     */
    private function use_metadata_turn() {
        return $this->conversation !== null
            && (bool) AIPS_Config::get_instance()->get_option('aips_conversational_metadata_turn');
    }

    /**
     * Begin a fresh transcript for a generation run, when enabled.
     *
     * @return void
     */
    private function start_conversation() {
        $this->conversation = $this->use_conversation() ? new AIPS_AI_Conversation() : null;
    }

    /**
     * Discard the current transcript.
     *
     * Called once a run finishes so a reused generator instance (the component
     * regeneration service keeps one) never leaks turns between posts.
     *
     * @return void
     */
    private function end_conversation() {
        $this->conversation = null;
    }

    /**
     * Whether conversational generation is available for this request.
     *
     * Exposed so callers that drive individual components — component
     * regeneration in particular — can decide whether it is worth rebuilding a
     * transcript before invoking the generator.
     *
     * @return bool
     */
    public function supports_conversation() {
        return $this->use_conversation();
    }

    /**
     * Seed the generator with an existing transcript.
     *
     * Used when regenerating a single component of an already-generated post: the
     * original article is replayed as a model turn so the follow-up prompt can
     * refer back to it instead of pasting the body in again.
     *
     * Pass null to clear.
     *
     * @param AIPS_AI_Conversation|null $conversation Transcript to resume, or null.
     * @return void
     */
    public function set_conversation($conversation = null) {
        if ($conversation !== null && !($conversation instanceof AIPS_AI_Conversation)) {
            return;
        }

        // Honour the same setting/provider gate as a full run; without provider
        // support the transcript would be dropped and the follow-up prompts, which
        // omit the article, would have no context at all.
        if ($conversation !== null && !$this->use_conversation()) {
            return;
        }

        $this->conversation = $conversation;
    }

    /**
     * Generate a post title for a context, optionally continuing a conversation.
     *
     * Public entry point for callers that already hold a generation context —
     * notably component regeneration, which must not go through the legacy
     * template/voice/topic argument list.
     *
     * @param AIPS_Generation_Context $context      Generation context.
     * @param string                  $content      Article content used as context
     *                                              when no conversation is active.
     * @param array                   $ai_variables Optional resolved AI variables.
     * @param array                   $options      AI options.
     * @return string|WP_Error Generated title or WP_Error on failure.
     */
    public function generate_title_for_context($context, $content = '', $ai_variables = array(), $options = array()) {
        return $this->generate_title_from_context($context, $content, $ai_variables, $options);
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

        // Avoid building the content context when the title prompt does not
        // contain any AI variables to resolve.
        if (!method_exists($this->template_processor, 'extract_ai_variables')) {
            return array();
        }

        $ai_variables = $this->template_processor->extract_ai_variables($title_prompt);
        if (empty($ai_variables)) {
            return array();
        }

        // Build context from content prompt and generated content only when AI
        // variables are present. Use smart truncation to preserve context from
        // both beginning and end of content.
        //
        // In a conversation the article is already the preceding model turn, so
        // re-sending a truncated copy would be pure waste — and a truncation the
        // model can only reason about partially.
        if ($this->conversation !== null) {
            $context_str = 'Use the article you just wrote as the context.';
        } else {
            $context_str = "Content Prompt: " . $context->get_content_prompt() . "\n\n";
            $context_str .= "Generated Article Content:\n" . $this->smart_truncate_content($content, 2000);
        }

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
    private function resolve_ai_variables_for_template_string($template_string, $context_str, $log_type = 'ai_variables') {
        if (!method_exists($this->template_processor, 'extract_ai_variables')) {
            return array();
        }

        $ai_variables = $this->template_processor->extract_ai_variables($template_string);

        if (empty($ai_variables)) {
            return array();
        }

        $resolve_prompt = $this->template_processor->build_ai_variables_prompt($ai_variables, $context_str);

        // Max tokens of 200 is sufficient for JSON responses with typical variable values.
        // This is bookkeeping rather than post content, so it reads the transcript
        // but does not append to it — the title turn should follow the article directly.
        $options = array('max_tokens' => 200, 'conversation_turn' => false);
        $result = $this->generate_content($resolve_prompt, $options, $log_type);

        if (is_wp_error($result)) {
            $message = 'Failed to resolve AI variables: ' . $result->get_error_message();
            $this->logger->log($message, 'warning');
            if ($this->current_history) {
                $this->current_history->record('warning', $message, null, null, array('component' => $log_type));
            }
            return array();
        }

        $resolved_values = $this->template_processor->parse_ai_variables_response($result, $ai_variables);

        if (empty($resolved_values)) {
            $message = 'AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.';
            $context = array('variables' => $ai_variables, 'component' => $log_type);
            $this->logger->log($message, 'warning', $context);
            if ($this->current_history) {
                $this->current_history->record('warning', $message, array('variables' => $ai_variables, 'raw_response' => $result, 'component' => $log_type));
            }
        } else {
            $this->logger->log('Resolved AI variables', 'info', array('variables' => $ai_variables, 'resolved' => $resolved_values, 'component' => $log_type));
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
    private function build_featured_image_variable_context($context, $content = '', $title = '') {
        $context_parts = array();

        if (!empty($context->get_content_prompt())) {
            $context_parts[] = 'Content Prompt: ' . $context->get_content_prompt();
        }

        if (!empty($title)) {
            $context_parts[] = 'Generated Post Title: ' . $title;
        }

        if (!empty($content)) {
            $context_parts[] = "Generated Article Content:\n" . $this->smart_truncate_content($content, 1600);
        }

        if (!empty($context->get_topic())) {
            $context_parts[] = 'Topic: ' . $context->get_topic();
        }

        return implode("\n\n", $context_parts);
    }

    /**
     * Build featured image variable context for a conversational run.
     *
     * The article and title are already turns in the transcript, so only the
     * topic is worth restating.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @return string
     */
    private function build_featured_image_variable_context_conversational($context) {
        $context_parts = array('Use the article and title from this conversation as the context.');

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
     * @param AIPS_Generation_Context $context Generation context.
     * @param string                  $content Generated article content.
     * @param string                  $title   Generated post title.
     * @return string Processed image prompt with all variables replaced.
     */
    public function process_featured_image_prompt($context, $content = '', $title = '') {
        $image_prompt = $this->post_featured_image_prompt_builder->build($context, $context->get_topic());

        if (empty($image_prompt)) {
            return '';
        }

        $topic_str = $context->get_topic();
        $resolved_ai_variables = array();

        if (method_exists($this->template_processor, 'has_ai_variables') && $this->template_processor->has_ai_variables($image_prompt)) {
            $image_context = ($this->conversation !== null)
                ? $this->build_featured_image_variable_context_conversational($context)
                : $this->build_featured_image_variable_context($context, $content, $title);

            $resolved_ai_variables = $this->resolve_ai_variables_for_template_string($image_prompt, $image_context, 'ai_variables_featured_image');
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
    private function remove_unresolved_template_placeholders($prompt) {
        $prompt = (string) $prompt;
        $prompt = preg_replace('/\{\{[^{}]+\}\}/', '', $prompt);

        if (!is_string($prompt)) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', $prompt));
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
     * Delegates title prompt construction to AIPS_Prompt_Builder_Post_Title for consistency
     * and to follow the Single Responsibility Principle.
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
     * Delegates title prompt construction to AIPS_Prompt_Builder_Post_Title.
     * Strips surrounding quotes from the AI output before returning.
     *
     * @param AIPS_Generation_Context $context      Generation context.
     * @param string                  $content      Generated article content used as context.
     * @param array                   $ai_variables Optional resolved AI variables.
     * @param array                   $options      AI options (e.g., model, max_tokens override).
     * @return string|WP_Error Generated title string or WP_Error on failure.
     */
    private function generate_title_from_context($context, $content = '', $ai_variables = array(), $options = array()) {
        // In a conversation the article is the preceding model turn, so the prompt
        // refers back to it instead of carrying another full copy.
        if ($this->conversation !== null) {
            $prompt = $this->post_title_prompt_builder->build_followup($context);
        } else {
            $prompt = $this->post_title_prompt_builder->build($context, null, null, $content);
        }

        // Apply resolved AI variables so that any {{VariableName}} placeholders in the
        // title instructions are substituted before the prompt is sent to the AI.
        // Without this step, raw placeholder syntax reaches the model and causes it to
        // respond with only the variable value (e.g. a single word) instead of a full title.
        if (!empty($ai_variables)) {
            $prompt = $this->template_processor->process_with_ai_variables(
                $prompt,
                $context->get_topic(),
                $ai_variables
            );
        }

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
     * Delegates excerpt prompt construction to AIPS_Prompt_Builder_Post_Excerpt.
     * Ensures the excerpt length is within a reasonable limit and removes
     * surrounding quotes from the AI output.
     *
     * @param string                         $title   Title of the generated article.
     * @param string                         $content The article content to summarize.
     * @param object|null                    $voice   Optional voice object with excerpt instructions.
     * @param string|null                    $topic   Optional topic to be injected into prompts.
     * @param array                          $options AI options.
     * @param object|AIPS_Generation_Context $subject Optional template/author object or generation context for diversity injection.
     * @return string Excerpt string (40-60 words as prompted). Empty string on failure.
     */
    public function generate_excerpt($title, $content, $voice = null, $topic = null, $options = array(), $subject = null) {
        // In a conversation the article and title are already turns in the
        // transcript, so neither is pasted back into the prompt.
        if ($this->conversation !== null) {
            $excerpt_prompt = $this->post_excerpt_prompt_builder->build_followup($voice, $topic);
        } else {
            $excerpt_prompt = $this->post_excerpt_prompt_builder->build($title, $content, $voice, $topic, $subject);
        }

        // Request excerpt from AI service
        $result = $this->generate_content($excerpt_prompt, $options, 'excerpt');

        if (is_wp_error($result)) {
            // Return a safe empty excerpt when excerpt generation fails
            return '';
        }

        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

        return self::truncate_excerpt($excerpt);
    }

    /**
     * Generate an excerpt from a generation context.
     *
     * @param string                  $title   Title of the generated article.
     * @param string                  $content The article content to summarize.
     * @param AIPS_Generation_Context $context Generation context.
     * @param array                   $options AI options.
     * @param bool|null               $generation_success Output parameter. Set to true on success, false on failure.
     * @return string Excerpt string (40-60 words as prompted). Empty string on failure.
     */
    private function generate_excerpt_from_context($title, $content, $context, $options = array(), &$generation_success = null) {
        // For template contexts with voice, pass voice object to prompt builder
        $voice_obj = null;
        if ($context->get_type() === 'template' && $context->get_voice_id()) {
            $voice_obj = $context->get_voice();
        }

        $topic_str = $context->get_topic();

        // In a conversation the article and title are the two preceding turns, so
        // neither is pasted back into the prompt.
        if ($this->conversation !== null) {
            $excerpt_prompt = $this->post_excerpt_prompt_builder->build_followup($voice_obj, $topic_str);
        } else {
            $excerpt_prompt = $this->post_excerpt_prompt_builder->build($title, $content, $voice_obj, $topic_str, $context);
        }

        // Request excerpt from AI service
        $result = $this->generate_content($excerpt_prompt, $options, 'excerpt');

        if (is_wp_error($result)) {
            $generation_success = false;
            // Return a safe empty excerpt when excerpt generation fails
            return '';
        }

        $generation_success = true;
        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

        return self::truncate_excerpt($excerpt);
    }

    /**
     * Safety net for excerpt length.
     *
     * The excerpt prompt asks for 40-60 words (roughly 250-350 characters) and
     * that full text is what gets stored as post_excerpt. This is only a guard
     * against a model that ignores the word limit entirely — it is NOT the SEO
     * meta-description cap. AIPS_Post_Manager::sanitize_meta_description()
     * independently trims the meta description to 160 characters, so capping
     * here as well would truncate every excerpt to less than half the length the
     * prompt asked for.
     *
     * Uses mb_substr because a byte-wise cut can land mid-character and produce
     * invalid UTF-8, and backs off to the last word boundary so the excerpt does
     * not end mid-word.
     *
     * @param string $excerpt Excerpt text.
     * @return string
     */
    private static function truncate_excerpt($excerpt) {
        $excerpt = (string) $excerpt;
        $limit   = self::EXCERPT_MAX_CHARS;

        if (mb_strlen($excerpt) <= $limit) {
            return $excerpt;
        }

        $truncated = mb_substr($excerpt, 0, $limit);
        $last_space = mb_strrpos($truncated, ' ');

        // Only back off to a word boundary when doing so keeps most of the text;
        // a very long single token should still be cut rather than emptied.
        if ($last_space !== false && $last_space >= (int) ($limit * 0.6)) {
            $truncated = mb_substr($truncated, 0, $last_space);
        }

        return rtrim($truncated);
    }

    /**
     * Request every remaining component in one structured turn.
     *
     * Collapses AI variable resolution, title, excerpt, and featured image prompt
     * into a single JSON request on the conversation that already holds the
     * article. Returns null when the turn fails or comes back unusable, in which
     * case the caller falls back to the separate per-component requests.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @return array|null Array with 'title', 'excerpt', 'image_prompt', and
     *                    'ai_variables' keys, or null to fall back.
     */
    private function generate_metadata_turn($context) {
        $image_prompt_template = '';

        if ($context->should_generate_featured_image() && $context->get_featured_image_source() === 'ai_prompt') {
            $image_prompt_template = (string) $this->post_featured_image_prompt_builder->build($context, $context->get_topic());
        }

        // Collect the placeholders appearing across both templates so the model
        // resolves them once, in the same turn that consumes them. The title
        // instructions are read through the builder so a voice override — which may
        // carry different placeholders than the template's own title prompt — is
        // the string actually scanned.
        $ai_variables = array();

        if (method_exists($this->template_processor, 'extract_ai_variables')) {
            $title_instructions = $this->get_post_metadata_prompt_builder()->resolve_title_instructions($context, $context->get_topic());

            $ai_variables = array_values(array_unique(array_merge(
                (array) $this->template_processor->extract_ai_variables($title_instructions),
                (array) $this->template_processor->extract_ai_variables($image_prompt_template)
            )));
        }

        $prompt = $this->get_post_metadata_prompt_builder()->build($context, $ai_variables, $image_prompt_template);
        $schema = $this->get_post_metadata_prompt_builder()->get_schema($ai_variables, $image_prompt_template !== '');

        if ($this->current_history) {
            $this->current_history->record(
                'ai_request',
                'Requesting AI generation for post metadata',
                array('prompt' => $prompt),
                null,
                array('component' => 'metadata')
            );
        }

        $options = array(
            'json_schema'  => $schema,
            'max_tokens'   => 800,
            'conversation' => $this->conversation,
        );

        $result = $this->ai_service->generate_json($prompt, $options);

        if (is_wp_error($result) || !is_array($result)) {
            $message = is_wp_error($result) ? $result->get_error_message() : 'Metadata turn returned no usable JSON.';

            $this->logger->log('Combined metadata turn failed; falling back to separate requests: ' . $message, 'warning');

            if ($this->current_history) {
                $this->current_history->record(
                    'warning',
                    'Combined metadata turn failed; falling back to separate requests: ' . $message,
                    null,
                    null,
                    array('component' => 'metadata')
                );
            }

            return null;
        }

        $title   = isset($result['title']) ? trim((string) $result['title']) : '';
        $excerpt = isset($result['excerpt']) ? trim((string) $result['excerpt']) : '';

        // Without a title there is nothing to salvage; the fallback path produces a
        // better result than half a metadata set.
        if ($title === '') {
            $this->logger->log('Combined metadata turn returned no title; falling back to separate requests.', 'warning');

            return null;
        }

        // The conversation must reflect what the model actually produced so any
        // later turn (for example the image prompt fallback) stays coherent.
        if ($this->conversation !== null) {
            $this->conversation->add_exchange($prompt, wp_json_encode($result));
        }

        if ($this->current_history) {
            $this->current_history->record(
                'ai_response',
                'AI generation successful for post metadata',
                null,
                wp_json_encode($result),
                array('component' => 'metadata')
            );
        }

        $ai_variable_values = array();

        if (isset($result['ai_variables']) && is_array($result['ai_variables'])) {
            foreach ($result['ai_variables'] as $name => $value) {
                if (is_scalar($value)) {
                    $ai_variable_values[$name] = (string) $value;
                }
            }
        }

        $image_prompt = isset($result['image_prompt']) ? trim((string) $result['image_prompt']) : '';

        return array(
            'title'        => preg_replace('/^["\']|["\']$/', '', $title),
            'excerpt'      => $excerpt,
            'image_prompt' => $image_prompt !== '' ? $this->remove_unresolved_template_placeholders($image_prompt) : '',
            'ai_variables' => $ai_variable_values,
        );
    }

    /**
     * Generate a preview of a post from a context without creating it in WordPress.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @return array|WP_Error Array with title, content, excerpt, and image prompt, or WP_Error.
     */
    public function generate_preview($context) {
        $this->start_conversation();

        // Build the full content prompt from context
        $content_prompt = $this->post_content_prompt_builder->build($context);

        // Build contextual instructions
        $content_context = $this->prompt_builder->build_content_context($context);
        $content_options = array();

        if (!empty($content_context)) {
            $content_options['context'] = $content_context;
        }

        // Ask AI to generate the article body
        $content = $this->generate_content($content_prompt, $content_options, 'content_preview');

        if (is_wp_error($content)) {
            $this->end_conversation();

            return $content;
        }

        $content = $this->normalize_generated_content_for_wordpress($content);

        // Resolve AI variables
        $ai_variables = $this->resolve_ai_variables_from_context($context, $content);

        // Generate the title
        $title = $this->generate_title_from_context($context, $content, $ai_variables);

        if (is_wp_error($title)) {
            // Fallback title on error
            $title = __('Error generating title', 'ai-post-scheduler');
        }

        $content = $this->strip_leading_title_block_from_content($content);

        // Generate excerpt
        $excerpt_content = mb_substr($content, 0, 6000);
        $excerpt = $this->generate_excerpt_from_context($title, $excerpt_content, $context);

        $result = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'image_prompt' => '',
            'image_source' => $context->get_featured_image_source(),
        );

        // Handle image preview data (not generation)
        if ($context->should_generate_featured_image()) {
            if ($context->get_featured_image_source() === 'ai_prompt') {
                $result['image_prompt'] = $this->process_featured_image_prompt($context, $content, $title);
            } elseif ($context->get_featured_image_source() === 'unsplash') {
                $keywords = $context->get_unsplash_keywords();
                $topic_str = $context->get_topic();
                $processed_keywords = $this->template_processor->process($keywords, $topic_str);
                $result['image_prompt'] = $processed_keywords;
            }
        }

        $this->end_conversation();

        return $result;
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
            $result = $this->generate_post_from_context($template_or_context);

            if (is_wp_error($result) && $template_or_context->get_creation_method() !== 'scheduled') {
                $this->emit_generation_failure_notification($template_or_context, $result);
            }

            return $result;
        }

        // Legacy template-based approach - convert to context and delegate
        $template = $template_or_context;
        $context = new AIPS_Template_Context($template, $voice, $topic);
        $result = $this->generate_post_from_context($context);

        if (is_wp_error($result)) {
            $this->emit_generation_failure_notification($context, $result);
        }

        return $result;
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
        $generation_start = microtime(true);
        $component_statuses = array(
            'post_title'     => false,
            'post_excerpt'   => false,
            'featured_image' => !$context->should_generate_featured_image(),
            'post_content'   => false,
        );

        // Dispatch post generation started event
        do_action('aips_post_generation_started', $context->get_id(), $context->get_topic() ? $context->get_topic() : '');

        // Create new history container using new API
        // Extract source information from context
        $history_metadata = array();

        if ($context instanceof AIPS_Template_Context) {
            $history_metadata['template_id'] = $context->get_id();
            $template = $context->get_template();
            if ($template && !empty($template->campaign_id)) {
                $history_metadata['campaign_id'] = absint($template->campaign_id);
            }
        } elseif ($context instanceof AIPS_Topic_Context) {
            // For topic context, store author_id and topic_id
            $history_metadata['topic_id'] = $context->get_id();
            $author = $context->get_author();
            if ($author && isset($author->id)) {
                $history_metadata['author_id'] = $author->id;
            }
        }

        // Get creation_method from context, default to 'manual' if not specified
        $creation_method = $context->get_creation_method() ?: 'manual';
        $history_metadata['creation_method'] = $creation_method;

        $this->current_history = $this->history_service->create('post_generation', $history_metadata)->with_session($context);

        if (!$this->current_history->get_id()) {
            // Fallback if history creation fails (though unlikely)
            $this->logger->log('Failed to create history record', 'error');
        }

        // Open a transcript for this run when conversational generation is enabled
        // and the active provider can replay it.
        $this->start_conversation();

        // Build the full content prompt from context
        $content_prompt = $this->post_content_prompt_builder->build($context);

        if ($this->current_history) {
            $this->current_history->record(
                'log',
                "Built content prompt",
                array('prompt' => isset($content_prompt) ? $content_prompt : ''),
                null,
                array('component' => 'content')
            );
        }

        // Build contextual instructions to pass through AI Engine context channel.
        $content_context = $this->prompt_builder->build_content_context($context);
        $content_options = array();

        if (!empty($content_context)) {
            $content_options['context'] = $content_context;
        }

        // Generate the Post Content
        $content = $this->generate_content($content_prompt, $content_options, 'content');

        if (is_wp_error($content)) {
            $this->current_history->record_error($content->get_error_message(), array(
                'component' => 'content',
                'prompt' => $content_prompt,
            ));
            $content = '';
        }

        $content = $this->normalize_generated_content_for_wordpress($content);
        $component_statuses['post_content'] = ($content !== '');

        if (!$component_statuses['post_content']) {
            $error_message = __('Post generation failed before a usable Post Content could be created.', 'ai-post-scheduler');

            $error = new WP_Error(
                'aips_generation_missing_required_content',
                $error_message,
                array(
                    'component_statuses' => $component_statuses,
                )
            );

            $this->current_history->complete_failure($error_message, array(
                'component' => 'post_content',
                'component_statuses' => $component_statuses,
                'content_length' => mb_strlen($content),
            ));

            $this->current_history->record(
                'metric_generation_result',
                'Generation failed - required Post Content was not generated',
                array(
                    'outcome'          => 'failed',
                    'duration_seconds' => (int) round( microtime(true) - $generation_start ),
                    'image_attempted'  => false,
                    'image_success'    => null,
                )
            );

            $this->logger->log('Post generation failed before post creation', 'error', array(
                'context_type' => $context->get_type(),
                'context_id' => $context->get_id(),
                'component_statuses' => $component_statuses,
            ));

            $this->end_conversation();

            return $error;
        }

        // When the combined metadata turn is enabled, ask for the AI variables,
        // title, excerpt, and image prompt in one structured request. It returns
        // null on any failure, in which case the per-component requests below run
        // exactly as they always have.
        $metadata = $this->use_metadata_turn() ? $this->generate_metadata_turn($context) : null;
        $resolved_image_prompt = null;

        if ($metadata !== null) {
            $ai_variables          = $metadata['ai_variables'];
            $title                 = $metadata['title'];
            $resolved_image_prompt = $metadata['image_prompt'];
        } else {
            // Resolve AI variables from the Title prompt using the generated content
            $ai_variables = $this->resolve_ai_variables_from_context($context, $content);

            // Generate the title using the context and content.
            $title = $this->generate_title_from_context($context, $content, $ai_variables);
        }

        // Log post title
        if ($this->current_history) {
            $this->current_history->record(
                'info',
                "Post title generated",
                array(),
                null,
                array('component' => 'title')
            );
        }

        // Detect unresolved template placeholders in the generated Title.
        $has_unresolved_placeholders = false;

        if (!is_wp_error($title) && is_string($title)) {
            if (strpos($title, '{{') !== false && strpos($title, '}}') !== false) {
                $has_unresolved_placeholders = true;

                // Log a warning for observability when AI variables were not resolved correctly.
                $warn_ctx = array(
                    'context_type' => $context->get_type(),
                    'context_id'   => $context->get_id(),
                    'topic'        => $context->get_topic(),
                );
                $this->logger->log( 'Generated title contains unresolved AI variables; falling back to safe default title.', 'warning', $warn_ctx );
                if ($this->current_history) {
                    $this->current_history->record( 'warning', 'Generated title contains unresolved AI variables; falling back to safe default title.', null, null, $warn_ctx );
                }
            }
        }

        if (is_wp_error($title) || $has_unresolved_placeholders) {
            // Fall back to a safe default Title when AI fails or leaves unresolved variables.
            $base_title = __('AIPS Generated Post', 'ai-post-scheduler');
            $topic_str = $context->get_topic();

            if (!empty($topic_str)) {
                // Include topic in fallback Title for context, truncated for safety
                $base_title .= ': ' . mb_substr($topic_str, 0, 50) . (mb_strlen($topic_str) > 50 ? '...' : '');
            }

            $title = $base_title . ' - ' . AIPS_DateTime::now()->toDisplay();
            $component_statuses['post_title'] = false;
        } else {
            $component_statuses['post_title'] = true;
        }

        $content = $this->strip_leading_title_block_from_content($content);

        if ($metadata !== null) {
            // The excerpt came back with the metadata turn; apply the same length
            // cap generate_excerpt_from_context() enforces.
            $excerpt         = self::truncate_excerpt($metadata['excerpt']);
            $excerpt_success = ($excerpt !== '');
        } else {
            // Use actual generated Content for excerpt, truncated to prevent token limits
            $excerpt_content = mb_substr($content, 0, 6000);
            $excerpt_success = false;
            $excerpt = $this->generate_excerpt_from_context($title, $excerpt_content, $context, array(), $excerpt_success);
        }

        // Set Post Excerpt component status based on whether excerpt generation was successful
        $component_statuses['post_excerpt'] = (bool) $excerpt_success;

        // Determine whether this Post has "Partial Generations" or not
        $generation_incomplete = in_array(false, $component_statuses, true);

        // Use Post Manager Service to save the generated post in WP
        $post_creation_data = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'context' => $context,
            // Provide SEO context for downstream plugins.
            'focus_keyword' => $context->get_topic() ? $context->get_topic() : $title,
            'meta_description' => $excerpt,
            'seo_title' => $title,
            'generation_incomplete' => $generation_incomplete,
            'component_statuses' => $component_statuses,
        );

        // Allow integrations to hook before the post is created.
        do_action('aips_post_generation_before_post_create', $post_creation_data);

        $post_id = $this->post_manager->create_post($post_creation_data);

        if (is_wp_error($post_id)) {
            // Use new history API to complete with failure
            $this->current_history->complete_failure($post_id->get_error_message(), array(
                'component' => 'post_creation',
                'title' => $title,
                'content_length' => strlen($content),
            ));

            // Write a metric snapshot so the metrics repository can count this failure
            // without querying scattered tables.
            $this->current_history->record(
                'metric_generation_result',
                'Generation failed — post could not be created',
                array(
                    'outcome'          => 'failed',
                    'duration_seconds' => (int) round( microtime(true) - $generation_start ),
                    'image_attempted'  => false,
                    'image_success'    => null,
                )
            );

            $this->end_conversation();

            return $post_id;
        }

        // Handle featured image generation/selection.
        $featured_image_success = !$context->should_generate_featured_image();
        $featured_image_id = $this->set_featured_image_from_context($context, $post_id, $title, $featured_image_success, $content, $resolved_image_prompt);
        $component_statuses['featured_image'] = (bool) $featured_image_success;

        $generation_incomplete = in_array(false, $component_statuses, true);
        $this->post_manager->update_generation_status_meta($post_id, $component_statuses, $generation_incomplete);

        if ($generation_incomplete) {
            do_action('aips_post_generation_incomplete', $post_id, $component_statuses, $context, $this->current_history ? $this->current_history->get_id() : 0);
        }

        // Use new history API to complete with success
        $this->current_history->complete_success(array(
            'post_id' => $post_id,
            'generated_title' => $title,
            'generated_content' => $content,
            'generation_incomplete' => $generation_incomplete,
            'component_statuses' => $component_statuses,
        ));

        if ($context instanceof AIPS_Template_Context) {
            $template = $context->get_template();
            if ($template && !empty($template->campaign_id)) {
                AIPS_Campaigns_Repository::instance()->flush_campaign_cache((int) $template->campaign_id);
            }
        }

        // Write a structured metric snapshot to history_log.  The metrics
        // repository reads these entries to compute image failure rates and
        // other per-generation signals without touching post_meta.
        $image_was_attempted = $context->should_generate_featured_image();
        $this->current_history->record(
            'metric_generation_result',
            'Generation metric snapshot',
            array(
                'outcome'            => $generation_incomplete ? 'partial' : 'completed',
                'duration_seconds'   => (int) round( microtime(true) - $generation_start ),
                'image_attempted'    => $image_was_attempted,
                'image_success'      => $image_was_attempted ? (bool) $featured_image_success : null,
                'word_count'         => str_word_count( wp_strip_all_tags( (string) $content ) ),
                'char_count'         => mb_strlen( (string) $content ),
                'component_statuses' => $component_statuses,
            )
        );

        // Log activity
        if ($generation_incomplete) {
            $this->current_history->record(
                'warning',
                sprintf('Post "%s" generated with missing components', $title),
                null,
                null,
                array(
                    'post_id' => $post_id,
                    'context_type' => $context->get_type(),
                    'context_id' => $context->get_id(),
                    'component_statuses' => $component_statuses,
                )
            );

            $this->logger->log('Post generated with missing components', 'warning', array(
                'post_id' => $post_id,
                'context_type' => $context->get_type(),
                'context_id' => $context->get_id(),
                'title' => $title,
                'component_statuses' => $component_statuses,
            ));
        } else {
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

            $this->logger->log('Post generated successfully', 'info', array(
                'post_id' => $post_id,
                'context_type' => $context->get_type(),
                'context_id' => $context->get_id(),
                'title' => $title
            ));
        }

        // Trigger hook for other systems to respond to the new post
        // For backward compatibility, extract template if it's a template context
        if ($context instanceof AIPS_Template_Context) {
            $template_obj = $context->get_template();
            do_action('aips_post_generated', $post_id, $template_obj, $this->current_history->get_id(), $context);
        } else {
            do_action('aips_post_generated', $post_id, $context, $this->current_history->get_id(), $context);
        }

        $this->end_conversation();

        return $post_id;
    }

    /**
     * Emit a generation failure notification for non-scheduled runs.
     *
     * @param AIPS_Generation_Context $context Generation context.
     * @param WP_Error                $error   Error object.
     * @return void
     */
    private function emit_generation_failure_notification($context, WP_Error $error) {
        $resource_label = __('Manual generation', 'ai-post-scheduler');
        $dedupe_parts = array('generation_failed', $context->get_type(), $context->get_id(), $error->get_error_code());

        if ($context instanceof AIPS_Template_Context) {
            $template = $context->get_template();
            if ($template && !empty($template->name)) {
                $resource_label = sprintf(__('template "%s"', 'ai-post-scheduler'), $template->name);
            }
        } elseif ($context instanceof AIPS_Topic_Context && !empty($context->get_topic())) {
            $resource_label = sprintf(__('author topic "%s"', 'ai-post-scheduler'), $context->get_topic());
        }

        do_action('aips_generation_failed', array(
            'resource_label'  => $resource_label,
            'error_code'      => $error->get_error_code(),
            'error_message'   => $error->get_error_message(),
            'context_type'    => $context->get_type(),
            'context_id'      => $context->get_id(),
            'history_id'      => $this->current_history ? $this->current_history->get_id() : 0,
            'creation_method' => $context->get_creation_method(),
            'topic'           => $context->get_topic(),
            'url'             => AIPS_Admin_Menu_Helper::get_page_url('history'),
            'dedupe_key'      => implode('_', array_map('sanitize_key', array_map('strval', $dedupe_parts))),
            'dedupe_window'   => 900,
        ));
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
    private function set_featured_image_from_context($context, $post_id, $title, &$component_success = null, $content = '', $precomputed_image_prompt = null) {
        $featured_image_id = null;
        $featured_image_source = '';

        if (!$context->should_generate_featured_image()) {
            $component_success = true;
            return null;
        }

        $featured_image_source = $context->get_featured_image_source();
        $allowed_sources = array('ai_prompt', 'unsplash', 'media_library');

        if (!in_array($featured_image_source, $allowed_sources, true)) {
            $featured_image_source = 'ai_prompt';
        }

        $image_generation_start = microtime(true);
        $featured_image_result = null;

        if ($featured_image_source === 'unsplash') {
            $keywords = $context->get_unsplash_keywords();
            $topic_str = $context->get_topic();

            $processed_keywords = $this->template_processor->process($keywords, $topic_str);
            $featured_image_result = $this->image_service->fetch_and_upload_unsplash_image($processed_keywords, $title);

            if (!is_wp_error($featured_image_result)) {
                $featured_image_id = $featured_image_result;

                $this->post_manager->set_featured_image($post_id, $featured_image_id);
                $component_success = true;
            }
        } elseif ($featured_image_source === 'media_library') {
            $media_ids = $context->get_media_library_ids();
            $featured_image_result = $this->image_service->select_media_library_image($media_ids);

            if (!is_wp_error($featured_image_result)) {
                $this->post_manager->set_featured_image($post_id, $featured_image_result);

                $featured_image_id = $featured_image_result;
                $component_success = true;
            }
        } elseif ($context->get_image_prompt()) {
            // The combined metadata turn already produced a fully resolved prompt;
            // reuse it rather than spending another call to rebuild the same thing.
            $processed_image_prompt = ($precomputed_image_prompt !== null && $precomputed_image_prompt !== '')
                ? $precomputed_image_prompt
                : $this->process_featured_image_prompt($context, $content, $title);

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

                $this->post_manager->set_featured_image($post_id, $featured_image_id);
                $component_success = true;

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
            $component_success = false;
            $this->logger->log('Featured image handling failed: ' . $featured_image_result->get_error_message(), 'error');

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

        if ($this->current_history) {
            $this->current_history->record(
                'metric_generation_result',
                'Featured image generation metric snapshot',
                array(
                    'outcome'          => is_wp_error($featured_image_result) ? 'failed' : 'completed',
                    'duration_seconds' => (int) round( microtime(true) - $image_generation_start ),
                    'image_attempted'  => true,
                    'image_success'    => !is_wp_error($featured_image_result),
                    'image_source'     => $featured_image_source,
                ),
                null,
                array('component' => 'featured_image')
            );
        }

        return $featured_image_id;
    }

    /**
     * Normalize generated content so post bodies are consistently stored as HTML.
     *
     * Public so diagnostic surfaces can show the exact before/after of this step
     * without re-implementing it; it is a pure transform with no side effects.
     *
     * @param string $content Raw generated content.
     * @return string Sanitized HTML content.
     */
    public function normalize_generated_content_for_wordpress($content) {
        if (!is_string($content)) {
            return '';
        }

        $normalized_content = trim($content);

        if ($normalized_content === '') {
            return '';
        }

        if ( $this->markdown_parser && $this->markdown_parser->is_markdown( $normalized_content ) && ! $this->markdown_parser->contains_html( $normalized_content ) ) {
            $normalized_content = $this->markdown_parser->parse( $normalized_content );
        }

        return wp_kses_post($normalized_content);
    }

    /**
     * Remove a prepended title block from generated content when present.
     *
     * Some models occasionally emit an article title at the start of content
     * (for example as <h1>Title</h1> or Markdown "# Title"). WordPress already
     * renders the post title separately, so keeping this heading creates a
     * duplicated "second title" in the article body.
     *
     * @param string $content Generated content.
     * @return string Content without a leading title block.
     */
    private function strip_leading_title_block_from_content($content) {
        if (!is_string($content)) {
            return '';
        }

        $cleaned = ltrim($content);

        // Remove a leading HTML <h1>...</h1> title block.
        $cleaned = preg_replace('/^<h1\b[^>]*>[\s\S]*?<\/h1>\s*/i', '', $cleaned, 1);

        // Remove a leading Markdown "# Title" block when Markdown slipped through.
        $cleaned = preg_replace('/^#\\s+[^\\n]+\\s*/u', '', $cleaned, 1);
        

        return ltrim((string) $cleaned);
    }

    /**
     * Set the history container for logging
     *
     * Allows external code to set a specific history container for logging.
     * Useful for component regeneration where we want to log to a specific container.
     *
     * @param AIPS_History_Container $history_container History container instance
     */
    public function set_history_container($history_container) {
        $this->current_history = $history_container;
    }
}
