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
     * @var AIPS_AI_Service AI service instance
     */
    private $ai_service;
    
    /**
     * @var AIPS_Logger Logger instance
     */
    private $logger;
    
    /**
     * @var AIPS_Config Configuration manager
     */
    private $config;
    
    /**
     * Initialize the Research Service.
     *
     * @param AIPS_AI_Service|null $ai_service Optional AI service instance for dependency injection.
     */
    public function __construct($ai_service = null) {
        $this->ai_service = $ai_service ?: new AIPS_AI_Service();
        $this->logger = new AIPS_Logger();
        $this->config = AIPS_Config::get_instance();
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
        
        $result = $this->ai_service->generate_text($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ));
        
        if (is_wp_error($result)) {
            $this->logger->log("Research failed: " . $result->get_error_message(), 'error');
            return $result;
        }
        
        // Parse AI response into structured data
        $topics = $this->parse_research_response($result, $count);
        
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
        $current_date = current_time('F j, Y');
        $current_year = current_time('Y');
        
        $prompt = "You are a content research expert analyzing trending topics for '{$niche}' as of {$current_date}.\n\n";
        
        $prompt .= "Your task: Identify the top {$count} most trending, relevant, and engaging topics in this niche right now.\n\n";
        
        if (!empty($keywords)) {
            $keyword_list = implode(', ', $keywords);
            $prompt .= "Focus areas: {$keyword_list}\n\n";
        }
        
        $prompt .= "Consider:\n";
        $prompt .= "1. Current events and news in {$current_year}\n";
        $prompt .= "2. Seasonal relevance for " . current_time('F') . "\n";
        $prompt .= "3. Search trends and user interest\n";
        $prompt .= "4. Evergreen value combined with timeliness\n";
        $prompt .= "5. Content gap opportunities\n\n";
        
        $prompt .= "Return ONLY a valid JSON array of objects. Each object must have:\n";
        $prompt .= "- \"topic\": The topic/title (string)\n";
        $prompt .= "- \"score\": Relevance score 1-100 (integer)\n";
        $prompt .= "- \"reason\": Why it's trending (max 100 chars, string)\n";
        $prompt .= "- \"keywords\": Related keywords (array of 3-5 strings)\n\n";
        
        $prompt .= "Example format:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"topic\": \"How AI is Transforming Content Creation in 2025\",\n";
        $prompt .= "    \"score\": 95,\n";
        $prompt .= "    \"reason\": \"High search volume, current AI adoption surge\",\n";
        $prompt .= "    \"keywords\": [\"AI content\", \"automation\", \"GPT-4\", \"content marketing\", \"2025 trends\"]\n";
        $prompt .= "  }\n";
        $prompt .= "]\n\n";
        
        $prompt .= "Return ONLY the JSON array. No markdown, no explanations, no code blocks.";
        
        return $prompt;
    }
    
    /**
     * Parse the AI research response into structured topic data.
     *
     * Extracts and validates topic information from the AI's JSON response.
     *
     * @param string $response The raw AI response.
     * @param int    $count    Expected number of topics.
     * @return array|WP_Error  Parsed topics array or WP_Error on parse failure.
     */
    private function parse_research_response($response, $count) {
        // Clean up the response
        $json_str = trim($response);
        
        // Remove potential markdown code blocks
        $json_str = preg_replace('/^```json\s*/m', '', $json_str);
        $json_str = preg_replace('/^```\s*/m', '', $json_str);
        $json_str = preg_replace('/```$/m', '', $json_str);
        $json_str = trim($json_str);
        
        // Decode JSON
        $topics = json_decode($json_str, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log("Failed to parse research JSON: " . json_last_error_msg(), 'error', array(
                'response_preview' => substr($json_str, 0, 200),
            ));
            
            // Fallback: try to extract topics from text
            return $this->fallback_parse_topics($response, $count);
        }
        
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
        $validated_topics = array_slice($validated_topics, 0, $count);
        
        return $validated_topics;
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
                ? array_map('sanitize_text_field', $topic['keywords'])
                : array(),
            'researched_at' => current_time('mysql'),
        );
    }
    
    /**
     * Fallback parser for when JSON parsing fails.
     *
     * Attempts to extract topic information from free-form text.
     *
     * @param string $response The raw AI response.
     * @param int    $count    Number of topics to extract.
     * @return array|WP_Error  Extracted topics or WP_Error.
     */
    private function fallback_parse_topics($response, $count) {
        $this->logger->log("Attempting fallback topic extraction", 'info');
        
        // Split by lines and look for topic-like patterns
        $lines = explode("\n", $response);
        $topics = array();
        $score = 100; // Start high and decrease
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and short lines
            if (strlen($line) < 10) {
                continue;
            }
            
            // Remove common list markers
            $line = preg_replace('/^[\d]+[\.\)]\s*/', '', $line);
            $line = preg_replace('/^[-*•]\s*/', '', $line);
            $line = trim($line);
            
            if (!empty($line)) {
                $topics[] = array(
                    'topic' => sanitize_text_field($line),
                    'score' => $score,
                    'reason' => 'Extracted from AI response',
                    'keywords' => array(),
                    'researched_at' => current_time('mysql'),
                );
                
                $score = max(50, $score - 5); // Decrease score for each topic
                
                if (count($topics) >= $count) {
                    break;
                }
            }
        }
        
        if (empty($topics)) {
            return new WP_Error('extraction_failed', __('Failed to extract topics from AI response.', 'ai-post-scheduler'));
        }
        
        return $topics;
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
        
        // Already sorted by score in parse_research_response
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
        $current_year = current_time('Y');
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
        $current_month = current_time('n'); // 1-12
        $seasonal_months = array(
            array(12, 1, 2) => array('winter', 'holiday', 'christmas', 'new year'),
            array(3, 4, 5) => array('spring', 'easter', 'tax'),
            array(6, 7, 8) => array('summer', 'vacation', 'back to school'),
            array(9, 10, 11) => array('fall', 'autumn', 'halloween', 'thanksgiving', 'black friday'),
        );
        
        foreach ($seasonal_months as $months => $terms) {
            if (in_array($current_month, $months)) {
                foreach ($terms as $term) {
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
        
        // If scores are equal, compare by keyword count
        $keywords1 = isset($topic1['keywords']) ? count($topic1['keywords']) : 0;
        $keywords2 = isset($topic2['keywords']) ? count($topic2['keywords']) : 0;
        
        return $keywords2 <=> $keywords1;
    }
}
