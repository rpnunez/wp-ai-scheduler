<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Repository {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aips_schedule';
    }
    
    public function get_all() {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'aips_templates';
        
        return $wpdb->get_results("
            SELECT s.*, t.name as template_name, t.prompt_template
            FROM {$this->table} s
            LEFT JOIN {$templates_table} t ON s.template_id = t.id
            ORDER BY s.created_at DESC
        ");
    }
    
    public function get_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
    }
    
    public function create($data) {
        global $wpdb;
        
        $wpdb->insert(
            $this->table,
            array(
                'template_id' => $data['template_id'],
                'frequency' => $data['frequency'],
                'next_run' => $data['next_run'],
                'is_active' => isset($data['is_active']) ? $data['is_active'] : 1,
                'topic' => isset($data['topic']) ? $data['topic'] : '',
                'article_structure_id' => isset($data['article_structure_id']) ? $data['article_structure_id'] : null,
                'rotation_pattern' => isset($data['rotation_pattern']) ? $data['rotation_pattern'] : null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }

    public function create_bulk($schedules) {
        global $wpdb;
        
        if (empty($schedules)) {
            return 0;
        }

        $values = array();
        $placeholders = array();

        foreach ($schedules as $schedule) {
            array_push($values,
                $schedule['template_id'],
                $schedule['frequency'],
                $schedule['next_run'],
                isset($schedule['is_active']) ? $schedule['is_active'] : 1,
                isset($schedule['topic']) ? $schedule['topic'] : '',
                isset($schedule['article_structure_id']) ? $schedule['article_structure_id'] : null,
                isset($schedule['rotation_pattern']) ? $schedule['rotation_pattern'] : null,
                current_time('mysql'),
                current_time('mysql')
            );
            $placeholders[] = "(%d, %s, %s, %d, %s, %d, %s, %s, %s)";
        }

        $query = "INSERT INTO {$this->table} (template_id, frequency, next_run, is_active, topic, article_structure_id, rotation_pattern, created_at, updated_at) VALUES " . implode(', ', $placeholders);

        return $wpdb->query($wpdb->prepare($query, $values));
    }
    
    public function update($id, $data) {
        global $wpdb;
        
        $data['updated_at'] = current_time('mysql');
        $format = array();
        
        foreach ($data as $key => $value) {
            if ($value === null) {
                $format[] = null; // Let wpdb handle null
            } elseif (is_int($value)) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }
        
        return $wpdb->update(
            $this->table,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    public function update_last_run($id, $timestamp) {
        return $this->update($id, array('last_run' => $timestamp));
    }
    
    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($this->table, array('id' => $id), array('%d'));
    }

    public function set_active($id, $is_active) {
        return $this->update($id, array('is_active' => $is_active));
    }

    /**
     * Get active schedules that are due for execution.
     *
     * @param string $current_time MySQL datetime string.
     * @param int $limit Max number of records to return.
     * @return array List of schedule objects joined with template data.
     */
    public function get_due_schedules_with_active_templates($current_time, $limit = 5) {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'aips_templates';

        // Select all columns from templates (t.*) and schedules (s.*)
        // Ensure schedule_id is explicitly available
        return $wpdb->get_results($wpdb->prepare("
            SELECT t.*, s.*, s.id AS schedule_id
            FROM {$this->table} s
            INNER JOIN {$templates_table} t ON s.template_id = t.id
            WHERE s.is_active = 1
            AND s.next_run <= %s
            AND t.is_active = 1
            ORDER BY s.next_run ASC
            LIMIT %d
        ", $current_time, $limit));
    }

    public function get_all_active_for_stats() {
        global $wpdb;
        return $wpdb->get_results("SELECT template_id, next_run, frequency FROM {$this->table} WHERE is_active = 1 ORDER BY template_id");
    }
}
