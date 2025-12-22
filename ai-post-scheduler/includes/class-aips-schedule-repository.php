<?php
/**
 * Schedule Repository
 *
 * Handles all database operations for schedule records.
 * Provides query optimization and abstraction from direct $wpdb usage.
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
 * Repository for managing schedules in the database.
 * Encapsulates all SQL queries and database operations for schedule records.
 */
class AIPS_Schedule_Repository extends AIPS_Base_Repository {
    
    /**
     * Initialize the schedule repository.
     */
    public function __construct() {
        parent::__construct('aips_schedule');
    }
    
    /**
     * Get all schedules with template information.
     *
     * @return array Array of schedule objects with template names.
     */
    public function get_all_schedules() {
        $templates_table = $this->wpdb->prefix . 'aips_templates';
        
        return $this->wpdb->get_results("
            SELECT s.*, t.name as template_name 
            FROM {$this->table_name} s 
            LEFT JOIN {$templates_table} t ON s.template_id = t.id 
            ORDER BY s.next_run ASC
        ");
    }
    
    /**
     * Get schedules that are due for execution.
     *
     * Returns active schedules where next_run is in the past and the template is active.
     *
     * @param string $current_time Optional. Current time in MySQL format. Defaults to current_time('mysql').
     * @return array Array of schedule objects with full template data.
     */
    public function get_due_schedules($current_time = null) {
        if ($current_time === null) {
            $current_time = current_time('mysql');
        }
        
        $templates_table = $this->wpdb->prefix . 'aips_templates';
        
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT s.id AS schedule_id, s.*, t.*
            FROM {$this->table_name} s 
            INNER JOIN {$templates_table} t ON s.template_id = t.id 
            WHERE s.is_active = 1 
            AND s.next_run <= %s 
            AND t.is_active = 1
            ORDER BY s.next_run ASC
        ", $current_time));
    }
    
    /**
     * Save a schedule (create or update).
     *
     * @param array $data Schedule data.
     * @return int|false Schedule ID or false on failure.
     */
    public function save($data) {
        $schedule_data = array(
            'template_id' => absint($data['template_id']),
            'frequency' => sanitize_text_field($data['frequency']),
            'next_run' => sanitize_text_field($data['next_run']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
        );
        
        $format = array('%d', '%s', '%s', '%d', '%s');

        if (!empty($data['id'])) {
            $result = $this->update(absint($data['id']), $schedule_data, $format);
            return $result !== false ? absint($data['id']) : false;
        } else {
            return $this->insert($schedule_data, $format);
        }
    }
    
    /**
     * Toggle schedule active status.
     *
     * @param int $id        Schedule ID.
     * @param int $is_active Active status (0 or 1).
     * @return int|false Number of rows affected or false on error.
     */
    public function toggle_active($id, $is_active) {
        return $this->update($id, array('is_active' => $is_active), array('%d'));
    }
    
    /**
     * Update schedule run times.
     *
     * Updates both last_run and next_run timestamps for a schedule.
     *
     * @param int    $id       Schedule ID.
     * @param string $last_run Last run time in MySQL format.
     * @param string $next_run Next run time in MySQL format.
     * @return int|false Number of rows affected or false on error.
     */
    public function update_run_times($id, $last_run, $next_run) {
        return $this->update($id, array(
            'last_run' => $last_run,
            'next_run' => $next_run,
        ), array('%s', '%s'));
    }
    
    /**
     * Get active schedules count.
     *
     * @return int Number of active schedules.
     */
    public function get_active_count() {
        return $this->count(array('is_active' => 1));
    }
    
    /**
     * Get schedules for a specific template.
     *
     * @param int  $template_id Template ID.
     * @param bool $active_only Optional. Whether to return only active schedules.
     * @return array Array of schedule objects.
     */
    public function get_by_template($template_id, $active_only = false) {
        $where = array('template_id' => $template_id);
        
        if ($active_only) {
            $where['is_active'] = 1;
        }
        
        return $this->find_all(array(
            'where' => $where,
            'order_by' => 'next_run',
            'order' => 'ASC',
        ));
    }
}
