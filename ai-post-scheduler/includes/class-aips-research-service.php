<?php
/**
 * Research Service
 *
 * Handles automated topic research and trend analysis using AI.
 * Discovers trending topics in specified niches and ranks them by relevance.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Research_Service
 *
 * Provides automated content research capabilities to discover trending topics,
 * analyze their relevance, and suggest top topics for content generation.
 */
class AIPS_Research_Service {

    /**
     * @var AIPS_AI_Service_Interface AI service instance
     */
    private $ai_service;

    /**
     * @var AIPS_Logger_Interface Logger instance
     */
    private $logger;

    /**
     * Initialize the Research Service.
     *
     * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service instance for dependency injection.
     */
    public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
        $container = AIPS_Container::get_instance();
        $this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
        $this->logger = $container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger();
    }

    /**
     * Research trending topics in a specific niche.
     *
     * Uses AI to discover what's currently trending in the given niche,
     * analyzes relevance, and returns ranked topics with scores.
     *
     * @param string $niche     The niche or industry to research.
     * @param int    $count     Number of topics to return (default 10).
     * @param array  $keywords  Optional keywords to focus the research.
     * @return array|WP_Error   Array of topics with scores, or WP_Error on failure.
     */
    public function research_trending_topics($niche, $count = 10, $keywords = array()) {
        if (empty($niche)) {
            return new WP_Error('missing_niche', __('Niche parameter is required for research.', 'ai-post-scheduler'));
        }

        if (!$this->ai_service->is_available()) {
            return new WP_Error('ai_unavailable', __('AI Engine is not available for research.', 'ai-post-scheduler'));
        }

        // Validate count
        $count = max(1, min(50, absint($count)));

        // Build research prompt
        $prompt = $this->build_research_prompt($niche, $count, $keywords);

        // Execute AI research
        $this->logger->log("Starting trending topics research for niche: {$niche}", 'info', array(
            'niche' => $niche,
            'count' => $count,
            'keywords' => $keywords,
        ));

        $result = $this->ai_service->generate_json($prompt, array(
            'temperature' => 0.7,
            'json_schema' => $this->get_topic_json_schema(),
        ));

        if (is_wp_error($result)) {
            $this->logger->log("Research failed: " . $result->get_error_message(), 'error');
            return $result;
        }

        $topics = $this->validate_and_normalize_topics($result, $count);

        if (is_wp_error($topics)) {
            return $topics;
        }

        $this->logger->log("Research completed successfully", 'info', array(
            'topics_found' => count($topics),
        ));

        return $topics;
    }

    /**
     * Build the AI prompt for trending topics research.
     *
     * Creates a detailed prompt that guides the AI to provide
     * structured, ranked topic suggestions.
     *
     * @param string $niche    The niche to research.
     * @param int    $count    Number of topics to generate.
     * @param array  $keywords Additional keywords for context.
     * @return string The formatted prompt.
     */
    private function build_research_prompt($niche, $count, $keywords) {
        $now = AIPS_DateTime::now();
        $current_date = $now->toDisplay('F j, Y');
        $current_year = $now->toDisplay('Y');

        $prompt = "You are a content research expert analyzing trending topics for '{$niche}' as of {$current_date}.\n\n";

        $prompt .= "Your task: Identify the top {$count} most trending, relevant, and engaging topics in this niche right now.\n\n";

        if (!empty($keywords)) {
            $keyword_list = implode(', ', $keywords);
            $prompt .= "Focus areas: {$keyword_list}\n\n";
        }

        $prompt .= "Consider:\n";
        $prompt .= "1. Current events and news in {$current_year}\n";
        $prompt .= "2. Seasonal relevance for " . $now->toDisplay('F') . "\n";
        $prompt .= "3. Search trends and user interest\n";
        $prompt .= "4. Evergreen value combined with timeliness\n";
        $prompt .= "5. Content gap opportunities\n\n";

        $prompt .= "Return a JSON array where each item has: \"topic\" (string), \"score\" (integer 1-100), \"reason\" (string, max 100 chars), \"keywords\" (array of 3-5 strings).";

        return $prompt;
    }

    /**
     * Validate and normalize topics from JSON response.
     *
     * Validates topic structure and normalizes data from JSON response.
     * This is used when generate_json returns structured data directly.
     *
     * @param array $topics  The JSON array of topics.
     * @param int   $count   Expected number of topics.
     * @return array|WP_Error Validated topics array or WP_Error.
     */
    private function validate_and_normalize_topics($topics, $count) {
        if (!is_array($topics)) {
            return new WP_Error('invalid_format', __('AI response is not in expected array format.', 'ai-post-scheduler'));
        }

        // Validate and normalize topics
        $validated_topics = array();
        foreach ($topics as $topic) {
            if ($this->validate_topic_structure($topic)) {
                $validated_topics[] = $this->normalize_topic($topic);
            }
        }

        if (empty($validated_topics)) {
            return new WP_Error('no_valid_topics', __('No valid topics found in AI response.', 'ai-post-scheduler'));
        }

        // Sort by score (highest first)
        usort($validated_topics, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Limit to requested count
        return array_slice($validated_topics, 0, $count);
    }

    /**
     * JSON schema for the topic array returned by the AI.
     *
     * Passed to providers that support native structured-JSON output (e.g. Gemini
     * via as_json_response()) so the model is constrained to the expected shape
     * and the text-based extraction fallback is not needed.
     *
     * @return array<string, mixed>
     */
    private function get_topic_json_schema(): array {
        return array(
            'type'  => 'array',
            'items' => array(
                'type'       => 'object',
                'properties' => array(
                    'topic'    => array('type' => 'string'),
                    'score'    => array('type' => 'integer'),
                    'reason'   => array('type' => 'string'),
                    'keywords' => array(
                        'type'  => 'array',
                        'items' => array('type' => 'string'),
                    ),
                ),
                'required' => array('topic', 'score', 'reason', 'keywords'),
            ),
        );
    }

    /**
     * Validate topic structure has required fields.
     *
     * @param mixed $topic The topic data to validate.
     * @return bool True if valid, false otherwise.
     */
    private function validate_topic_structure($topic) {
        if (!is_array($topic)) {
            return false;
        }

        return isset($topic['topic'])
            && isset($topic['score'])
            && !empty($topic['topic']);
    }

    /**
     * Normalize a topic with default values for missing fields.
     *
     * @param array $topic The topic data to normalize.
     * @return array Normalized topic.
     */
    private function normalize_topic($topic) {
        return array(
            'topic' => sanitize_text_field($topic['topic']),
            'score' => absint($topic['score']),
            'reason' => isset($topic['reason']) ? sanitize_text_field($topic['reason']) : '',
            'keywords' => isset($topic['keywords']) && is_array($topic['keywords'])
                ? AIPS_Utilities::sanitize_string_array($topic['keywords'])
                : array(),
            'researched_at' => AIPS_DateTime::now()->timestamp(),
        );
    }

    /**
     * Get the top N topics from a list of researched topics.
     *
     * @param array $topics All researched topics.
     * @param int   $count  Number of top topics to return.
     * @return array Top N topics by score.
     */
    public function get_top_topics($topics, $count = 5) {
        if (!is_array($topics) || empty($topics)) {
            return array();
        }

        return array_slice($topics, 0, absint($count));
    }

    /**
     * Analyze topic freshness based on keywords and temporal indicators.
     *
     * Checks if a topic is timely and relevant based on current date,
     * seasonal factors, and trending indicators.
     *
     * @param array $topic Topic data with keywords.
     * @return array Freshness analysis with score and reasons.
     */
    public function analyze_topic_freshness($topic) {
        $freshness_score = 50; // Base score
        $indicators = array();

        $topic_text = strtolower($topic['topic']);
        $keywords = isset($topic['keywords']) ? array_map('strtolower', $topic['keywords']) : array();
        $all_text = $topic_text . ' ' . implode(' ', $keywords);

        // Check for current year
        $current_year = AIPS_DateTime::now()->toDisplay('Y');
        if (strpos($all_text, $current_year) !== false) {
            $freshness_score += 20;
            $indicators[] = "Mentions current year ({$current_year})";
        }

        // Check for temporal words
        $temporal_words = array('now', 'today', 'current', 'latest', 'new', 'trending', 'this year');
        foreach ($temporal_words as $word) {
            if (strpos($all_text, $word) !== false) {
                $freshness_score += 10;
                $indicators[] = "Contains temporal indicator: {$word}";
                break;
            }
        }

        // Check for seasonal relevance
        $current_month = (int) AIPS_DateTime::now()->toDisplay('n'); // 1-12
        $seasonal_months = array(
            array(
                'months' => array(12, 1, 2),
                'terms' => array('winter', 'holiday', 'christmas', 'new year'),
            ),
            array(
                'months' => array(3, 4, 5),
                'terms' => array('spring', 'easter', 'tax'),
            ),
            array(
                'months' => array(6, 7, 8),
                'terms' => array('summer', 'vacation', 'back to school'),
            ),
            array(
                'months' => array(9, 10, 11),
                'terms' => array('fall', 'autumn', 'halloween', 'thanksgiving', 'black friday'),
            ),
        );

        foreach ($seasonal_months as $group) {
            if (in_array($current_month, $group['months'])) {
                foreach ($group['terms'] as $term) {
                    if (strpos($all_text, $term) !== false) {
                        $freshness_score += 15;
                        $indicators[] = "Seasonally relevant: {$term}";
                        break 2;
                    }
                }
            }
        }

        // Cap at 100
        $freshness_score = min(100, $freshness_score);

        return array(
            'score' => $freshness_score,
            'indicators' => $indicators,
            'is_fresh' => $freshness_score >= 70,
        );
    }

    /**
     * Compare two topics for relevance based on scores and keywords.
     *
     * @param array $topic1 First topic.
     * @param array $topic2 Second topic.
     * @return int  Comparison result (-1, 0, 1).
     */
    public function compare_topics($topic1, $topic2) {
        $score1 = isset($topic1['score']) ? $topic1['score'] : 0;
        $score2 = isset($topic2['score']) ? $topic2['score'] : 0;

        if ($score1 !== $score2) {
            return $score2 <=> $score1; // Higher scores first
        }

        // If scores are equal, compare by keyword count (more keywords first)
        $keywords1 = isset($topic1['keywords']) ? count($topic1['keywords']) : 0;
        $keywords2 = isset($topic2['keywords']) ? count($topic2['keywords']) : 0;

        return $keywords1 <=> $keywords2;
    }

    /**
     * Research topics using pre-fetched source content as context.
     *
     * Combines scraped content from source URLs with AI research to produce
     * topic ideas that are specifically grounded in the source material.
     *
     * @param int[]  $term_ids   Source group term IDs to include.
     * @param string $niche      Niche or topic area to guide the research.
     * @param int    $count      Number of topics to generate (default 10).
     * @param array  $keywords   Optional focus keywords.
     * @return array|WP_Error  Array of topic objects, or WP_Error on failure.
     */
    public function research_from_sources( array $term_ids, $niche, $count = 10, $keywords = array() ) {
        if ( empty( $term_ids ) ) {
            return new WP_Error( 'no_term_ids', __( 'At least one source group is required.', 'ai-post-scheduler' ) );
        }

        if ( empty( $niche ) ) {
            return new WP_Error( 'missing_niche', __( 'Niche parameter is required for source-based research.', 'ai-post-scheduler' ) );
        }

        if ( ! $this->ai_service->is_available() ) {
            return new WP_Error( 'ai_unavailable', __( 'AI Engine is not available for research.', 'ai-post-scheduler' ) );
        }

        $count = max( 1, min( 50, absint( $count ) ) );

        // Fetch source rows and any scraped content.
        $sources_repo = new AIPS_Sources_Repository();
        $data_repo    = new AIPS_Sources_Data_Repository();

        $source_rows = $sources_repo->get_by_group_term_ids( $term_ids, true );
        if ( empty( $source_rows ) ) {
            return new WP_Error( 'no_sources', __( 'No active sources found for the selected groups.', 'ai-post-scheduler' ) );
        }

        $source_ids  = array_map( function ( $s ) { return (int) $s->id; }, $source_rows );
        $content_map = $data_repo->get_extracted_texts_by_source_ids( $source_ids );

        // Build a context block from whatever content is available.
        $source_context = '';
        foreach ( $source_rows as $source ) {
            $sid   = (int) $source->id;
            $label = ! empty( $source->label ) ? $source->label : $source->url;
            $source_context .= sprintf( "--- Source: %s (%s) ---\n", $label, $source->url );
            if ( isset( $content_map[$sid] ) ) {
                $snippet = $content_map[$sid]->extracted_text;
                if ( mb_strlen( $snippet ) > 1500 ) {
                    $snippet = mb_substr( $snippet, 0, 1500 ) . '…';
                }
                $source_context .= $snippet . "\n";
            } else {
                $source_context .= "[No fetched content available]\n";
            }
            $source_context .= "\n";
        }

        $prompt = $this->build_source_research_prompt( $niche, $count, $keywords, $source_context );

        $this->logger->log(
            sprintf( 'AIPS_Research_Service: source-based research for niche "%s" with %d source(s).', $niche, count( $source_rows ) ),
            'info'
        );

        $result = $this->ai_service->generate_json( $prompt, array(
            'temperature' => 0.7,
            'json_schema' => $this->get_topic_json_schema(),
        ) );

        if ( is_wp_error( $result ) ) {
            $this->logger->log( 'Source-based research failed: ' . $result->get_error_message(), 'error' );
            return $result;
        }

        $topics = $this->validate_and_normalize_topics( $result, $count );

        if ( is_wp_error( $topics ) ) {
            return $topics;
        }

        return $topics;
    }

    /**
     * Build the AI prompt for source-grounded research.
     *
     * @param string $niche          The niche context.
     * @param int    $count          Number of topics to produce.
     * @param array  $keywords       Optional focus keywords.
     * @param string $source_context Scraped source content block.
     * @return string Formatted prompt.
     */
    private function build_source_research_prompt( $niche, $count, $keywords, $source_context ) {
        $current_date = AIPS_DateTime::now()->toDisplay( 'F j, Y' );

        $prompt  = "You are a content research expert. Using the source material below as your primary reference, ";
        $prompt .= "identify {$count} specific, high-value blog post topics for the '{$niche}' niche as of {$current_date}.\n\n";

        if ( ! empty( $keywords ) ) {
            $keyword_list = implode( ', ', (array) $keywords );
            $prompt .= "Additional focus keywords: {$keyword_list}\n\n";
        }

        $prompt .= "SOURCE MATERIAL:\n";
        $prompt .= $source_context . "\n";

        $prompt .= "Instructions:\n";
        $prompt .= "- Ground your topic suggestions in the specific facts, trends, and insights from the sources above.\n";
        $prompt .= "- Prefer specific, actionable topics over generic ones.\n";
        $prompt .= "- Consider gaps or follow-up angles suggested by the source content.\n\n";

        $prompt .= "Return a JSON array where each item has: \"topic\" (string), \"score\" (integer 1-100), \"reason\" (string, max 100 chars), \"keywords\" (array of 3-5 strings).";

        return $prompt;
    }
}
