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

if (!trait_exists('AIPS_Cacheable_Repository')) {
    require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Schedule_Repository
 *
 * Repository pattern implementation for schedule data access.
 * Encapsulates all database operations related to scheduling.
 */
class AIPS_Schedule_Repository implements AIPS_Schedule_Repository_Interface {
    use AIPS_Cacheable_Repository;

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
     * Initialize the repository.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
    }
    
    /**
     * Get all schedules with optional template details.
     *
     * Results are cached with a medium-tier persistent cache and invalidated
     * whenever a schedule is created, updated, or deleted.
     *
     * @param bool $active_only Optional. Return only active schedules. Default false.
     * @return array Array of schedule objects with template names.
     */
    public function get_all($active_only = false) {
        $active_only = (bool) $active_only;
        return $this->cache_read(
            'schedules.get_all',
            array( 'active_only' => $active_only ),
            function() use ( $active_only ) {
                $where = $active_only ? "WHERE s.is_active = 1" : "";
                return $this->wpdb->get_results( "
                    SELECT s.*, t.name as template_name
                    FROM {$this->schedule_table} s
                    LEFT JOIN {$this->templates_table} t ON s.template_id = t.id
                    $where
                    ORDER BY s.next_run ASC
                " );
            }
        );
    }
    
    /**
     * Get a single schedule by ID.
     *
     * Non-null results are cached with a long-tier persistent cache.
     * Null results (record not found) are never cached.
     *
     * @param int $id Schedule ID.
     * @return object|null Schedule object or null if not found.
     */
    public function get_by_id($id) {
        $id = absint( $id );
        return $this->cache_read(
            'schedules.get_by_id',
            array( 'schedule_id' => $id ),
            function() use ( $id ) {
                return $this->wpdb->get_row( $this->wpdb->prepare(
                    "SELECT * FROM {$this->schedule_table} WHERE id = %d",
                    $id
                ) );
            }
        );
    }
    
    /**
     * Get schedules that are due to run.
     *
     * Results are cached at request scope only. The timestamp parameter
     * creates a unique cache key per second, so request-scoped caching
     * deduplicates calls within one cron invocation without polluting the
     * persistent cache with many near-identical time-keyed entries.
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
        $limit        = (int) $limit;
        return $this->cache_read(
            'schedules.get_due_schedules',
            array( 'current_time' => $current_time, 'limit' => $limit ),
            function() use ( $current_time, $limit ) {
                // Use INNER JOIN to ensure we only get schedules with valid active templates.
                // Select t.* first, then s.* to let schedule fields override template fields where they overlap,
                // but alias s.id as schedule_id to avoid confusion with template id.
                return $this->wpdb->get_results( $this->wpdb->prepare( "
                    SELECT t.*, s.*, s.id AS schedule_id
                    FROM {$this->schedule_table} s
                    INNER JOIN {$this->templates_table} t ON s.template_id = t.id
                    WHERE s.is_active = 1
                    AND s.next_run <= %d
                    AND t.is_active = 1
                    ORDER BY s.next_run ASC
                    LIMIT %d
                ", $current_time, $limit ) );
            }
        );
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
        $next_run = $this->normalize_datetime_input(isset($data['next_run']) ? $data['next_run'] : 0);

        $insert_data = array(
            'template_id' => absint($data['template_id']),
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
            'frequency' => sanitize_text_field($data['frequency']),
            'next_run' => $next_run,
            'is_active' => isset($data['is_active']) && 1 === absint($data['is_active']) ? 1 : 0,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
            'campaign_id' => !empty($data['campaign_id']) ? absint($data['campaign_id']) : null,
            'schedule_type' => isset($data['schedule_type']) ? sanitize_key($data['schedule_type']) : 'post_generation',
        );

        $format = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s');
        
        if (isset($data['article_structure_id'])) {
            $insert_data['article_structure_id'] = !empty($data['article_structure_id']) ? absint($data['article_structure_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['rotation_pattern'])) {
            $insert_data['rotation_pattern'] = !empty($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null;
            $format[] = '%s';
        }

        if (isset($data['author_id'])) {
            $insert_data['author_id'] = !empty($data['author_id']) ? absint($data['author_id']) : null;
            $format[] = '%d';
        }

        if (isset($data['campaign_mode'])) {
            $insert_data['campaign_mode'] = sanitize_key($data['campaign_mode']);
            $format[] = '%s';
        }

        if (array_key_exists('post_type_rules', $data)) {
            $insert_data['post_type_rules'] = !empty($data['post_type_rules']) ? wp_unslash($data['post_type_rules']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('blackout_dates', $data)) {
            $insert_data['blackout_dates'] = !empty($data['blackout_dates']) ? sanitize_textarea_field($data['blackout_dates']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('time_window_start', $data)) {
            $insert_data['time_window_start'] = !empty($data['time_window_start']) ? sanitize_text_field($data['time_window_start']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('time_window_end', $data)) {
            $insert_data['time_window_end'] = !empty($data['time_window_end']) ? sanitize_text_field($data['time_window_end']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('day_preferences', $data)) {
            $insert_data['day_preferences'] = !empty($data['day_preferences']) ? sanitize_text_field($data['day_preferences']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('season_end_date', $data)) {
            $insert_data['season_end_date'] = !empty($data['season_end_date']) ? absint($data['season_end_date']) : null;
            $format[] = '%d';
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
            $this->invalidate_cache_domain( 'schedule', array(), 'schedule_created' );
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
            $update_data['next_run'] = $this->normalize_datetime_input($data['next_run']);
            $format[] = '%d';
        }
        
        if (isset($data['last_run'])) {
            $update_data['last_run'] = $this->normalize_datetime_input($data['last_run']);
            $format[] = '%d';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
            $format[] = '%d';
        }
        
        if (isset($data['topic'])) {
            $update_data['topic'] = sanitize_text_field($data['topic']);
            $format[] = '%s';
        }

        if (array_key_exists('campaign_id', $data)) {
            $update_data['campaign_id'] = !empty($data['campaign_id']) ? absint($data['campaign_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['article_structure_id'])) {
            $update_data['article_structure_id'] = !empty($data['article_structure_id']) ? absint($data['article_structure_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['rotation_pattern'])) {
            $update_data['rotation_pattern'] = !empty($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null;
            $format[] = '%s';
        }

        if (isset($data['author_id'])) {
            $update_data['author_id'] = !empty($data['author_id']) ? absint($data['author_id']) : null;
            $format[] = '%d';
        }

        if (isset($data['campaign_mode'])) {
            $update_data['campaign_mode'] = sanitize_key($data['campaign_mode']);
            $format[] = '%s';
        }

        if (array_key_exists('post_type_rules', $data)) {
            $update_data['post_type_rules'] = !empty($data['post_type_rules']) ? wp_unslash($data['post_type_rules']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('blackout_dates', $data)) {
            $update_data['blackout_dates'] = !empty($data['blackout_dates']) ? sanitize_textarea_field($data['blackout_dates']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('time_window_start', $data)) {
            $update_data['time_window_start'] = !empty($data['time_window_start']) ? sanitize_text_field($data['time_window_start']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('time_window_end', $data)) {
            $update_data['time_window_end'] = !empty($data['time_window_end']) ? sanitize_text_field($data['time_window_end']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('day_preferences', $data)) {
            $update_data['day_preferences'] = !empty($data['day_preferences']) ? sanitize_text_field($data['day_preferences']) : null;
            $format[] = '%s';
        }

        if (array_key_exists('season_end_date', $data)) {
            $update_data['season_end_date'] = !empty($data['season_end_date']) ? absint($data['season_end_date']) : null;
            $format[] = '%d';
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
            $this->invalidate_cache_domain( 'schedule', array( 'schedule_id' => absint( $id ) ), 'schedule_updated' );
        }

		return $result !== false;
	}

	/**
	 * Atomically claim a due schedule by advancing next_run only when the row
	 * still has the expected due timestamp.
	 *
	 * This acts as a compare-and-swap lock for cron workers: only the first
	 * worker that updates the matching row gets to process the schedule.
	 *
	 * @param int $id Schedule ID.
	 * @param int $expected_next_run Previously-read next_run timestamp.
	 * @param int $new_next_run New next_run timestamp.
	 * @return bool True when the schedule was claimed, false otherwise.
	 */
	public function claim_due_schedule($id, $expected_next_run, $new_next_run) {
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->schedule_table}
				SET next_run = %d
				WHERE id = %d
				AND is_active = 1
				AND next_run = %d",
				(int) $new_next_run,
				(int) $id,
				(int) $expected_next_run
			)
		);

		if ($result !== false && $result > 0) {
			delete_transient('aips_pending_schedule_stats');
			$this->invalidate_cache_domain( 'schedule', array( 'schedule_id' => absint( $id ) ), 'schedule_claimed' );
			return true;
		}

		return false;
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
            $this->invalidate_cache_domain( 'schedule', array( 'schedule_id' => absint( $id ) ), 'schedule_deleted' );
        }

        return $result !== false;
    }

    /**
     * Count schedules owned by a campaign.
     *
     * @param int $campaign_id Campaign ID.
     * @return int
     */
    public function count_by_campaign($campaign_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->schedule_table} WHERE campaign_id = %d",
            absint($campaign_id)
        ));
    }

    /**
     * Return the subset of the given IDs that belong to a campaign.
     *
     * Uses a single IN() query instead of one get_by_id() call per ID,
     * avoiding the N+1 pattern in bulk-delete guards.
     *
     * @param int[] $ids Array of schedule IDs to check.
     * @return int[] Schedule IDs that have a non-NULL campaign_id.
     */
    public function get_campaign_owned_ids(array $ids) {
        $ids = array_filter(array_map('absint', $ids));

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $rows = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->schedule_table} WHERE id IN ($placeholders) AND campaign_id IS NOT NULL",
                $ids
            )
        );

        return array_map('intval', $rows);
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
            $this->invalidate_cache_domain( 'schedule', array(), 'schedule_template_deleted' );
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
            $this->invalidate_cache_domain( 'schedule', array( 'schedule_id' => absint( $id ) ), 'schedule_batch_progress_updated' );
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
                $this->normalize_datetime_input(isset($data['next_run']) ? $data['next_run'] : 0),
                isset($data['is_active']) ? (int) $data['is_active'] : 0,
                isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
                isset($data['article_structure_id']) ? absint($data['article_structure_id']) : null,
                isset($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null
            );
            $placeholders[] = "(%d, %s, %d, %d, %s, %d, %s)";
        }

        $query .= implode(', ', $placeholders);

        $result = $this->wpdb->query($this->wpdb->prepare($query, $values));

        if ($result) {
            delete_transient('aips_pending_schedule_stats');
            $this->invalidate_cache_domain( 'schedule', array(), 'schedule_bulk_created' );
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
            $this->invalidate_cache_domain( 'schedule', array(), 'schedule_bulk_deleted' );
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
            $this->invalidate_cache_domain( 'schedule', array(), 'schedule_bulk_active_updated' );
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
     * Results are cached with a medium-tier persistent cache and invalidated
     * whenever a schedule is created, updated, or deleted.
     *
     * @return array Array of schedule objects (template_id, next_run, frequency).
     */
    public function get_active_schedules() {
        return $this->cache_read(
            'schedules.get_active',
            array(),
            function() {
                return $this->wpdb->get_results(
                    "SELECT template_id, next_run, frequency FROM {$this->schedule_table} WHERE is_active = 1 ORDER BY template_id"
                );
            }
        );
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

    /**
     * Normalize legacy MySQL datetimes and timestamp-like values to UTC timestamps.
     *
     * The schedule schema stores bigint timestamps, but some older call sites and
     * tests still pass MySQL datetime strings.
     *
     * @param mixed $value Timestamp or datetime-like input.
     * @return int UTC Unix timestamp, or 0 when the value cannot be parsed.
     */
    private function normalize_datetime_input($value) {
        if ($value instanceof AIPS_DateTime) {
            return $value->timestamp();
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value)) {
            $parsed = AIPS_DateTime::fromMysqlOrNull(sanitize_text_field($value));
            if ($parsed !== null) {
                return $parsed->timestamp();
            }
        }

        return 0;
    }

    /**
     * Return the repository cache group for schedule reads.
     *
     * @return string
     */
    protected function repository_cache_group(): string {
        return 'aips_schedules';
    }

    /**
     * Return the explicit repository cache policies for schedule reads.
     *
     * @return array
     */
    protected function repository_cache_policies(): array {
        return array(
            'schedules.get_all'           => array(
                'tier'        => 'medium',
                'tags'        => array( 'schedules' ),
                'description' => 'Cache schedule list reads including active-only filtering.',
            ),
            'schedules.get_by_id'         => array(
                'tier'        => 'long',
                'tags'        => array( 'schedules', 'schedule:{schedule_id}' ),
                'cache_null'  => false,
                'description' => 'Cache single schedule reads by ID.',
            ),
            'schedules.get_due_schedules' => array(
                'tier'        => 'request',
                'tags'        => array( 'schedules' ),
                'description' => 'Request-scoped cache for due schedules; avoids persistent pollution from timestamp-keyed entries.',
            ),
            'schedules.get_active'        => array(
                'tier'        => 'medium',
                'tags'        => array( 'schedules' ),
                'description' => 'Cache active schedule list for scheduling-calculation callers.',
            ),
        );
    }
}
