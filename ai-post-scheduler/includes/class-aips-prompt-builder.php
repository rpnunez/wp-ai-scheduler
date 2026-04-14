<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Prompt_Builder {

	private $template_processor;
	private $structure_manager;
    private $sources_repo;
	private $post_content_builder;
	private $post_title_builder;
	private $post_excerpt_builder;
	private $post_featured_image_builder;
    private static $sources_filter_registered = false;

	public function __construct($template_processor = null, $structure_manager = null, $sources_repo = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
        $this->sources_repo = $sources_repo ?: new AIPS_Sources_Repository();

        // Register the content prompt sources filter once.
        if (!self::$sources_filter_registered) {
            add_filter('aips_content_prompt', array($this, 'inject_sources_into_content_prompt'), 10, 3);
            self::$sources_filter_registered = true;
        }
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
        return $this->get_post_content_builder()->build($template_or_context, $topic, $voice);
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
		return $this->get_post_title_builder()->build($template_or_context, $topic, $voice, $content);
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
        return $this->get_post_excerpt_builder()->build($title, $content, $voice, $topic);
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
        return $this->get_post_excerpt_builder()->build_instructions($voice, $topic);
    }

	/**
	 * Build the processed featured image prompt.
	 *
	 * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null                    $topic Topic string for legacy flows.
	 * @return string
	 */
	public function build_featured_image_prompt($template_or_context, $topic = null) {
		return $this->get_post_featured_image_builder()->build($template_or_context, $topic);
	}

    /**
     * Standard output instructions for article formatting.
     *
     * @return string
     */
    private function get_output_instructions() {
        return <<<'INSTRUCTIONS'
CRITICAL INSTRUCTIONS:
- Output ONLY the article content, nothing else
- Do NOT include any preamble, thinking text, or commentary like "Let's create..." or "Here's..."
- Do NOT use markdown formatting (no ```, no **, no __)
- Use proper HTML tags: <h2> for section titles, <p> for paragraphs
- For code samples: wrap code in <pre><code> tags with HTML entities (use &lt; for <, &gt; for >, &amp; for &)
- Example code format: <pre><code>&lt;div class="example"&gt;content&lt;/div&gt;</code></pre>
- Do NOT include markdown code fences like ```html or ```
- Start directly with the article content (typically an opening paragraph or <h2> heading)
- End with a concise summary paragraph
INSTRUCTIONS;
    }

    /**
     * Build a compact site-wide context block for inclusion at the top of AI prompts.
     *
     * Reads the site-wide content strategy settings via AIPS_Site_Context and
     * formats them into a structured text block. Only non-empty / non-default
     * values are included so the prompt is not padded with placeholder lines.
     *
     * This block intentionally does not include or reference trusted source URLs.
     * Any source instructions are injected separately (for example via
     * build_sources_block()) when that behavior is explicitly enabled.
     *
     * Returns an empty string when no site-wide settings have been configured,
     * allowing callers to safely append the result without extra whitespace.
     *
     * @return string Formatted context block ending with two newlines, or empty string.
     */
    public function build_site_context_block() {
        $ctx   = AIPS_Site_Context::get();
        $lines = array();

        if (!empty($ctx['niche'])) {
            $lines[] = 'Site niche: ' . $ctx['niche'];
        }

        if (!empty($ctx['target_audience'])) {
            $lines[] = 'Target audience: ' . $ctx['target_audience'];
        }

        if (!empty($ctx['content_goals'])) {
            $lines[] = 'Content goals: ' . $ctx['content_goals'];
        }

        if (!empty($ctx['brand_voice'])) {
            $lines[] = 'Brand voice/tone: ' . $ctx['brand_voice'];
        }

        if (!empty($ctx['content_language']) && $ctx['content_language'] !== 'en') {
            $lines[] = 'Language: ' . $ctx['content_language'];
        }

        if (!empty($ctx['content_guidelines'])) {
            $lines[] = 'Content guidelines: ' . $ctx['content_guidelines'];
        }

        if (!empty($ctx['excluded_topics'])) {
            $lines[] = 'Topics to avoid globally: ' . $ctx['excluded_topics'];
        }

        if (empty($lines)) {
            return '';
        }

        return "Site-wide content context:\n" . implode("\n", $lines) . "\n\n";
    }

    /**
     * Build a trusted sources block for inclusion in AI prompts.
     *
     * When extracted text is available in aips_sources_data the block includes
     * a snippet of the fetched content alongside the URL, giving the model
     * real reference material to draw from.  Sources that have not yet been
     * fetched fall back gracefully to URL-only entries.
     *
     * @param int[] $term_ids Source group term IDs to fetch sources from.
     * @return string Formatted sources block, or empty string.
     */
    public function build_sources_block(array $term_ids) {
        if (empty($term_ids)) {
            return '';
        }

        $source_rows = $this->sources_repo->get_by_group_term_ids($term_ids, true);

        if (empty($source_rows)) {
            return '';
        }

        // Bulk-load any available extracted text for these sources.
        $source_ids  = array_map(function ($s) { return (int) $s->id; }, $source_rows);
        $data_repo   = new AIPS_Sources_Data_Repository();
        $content_map = $data_repo->get_extracted_texts_by_source_ids($source_ids);

        $snippet_max = absint(get_option('aips_source_snippet_max_chars', AIPS_Sources_Fetcher::DEFAULT_PROMPT_SNIPPET_CHARS));
        if ($snippet_max < 100) {
            $snippet_max = AIPS_Sources_Fetcher::DEFAULT_PROMPT_SNIPPET_CHARS;
        }

        $block = "Trusted Sources (use the following content and URLs as factual references):\n\n";

        foreach ($source_rows as $source) {
            $sid   = (int) $source->id;
            $label = !empty($source->label) ? $source->label : $source->url;

            $block .= sprintf("--- Source: %s (%s) ---\n", $label, $source->url);

            if (isset($content_map[$sid])) {
                $snippet = $content_map[$sid]->extracted_text;
                if (mb_strlen($snippet) > $snippet_max) {
                    $snippet = mb_substr($snippet, 0, $snippet_max) . '…';
                }
                $block .= $snippet . "\n";
            } else {
                $block .= "[Content not yet fetched — reference this URL where relevant]\n";
            }

            $block .= "\n";
        }

        /**
         * Filters the formatted sources block before it is injected into a prompt.
         *
         * @since 2.4.0
         * @param string   $block       The formatted sources block.
         * @param int[]    $term_ids    Source group term IDs that were queried.
         * @param object[] $source_rows Source row objects that were found.
         */
        return apply_filters('aips_sources_block', $block, $term_ids, $source_rows);
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
        $content_prompt = $this->get_post_content_builder()->build($template_data, $sample_topic, $voice);

        // Build title prompt
        $sample_content = '[Generated article content would appear here]';
        $title_prompt = $this->get_post_title_builder()->build($template_data, $sample_topic, $voice, $sample_content, true);

        // Build excerpt prompt (requires title and content)
        $sample_title = '[Generated title would appear here]';
        $excerpt_prompt = $this->get_post_excerpt_builder()->build($sample_title, $sample_content, $voice, $sample_topic, true);

        // Build image prompt if enabled
        $image_prompt_processed = $this->get_post_featured_image_builder()->build($template_data, $sample_topic);

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
                'include_sources' => !empty($template_data->include_sources),
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

	/**
	 * Get the dedicated post title prompt builder.
	 *
	 * @return AIPS_Prompt_Builder_Post_Title
	 */
	public function get_post_title_builder() {
		if (null === $this->post_title_builder) {
			$this->post_title_builder = new AIPS_Prompt_Builder_Post_Title($this->template_processor);
		}

		return $this->post_title_builder;
	}

	/**
	 * Get the dedicated post excerpt prompt builder.
	 *
	 * @return AIPS_Prompt_Builder_Post_Excerpt
	 */
	public function get_post_excerpt_builder() {
		if (null === $this->post_excerpt_builder) {
			$this->post_excerpt_builder = new AIPS_Prompt_Builder_Post_Excerpt($this->template_processor);
		}

		return $this->post_excerpt_builder;
	}

	/**
	 * Get the dedicated post content prompt builder.
	 *
	 * @return AIPS_Prompt_Builder_Post_Content
	 */
	public function get_post_content_builder() {
		if (null === $this->post_content_builder) {
            $article_structure_section_builder = new AIPS_Prompt_Builder_Article_Structure_Section($this->structure_manager, null, $this->template_processor);
            $this->post_content_builder = new AIPS_Prompt_Builder_Post_Content($this->template_processor, $article_structure_section_builder);
		}

		return $this->post_content_builder;
	}

	/**
	 * Get the dedicated post featured image prompt builder.
	 *
	 * @return AIPS_Prompt_Builder_Post_Featured_Image
	 */
	public function get_post_featured_image_builder() {
		if (null === $this->post_featured_image_builder) {
			$this->post_featured_image_builder = new AIPS_Prompt_Builder_Post_Featured_Image($this->template_processor);
		}

		return $this->post_featured_image_builder;
	}

    /**
     * Inject trusted sources into the content prompt via filter.
     *
     * This method centralizes the logic for prepending the "Trusted sources" block
     * instead of handling it inside individual prompt builder classes.
     *
     * It supports both context-based generation (AIPS_Generation_Context) and the
     * legacy template object flow used by AIPS_Prompt_Builder_Post_Content.
     *
     * @param string $prompt  The already-processed content prompt.
     * @param mixed  $subject Generation context or legacy template object.
     * @param string $topic   Topic string (may be null).
     * @return string Prompt with sources block prepended when enabled.
     */
    public function inject_sources_into_content_prompt($prompt, $subject, $topic = null) {
        $include_sources = false;
        $group_ids       = array();

        // Context-based flow implements the generation context interface.
        if ($subject instanceof AIPS_Generation_Context) {
            if ($subject->get_include_sources()) {
                $include_sources = true;
                $group_ids       = $subject->get_source_group_ids();
            }
        } else {
            // Legacy template object flow.
            if (!empty($subject) && !empty($subject->include_sources)) {
                $include_sources = true;
                if (!empty($subject->source_group_ids)) {
                    $decoded   = json_decode($subject->source_group_ids, true);
                    $group_ids = is_array($decoded) ? array_map('intval', $decoded) : array();
                }
            }
        }

        if (!$include_sources) {
            return $prompt;
        }

        $sources_block = $this->build_sources_block($group_ids);

        if (empty($sources_block)) {
            return $prompt;
        }

        return $sources_block . $prompt;
    }
}
