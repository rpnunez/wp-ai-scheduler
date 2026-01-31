<?php
/**
 * Tests for AIPS_Trending_Topics_Repository
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_Trending_Topics_Repository extends WP_UnitTestCase {
    
    private $repository;
    
    public function setUp(): void {
        parent::setUp();
        $this->repository = new AIPS_Trending_Topics_Repository();
        
        // Ensure table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'aips_trending_topics';
        
        // Create table if it doesn't exist (for testing)
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            niche varchar(255) NOT NULL,
            topic varchar(500) NOT NULL,
            score int(11) NOT NULL DEFAULT 50,
            reason text DEFAULT NULL,
            keywords text DEFAULT NULL,
            researched_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY niche_idx (niche),
            KEY score_idx (score),
            KEY researched_at_idx (researched_at)
        ) {$charset_collate};";
        
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        }
        dbDelta($sql);
    }
    
    public function tearDown(): void {
        // Clean up test data
        global $wpdb;
        $table_name = $wpdb->prefix . 'aips_trending_topics';
        $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        parent::tearDown();
    }
    
    /**
     * Test repository instantiation.
     */
    public function test_repository_instantiation() {
        $this->assertInstanceOf(AIPS_Trending_Topics_Repository::class, $this->repository);
    }
    
    /**
     * Test creating a topic.
     */
    public function test_create_topic() {
        $data = array(
            'niche' => 'Digital Marketing',
            'topic' => 'Test Topic',
            'score' => 90,
            'reason' => 'Test reason',
            'keywords' => array('test', 'keyword'),
            'researched_at' => current_time('mysql'),
        );
        
        $topic_id = $this->repository->create($data);
        
        $this->assertIsInt($topic_id);
        $this->assertGreaterThan(0, $topic_id);
    }
    
    /**
     * Test creating topic with missing required fields.
     */
    public function test_create_topic_missing_fields() {
        $data = array(
            'niche' => 'Test Niche',
            // Missing 'topic' field
        );
        
        $result = $this->repository->create($data);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test getting topic by ID.
     */
    public function test_get_by_id() {
        $topic_id = $this->repository->create(array(
            'niche' => 'Technology',
            'topic' => 'AI Trends',
            'score' => 85,
            'keywords' => array('AI', 'trends'),
        ));
        
        $topic = $this->repository->get_by_id($topic_id);
        
        $this->assertIsArray($topic);
        $this->assertEquals('AI Trends', $topic['topic']);
        $this->assertEquals(85, $topic['score']);
    }
    
    /**
     * Test getting all topics.
     */
    public function test_get_all() {
        // Create multiple topics
        $this->repository->create(array(
            'niche' => 'Marketing',
            'topic' => 'Topic 1',
            'score' => 90,
        ));
        
        $this->repository->create(array(
            'niche' => 'Marketing',
            'topic' => 'Topic 2',
            'score' => 85,
        ));
        
        $topics = $this->repository->get_all();
        
        $this->assertIsArray($topics);
        $this->assertGreaterThanOrEqual(2, count($topics));
    }
    
    /**
     * Test getting topics with filters.
     */
    public function test_get_all_with_filters() {
        $this->repository->create(array(
            'niche' => 'Marketing',
            'topic' => 'High Score Topic',
            'score' => 95,
        ));
        
        $this->repository->create(array(
            'niche' => 'Marketing',
            'topic' => 'Low Score Topic',
            'score' => 60,
        ));
        
        $topics = $this->repository->get_all(array(
            'niche' => 'Marketing',
            'min_score' => 80,
        ));
        
        $this->assertIsArray($topics);
        $this->assertCount(1, $topics);
        $this->assertEquals('High Score Topic', $topics[0]['topic']);
    }
    
    /**
     * Test getting topics by niche.
     */
    public function test_get_by_niche() {
        $this->repository->create(array(
            'niche' => 'Technology',
            'topic' => 'Tech Topic',
            'score' => 88,
        ));
        
        $this->repository->create(array(
            'niche' => 'Marketing',
            'topic' => 'Marketing Topic',
            'score' => 92,
        ));
        
        $topics = $this->repository->get_by_niche('Technology', 10, 30);
        
        $this->assertIsArray($topics);
        $this->assertCount(1, $topics);
        $this->assertEquals('Tech Topic', $topics[0]['topic']);
    }
    
    /**
     * Test getting top topics.
     */
    public function test_get_top_topics() {
        $this->repository->create(array(
            'niche' => 'Test',
            'topic' => 'High Score',
            'score' => 98,
        ));
        
        $this->repository->create(array(
            'niche' => 'Test',
            'topic' => 'Medium Score',
            'score' => 85,
        ));
        
        $this->repository->create(array(
            'niche' => 'Test',
            'topic' => 'Low Score',
            'score' => 70,
        ));
        
        $top_topics = $this->repository->get_top_topics(2, 7);
        
        $this->assertIsArray($top_topics);
        $this->assertCount(2, $top_topics);
        $this->assertEquals('High Score', $top_topics[0]['topic']);
        $this->assertEquals('Medium Score', $top_topics[1]['topic']);
    }
    
    /**
     * Test searching topics.
     */
    public function test_search_topics() {
        $this->repository->create(array(
            'niche' => 'Technology',
            'topic' => 'Artificial Intelligence in Healthcare',
            'score' => 90,
        ));
        
        $this->repository->create(array(
            'niche' => 'Marketing',
            'topic' => 'SEO Best Practices',
            'score' => 85,
        ));
        
        $results = $this->repository->search('intelligence', 10);
        
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertStringContainsString('Intelligence', $results[0]['topic']);
    }
    
    /**
     * Test updating a topic.
     */
    public function test_update_topic() {
        $topic_id = $this->repository->create(array(
            'niche' => 'Test',
            'topic' => 'Original Topic',
            'score' => 80,
        ));
        
        $result = $this->repository->update($topic_id, array(
            'topic' => 'Updated Topic',
            'score' => 95,
        ));
        
        $this->assertTrue($result);
        
        $topic = $this->repository->get_by_id($topic_id);
        $this->assertEquals('Updated Topic', $topic['topic']);
        $this->assertEquals(95, $topic['score']);
    }
    
    /**
     * Test deleting a topic.
     */
    public function test_delete_topic() {
        $topic_id = $this->repository->create(array(
            'niche' => 'Test',
            'topic' => 'To Delete',
            'score' => 70,
        ));
        
        $result = $this->repository->delete($topic_id);
        
        $this->assertTrue($result);
        
        $topic = $this->repository->get_by_id($topic_id);
        $this->assertNull($topic);
    }
    
    /**
     * Test deleting topics by niche.
     */
    public function test_delete_by_niche() {
        $this->repository->create(array(
            'niche' => 'ToDelete',
            'topic' => 'Topic 1',
            'score' => 80,
        ));
        
        $this->repository->create(array(
            'niche' => 'ToDelete',
            'topic' => 'Topic 2',
            'score' => 85,
        ));
        
        $this->repository->create(array(
            'niche' => 'ToKeep',
            'topic' => 'Topic 3',
            'score' => 90,
        ));
        
        $deleted = $this->repository->delete_by_niche('ToDelete');
        
        $this->assertEquals(2, $deleted);
        
        $remaining = $this->repository->get_by_niche('ToDelete');
        $this->assertEmpty($remaining);
    }
    
    /**
     * Test save research batch.
     */
    public function test_save_research_batch() {
        $topics = array(
            array(
                'topic' => 'Batch Topic 1',
                'score' => 90,
                'reason' => 'Test',
                'keywords' => array('batch', 'test'),
            ),
            array(
                'topic' => 'Batch Topic 2',
                'score' => 85,
                'reason' => 'Test',
                'keywords' => array('batch', 'test2'),
            ),
        );
        
        $saved_count = $this->repository->save_research_batch($topics, 'Batch Test');
        
        $this->assertEquals(2, $saved_count);
        
        $saved_topics = $this->repository->get_by_niche('Batch Test');
        $this->assertCount(2, $saved_topics);
    }
    
    /**
     * Test get statistics.
     */
    public function test_get_stats() {
        $this->repository->create(array(
            'niche' => 'Niche1',
            'topic' => 'Topic 1',
            'score' => 90,
        ));
        
        $this->repository->create(array(
            'niche' => 'Niche2',
            'topic' => 'Topic 2',
            'score' => 80,
        ));
        
        $stats = $this->repository->get_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_topics', $stats);
        $this->assertArrayHasKey('niches_count', $stats);
        $this->assertArrayHasKey('avg_score', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['total_topics']);
        $this->assertGreaterThanOrEqual(2, $stats['niches_count']);
    }
    
    /**
     * Test get niche stats.
     */
    public function test_get_niche_stats() {
        $this->repository->create(array(
            'niche' => 'SpecificNiche',
            'topic' => 'Topic 1',
            'score' => 95,
        ));
        
        $this->repository->create(array(
            'niche' => 'SpecificNiche',
            'topic' => 'Topic 2',
            'score' => 85,
        ));
        
        $stats = $this->repository->get_niche_stats('SpecificNiche');
        
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['topic_count']);
        $this->assertEquals(90, $stats['avg_score']);
        $this->assertEquals(95, $stats['highest_score']);
    }
    
    /**
     * Test topic exists check.
     */
    public function test_topic_exists() {
        $this->repository->create(array(
            'niche' => 'Test',
            'topic' => 'Existing Topic',
            'score' => 80,
        ));
        
        $exists = $this->repository->topic_exists('Existing Topic', 'Test', 7);
        $this->assertTrue($exists);
        
        $not_exists = $this->repository->topic_exists('Non-existing Topic', 'Test', 7);
        $this->assertFalse($not_exists);
    }
    
    /**
     * Test get niche list.
     */
    public function test_get_niche_list() {
        $this->repository->create(array('niche' => 'Niche A', 'topic' => 'Topic 1', 'score' => 80));
        $this->repository->create(array('niche' => 'Niche A', 'topic' => 'Topic 2', 'score' => 85));
        $this->repository->create(array('niche' => 'Niche B', 'topic' => 'Topic 3', 'score' => 90));
        
        $niches = $this->repository->get_niche_list();
        
        $this->assertIsArray($niches);
        $this->assertGreaterThanOrEqual(2, count($niches));
        
        // Verify structure
        $this->assertArrayHasKey('niche', $niches[0]);
        $this->assertArrayHasKey('count', $niches[0]);
    }
    
    /**
     * Test save_research_batch skips existing topics within 7 days.
     */
    public function test_save_research_batch_skips_existing_topics() {
        // Create an existing topic
        $this->repository->create(array(
            'niche' => 'TestNiche',
            'topic' => 'Existing Topic',
            'score' => 85,
            'researched_at' => current_time('mysql'),
        ));
        
        // Try to save a batch that includes the existing topic
        $topics = array(
            array(
                'topic' => 'Existing Topic',  // This should be skipped
                'score' => 90,
                'reason' => 'Test',
                'keywords' => array('test'),
            ),
            array(
                'topic' => 'New Topic',  // This should be saved
                'score' => 88,
                'reason' => 'Test',
                'keywords' => array('test'),
            ),
        );
        
        $saved_count = $this->repository->save_research_batch($topics, 'TestNiche');
        
        // Only the new topic should be saved
        $this->assertEquals(1, $saved_count);
        
        // Verify only one new topic was added (total should be 2)
        // Using 7 days to match the deduplication window in save_research_batch
        $all_topics = $this->repository->get_by_niche('TestNiche', 20, 7);
        $this->assertCount(2, $all_topics);
    }
    
    /**
     * Test save_research_batch skips duplicates within the same batch.
     */
    public function test_save_research_batch_skips_batch_duplicates() {
        $topics = array(
            array(
                'topic' => 'Duplicate Topic',
                'score' => 90,
                'reason' => 'Test',
                'keywords' => array('test'),
            ),
            array(
                'topic' => 'Duplicate Topic',  // Same as above, should be skipped
                'score' => 85,
                'reason' => 'Test',
                'keywords' => array('test'),
            ),
            array(
                'topic' => 'Unique Topic',
                'score' => 88,
                'reason' => 'Test',
                'keywords' => array('test'),
            ),
        );
        
        $saved_count = $this->repository->save_research_batch($topics, 'DuplicateTest');
        
        // Only 2 unique topics should be saved
        $this->assertEquals(2, $saved_count);
        
        $all_topics = $this->repository->get_by_niche('DuplicateTest');
        $this->assertCount(2, $all_topics);
    }
    
    /**
     * Test save_research_batch returns 0 for empty batch.
     */
    public function test_save_research_batch_empty_batch() {
        $saved_count = $this->repository->save_research_batch(array(), 'EmptyTest');
        $this->assertEquals(0, $saved_count);
    }
    
    /**
     * Test create_bulk inserts multiple topics successfully.
     */
    public function test_create_bulk_success() {
        $topics = array(
            array(
                'niche' => 'BulkTest',
                'topic' => 'Bulk Topic 1',
                'score' => 90,
                'reason' => 'Test reason 1',
                'keywords' => array('bulk', 'test'),
                'researched_at' => current_time('mysql'),
            ),
            array(
                'niche' => 'BulkTest',
                'topic' => 'Bulk Topic 2',
                'score' => 85,
                'reason' => 'Test reason 2',
                'keywords' => array('bulk', 'test2'),
                'researched_at' => current_time('mysql'),
            ),
        );
        
        $result = $this->repository->create_bulk($topics);
        
        $this->assertTrue($result);
        
        $saved_topics = $this->repository->get_by_niche('BulkTest');
        $this->assertCount(2, $saved_topics);
    }
    
    /**
     * Test create_bulk skips entries with missing required fields.
     */
    public function test_create_bulk_skips_invalid_entries() {
        $topics = array(
            array(
                'niche' => 'ValidTest',
                'topic' => 'Valid Topic',
                'score' => 90,
            ),
            array(
                'niche' => 'ValidTest',
                // Missing 'topic' field - should be skipped
                'score' => 85,
            ),
            array(
                // Missing 'niche' field - should be skipped
                'topic' => 'Invalid Topic',
                'score' => 80,
            ),
        );
        
        $result = $this->repository->create_bulk($topics);
        
        $this->assertTrue($result);
        
        $saved_topics = $this->repository->get_by_niche('ValidTest');
        $this->assertCount(1, $saved_topics);
        $this->assertEquals('Valid Topic', $saved_topics[0]['topic']);
    }
    
    /**
     * Test create_bulk returns false for empty array.
     */
    public function test_create_bulk_empty_array() {
        $result = $this->repository->create_bulk(array());
        $this->assertFalse($result);
    }
    
    /**
     * Test delete_bulk deletes multiple topics by ID.
     */
    public function test_delete_bulk_deletes_multiple() {
        $id1 = $this->repository->create(array(
            'niche' => 'DeleteTest',
            'topic' => 'To Delete 1',
            'score' => 80,
        ));
        
        $id2 = $this->repository->create(array(
            'niche' => 'DeleteTest',
            'topic' => 'To Delete 2',
            'score' => 85,
        ));
        
        $id3 = $this->repository->create(array(
            'niche' => 'DeleteTest',
            'topic' => 'To Keep',
            'score' => 90,
        ));
        
        $result = $this->repository->delete_bulk(array($id1, $id2));
        
        $this->assertEquals(2, $result);
        
        // Explicitly pass days parameter for test clarity
        $remaining = $this->repository->get_by_niche('DeleteTest', 20, 1);
        $this->assertCount(1, $remaining);
        $this->assertEquals('To Keep', $remaining[0]['topic']);
    }
    
    /**
     * Test delete_bulk handles invalid/0 IDs by filtering them out.
     */
    public function test_delete_bulk_filters_invalid_ids() {
        $id1 = $this->repository->create(array(
            'niche' => 'FilterTest',
            'topic' => 'To Delete',
            'score' => 80,
        ));
        
        // Pass mix of valid and invalid IDs
        $result = $this->repository->delete_bulk(array($id1, 0, -5, 'invalid'));
        
        $this->assertEquals(1, $result);
        
        $topic = $this->repository->get_by_id($id1);
        $this->assertNull($topic);
    }
    
    /**
     * Test delete_bulk returns 0 for empty array.
     */
    public function test_delete_bulk_empty_array() {
        $result = $this->repository->delete_bulk(array());
        $this->assertEquals(0, $result);
    }
    
    /**
     * Test delete_bulk returns 0 when all IDs are invalid.
     */
    public function test_delete_bulk_all_invalid_ids() {
        $result = $this->repository->delete_bulk(array(0, -1, 'invalid'));
        $this->assertEquals(0, $result);
    }
}
