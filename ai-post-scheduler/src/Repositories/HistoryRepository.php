<?php
namespace AIPS\Repositories;

/**
 * History Repository
 *
 * Database abstraction layer for history operations.
 * Provides a clean interface for CRUD operations on the history table.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HistoryRepository
 *
 * Repository pattern implementation for history data access.
 * Encapsulates all database operations related to generation history.
 */
class HistoryRepository {
    
    /**
     * @var string The history table name (with prefix)
     */
    private $table_name;
    private $table_name_log;
    
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
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->table_name_log = $wpdb->prefix . 'aips_history_log';
    }
    
    /**
     * Get paginated history with optional filtering.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type int    $per_page    Number of items per page. Default 20.
     *     @type int    $page        Current page number. Default 1.
     *     @type string $status      Filter by status. Default empty.
     *     @type string $search      Search term for title. Default empty.
     *     @type int    $template_id Filter by template ID. Default 0.
     *     @type string $orderby     Column to order by. Default 'created_at'.
     *     @type string $order       Order direction (ASC/DESC). Default 'DESC'.
     * }
     * @return array {
     *     @type array $items        Array of history items.
     *     @type int   $total        Total number of items.
     *     @type int   $pages        Total number of pages.
     *     @type int   $current_page Current page number.
     * }
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
            'fields' => 'all',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build select fields
        $fields_sql = "h.*, t.name as template_name";
        if ($args['fields'] === 'list') {
            $fields_sql = "h.id, h.uuid, h.post_id, h.template_id, h.status, h.generated_title, h.created_at, h.error_message, h.created_at, h.completed_at, t.name as template_name";
        }

        // Build where clauses
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

        // Validate orderby and order
        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'status')) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $templates_table = $this->wpdb->prefix . 'aips_templates';
        
        // Query for items
        $query_args = $where_args;
        $query_args[] = $args['per_page'];
        $query_args[] = $offset;

        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT $fields_sql
            FROM {$this->table_name} h 
            LEFT JOIN {$templates_table} t ON h.template_id = t.id 
            WHERE $where_sql
            ORDER BY h.$orderby $order 
            LIMIT %d OFFSET %d
        ", $query_args));
        
        // Query for total count
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
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }
    
    /**
     * Get a single history item by ID.
     *
     * @param int $id History item ID.
     * @return object|null History item or null if not found.
     */
    public function get_by_id($id) {
        $history = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));

        if ($history) {
            $history->log = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->table_name_log} WHERE history_id = %d ORDER BY timestamp ASC",
                $id
            ));
        }

        return $history;
    }
    
    /**
     * Check if a post has a completed history entry.
     *
     * @param int $post_id The post ID to check.
     * @return bool True if the post has a completed history entry, false otherwise.
     */
    public function post_has_history_and_completed($post_id) {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d AND status = 'completed'",
            $post_id
        ));
        
        return (bool) $count;
    }
    
    /**
     * Get history record by post ID.
     *
     * @param int $post_id The post ID to find.
     * @return object|null History record or null if not found.
     */
    public function get_by_post_id($post_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
            $post_id
        ));
    }
    
    /**
     * Add a log entry to a history item.
     *
     * @param int    $history_id      The ID of the history item.
     * @param string $log_type        The type of log entry (e.g., 'ai_call', 'error').
     * @param array  $details         The details of the log entry.
     * @param int    $history_type_id Optional. History type constant from AIPS_History_Type. Default \AIPS_History_Type::LOG.
     * @return int|false The inserted ID on success, false on failure.
     */
    public function add_log_entry($history_id, $log_type, $details, $history_type_id = null) {
        // Default to LOG type if not specified
        if ($history_type_id === null) {
            $history_type_id = \AIPS_History_Type::LOG;
        }
        
        $insert_data = array(
            'history_id' => $history_id,
            'log_type' => $log_type,
            'history_type_id' => $history_type_id,
            'details' => wp_json_encode($details),
        );
        
        $format = array('%d', '%s', '%d', '%s');
        
        $result = $this->wpdb->insert($this->table_name_log, $insert_data, $format);
        
        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Get overall statistics for history.
     *
     * @return array {
     *     @type int   $total        Total number of items.
     *     @type int   $completed    Number of completed items.
     *     @type int   $failed       Number of failed items.
     *     @type int   $processing   Number of processing items.
     *     @type float $success_rate Success rate percentage.
     * }
     */
    public function get_stats() {
        $cached_stats = get_transient('aips_history_stats');

        if ($cached_stats !== false) {
            return $cached_stats;
        }

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

        set_transient('aips_history_stats', $stats, HOUR_IN_SECONDS);
        
        return $stats;
    }

    /**
     * Get statistics for a specific template.
     *
     * @param int $template_id Template ID.
     * @return int Number of completed posts for this template.
     */
    public function get_template_stats($template_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE template_id = %d AND status = 'completed'",
            $template_id
        ));
    }

    /**
     * Get statistics for all templates.
     *
     * @return array Associative array of template ID => count.
     */
    public function get_all_template_stats() {
        $results = $this->wpdb->get_results("
            SELECT template_id, COUNT(*) as count
            FROM {$this->table_name}
            WHERE status = 'completed'
            GROUP BY template_id
        ");

        $stats = array();
        foreach ($results as $row) {
            $stats[$row->template_id] = (int) $row->count;
        }

        return $stats;
    }
    
    /**
     * Create a new history entry.
     *
     * @param array $data {
     *     History data.
     *
     *     @type int    $template_id        Template ID.
     *     @type int    $author_id          Author ID (for topic-based generation).
     *     @type int    $topic_id           Topic ID (for topic-based generation).
     *     @type string $creation_method    Creation method (manual or scheduled).
     *     @type string $status             Status (pending, processing, completed, failed).
     *     @type string $prompt             AI prompt used.
     *     @type string $generated_title    Generated post title.
     *     @type string $generated_content  Generated post content.
     *     @type string $generation_log     JSON-encoded generation log.
     *     @type string $error_message      Error message if failed.
     *     @type int    $post_id            WordPress post ID if created.
     * }
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        $insert_data = array(
            'uuid' => isset($data['uuid']) ? $data['uuid'] : null,
            'template_id' => isset($data['template_id']) ? absint($data['template_id']) : null,
            'author_id' => isset($data['author_id']) ? absint($data['author_id']) : null,
            'topic_id' => isset($data['topic_id']) ? absint($data['topic_id']) : null,
            'creation_method' => isset($data['creation_method']) ? sanitize_text_field($data['creation_method']) : null,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'pending',
            'prompt' => isset($data['prompt']) ? wp_kses_post($data['prompt']) : '',
            'generated_title' => isset($data['generated_title']) ? sanitize_text_field($data['generated_title']) : '',
            'generated_content' => isset($data['generated_content']) ? wp_kses_post($data['generated_content']) : '',
            'error_message' => isset($data['error_message']) ? sanitize_text_field($data['error_message']) : '',
            'post_id' => isset($data['post_id']) ? absint($data['post_id']) : null,
        );
        
        $format = array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d');
        
        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);
        
        if ($result) {
            delete_transient('aips_history_stats');
        }

        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update an existing history entry.
     *
     * @param int   $id   History item ID.
     * @param array $data Data to update (same structure as create).
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $update_data = array();
        $format = array();
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }
        
        if (isset($data['post_id'])) {
            $update_data['post_id'] = absint($data['post_id']);
            $format[] = '%d';
        }
        
        if (isset($data['generated_title'])) {
            $update_data['generated_title'] = sanitize_text_field($data['generated_title']);
            $format[] = '%s';
        }
        
        if (isset($data['generated_content'])) {
            $update_data['generated_content'] = wp_kses_post($data['generated_content']);
            $format[] = '%s';
        }
        
        if (isset($data['error_message'])) {
            $update_data['error_message'] = sanitize_text_field($data['error_message']);
            $format[] = '%s';
        }
        
        if (isset($data['author_id'])) {
            $update_data['author_id'] = absint($data['author_id']);
            $format[] = '%d';
        }
        
        if (isset($data['topic_id'])) {
            $update_data['topic_id'] = absint($data['topic_id']);
            $format[] = '%d';
        }
        
        if (isset($data['creation_method'])) {
            $update_data['creation_method'] = sanitize_text_field($data['creation_method']);
            $format[] = '%s';
        }
        
        if (isset($data['completed_at'])) {
            $update_data['completed_at'] = sanitize_text_field($data['completed_at']);
            $format[] = '%s';
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

        if ($result !== false) {
            delete_transient('aips_history_stats');
        }

        return $result !== false;
    }
    
    /**
     * Delete history entries.
     *
     * @param string $status Optional. Delete only entries with this status. Default empty (all).
     * @return int|false Number of rows affected or false on failure.
     */
    public function delete_by_status($status = '') {
        delete_transient('aips_history_stats');

        if (empty($status)) {
            return $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
        }
        
        return $this->wpdb->delete($this->table_name, array('status' => $status), array('%s'));
    }
    
    /**
     * Delete a single history entry by ID.
     *
     * @param int $id History item ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));

        if ($result !== false) {
            delete_transient('aips_history_stats');
        }

        return $result !== false;
    }

    /**
     * Delete multiple history entries by ID.
     *
     * @param array $ids Array of history item IDs.
     * @return int|false Number of rows affected or false on failure.
     */
    public function delete_bulk($ids) {
        if (empty($ids)) {
            return 0;
        }

        // Sanitize IDs
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $ids_sql = implode(',', $ids);

        $result = $this->wpdb->query("DELETE FROM {$this->table_name} WHERE id IN ($ids_sql)");

        if ($result !== false) {
            delete_transient('aips_history_stats');
        }

        return $result;
    }
}
