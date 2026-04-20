<?php
/**
 * Schedule Repository
 *
 * Database abstraction layer for schedule operations.
 * Provides a clean interface for CRUD operations on the schedule table.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Schedule_Repository
 *
 * Repository pattern implementation for schedule data access.
 * Encapsulates all database operations related to scheduling.
 */
class AIPS_Schedule_Repository implements AIPS_Schedule_Repository_Interface {

    /**
     * @var self|null Singleton instance.
     */
    private static $instance = null;

    /**
     * Get the shared singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @var string The schedule table name (with prefix)
     */
    private $schedule_table;
    
    /**
     * @var string The templates table name (with prefix)
     */
    private $templates_table;
    
    /**
     * @var wpdb WordPress database abstraction object
     */
    private $wpdb;

    /**
     * @var AIPS_Cache In-request identity-map cache.
     */
    private $cache = null;
    
    /**
     * Initialize the repository.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->cache = AIPS_Cache_Factory::named( 'aips_schedule_repository' );
    }
    
    /**
     * Get all schedules with optional template details.
     *
     * Results are cached for the duration of the request so repeat calls
     * within the same request do not issue additional DB queries.
     *
     * @param bool $active_only Optional. Return only active schedules. Default false.
     * @return array Array of schedule objects with template names.
     */
    public function get_all($active_only = false) {
        $key = 'all:' . ( $active_only ? '1' : '0' );
        if ( $this->cache->has( $key ) ) {
            return $this->cache->get( $key );
        }
        $where  = $active_only ? "WHERE s.is_active = 1" : "";
        $result = $this->wpdb->get_results( "
            SELECT s.*, t.name as template_name 
            FROM {$this->schedule_table} s 
            LEFT JOIN {$this->templates_table} t ON s.template_id = t.id 
            $where
            ORDER BY s.next_run ASC
        " );
        $this->cache->set( $key, $result );
        return $result;
    }
    
    /**
     * Get a single schedule by ID.
     *
     * Non-null results are cached for the duration of the request.
     *
     * @param int $id Schedule ID.
     * @return object|null Schedule object or null if not found.
     */
    public function get_by_id($id) {
        $key = 'id:' . (int) $id;
        if ( $this->cache->has( $key ) ) {
            return $this->cache->get( $key );
        }
        $result = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->schedule_table} WHERE id = %d",
            $id
        ) );
        if ( $result !== null ) {
            $this->cache->set( $key, $result );
        }
        return $result;
    }
    
    /**
     * Get schedules that are due to run.
     *
     * Results are cached for the duration of the request. The cache is
     * invalidated whenever any schedule is mutated, so re-running the
     * scheduler within the same request correctly picks up the updated
     * next_run timestamps.
     *
     * @param int  $current_time Optional. UTC Unix timestamp. Default current time.
     * @param int  $limit        Optional. Maximum number of schedules to retrieve. Default 5.
     * @return array Array of schedule objects that should run now.
     */
    public function get_due_schedules($current_time = null, $limit = 5) {
        if ($current_time === null) {
            $current_time = AIPS_DateTime::now()->timestamp();
        }
        $current_time = (int) $current_time;
        $key = 'due:' . $current_time . ':' . (int) $limit;
        if ( $this->cache->has( $key ) ) {
            return $this->cache->get( $key );
        }
        // Use INNER JOIN to ensure we only get schedules with valid active templates.
        // Select t.* first, then s.* to let schedule fields override template fields where they overlap,
        // but alias s.id as schedule_id to avoid confusion with template id.
        $result = $this->wpdb->get_results( $this->wpdb->prepare( "
            SELECT t.*, s.*, s.id AS schedule_id
            FROM {$this->schedule_table} s 
            INNER JOIN {$this->templates_table} t ON s.template_id = t.id
            WHERE s.is_active = 1 
            AND s.next_run <= %d
            AND t.is_active = 1
            ORDER BY s.next_run ASC
            LIMIT %d
        ", $current_time, $limit ) );
        $this->cache->set( $key, $result );
        return $result;
    }

    /**
     * Get upcoming active schedules.
     *
     * @param int $limit Number of schedules to retrieve. Default 5.
     * @return array Array of schedule objects with template names.
     */
    public function get_upcoming($limit = 5) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT s.*, t.name as template_name
            FROM {$this->schedule_table} s
            LEFT JOIN {$this->templates_table} t ON s.template_id = t.id
            WHERE s.is_active = 1
            ORDER BY s.next_run ASC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get schedules by template ID.
     *
     * @param int $template_id Template ID.
     * @return array Array of schedule objects for this template.
     */
    public function get_by_template($template_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT * FROM {$this->schedule_table} WHERE template_id = %d ORDER BY next_run ASC
        ", $template_id));
    }
    
    /**
     * Create a new schedule.
     *
     * @param array $data {
     *     Schedule data.
     *
     *     @type int    $template_id           Template ID.
     *     @type int    $article_structure_id  Optional article structure ID.
     *     @type string $rotation_pattern      Optional rotation pattern (sequential, random, weighted, alternating).
     *     @type string $frequency             Frequency identifier (daily, weekly, etc.).
     *     @type string $next_run              Next run datetime in MySQL format.
     *     @type int    $is_active             Active status (1 or 0).
     *     @type string $topic                 Optional topic for generation.
     *     @type string $schedule_type         Optional schedule type discriminator (default: post_generation).
     *     @type string $circuit_state         Optional circuit-breaker state (open|half_open|closed). Defaults to 'closed'.
     *     @type string $run_state             Optional JSON string capturing current run outcome. Defaults to NULL.
     *     @type string $batch_progress        Optional JSON string for resumable batch cursor. Defaults to NULL.
     * }
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        $insert_data = array(
            'template_id' => absint($data['template_id']),
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
            'frequency' => sanitize_text_field($data['frequency']),
            'next_run' => sanitize_text_field($data['next_run']),
            'is_active' => isset($data['is_active']) && 1 === absint($data['is_active']) ? 1 : 0,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
            'schedule_type' => isset($data['schedule_type']) ? sanitize_key($data['schedule_type']) : 'post_generation',
        );
        
        $format = array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s');
        
        if (isset($data['article_structure_id'])) {
            $insert_data['article_structure_id'] = !empty($data['article_structure_id']) ? absint($data['article_structure_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['rotation_pattern'])) {
            $insert_data['rotation_pattern'] = !empty($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null;
            $format[] = '%s';
        }

        if (isset($data['circuit_state'])) {
            $allowed_circuit_states = array('open', 'half_open', 'closed');
            $state = sanitize_key($data['circuit_state']);
            $insert_data['circuit_state'] = in_array($state, $allowed_circuit_states, true) ? $state : 'closed';
            $format[] = '%s';
        }

        if (array_key_exists('run_state', $data)) {
            $insert_data['run_state'] = !empty($data['run_state']) ? wp_unslash($data['run_state']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('batch_progress', $data)) {
            $insert_data['batch_progress'] = !empty($data['batch_progress']) ? wp_unslash($data['batch_progress']) : null;
            $format[] = '%s';
        }
        
        $result = $this->wpdb->insert($this->schedule_table, $insert_data, $format);
        
        if ($result) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update an existing schedule.
     *
     * @param int   $id   Schedule ID.
     * @param array $data Data to update (same structure as create).
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $update_data = array();
        $format = array();
        
        if (isset($data['template_id'])) {
            $update_data['template_id'] = absint($data['template_id']);
            $format[] = '%d';
        }
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
            $format[] = '%s';
        }
        
        if (isset($data['frequency'])) {
            $update_data['frequency'] = sanitize_text_field($data['frequency']);
            $format[] = '%s';
        }
        
        if (isset($data['next_run'])) {
            $update_data['next_run'] = sanitize_text_field($data['next_run']);
            $format[] = '%s';
        }
        
        if (isset($data['last_run'])) {
            $update_data['last_run'] = sanitize_text_field($data['last_run']);
            $format[] = '%s';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
            $format[] = '%d';
        }
        
        if (isset($data['topic'])) {
            $update_data['topic'] = sanitize_text_field($data['topic']);
            $format[] = '%s';
        }
        
        if (isset($data['article_structure_id'])) {
            $update_data['article_structure_id'] = !empty($data['article_structure_id']) ? absint($data['article_structure_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['rotation_pattern'])) {
            $update_data['rotation_pattern'] = !empty($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }

        if (isset($data['schedule_history_id'])) {
            $update_data['schedule_history_id'] = !empty($data['schedule_history_id']) ? absint($data['schedule_history_id']) : null;
            $format[] = '%d';
        }

        if (isset($data['schedule_type'])) {
            $update_data['schedule_type'] = sanitize_key($data['schedule_type']);
            $format[] = '%s';
        }

        if (isset($data['circuit_state'])) {
            $allowed_circuit_states = array('open', 'half_open', 'closed');
            $state = sanitize_key($data['circuit_state']);
            $update_data['circuit_state'] = in_array($state, $allowed_circuit_states, true) ? $state : 'closed';
            $format[] = '%s';
        }

        if (array_key_exists('run_state', $data)) {
            $update_data['run_state'] = !empty($data['run_state']) ? wp_unslash($data['run_state']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('batch_progress', $data)) {
            $update_data['batch_progress'] = !empty($data['batch_progress']) ? wp_unslash($data['batch_progress']) : null;
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->schedule_table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        if ($result !== false) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result !== false;
    }
    
    /**
     * Delete a schedule by ID.
     *
     * @param int $id Schedule ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete($this->schedule_table, array('id' => $id), array('%d'));

        if ($result !== false) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result !== false;
    }
    
    /**
     * Delete all schedules for a template.
     *
     * @param int $template_id Template ID.
     * @return int|false Number of rows affected or false on failure.
     */
    public function delete_by_template($template_id) {
        $result = $this->wpdb->delete($this->schedule_table, array('template_id' => $template_id), array('%d'));

        if ($result !== false) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result;
    }
    
    /**
     * Update the last_run timestamp for a schedule.
     *
     * @param int      $id        Schedule ID.
     * @param int|null $timestamp Optional. UTC Unix timestamp. Default current time.
     * @return bool True on success, false on failure.
     */
    public function update_last_run($id, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = AIPS_DateTime::now()->timestamp();
        }
        
        return $this->update($id, array('last_run' => (int) $timestamp));
    }
    
    /**
     * Update the next_run timestamp for a schedule.
     *
     * @param int $id        Schedule ID.
     * @param int $timestamp UTC Unix timestamp.
     * @return bool True on success, false on failure.
     */
    public function update_next_run($id, $timestamp) {
        return $this->update($id, array('next_run' => (int) $timestamp));
    }
    
    /**
     * Toggle schedule active status.
     *
     * @param int  $id        Schedule ID.
     * @param bool $is_active Active status.
     * @return bool True on success, false on failure.
     */
    public function set_active($id, $is_active) {
        return $this->update($id, array('is_active' => $is_active));
    }

    /**
     * Persist batch progress for a schedule.
     *
     * Records how many posts in a multi-post batch have been generated so
     * that an interrupted run can resume from the correct index on the next
     * cron invocation.
     *
     * Storing the generated post IDs in the cursor makes resumption more
     * robust: if the process crashes after a post is created but before this
     * method runs, the next cron tick uses `count(post_ids)` as the
     * authoritative completed count and pre-populates the post-ID list, so
     * the batch resumes from the right position without creating duplicates.
     *
     * This method writes directly to the DB without invalidating the
     * `aips_pending_schedule_stats` transient because it is called once per
     * successfully generated post and the transient is not affected by
     * in-flight progress data.
     *
     * @param int   $id        Schedule ID.
     * @param int   $completed Number of posts successfully generated so far.
     * @param int   $total     Total posts expected for this batch.
     * @param int   $last_index Zero-based index of the last successfully generated post.
     * @param array $post_ids  IDs of all posts generated so far (prior runs + current session).
     * @return bool True on success, false on failure.
     */
    public function update_batch_progress($id, $completed, $total, $last_index, $post_ids = array()) {
        $progress = wp_json_encode(array(
            'completed'  => absint($completed),
            'total'      => absint($total),
            'last_index' => absint($last_index),
            'post_ids'   => array_values(array_map('absint', $post_ids)),
        ));
        $result = $this->wpdb->update(
            $this->schedule_table,
            array('batch_progress' => $progress),
            array('id' => absint($id)),
            array('%s'),
            array('%d')
        );
        if ( $result !== false ) {
            $this->cache->flush();
        }
        return $result !== false;
    }

    /**
     * Clear batch progress for a schedule once a batch finishes successfully.
     *
     * @param int $id Schedule ID.
     * @return bool True on success, false on failure.
     */
    public function clear_batch_progress($id) {
        return $this->update($id, array('batch_progress' => null));
    }

    /**
     * Store the current run state for a schedule as a structured JSON object.
     *
     * Captures the outcome of the most recent run attempt including success/failure
     * status, post counts, and any error details.  Replaces the single `last_error`
     * text field so callers can store richer context (e.g. partial successes, error
     * codes, timestamps) that can drive future circuit-breaker logic.
     *
     * @param int   $id    Schedule ID.
     * @param array $state Associative array to serialise as JSON.
     *                     Recommended keys:
     *                       - 'status'      string  'success' | 'partial' | 'failed'
     *                       - 'error_code'  string  WP_Error error code, if any
     *                       - 'error_message' string Human-readable error text, if any
     *                       - 'completed'   int     Posts successfully generated
     *                       - 'total'       int     Posts requested for this run
     *                       - 'timestamp'   string  ISO-8601 timestamp of this state capture
     * @return bool True on success, false on failure.
     */
    public function update_run_state($id, array $state) {
        return $this->update($id, array('run_state' => wp_json_encode($state)));
    }

    /**
     * Create multiple schedules in a single query.
     *
     * @param array $schedules Array of schedule data arrays.
     * @return int Number of rows inserted.
     */
    public function create_bulk($schedules) {
        if (empty($schedules)) {
            return 0;
        }

        $values = array();
        $placeholders = array();
        $query = "INSERT INTO {$this->schedule_table} (template_id, frequency, next_run, is_active, topic, article_structure_id, rotation_pattern) VALUES ";

        foreach ($schedules as $data) {
            array_push($values,
                absint($data['template_id']),
                sanitize_text_field($data['frequency']),
                sanitize_text_field($data['next_run']),
                isset($data['is_active']) ? (int) $data['is_active'] : 0,
                isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
                isset($data['article_structure_id']) ? absint($data['article_structure_id']) : null,
                isset($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null
            );
            $placeholders[] = "(%d, %s, %s, %d, %s, %d, %s)";
        }

        $query .= implode(', ', $placeholders);

        $result = $this->wpdb->query($this->wpdb->prepare($query, $values));

        if ($result) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result;
    }
    
    /**
     * Delete multiple schedules by ID.
     *
     * @param int[] $ids Array of schedule IDs to delete.
     * @return int Number of rows deleted, or false on failure.
     */
    public function delete_bulk(array $ids) {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->schedule_table} WHERE id IN ($placeholders)",
                $ids
            )
        );

        if ($result !== false) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result;
    }

    /**
     * Set the active status for multiple schedules.
     *
     * @param int[] $ids       Array of schedule IDs.
     * @param int   $is_active 1 to activate, 0 to pause.
     * @return int|false Number of rows updated, or false on failure.
     */
    public function set_active_bulk(array $ids, $is_active) {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $is_active = $is_active ? 1 : 0;
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $query_args = array_merge(array($is_active), $ids);
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->schedule_table} SET is_active = %d WHERE id IN ($placeholders)",
                $query_args
            )
        );

        if ($result !== false) {
            delete_transient('aips_pending_schedule_stats');
            $this->cache->flush();
        }

        return $result;
    }

    /**
     * Get post count for a set of schedule IDs (sum of template post_quantity).
     *
     * Each schedule runs once and generates as many posts as its template's
     * post_quantity setting specifies (minimum 1).
     *
     * @param int[] $ids Array of schedule IDs.
     * @return int Total number of posts that would be generated.
     */
    public function get_post_count_for_schedules(array $ids) {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(COALESCE(NULLIF(t.post_quantity, 0), 1))
                 FROM {$this->schedule_table} s
                 LEFT JOIN {$this->templates_table} t ON s.template_id = t.id
                 WHERE s.id IN ($placeholders)",
                $ids
            )
        );

        return (int) $result;
    }

    /**
     * Get all active schedules.
     *
     * Returns schedules with only the columns needed for schedule calculations
     * (template_id, next_run, frequency), ordered by template_id.
     *
     * Results are cached for the duration of the request.
     *
     * @return array Array of schedule objects (template_id, next_run, frequency).
     */
    public function get_active_schedules() {
        $key = 'active_schedules';
        if ( $this->cache->has( $key ) ) {
            return $this->cache->get( $key );
        }
        $result = $this->wpdb->get_results(
            "SELECT template_id, next_run, frequency FROM {$this->schedule_table} WHERE is_active = 1 ORDER BY template_id"
        );
        $this->cache->set( $key, $result );
        return $result;
    }

    /**
     * Get active schedules for a specific template.
     *
     * @param int $template_id Template ID.
     * @return array Array of active schedule objects for this template.
     */
    public function get_active_schedules_by_template($template_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->schedule_table} WHERE template_id = %d AND is_active = 1",
            absint($template_id)
        ));
    }

    /**
     * Count schedules by status.
     *
     * @return array {
     *     @type int $total  Total number of schedules.
     *     @type int $active Number of active schedules.
     * }
     */
    public function count_by_status() {
        $results = $this->wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            FROM {$this->schedule_table}
        ");
        
        return array(
            'total' => isset($results->total) ? (int) $results->total : 0,
            'active' => isset($results->active) ? (int) $results->active : 0,
        );
    }
}
