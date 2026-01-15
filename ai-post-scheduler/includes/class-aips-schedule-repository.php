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
class AIPS_Schedule_Repository {
    
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
     * @param bool $active_only Optional. Return only active schedules. Default false.
     * @return array Array of schedule objects with template names.
     */
    public function get_all($active_only = false) {
        $where = $active_only ? "WHERE s.is_active = 1" : "";
        
        return $this->wpdb->get_results("
            SELECT s.*, t.name as template_name 
            FROM {$this->schedule_table} s 
            LEFT JOIN {$this->templates_table} t ON s.template_id = t.id 
            $where
            ORDER BY s.next_run ASC
        ");
    }
    
    /**
     * Get a single schedule by ID.
     *
     * @param int $id Schedule ID.
     * @return object|null Schedule object or null if not found.
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->schedule_table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get schedules that are due to run.
     *
     * @param string $current_time Optional. Current time in MySQL format. Default current time.
     * @return array Array of schedule objects that should run now.
     */
    public function get_due_schedules($current_time = null) {
        if ($current_time === null) {
            $current_time = current_time('mysql');
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT s.*, t.name as template_name 
            FROM {$this->schedule_table} s 
            LEFT JOIN {$this->templates_table} t ON s.template_id = t.id 
            WHERE s.is_active = 1 
            AND s.next_run <= %s
            ORDER BY s.next_run ASC
        ", $current_time));
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
     * }
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        $insert_data = array(
            'template_id' => absint($data['template_id']),
            'frequency' => sanitize_text_field($data['frequency']),
            'next_run' => sanitize_text_field($data['next_run']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
        );
        
        $format = array('%d', '%s', '%s', '%d', '%s', '%s');
        
        if (isset($data['article_structure_id'])) {
            $insert_data['article_structure_id'] = !empty($data['article_structure_id']) ? absint($data['article_structure_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['rotation_pattern'])) {
            $insert_data['rotation_pattern'] = !empty($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null;
            $format[] = '%s';
        }
        
        $result = $this->wpdb->insert($this->schedule_table, $insert_data, $format);
        
        if ($result) {
            delete_transient('aips_pending_schedule_stats');
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
        }

        return $result;
    }
    
    /**
     * Update the last_run timestamp for a schedule.
     *
     * @param int    $id        Schedule ID.
     * @param string $timestamp Optional. Timestamp in MySQL format. Default current time.
     * @return bool True on success, false on failure.
     */
    public function update_last_run($id, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = current_time('mysql');
        }
        
        return $this->update($id, array('last_run' => $timestamp));
    }
    
    /**
     * Update the next_run timestamp for a schedule.
     *
     * @param int    $id        Schedule ID.
     * @param string $timestamp Timestamp in MySQL format.
     * @return bool True on success, false on failure.
     */
    public function update_next_run($id, $timestamp) {
        return $this->update($id, array('next_run' => $timestamp));
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
        }

        return $result;
    }
    
    /**
     * Update the next_run timestamp for a schedule atomically.
     *
     * Only updates if the current next_run matches the expected old_next_run.
     * This provides optimistic locking for concurrent processing.
     *
     * @param int    $id           Schedule ID.
     * @param string $new_next_run New timestamp in MySQL format.
     * @param string $old_next_run Expected old timestamp in MySQL format.
     * @return bool True if updated, false if not (e.g. record changed or not found).
     */
    public function update_next_run_atomic($id, $new_next_run, $old_next_run) {
        $result = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->schedule_table} SET next_run = %s WHERE id = %d AND next_run = %s",
            $new_next_run,
            $id,
            $old_next_run
        ));

        if ($result) {
            delete_transient('aips_pending_schedule_stats');
        }

        return $result > 0;
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
            'total' => (int) $results->total,
            'active' => (int) $results->active,
        );
    }
}
