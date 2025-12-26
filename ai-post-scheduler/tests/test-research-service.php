<?php
/**
 * Tests for AIPS_Research_Service
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_Research_Service extends WP_UnitTestCase {
    
    private $research_service;
    private $mock_ai_service;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mock AI service
        $this->mock_ai_service = $this->createMock(AIPS_AI_Service::class);
        $this->research_service = new AIPS_Research_Service($this->mock_ai_service);
    }
    
    public function tearDown(): void {
        parent::tearDown();
    }
    
    /**
     * Test service instantiation.
     */
    public function test_service_instantiation() {
        $this->assertInstanceOf(AIPS_Research_Service::class, $this->research_service);
    }
    
    /**
     * Test research with empty niche returns error.
     */
    public function test_research_empty_niche_returns_error() {
        $result = $this->research_service->research_trending_topics('', 10);
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('missing_niche', $result->get_error_code());
    }
    
    /**
     * Test research when AI service unavailable.
     */
    public function test_research_ai_unavailable() {
        $this->mock_ai_service->method('is_available')->willReturn(false);
        
        $result = $this->research_service->research_trending_topics('Digital Marketing', 10);
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('ai_unavailable', $result->get_error_code());
    }
    
    /**
     * Test research with valid parameters.
     */
    public function test_research_with_valid_parameters() {
        $this->mock_ai_service->method('is_available')->willReturn(true);
        
        $mock_response = json_encode(array(
            array(
                'topic' => 'How AI is Transforming Content Marketing in 2025',
                'score' => 95,
                'reason' => 'High search volume and current trends',
                'keywords' => array('AI', 'content marketing', '2025', 'automation'),
            ),
            array(
                'topic' => 'Best SEO Strategies for E-commerce',
                'score' => 88,
                'reason' => 'Evergreen topic with seasonal interest',
                'keywords' => array('SEO', 'e-commerce', 'optimization', 'traffic'),
            ),
        ));
        
        $this->mock_ai_service->method('generate_text')->willReturn($mock_response);
        
        $result = $this->research_service->research_trending_topics('Digital Marketing', 10, array('SEO', 'content'));
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(95, $result[0]['score']);
        $this->assertEquals('How AI is Transforming Content Marketing in 2025', $result[0]['topic']);
    }
    
    /**
     * Test research with AI error.
     */
    public function test_research_with_ai_error() {
        $this->mock_ai_service->method('is_available')->willReturn(true);
        $this->mock_ai_service->method('generate_text')->willReturn(
            new WP_Error('generation_failed', 'AI generation failed')
        );
        
        $result = $this->research_service->research_trending_topics('Technology', 5);
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('generation_failed', $result->get_error_code());
    }
    
    /**
     * Test count validation.
     */
    public function test_count_validation() {
        $this->mock_ai_service->method('is_available')->willReturn(true);
        
        $mock_response = json_encode(array(
            array('topic' => 'Topic 1', 'score' => 90, 'reason' => 'Test', 'keywords' => array()),
        ));
        
        $this->mock_ai_service->method('generate_text')->willReturn($mock_response);
        
        // Test count too high
        $result = $this->research_service->research_trending_topics('Test', 100);
        $this->assertIsArray($result);
        
        // Test count too low
        $result = $this->research_service->research_trending_topics('Test', 0);
        $this->assertIsArray($result);
    }
    
    /**
     * Test get top topics.
     */
    public function test_get_top_topics() {
        $topics = array(
            array('topic' => 'Topic 1', 'score' => 95),
            array('topic' => 'Topic 2', 'score' => 88),
            array('topic' => 'Topic 3', 'score' => 92),
            array('topic' => 'Topic 4', 'score' => 85),
            array('topic' => 'Topic 5', 'score' => 90),
            array('topic' => 'Topic 6', 'score' => 78),
        );
        
        $top_5 = $this->research_service->get_top_topics($topics, 5);
        
        $this->assertCount(5, $top_5);
    }
    
    /**
     * Test get top topics with empty array.
     */
    public function test_get_top_topics_empty_array() {
        $result = $this->research_service->get_top_topics(array(), 5);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    /**
     * Test topic freshness analysis with current year.
     */
    public function test_analyze_topic_freshness_current_year() {
        $topic = array(
            'topic' => 'AI Trends in ' . date('Y'),
            'keywords' => array('AI', 'trends', date('Y')),
        );
        
        $analysis = $this->research_service->analyze_topic_freshness($topic);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('score', $analysis);
        $this->assertArrayHasKey('indicators', $analysis);
        $this->assertArrayHasKey('is_fresh', $analysis);
        $this->assertGreaterThan(50, $analysis['score']);
    }
    
    /**
     * Test topic freshness analysis with temporal words.
     */
    public function test_analyze_topic_freshness_temporal_words() {
        $topic = array(
            'topic' => 'Latest SEO Trends Now',
            'keywords' => array('SEO', 'latest', 'trending'),
        );
        
        $analysis = $this->research_service->analyze_topic_freshness($topic);
        
        $this->assertGreaterThan(50, $analysis['score']);
        $this->assertNotEmpty($analysis['indicators']);
    }
    
    /**
     * Test topic freshness analysis with seasonal relevance.
     */
    public function test_analyze_topic_freshness_seasonal() {
        $month = date('n');
        
        // Get appropriate seasonal term based on current month
        $seasonal_terms = array(
            array(12, 1, 2) => 'holiday',
            array(3, 4, 5) => 'spring',
            array(6, 7, 8) => 'summer',
            array(9, 10, 11) => 'fall',
        );
        
        $seasonal_term = 'test';
        foreach ($seasonal_terms as $months => $term) {
            if (in_array($month, $months)) {
                $seasonal_term = $term;
                break;
            }
        }
        
        $topic = array(
            'topic' => 'Marketing for ' . $seasonal_term,
            'keywords' => array($seasonal_term, 'marketing'),
        );
        
        $analysis = $this->research_service->analyze_topic_freshness($topic);
        
        $this->assertGreaterThan(50, $analysis['score']);
    }
    
    /**
     * Test compare topics by score.
     */
    public function test_compare_topics_by_score() {
        $topic1 = array('topic' => 'Topic 1', 'score' => 95, 'keywords' => array('a', 'b'));
        $topic2 = array('topic' => 'Topic 2', 'score' => 88, 'keywords' => array('c', 'd'));
        
        $result = $this->research_service->compare_topics($topic1, $topic2);
        
        // Topic1 has higher score, so should come first (negative result)
        $this->assertEquals(-1, $result);
    }
    
    /**
     * Test compare topics with equal scores.
     */
    public function test_compare_topics_equal_scores() {
        $topic1 = array('topic' => 'Topic 1', 'score' => 90, 'keywords' => array('a', 'b', 'c'));
        $topic2 = array('topic' => 'Topic 2', 'score' => 90, 'keywords' => array('d', 'e'));
        
        $result = $this->research_service->compare_topics($topic1, $topic2);
        
        // Topic1 has more keywords; according to implementation this yields a positive result
        $this->assertEquals(1, $result);
    }
    
    /**
     * Test fallback parsing when JSON fails.
     */
    public function test_fallback_parsing() {
        $this->mock_ai_service->method('is_available')->willReturn(true);
        
        // Mock response that's not valid JSON
        $mock_response = "1. Topic One\n2. Topic Two\n3. Topic Three";
        
        $this->mock_ai_service->method('generate_text')->willReturn($mock_response);
        
        $result = $this->research_service->research_trending_topics('Test', 5);
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
    
    /**
     * Test JSON parsing with markdown code blocks.
     */
    public function test_json_parsing_with_markdown() {
        $this->mock_ai_service->method('is_available')->willReturn(true);
        
        $mock_response = "```json\n" . json_encode(array(
            array('topic' => 'Test Topic', 'score' => 90, 'reason' => 'Test', 'keywords' => array()),
        )) . "\n```";
        
        $this->mock_ai_service->method('generate_text')->willReturn($mock_response);
        
        $result = $this->research_service->research_trending_topics('Test', 5);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
}
