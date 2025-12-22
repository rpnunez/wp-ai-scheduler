<?php
/**
 * History Repository
 *
 * Handles all database operations for generation history records.
 * Provides query optimization and abstraction from direct $wpdb usage.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_History_Repository
 *
 * Repository for managing post generation history in the database.
 * Encapsulates all SQL queries and database operations for history records.
 */
class AIPS_History_Repository extends AIPS_Base_Repository {
    
    /**
     * Initialize the history repository.
     */
    public function __construct() {
        parent::__construct('aips_history');
    }
    
    /**
     * Get paginated history with optional filtering.
     *
     * @param array $args Query arguments (per_page, page, status, search, template_id, orderby, order).
     * @return array Array containing items, total, pages, and current_page.
     */
    public function get_history($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'template_id' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build WHERE conditions
        $where_clauses = array("1=1");
        $where_args = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "h.status = %s";
            $where_args[] = $args['status'];
        }

        if (!empty($args['template_id'])) {
            $where_clauses[] = "h.template_id = %d";
            $where_args[] = $args['template_id'];
        }

        if (!empty($args['search'])) {
            $where_clauses[] = "h.generated_title LIKE %s";
            $where_args[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_sql = implode(' AND ', $where_clauses);

        // Validate orderby
        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'status')) 
            ? $args['orderby'] 
            : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $templates_table = $this->wpdb->prefix . 'aips_templates';
        
        // Build query arguments
        $query_args = $where_args;
        $query_args[] = $args['per_page'];
        $query_args[] = $offset;

        // Execute main query
        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT h.*, t.name as template_name 
            FROM {$this->table_name} h 
            LEFT JOIN {$templates_table} t ON h.template_id = t.id 
            WHERE $where_sql
            ORDER BY h.$orderby $order 
            LIMIT %d OFFSET %d
        ", $query_args));
        
        // Get total count
        if (!empty($where_args)) {
            $total = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql",
                $where_args
            ));
        } else {
            $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql");
        }
        
        return array(
            'items' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }
    
    /**
     * Get history statistics.
     *
     * Returns counts for total, completed, failed, and processing records,
     * along with success rate calculation.
     *
     * @return array Statistics array.
     */
    public function get_stats() {
        $results = $this->wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
            FROM {$this->table_name}
        ");

        $stats = array(
            'total' => (int) $results->total,
            'completed' => (int) $results->completed,
            'failed' => (int) $results->failed,
            'processing' => (int) $results->processing,
        );
        
        $stats['success_rate'] = $stats['total'] > 0 
            ? round(($stats['completed'] / $stats['total']) * 100, 1) 
            : 0;
        
        return $stats;
    }

    /**
     * Get statistics for a specific template.
     *
     * @param int $template_id Template ID.
     * @return int Number of completed posts for the template.
     */
    public function get_template_stats($template_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE template_id = %d AND status = 'completed'",
            $template_id
        ));
    }
    
    /**
     * Clear history records.
     *
     * @param string $status Optional. Status to filter by (empty for all).
     * @return int|bool Number of records deleted or result of truncate.
     */
    public function clear_history($status = '') {
        if (empty($status)) {
            return $this->truncate();
        }
        
        return $this->delete_where(array('status' => $status), array('%s'));
    }
    
    /**
     * Create a new history record.
     *
     * @param array $data History data.
     * @return int|false The inserted record ID or false on failure.
     */
    public function create($data) {
        $defaults = array(
            'post_id' => null,
            'template_id' => null,
            'status' => 'pending',
            'prompt' => '',
            'generated_title' => '',
            'generated_content' => '',
            'generation_log' => '',
            'error_message' => '',
            'created_at' => current_time('mysql'),
            'completed_at' => null,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        return $this->insert($data, array(
            '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ));
    }
    
    /**
     * Update history record status and completion time.
     *
     * @param int    $id     History record ID.
     * @param string $status New status.
     * @param array  $data   Optional. Additional data to update.
     * @return int|false Number of rows affected or false on error.
     */
    public function update_status($id, $status, $data = array()) {
        $update_data = array_merge($data, array(
            'status' => $status,
        ));
        
        if ($status === 'completed' || $status === 'failed') {
            $update_data['completed_at'] = current_time('mysql');
        }
        
        return $this->update($id, $update_data);
    }
}
