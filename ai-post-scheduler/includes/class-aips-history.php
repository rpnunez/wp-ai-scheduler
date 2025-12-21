<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_History {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        
        add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
        add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
    }
    
    public function get_history($args = array()) {
        global $wpdb;
        
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

        // Fix: Use array for where clauses and arguments to avoid double prepare
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
            $where_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_sql = implode(' AND ', $where_clauses);

        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'status')) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $templates_table = $wpdb->prefix . 'aips_templates';
        
        // Arguments for the main query: where args + limit + offset
        $query_args = $where_args;
        $query_args[] = $args['per_page'];
        $query_args[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT h.*, t.name as template_name 
            FROM {$this->table_name} h 
            LEFT JOIN {$templates_table} t ON h.template_id = t.id 
            WHERE $where_sql
            ORDER BY h.$orderby $order 
            LIMIT %d OFFSET %d
        ", $query_args));
        
        // For the count query, we only need the where args
        if (!empty($where_args)) {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql",
                $where_args
            ));
        } else {
            // No placeholders, run directly
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql");
        }
        
        return array(
            'items' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }
    
    public function get_stats() {
        global $wpdb;
        
        // Optimized to single query
        $results = $wpdb->get_row("
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

    public function get_template_stats($template_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE template_id = %d AND status = 'completed'",
            $template_id
        ));
    }
    
    public function clear_history($status = '') {
        global $wpdb;
        
        if (empty($status)) {
            return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        }
        
        return $wpdb->delete($this->table_name, array('status' => $status), array('%s'));
    }
    
    public function ajax_clear_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        $this->clear_history($status);
        
        wp_send_json_success(array('message' => __('History cleared successfully.', 'ai-post-scheduler')));
    }
    
    public function ajax_retry_generation() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        global $wpdb;
        
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        
        if (!$history_id) {
            wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
        }
        
        $history_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $history_id
        ));
        
        if (!$history_item || !$history_item->template_id) {
            wp_send_json_error(array('message' => __('History item not found or no template associated.', 'ai-post-scheduler')));
        }
        
        $templates = new AIPS_Templates();
        $template = $templates->get($history_item->template_id);
        
        if (!$template) {
            wp_send_json_error(array('message' => __('Template no longer exists.', 'ai-post-scheduler')));
        }
        
        $generator = new AIPS_Generator();
        $result = $generator->generate_post($template);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Post regenerated successfully!', 'ai-post-scheduler'),
            'post_id' => $result
        ));
    }
    
    public function ajax_get_history_details() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        global $wpdb;
        
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        
        if (!$history_id) {
            wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
        }
        
        $history_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $history_id
        ));
        
        if (!$history_item) {
            wp_send_json_error(array('message' => __('History item not found.', 'ai-post-scheduler')));
        }
        
        $generation_log = array();
        if (!empty($history_item->generation_log)) {
            $generation_log = json_decode($history_item->generation_log, true);
        }
        
        $response = array(
            'id' => $history_item->id,
            'status' => $history_item->status,
            'created_at' => $history_item->created_at,
            'completed_at' => $history_item->completed_at,
            'generated_title' => $history_item->generated_title,
            'post_id' => $history_item->post_id,
            'error_message' => $history_item->error_message,
            'generation_log' => $generation_log,
        );
        
        wp_send_json_success($response);
    }
    
    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $history = $this->get_history(array(
            'page' => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
        ));
        
        $stats = $this->get_stats();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }
}
