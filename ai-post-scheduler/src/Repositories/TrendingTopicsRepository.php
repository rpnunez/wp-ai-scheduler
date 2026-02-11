<?php
namespace AIPS\Repositories;

/**
 * Trending Topics Repository
 *
 * Handles database operations for trending topics research data.
 * Stores, retrieves, and manages researched trending topics.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TrendingTopicsRepository
 *
 * Database access layer for trending topics research.
 * Provides CRUD operations and querying capabilities.
 */
class TrendingTopicsRepository {
    
    /**
     * @var wpdb WordPress database instance
     */
    private $wpdb;
    
    /**
     * @var string Trending topics table name
     */
    private $table_name;
    
    /**
     * Initialize the repository.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_trending_topics';
    }
    
    /**
     * Get all trending topics with optional filtering.
     *
     * @param array $args {
     *     Optional query arguments.
     *
     *     @type string $niche         Filter by niche.
     *     @type int    $min_score     Minimum relevance score.
     *     @type string $order_by      Order by column (default 'score').
     *     @type string $order         Order direction (default 'DESC').
     *     @type int    $limit         Number of results (default 50).
     *     @type int    $offset        Results offset (default 0).
     *     @type string $date_from     Start date for filtering.
     *     @type bool   $fresh_only    Only return fresh topics (default false).
     * }
     * @return array Array of trending topic records.
     */
    public function get_all($args = array()) {
        $defaults = array(
            'niche' => '',
            'min_score' => 0,
            'order_by' => 'score',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
            'date_from' => '',
            'fresh_only' => false,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $prepare_values = array();
        
        // Filter by niche
        if (!empty($args['niche'])) {
            $where[] = 'niche = %s';
            $prepare_values[] = $args['niche'];
        }
        
        // Filter by minimum score
        if ($args['min_score'] > 0) {
            $where[] = 'score >= %d';
            $prepare_values[] = $args['min_score'];
        }
        
        // Filter by date
        if (!empty($args['date_from'])) {
            $where[] = 'researched_at >= %s';
            $prepare_values[] = $args['date_from'];
        }
        
        // Filter fresh topics only (researched within last 7 days)
        if ($args['fresh_only']) {
            $where[] = 'researched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Validate order_by column
        $allowed_order_by = array('score', 'researched_at', 'topic', 'niche');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'score';
        
        // Validate order direction
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
        $prepare_values[] = absint($args['limit']);
        $prepare_values[] = absint($args['offset']);
        
        if (!empty($prepare_values)) {
            $query = $this->wpdb->prepare($query, $prepare_values);
        }
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get a single trending topic by ID.
     *
     * @param int $id Topic ID.
     * @return array|null Topic data or null if not found.
     */
    public function get_by_id($id) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        return $this->wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get trending topics for a specific niche.
     *
     * @param string $niche  Niche name.
     * @param int    $limit  Number of topics to return.
     * @param int    $days   Only include topics researched within N days.
     * @return array Array of topic records.
     */
    public function get_by_niche($niche, $limit = 20, $days = 30) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE niche = %s 
            AND researched_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY score DESC, researched_at DESC 
            LIMIT %d",
            $niche,
            $days,
            $limit
        );
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get top trending topics across all niches.
     *
     * @param int $count Number of top topics to return.
     * @param int $days  Only consider topics from last N days.
     * @return array Array of top topic records.
     */
    public function get_top_topics($count = 10, $days = 7) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE researched_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY score DESC, researched_at DESC 
            LIMIT %d",
            $days,
            $count
        );
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Search topics by keyword.
     *
     * @param string $keyword Search term.
     * @param int    $limit   Number of results.
     * @return array Array of matching topics.
     */
    public function search($keyword, $limit = 20) {
        $search_term = '%' . $this->wpdb->esc_like($keyword) . '%';
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE topic LIKE %s OR keywords LIKE %s OR niche LIKE %s
            ORDER BY score DESC 
            LIMIT %d",
            $search_term,
            $search_term,
            $search_term,
            $limit
        );
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Save researched topics to database.
     *
     * @param array $topics Array of topic data to save.
     * @param string $niche Niche these topics belong to.
     * @return int|false Number of topics inserted (may be less than input count if some are skipped due to validation or duplicates), or false on database error or empty input.
     */
    public function save_research_batch($topics, $niche) {
        if (empty($topics) || !is_array($topics)) {
            return false;
        }
        
        // Extract topic titles for duplicate checking
        $topic_titles = array_column($topics, 'topic');
        if (empty($topic_titles)) {
            return 0;
        }

        // Check for existing topics in this niche within last 7 days to prevent duplicates
        $placeholders = implode(',', array_fill(0, count($topic_titles), '%s'));
        $query = $this->wpdb->prepare(
            "SELECT topic FROM {$this->table_name}
            WHERE niche = %s
            AND researched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND topic IN ($placeholders)",
            array_merge(array($niche), $topic_titles)
        );

        $existing_topics = $this->wpdb->get_col($query);
        $existing_topics = $existing_topics ? array_map('strtolower', $existing_topics) : array();

        $batch_data = array();
        
        foreach ($topics as $topic) {
            // Skip duplicates
            if (in_array(strtolower($topic['topic']), $existing_topics)) {
                continue;
            }

            // Add to existing list to prevent duplicates within the batch itself
            $existing_topics[] = strtolower($topic['topic']);

            $batch_data[] = array(
                'niche' => $niche,
                'topic' => $topic['topic'],
                'score' => $topic['score'],
                'reason' => isset($topic['reason']) ? $topic['reason'] : '',
                'keywords' => isset($topic['keywords']) ? $topic['keywords'] : array(),
                'researched_at' => isset($topic['researched_at']) ? $topic['researched_at'] : current_time('mysql'),
            );
        }
        
        if (empty($batch_data)) {
            return 0;
        }

        $result = $this->create_bulk($batch_data);

        return $result ? count($batch_data) : false;
    }

    /**
     * Create multiple trending topic records in a single query.
     *
     * @param array $topics Array of topic data arrays.
     * @return bool True on success, false on failure.
     */
    public function create_bulk($topics) {
        if (empty($topics)) {
            return false;
        }

        $values = array();
        $placeholders = array();
        $query = "INSERT INTO {$this->table_name} (niche, topic, score, reason, keywords, researched_at) VALUES ";

        foreach ($topics as $data) {
            // Validate required fields.
            $niche = isset($data['niche']) ? sanitize_text_field($data['niche']) : '';
            $topic = isset($data['topic']) ? sanitize_text_field($data['topic']) : '';

            if ($niche === '' || $topic === '') {
                // Skip entries without a valid niche or topic.
                continue;
            }

            $score = isset($data['score']) ? absint($data['score']) : 0;
            $reason = isset($data['reason']) ? sanitize_text_field($data['reason']) : '';

            if (isset($data['keywords'])) {
                $keywords_value = $data['keywords'];
                $keywords_json = is_array($keywords_value)
                    ? wp_json_encode($keywords_value)
                    : $keywords_value;
            } else {
                $keywords_json = '[]';
            }

            $researched_at = isset($data['researched_at']) ? $data['researched_at'] : current_time('mysql');

            array_push(
                $values,
                $niche,
                $topic,
                $score,
                $reason,
                $keywords_json,
                $researched_at
            );
            $placeholders[] = "(%s, %s, %d, %s, %s, %s)";
        }

        if (empty($placeholders)) {
            // No valid rows to insert.
            return false;
        }
        $query .= implode(', ', $placeholders);

        $result = $this->wpdb->query($this->wpdb->prepare($query, $values));

        return $result !== false;
    }
    
    /**
     * Create a new trending topic record.
     *
     * @param array $data {
     *     Topic data.
     *
     *     @type string $niche         Niche/industry.
     *     @type string $topic         Topic title.
     *     @type int    $score         Relevance score (1-100).
     *     @type string $reason        Why it's trending.
     *     @type array  $keywords      Related keywords.
     *     @type string $researched_at Research timestamp.
     * }
     * @return int|false Insert ID on success, false on failure.
     */
    public function create($data) {
        $defaults = array(
            'niche' => '',
            'topic' => '',
            'score' => 50,
            'reason' => '',
            'keywords' => array(),
            'researched_at' => current_time('mysql'),
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['topic']) || empty($data['niche'])) {
            return false;
        }
        
        // Prepare keywords as JSON
        $keywords_json = is_array($data['keywords']) 
            ? wp_json_encode($data['keywords']) 
            : '[]';
        
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'niche' => sanitize_text_field($data['niche']),
                'topic' => sanitize_text_field($data['topic']),
                'score' => absint($data['score']),
                'reason' => sanitize_text_field($data['reason']),
                'keywords' => $keywords_json,
                'researched_at' => $data['researched_at'],
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update an existing trending topic record.
     *
     * @param int   $id   Topic ID.
     * @param array $data Updated data fields.
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $allowed_fields = array('topic', 'score', 'reason', 'keywords', 'niche');
        $update_data = array();
        $format = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'keywords') {
                    $update_data[$field] = is_array($data[$field]) 
                        ? wp_json_encode($data[$field]) 
                        : $data[$field];
                    $format[] = '%s';
                } elseif ($field === 'score') {
                    $update_data[$field] = absint($data[$field]);
                    $format[] = '%d';
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                    $format[] = '%s';
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a trending topic record.
     *
     * @param int $id Topic ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete all topics for a specific niche.
     *
     * @param string $niche Niche name.
     * @return int|false Number of deleted records, or false on failure.
     */
    public function delete_by_niche($niche) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('niche' => $niche),
            array('%s')
        );
        
        return $result;
    }
    
    /**
     * Delete multiple trending topics by ID.
     *
     * @param array $ids Array of topic IDs.
     * @return int|false Number of deleted records, or false on failure.
     */
    public function delete_bulk($ids) {
        if (empty($ids)) {
            return 0;
        }

        // Sanitize IDs and remove any invalid (zero) values
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
            $ids
        );

        return $this->wpdb->query($query);
    }

    /**
     * Delete old research data.
     *
     * @param int $days Delete topics older than N days.
     * @return int|false Number of deleted records, or false on failure.
     */
    public function delete_old_topics($days = 30) {
        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE researched_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        
        return $this->wpdb->query($query);
    }
    
    /**
     * Get statistics about researched topics.
     *
     * @return array Statistics data.
     */
    public function get_stats() {
        $stats = array(
            'total_topics' => 0,
            'niches_count' => 0,
            'avg_score' => 0,
            'recent_research_count' => 0,
        );
        
        // Total topics
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $stats['total_topics'] = absint($total);
        
        // Unique niches
        $niches = $this->wpdb->get_var("SELECT COUNT(DISTINCT niche) FROM {$this->table_name}");
        $stats['niches_count'] = absint($niches);
        
        // Average score
        $avg = $this->wpdb->get_var("SELECT AVG(score) FROM {$this->table_name}");
        $stats['avg_score'] = round(floatval($avg), 2);
        
        // Recent research (last 7 days)
        $recent = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE researched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stats['recent_research_count'] = absint($recent);
        
        return $stats;
    }
    
    /**
     * Get statistics for a specific niche.
     *
     * @param string $niche Niche name.
     * @return array Niche-specific statistics.
     */
    public function get_niche_stats($niche) {
        $stats = array(
            'topic_count' => 0,
            'avg_score' => 0,
            'highest_score' => 0,
            'latest_research' => null,
        );
        
        $query = $this->wpdb->prepare(
            "SELECT 
                COUNT(*) as topic_count,
                AVG(score) as avg_score,
                MAX(score) as highest_score,
                MAX(researched_at) as latest_research
            FROM {$this->table_name}
            WHERE niche = %s",
            $niche
        );
        
        $result = $this->wpdb->get_row($query, ARRAY_A);
        
        if ($result) {
            $stats['topic_count'] = absint($result['topic_count']);
            $stats['avg_score'] = round(floatval($result['avg_score']), 2);
            $stats['highest_score'] = absint($result['highest_score']);
            $stats['latest_research'] = $result['latest_research'];
        }
        
        return $stats;
    }
    
    /**
     * Check if a topic already exists.
     *
     * Prevents duplicate research entries.
     *
     * @param string $topic Topic title.
     * @param string $niche Niche name.
     * @param int    $days  Consider duplicates within N days.
     * @return bool True if exists, false otherwise.
     */
    public function topic_exists($topic, $niche, $days = 7) {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE topic = %s 
            AND niche = %s
            AND researched_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $topic,
            $niche,
            $days
        );
        
        $count = $this->wpdb->get_var($query);
        
        return absint($count) > 0;
    }
    
    /**
     * Get list of all niches with topic counts.
     *
     * @return array Array of niches with counts.
     */
    public function get_niche_list() {
        $query = "SELECT niche, COUNT(*) as count 
                  FROM {$this->table_name} 
                  GROUP BY niche 
                  ORDER BY count DESC";
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
}
