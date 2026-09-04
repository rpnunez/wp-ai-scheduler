<?php
/**
 * History Query Service
 *
 * Dedicated service for complex history log querying and reporting.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    die;
}

class AIPS_History_Query_Service {

    /**
     * @var wpdb WordPress database abstraction object
     */
    private $wpdb;

    /**
     * @var string The history table name (with prefix)
     */
    private $table_name;

    /**
     * @var string The history log table name (with prefix)
     */
    private $table_name_log;

    /**
     * Initialize the query service.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->table_name_log = $wpdb->prefix . 'aips_history_log';
    }

    /**
     * Return creation_method values used for internal lifecycle containers.
     *
     * @return string[]
     */
    private function get_auxiliary_creation_methods() {
        return array(
            'schedule_lifecycle',
            'template_lifecycle',
            'campaign_lifecycle',
            'notification_sent',
        );
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
            'campaign_id' => 0,
            'author_id' => 0,
            'post_type' => '',
            'domain' => '',
            'actor' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'fields' => 'all',
        );

        $args = wp_parse_args($args, $defaults);

        $offset = ($args['page'] - 1) * $args['per_page'];

        $domain_patterns = array(
            'author_topics' => 'author_topic%',
            'research' => '%research%',
            'sources' => '%source%',
            'embeddings' => '%embedding%',
            'internal_links' => '%internal_link%',
            'batch_jobs' => '%batch%',
        );

        $event_domain_case_parts = array('CASE');
        foreach ($domain_patterns as $domain_key => $domain_pattern) {
            $event_domain_case_parts[] = sprintf(
                "WHEN COALESCE(h.creation_method, '') LIKE '%s' THEN '%s'",
                esc_sql($domain_pattern),
                esc_sql($domain_key)
            );
        }
        $event_domain_case_parts[] = "ELSE 'post_generation'";
        $event_domain_case_parts[] = 'END';
        $event_domain_case_sql = implode("\n", $event_domain_case_parts);
        $event_label_case_sql = "CASE
                WHEN h.generated_title IS NOT NULL AND h.generated_title <> '' THEN h.generated_title
                WHEN h.topic_id IS NOT NULL THEN CONCAT('Topic #', h.topic_id)
                ELSE 'Generation Event'
            END";
        $actor_type_case_sql = "CASE
                WHEN COALESCE(h.creation_method, '') LIKE '%manual%' OR COALESCE(h.creation_method, '') LIKE '%admin%' THEN 'admin'
                ELSE 'system'
            END";

        // Build select fields
        if ($args['fields'] === 'list') {
            $fields_sql = "h.id, h.uuid, h.correlation_id, h.post_id, h.post_type, h.template_id, h.campaign_id, h.topic_id, h.status, h.generated_title, h.created_at, h.error_message, h.completed_at, h.creation_method,
                {$event_domain_case_sql} AS event_domain,
                {$event_label_case_sql} AS event_label,
                {$actor_type_case_sql} AS actor_type,
                t.name as template_name,
                CASE WHEN h.completed_at > 0 AND h.completed_at >= h.created_at THEN h.completed_at - h.created_at ELSE NULL END AS duration_seconds,
                ls.warning_count, ls.error_count, ls.ai_call_count, ls.latest_message";
        } elseif ($args['fields'] === 'all') {
            // Include longtext fields only when 'all' is explicitly requested or defaulted to, to prevent breaking changes
            $fields_sql = "h.id, h.uuid, h.correlation_id, h.post_id, h.post_type, h.template_id, h.campaign_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at, h.author_id, h.topic_id, h.creation_method, h.prompt, h.generated_content, h.generation_log,
                {$event_domain_case_sql} AS event_domain,
                {$event_label_case_sql} AS event_label,
                {$actor_type_case_sql} AS actor_type,
                t.name as template_name";
        } else {
            // For specifically 'performance' or any other restricted fields
            $fields_sql = "h.id, h.uuid, h.correlation_id, h.post_id, h.post_type, h.template_id, h.campaign_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at, h.author_id, h.topic_id, h.creation_method, h.prompt, t.name as template_name";
        }

        // Build where clauses
        $where_clauses = array("1=1");
        $where_args = array();

        $auxiliary_methods = $this->get_auxiliary_creation_methods();
        $auxiliary_placeholders = implode(', ', array_fill(0, count($auxiliary_methods), '%s'));
        $where_clauses[] = "COALESCE(h.creation_method, '') NOT IN ({$auxiliary_placeholders})";
        $where_args = array_merge($where_args, $auxiliary_methods);
        // Keep excluding legacy orphaned containers that have no contextual linkage.
        $where_clauses[] = "NOT (h.creation_method IS NULL AND h.template_id IS NULL AND h.topic_id IS NULL AND h.post_id IS NULL AND h.author_id IS NULL)";

        if (!empty($args['status'])) {
            $where_clauses[] = "h.status = %s";
            $where_args[] = $args['status'];
        }

        if (!empty($args['template_id'])) {
            $where_clauses[] = "h.template_id = %d";
            $where_args[] = $args['template_id'];
        }

        if (!empty($args['campaign_id'])) {
            $where_clauses[] = "h.campaign_id = %d";
            $where_args[] = $args['campaign_id'];
        }

        if (!empty($args['author_id'])) {
            $where_clauses[] = "h.author_id = %d";
            $where_args[] = $args['author_id'];
        }

        if (!empty($args['post_type'])) {
            $where_clauses[] = "h.post_type = %s";
            $where_args[] = sanitize_key($args['post_type']);
        }

        if (!empty($args['domain'])) {
            $domain = sanitize_key($args['domain']);

            if (isset($domain_patterns[$domain])) {
                $where_clauses[] = "COALESCE(h.creation_method, '') LIKE %s";
                $where_args[] = $domain_patterns[$domain];
            } elseif ($domain === 'post_generation') {
                $post_generation_clauses = array();
                foreach ($domain_patterns as $pattern) {
                    $post_generation_clauses[] = "COALESCE(h.creation_method, '') LIKE %s";
                    $where_args[] = $pattern;
                }
                $where_clauses[] = 'NOT (' . implode(' OR ', $post_generation_clauses) . ')';
            }
        }

        if (!empty($args['actor'])) {
            $actor = sanitize_key($args['actor']);

            if ($actor === 'admin') {
                $where_clauses[] = "(COALESCE(h.creation_method, '') LIKE %s OR COALESCE(h.creation_method, '') LIKE %s)";
                $where_args[] = '%manual%';
                $where_args[] = '%admin%';
            } elseif ($actor === 'system') {
                $where_clauses[] = "(COALESCE(h.creation_method, '') NOT LIKE %s AND COALESCE(h.creation_method, '') NOT LIKE %s)";
                $where_args[] = '%manual%';
                $where_args[] = '%admin%';
            }
        }

        if (!empty($args['date_from'])) {
            $date_from = sanitize_text_field($args['date_from']);
            $date_from_ts = strtotime($date_from . ' 00:00:00');

            if ($date_from_ts !== false) {
                $where_clauses[] = "h.created_at >= %d";
                $where_args[] = $date_from_ts;
            }
        }

        if (!empty($args['date_to'])) {
            $date_to = sanitize_text_field($args['date_to']);
            $date_to_ts = strtotime($date_to . ' 23:59:59');

            if ($date_to_ts !== false) {
                $where_clauses[] = "h.created_at <= %d";
                $where_args[] = $date_to_ts;
            }
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
            LEFT JOIN (
                SELECT history_id,
                    SUM(CASE WHEN history_type_id = 3 THEN 1 ELSE 0 END) AS warning_count,
                    SUM(CASE WHEN history_type_id = 2 THEN 1 ELSE 0 END) AS error_count,
                    SUM(CASE WHEN history_type_id = 5 THEN 1 ELSE 0 END) AS ai_call_count,
                    LEFT(SUBSTRING_INDEX(GROUP_CONCAT(details ORDER BY timestamp DESC SEPARATOR '||'), '||', 1), 180) AS latest_message
                FROM {$this->table_name_log}
                GROUP BY history_id
            ) ls ON h.id = ls.history_id
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
     * Get paginated posts whose latest completed generation is incomplete.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type int    $per_page    Number of items per page. Default 20.
     *     @type int    $page        Current page number. Default 1.
     *     @type string $search      Search term for title. Default empty.
     *     @type int    $template_id Filter by template ID. Default 0.
     *     @type int    $author_id   Filter by author ID. Default 0.
     *     @type string $orderby     Column to order by. Default 'created_at'.
     *     @type string $order       Order direction (ASC/DESC). Default 'DESC'.
     * }
     * @return array {
     *     @type array $items        Array of partial generation items.
     *     @type int   $total        Total number of items.
     *     @type int   $pages        Total number of pages.
     *     @type int   $current_page Current page number.
     * }
     */
    public function get_partial_generations($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'template_id' => 0,
            'campaign_id' => 0,
            'author_id' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $args['page'] = max(1, (int) $args['page']);
        $args['per_page'] = (int) $args['per_page'];

        $use_limit = $args['per_page'] > 0;
        $offset = $use_limit ? (($args['page'] - 1) * $args['per_page']) : 0;

        $where_clauses = array(
            "h.status = 'completed'",
            "h.post_id IS NOT NULL",
            "(pm_incomplete.meta_value = 'true' OR pm_had_partial.meta_value = 'true')",
        );
        $where_args = array();

        if (!empty($args['template_id'])) {
            $where_clauses[] = 'h.template_id = %d';
            $where_args[] = $args['template_id'];
        }

        if (!empty($args['campaign_id'])) {
            $where_clauses[] = 'h.campaign_id = %d';
            $where_args[] = $args['campaign_id'];
        }

        if (!empty($args['author_id'])) {
            $where_clauses[] = 'h.author_id = %d';
            $where_args[] = $args['author_id'];
        }

        if (!empty($args['search'])) {
            $where_clauses[] = '(h.generated_title LIKE %s OR p.post_title LIKE %s)';
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }

        $where_sql = implode(' AND ', $where_clauses);
        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'post_title', 'post_modified', 'post_status'), true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        if ($orderby === 'post_title') {
            $orderby_sql = "p.post_title $order";
        } elseif ($orderby === 'post_modified' || $orderby === 'post_status') {
            $orderby_sql = "p.$orderby $order";
        } else {
            $orderby_sql = "h.$orderby $order";
        }

        $templates_table = $this->wpdb->prefix . 'aips_templates';
        $posts_table = $this->wpdb->posts;
        $postmeta_table = $this->wpdb->postmeta;

        $query = "SELECT
                h.*,
                t.name as template_name,
                p.post_title,
                p.post_status,
                p.post_modified,
                p.post_date,
                pm_incomplete.meta_value as is_currently_incomplete,
                pm_status.meta_value as component_statuses
            FROM {$this->table_name} h
            INNER JOIN (
                SELECT post_id, MAX(id) AS latest_history_id
                FROM {$this->table_name}
                WHERE status = 'completed' AND post_id IS NOT NULL
                GROUP BY post_id
            ) latest ON latest.latest_history_id = h.id
            INNER JOIN {$posts_table} p ON h.post_id = p.ID
            LEFT JOIN {$postmeta_table} pm_incomplete ON pm_incomplete.post_id = p.ID AND pm_incomplete.meta_key = '" . AIPS_Post_Manager::META_GENERATION_INCOMPLETE . "'
            LEFT JOIN {$postmeta_table} pm_had_partial ON pm_had_partial.post_id = p.ID AND pm_had_partial.meta_key = '" . AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL . "'
            LEFT JOIN {$postmeta_table} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '" . AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES . "'
            LEFT JOIN {$templates_table} t ON h.template_id = t.id
            WHERE $where_sql
                ORDER BY $orderby_sql";

        $query_args = $where_args;

        if ($use_limit) {
            $query .= ' LIMIT %d OFFSET %d';
            $query_args[] = $args['per_page'];
            $query_args[] = $offset;
        }

        if (!empty($query_args)) {
            $results = $this->wpdb->get_results($this->wpdb->prepare($query, $query_args));
        } else {
            $results = $this->wpdb->get_results($query);
        }

        if (!empty($where_args)) {
            $total = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->table_name} h
                INNER JOIN (
                    SELECT post_id, MAX(id) AS latest_history_id
                    FROM {$this->table_name}
                    WHERE status = 'completed' AND post_id IS NOT NULL
                    GROUP BY post_id
                ) latest ON latest.latest_history_id = h.id
                INNER JOIN {$posts_table} p ON h.post_id = p.ID
                LEFT JOIN {$postmeta_table} pm_incomplete ON pm_incomplete.post_id = p.ID AND pm_incomplete.meta_key = '" . AIPS_Post_Manager::META_GENERATION_INCOMPLETE . "'
                LEFT JOIN {$postmeta_table} pm_had_partial ON pm_had_partial.post_id = p.ID AND pm_had_partial.meta_key = '" . AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL . "'
                WHERE $where_sql",
                $where_args
            ));
        } else {
            $total = $this->wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$this->table_name} h
                INNER JOIN (
                    SELECT post_id, MAX(id) AS latest_history_id
                    FROM {$this->table_name}
                    WHERE status = 'completed' AND post_id IS NOT NULL
                    GROUP BY post_id
                ) latest ON latest.latest_history_id = h.id
                INNER JOIN {$posts_table} p ON h.post_id = p.ID
                LEFT JOIN {$postmeta_table} pm_incomplete ON pm_incomplete.post_id = p.ID AND pm_incomplete.meta_key = '" . AIPS_Post_Manager::META_GENERATION_INCOMPLETE . "'
                LEFT JOIN {$postmeta_table} pm_had_partial ON pm_had_partial.post_id = p.ID AND pm_had_partial.meta_key = '" . AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL . "'
                WHERE $where_sql"
            );
        }

        return array(
            'items' => $results,
            'total' => (int) $total,
            'pages' => $use_limit ? (int) ceil($total / $args['per_page']) : ($total > 0 ? 1 : 0),
            'current_page' => $args['page'],
        );
    }


}