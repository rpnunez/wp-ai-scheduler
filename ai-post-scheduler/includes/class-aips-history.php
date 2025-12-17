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
    }
    
    public function get_history($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $where = "1=1";
        
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND h.status = %s", $args['status']);
        }
        
        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'status')) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $templates_table = $wpdb->prefix . 'aips_templates';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT h.*, t.name as template_name 
            FROM {$this->table_name} h 
            LEFT JOIN {$templates_table} t ON h.template_id = t.id 
            WHERE $where 
            ORDER BY h.$orderby $order 
            LIMIT %d OFFSET %d
        ", $args['per_page'], $offset));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} h WHERE $where");
        
        return array(
            'items' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }
    
    public function get_stats() {
        global $wpdb;
        
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'processing'"),
        );
        
        $stats['success_rate'] = $stats['total'] > 0 
            ? round(($stats['completed'] / $stats['total']) * 100, 1) 
            : 0;
        
        return $stats;
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
    
    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $history = $this->get_history(array(
            'page' => $current_page,
            'status' => $status_filter,
        ));
        
        $stats = $this->get_stats();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }
}
