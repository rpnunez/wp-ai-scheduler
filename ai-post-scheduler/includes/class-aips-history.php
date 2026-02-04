<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_History {
    
    private $table_name;
    
    /**
     * @var AIPS_History_Repository Repository for database operations
     */
    private $repository;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->repository = new AIPS_History_Repository();
        
        add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
        add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
        add_action('wp_ajax_aips_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
        add_action('wp_ajax_aips_reload_history', array($this, 'ajax_reload_history'));
    }
    
    public function get_history($args = array()) {
        return $this->repository->get_history($args);
    }
    
    public function get_stats() {
        return $this->repository->get_stats();
    }

    public function get_template_stats($template_id) {
        return $this->repository->get_template_stats($template_id);
    }

    public function get_all_template_stats() {
        return $this->repository->get_all_template_stats();
    }
    
    public function clear_history($status = '') {
        return $this->repository->delete_by_status($status);
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

    public function ajax_bulk_delete_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No items selected.', 'ai-post-scheduler')));
        }

        $result = $this->repository->delete_bulk($ids);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete items.', 'ai-post-scheduler')));
        }

        wp_send_json_success(array('message' => __('Selected items deleted successfully.', 'ai-post-scheduler')));
    }
    
    public function ajax_retry_generation() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        
        if (!$history_id) {
            wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
        }
        
        $history_item = $this->repository->get_by_id($history_id);
        
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
        
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        
        if (!$history_id) {
            wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
        }
        
        $history_item = $this->repository->get_by_id($history_id);
        
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

    /**
     * AJAX handler to reload the history table and updated stats.
     *
     * Returns the latest items HTML (table body only) and stats so the
     * client can refresh the view without a full page reload.
     */
    public function ajax_reload_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $history = $this->get_history(array(
            'page' => 1,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));

        $stats = $this->get_stats();

        ob_start();

        if (!empty($history['items'])) {
            foreach ($history['items'] as $item) {
                ?>
                <tr>
                    <th scope="row" class="check-column">
                        <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->id); ?>"><?php esc_html_e('Select Item', 'ai-post-scheduler'); ?></label>
                        <input id="cb-select-<?php echo esc_attr($item->id); ?>" type="checkbox" name="history[]" value="<?php echo esc_attr($item->id); ?>">
                    </th>
                    <td class="column-title">
                        <?php if ($item->post_id): ?>
                        <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
                            <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                        </a>
                        <?php else: ?>
                        <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                        <?php endif; ?>
                        <?php if ($item->status === 'failed' && $item->error_message): ?>
                        <div class="aips-error-message"><?php echo esc_html($item->error_message); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="column-template">
                        <?php echo esc_html($item->template_name ?: '-'); ?>
                    </td>
                    <td class="column-status">
                        <span class="aips-status aips-status-<?php echo esc_attr($item->status); ?>">
                            <?php echo esc_html(ucfirst($item->status)); ?>
                        </span>
                    </td>
                    <td class="column-date">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?>
                    </td>
                    <td class="column-actions">
                        <?php if ($item->post_id): ?>
                        <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" class="button button-small" target="_blank">
                            <?php esc_html_e('View', 'ai-post-scheduler'); ?>
                        </a>
                        <?php endif; ?>
                        <button class="button button-small aips-view-details" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Details', 'ai-post-scheduler'); ?>
                        </button>
                        <?php if ($item->status === 'failed' && $item->template_id): ?>
                        <button class="button button-small aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Retry', 'ai-post-scheduler'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        }

        $items_html = ob_get_clean();

        wp_send_json_success(array(
            'items_html' => $items_html,
            'stats' => array(
                'total' => (int) $stats['total'],
                'completed' => (int) $stats['completed'],
                'failed' => (int) $stats['failed'],
                'success_rate' => (float) $stats['success_rate'],
            ),
        ));
    }
    
    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $history = $this->get_history(array(
            'page' => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));
        
        $stats = $this->get_stats();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }
}
