<?php
/**
 * Embeddings Repository
 *
 * Data access for post embedding index state used by Internal Links.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Embeddings_Repository {

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $posts_table;

    /**
     * @var string
     */
    private $postmeta_table;

    /**
     * @var string
     */
    const META_STATUS = '_aips_embedding_status';

    /**
     * @var string
     */
    const META_INDEXED_AT = '_aips_embedding_indexed_at';

    /**
     * @var string
     */
    const META_ERROR = '_aips_embedding_error';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->posts_table = $wpdb->posts;
        $this->postmeta_table = $wpdb->postmeta;
    }

    /**
     * Get index summary counters.
     *
     * @return array
     */
    public function get_index_summary() {
        $total_published = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->posts_table} WHERE post_status = %s AND post_type = %s",
                'publish',
                'post'
            )
        );

        $indexed = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->posts_table} p
                INNER JOIN {$this->postmeta_table} pm ON pm.post_id = p.ID
                WHERE p.post_status = %s
                AND p.post_type = %s
                AND pm.meta_key = %s
                AND pm.meta_value = %s",
                'publish',
                'post',
                self::META_STATUS,
                'indexed'
            )
        );

        $pending = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->posts_table} p
                INNER JOIN {$this->postmeta_table} pm ON pm.post_id = p.ID
                WHERE p.post_status = %s
                AND p.post_type = %s
                AND pm.meta_key = %s
                AND pm.meta_value = %s",
                'publish',
                'post',
                self::META_STATUS,
                'pending'
            )
        );

        $error = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->posts_table} p
                INNER JOIN {$this->postmeta_table} pm ON pm.post_id = p.ID
                WHERE p.post_status = %s
                AND p.post_type = %s
                AND pm.meta_key = %s
                AND pm.meta_value = %s",
                'publish',
                'post',
                self::META_STATUS,
                'error'
            )
        );

        return array(
            'total_published' => $total_published,
            'indexed' => $indexed,
            'pending' => $pending,
            'error' => $error,
        );
    }

    /**
     * Get paginated index status rows.
     *
     * @param array $args Query args.
     * @return array
     */
    public function get_index_status_rows($args = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'status' => 'all',
        );

        $args = wp_parse_args($args, $defaults);
        $page = max(1, absint($args['page']));
        $per_page = max(1, min(100, absint($args['per_page'])));
        $offset = ($page - 1) * $per_page;

        $where = array('p.post_status = %s', 'p.post_type = %s');
        $where_args = array('publish', 'post');

        if (!empty($args['search'])) {
            $where[] = 'p.post_title LIKE %s';
            $where_args[] = '%' . $this->wpdb->esc_like((string) $args['search']) . '%';
        }

        $status = sanitize_key((string) $args['status']);
        if (in_array($status, array('indexed', 'pending', 'error'), true)) {
            $where[] = 'COALESCE(pm_status.meta_value, %s) = %s';
            $where_args[] = 'pending';
            $where_args[] = $status;
        }

        $where_sql = implode(' AND ', $where);

        $query_args = $where_args;
        $query_args[] = $per_page;
        $query_args[] = $offset;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    p.ID AS post_id,
                    p.post_title,
                    p.post_type,
                    p.post_date,
                    COALESCE(pm_status.meta_value, %s) AS index_status,
                    pm_indexed.meta_value AS indexed_at,
                    pm_error.meta_value AS index_error
                FROM {$this->posts_table} p
                LEFT JOIN {$this->postmeta_table} pm_status
                    ON pm_status.post_id = p.ID AND pm_status.meta_key = %s
                LEFT JOIN {$this->postmeta_table} pm_indexed
                    ON pm_indexed.post_id = p.ID AND pm_indexed.meta_key = %s
                LEFT JOIN {$this->postmeta_table} pm_error
                    ON pm_error.post_id = p.ID AND pm_error.meta_key = %s
                WHERE {$where_sql}
                ORDER BY p.post_date DESC
                LIMIT %d OFFSET %d",
                array_merge(
                    array('pending', self::META_STATUS, self::META_INDEXED_AT, self::META_ERROR),
                    $query_args
                )
            ),
            ARRAY_A
        );

        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->posts_table} p
                LEFT JOIN {$this->postmeta_table} pm_status
                    ON pm_status.post_id = p.ID AND pm_status.meta_key = %s
                WHERE {$where_sql}",
                array_merge(array(self::META_STATUS), $where_args)
            )
        );

        return array(
            'items' => $rows,
            'total' => $total,
            'pages' => (int) ceil($total / $per_page),
            'current_page' => $page,
            'per_page' => $per_page,
        );
    }

    /**
     * Find published posts for batch indexing.
     *
     * @param int $offset Row offset.
     * @param int $limit  Max rows.
     * @return array
     */
    public function get_published_posts_batch($offset, $limit) {
        $offset = max(0, absint($offset));
        $limit = max(1, min(200, absint($limit)));

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ID, post_title, post_excerpt, post_content, post_type, post_status
                FROM {$this->posts_table}
                WHERE post_status = %s AND post_type = %s
                ORDER BY ID ASC
                LIMIT %d OFFSET %d",
                'publish',
                'post',
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Mark post embedding status.
     *
     * @param int    $post_id Post ID.
     * @param string $status  indexed|pending|error.
     * @param string $error_message Optional error message.
     * @return void
     */
    public function set_post_index_status($post_id, $status, $error_message = '') {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return;
        }

        $status = sanitize_key($status);
        if (!in_array($status, array('indexed', 'pending', 'error'), true)) {
            $status = 'pending';
        }

        update_post_meta($post_id, self::META_STATUS, $status);

        if ($status === 'indexed') {
            update_post_meta($post_id, self::META_INDEXED_AT, current_time('mysql'));
            delete_post_meta($post_id, self::META_ERROR);
        } elseif ($status === 'error') {
            update_post_meta($post_id, self::META_ERROR, sanitize_text_field((string) $error_message));
        }
    }
}
