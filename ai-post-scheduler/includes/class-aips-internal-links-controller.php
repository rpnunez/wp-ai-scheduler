<?php
/**
 * Internal Links Controller
 *
 * Admin page + AJAX endpoints for semantic related posts and internal linking.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Internal_Links_Controller {

    /**
     * @var AIPS_Embeddings_Repository
     */
    private $embeddings_repository;

    /**
     * @var AIPS_Embeddings_Service
     */
    private $embeddings_service;

    /**
     * @var AIPS_Vector_Service
     */
    private $vector_service;

    /**
     * @var AIPS_Logger
     */
    private $logger;

    /**
     * @var string
     */
    const VECTOR_NAMESPACE = 'generated_posts';

    /**
     * @var int
     */
    const BULK_BATCH_DEFAULT = 25;

    public function __construct() {
        $this->embeddings_repository = new AIPS_Embeddings_Repository();
        $this->embeddings_service = new AIPS_Embeddings_Service();
        $this->vector_service = new AIPS_Vector_Service();
        $this->logger = new AIPS_Logger();

        add_action('wp_ajax_aips_get_index_status', array($this, 'ajax_get_index_status'));
        add_action('wp_ajax_aips_bulk_index_posts', array($this, 'ajax_bulk_index_posts'));
        add_action('wp_ajax_aips_index_single_post', array($this, 'ajax_index_single_post'));
        add_action('wp_ajax_aips_search_posts', array($this, 'ajax_search_posts'));
        add_action('wp_ajax_aips_find_related_posts', array($this, 'ajax_find_related_posts'));
        add_action('wp_ajax_aips_preview_links', array($this, 'ajax_preview_links'));
        add_action('wp_ajax_aips_save_links', array($this, 'ajax_save_links'));

        add_action('save_post_post', array($this, 'on_post_save_enqueue_index'), 20, 3);
        add_action('aips_generate_post_embedding', array($this, 'handle_async_index_post'), 10, 1);

        add_filter('the_content', array($this, 'append_related_posts_block'), 20);
    }

    /**
     * Render admin page.
     *
     * @return void
     */
    public function render_page() {
        $summary = $this->embeddings_repository->get_index_summary();
        $top_n_default = (int) get_option('aips_internal_links_top_n', 5);
        $min_score_default = (float) get_option('aips_internal_links_min_score', 0.75);

        include AIPS_PLUGIN_DIR . 'templates/admin/internal-links.php';
    }

    /**
     * Async post-save handler: enqueue embedding indexing.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post Post object.
     * @param bool    $update Whether update.
     * @return void
     */
    public function on_post_save_enqueue_index($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!($post instanceof WP_Post)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $this->embeddings_repository->set_post_index_status($post_id, 'pending');

        if (!wp_next_scheduled('aips_generate_post_embedding', array($post_id))) {
            wp_schedule_single_event(time() + 30, 'aips_generate_post_embedding', array($post_id));
        }
    }

    /**
     * Cron callback for async embedding generation.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function handle_async_index_post($post_id) {
        $this->index_post_embedding(absint($post_id));
    }

    /**
     * AJAX: get index status table rows.
     *
     * @return void
     */
    public function ajax_get_index_status() {
        $this->assert_admin_ajax();

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'all';

        $rows = $this->embeddings_repository->get_index_status_rows(array(
            'page' => $page,
            'per_page' => 20,
            'search' => $search,
            'status' => $status,
        ));

        if (!empty($rows['items']) && is_array($rows['items'])) {
            foreach ($rows['items'] as &$row) {
                $post_id = isset($row['post_id']) ? absint($row['post_id']) : 0;
                $row['edit_link'] = $post_id > 0 ? esc_url_raw(get_edit_post_link($post_id, '')) : '';
            }
            unset($row);
        }

        $summary = $this->embeddings_repository->get_index_summary();

        wp_send_json_success(array(
            'rows' => $rows,
            'summary' => $summary,
        ));
    }

    /**
     * AJAX: bulk index posts in batches.
     *
     * @return void
     */
    public function ajax_bulk_index_posts() {
        $this->assert_admin_ajax();

        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : self::BULK_BATCH_DEFAULT;
        $batch_size = max(1, min(100, $batch_size));

        $batch = $this->embeddings_repository->get_published_posts_batch($offset, $batch_size);
        $summary = $this->embeddings_repository->get_index_summary();

        $processed = 0;
        $success = 0;
        $errors = 0;

        foreach ($batch as $row) {
            $processed++;
            $result = $this->index_post_embedding((int) $row['ID']);
            if ($result['success']) {
                $success++;
            } else {
                $errors++;
            }
        }

        $new_offset = $offset + $processed;
        $done = $new_offset >= (int) $summary['total_published'] || $processed === 0;

        wp_send_json_success(array(
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'offset' => $new_offset,
            'done' => $done,
            'total' => (int) $summary['total_published'],
            'summary' => $this->embeddings_repository->get_index_summary(),
        ));
    }

    /**
     * AJAX: index one post now.
     *
     * @return void
     */
    public function ajax_index_single_post() {
        $this->assert_admin_ajax();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
        }

        $result = $this->index_post_embedding($post_id);
        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['message']));
        }

        wp_send_json_success(array(
            'message' => __('Post indexed successfully.', 'ai-post-scheduler'),
            'status' => 'indexed',
            'indexed_at' => current_time('mysql'),
        ));
    }

    /**
     * AJAX: search posts for selector autocomplete.
     *
     * @return void
     */
    public function ajax_search_posts() {
        $this->assert_admin_ajax();

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if ($term === '') {
            wp_send_json_success(array('posts' => array()));
        }

        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            's' => $term,
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));

        $posts = array();
        foreach ($query->posts as $post_id) {
            $posts[] = array(
                'id' => (int) $post_id,
                'title' => get_the_title($post_id),
                'excerpt' => wp_trim_words(wp_strip_all_tags((string) get_post_field('post_excerpt', $post_id)), 20),
            );
        }

        wp_send_json_success(array('posts' => $posts));
    }

    /**
     * AJAX: find related posts for a selected post.
     *
     * @return void
     */
    public function ajax_find_related_posts() {
        $this->assert_admin_ajax();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $max_links = isset($_POST['max_links']) ? absint($_POST['max_links']) : (int) get_option('aips_internal_links_top_n', 5);
        $min_similarity = isset($_POST['min_similarity']) ? (float) wp_unslash($_POST['min_similarity']) : (float) get_option('aips_internal_links_min_score', 0.75);

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
        }

        $related = $this->get_related_posts($post_id, $max_links, $min_similarity);
        wp_send_json_success(array('related_posts' => $related));
    }

    /**
     * AJAX: preview rewritten content with links.
     *
     * @return void
     */
    public function ajax_preview_links() {
        $this->assert_admin_ajax();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $related_post_ids_raw = isset($_POST['related_post_ids']) ? wp_unslash($_POST['related_post_ids']) : array();
        $related_post_ids = is_array($related_post_ids_raw) ? array_map('absint', $related_post_ids_raw) : array();
        $max_links = isset($_POST['max_links']) ? absint($_POST['max_links']) : 5;
        $max_links = max(1, min(20, $max_links));

        if ($post_id <= 0 || empty($related_post_ids)) {
            wp_send_json_error(array('message' => __('Missing post selection or related posts.', 'ai-post-scheduler')));
        }

        $source_post = get_post($post_id);
        if (!$source_post) {
            wp_send_json_error(array('message' => __('Source post not found.', 'ai-post-scheduler')));
        }

        $related_posts = $this->load_related_posts_by_ids($related_post_ids);
        $rewritten = $this->inject_internal_links((string) $source_post->post_content, $related_posts, $max_links);

        wp_send_json_success(array(
            'preview_html' => wp_kses_post($rewritten),
        ));
    }

    /**
     * AJAX: save rewritten post with generated links.
     *
     * @return void
     */
    public function ajax_save_links() {
        $this->assert_admin_ajax();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $related_post_ids_raw = isset($_POST['related_post_ids']) ? wp_unslash($_POST['related_post_ids']) : array();
        $related_post_ids = is_array($related_post_ids_raw) ? array_map('absint', $related_post_ids_raw) : array();
        $max_links = isset($_POST['max_links']) ? absint($_POST['max_links']) : 5;
        $max_links = max(1, min(20, $max_links));

        if ($post_id <= 0 || empty($related_post_ids)) {
            wp_send_json_error(array('message' => __('Missing post selection or related posts.', 'ai-post-scheduler')));
        }

        $source_post = get_post($post_id);
        if (!$source_post) {
            wp_send_json_error(array('message' => __('Source post not found.', 'ai-post-scheduler')));
        }

        $related_posts = $this->load_related_posts_by_ids($related_post_ids);
        $rewritten = $this->inject_internal_links((string) $source_post->post_content, $related_posts, $max_links);

        $updated = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => wp_kses_post($rewritten),
        ), true);

        if (is_wp_error($updated)) {
            wp_send_json_error(array('message' => $updated->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Internal links applied and post saved.', 'ai-post-scheduler'),
            'post_id' => $post_id,
        ));
    }

    /**
     * Optional frontend related posts block.
     *
     * @param string $content Post content.
     * @return string
     */
    public function append_related_posts_block($content) {
        if (is_admin() || !is_singular('post')) {
            return $content;
        }

        if ((int) get_option('aips_internal_links_show_related_frontend', 0) !== 1) {
            return $content;
        }

        global $post;
        if (!$post || empty($post->ID)) {
            return $content;
        }

        $related = $this->get_related_posts((int) $post->ID, 5, (float) get_option('aips_internal_links_min_score', 0.75));
        if (empty($related)) {
            return $content;
        }

        $items = array();
        foreach ($related as $item) {
            $items[] = sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_permalink($item['post_id'])),
                esc_html($item['title'])
            );
        }

        $block = '<div class="aips-related-posts"><h3>' . esc_html__('Related Posts', 'ai-post-scheduler') . '</h3><ul>' . implode('', $items) . '</ul></div>';
        return $content . $block;
    }

    /**
     * Index a post embedding and upsert to vector store.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private function index_post_embedding($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') {
            return array('success' => false, 'message' => __('Post is not indexable.', 'ai-post-scheduler'));
        }

        if (!$this->embeddings_service->is_embeddings_supported()) {
            $this->embeddings_repository->set_post_index_status($post_id, 'error', __('Embeddings not supported.', 'ai-post-scheduler'));
            return array('success' => false, 'message' => __('Embeddings are not supported.', 'ai-post-scheduler'));
        }

        $text = $this->build_post_embedding_text($post);
        if ($text === '') {
            $this->embeddings_repository->set_post_index_status($post_id, 'error', __('Post content is empty.', 'ai-post-scheduler'));
            return array('success' => false, 'message' => __('Post content is empty.', 'ai-post-scheduler'));
        }

        $this->embeddings_repository->set_post_index_status($post_id, 'pending');

        $embedding = $this->embeddings_service->generate_embedding($text);
        if (is_wp_error($embedding) || !is_array($embedding) || empty($embedding)) {
            $message = is_wp_error($embedding) ? $embedding->get_error_message() : __('Embedding generation failed.', 'ai-post-scheduler');
            $this->embeddings_repository->set_post_index_status($post_id, 'error', $message);
            return array('success' => false, 'message' => $message);
        }

        $upsert_result = $this->vector_service->upsert_vectors(self::VECTOR_NAMESPACE, array(
            array(
                'id' => 'post_' . $post_id,
                'values' => $embedding,
                'metadata' => array(
                    'post_id' => $post_id,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'title' => sanitize_text_field((string) $post->post_title),
                    'indexed_at' => current_time('mysql'),
                ),
            ),
        ));

        if (is_wp_error($upsert_result)) {
            $this->embeddings_repository->set_post_index_status($post_id, 'error', $upsert_result->get_error_message());
            return array('success' => false, 'message' => $upsert_result->get_error_message());
        }

        $this->embeddings_repository->set_post_index_status($post_id, 'indexed');
        return array('success' => true, 'message' => __('Indexed.', 'ai-post-scheduler'));
    }

    /**
     * Build text payload for post embeddings.
     *
     * @param WP_Post $post Post object.
     * @return string
     */
    private function build_post_embedding_text($post) {
        $title = (string) $post->post_title;
        $excerpt = (string) $post->post_excerpt;
        $content = wp_strip_all_tags((string) $post->post_content);
        $content = wp_trim_words($content, 250, '');

        return trim($title . "\n\n" . $excerpt . "\n\n" . $content);
    }

    /**
     * Get semantically related posts for a source post.
     *
     * @param int   $post_id Source post ID.
     * @param int   $top_n Max results.
     * @param float $min_score Minimum similarity.
     * @return array
     */
    private function get_related_posts($post_id, $top_n, $min_score) {
        $post_id = absint($post_id);
        $top_n = max(1, min(20, absint($top_n)));
        $min_score = min(1.0, max(0.1, (float) $min_score));

        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $text = $this->build_post_embedding_text($post);
        $embedding = $this->embeddings_service->generate_embedding($text);
        if (is_wp_error($embedding) || !is_array($embedding) || empty($embedding)) {
            return array();
        }

        $matches = $this->vector_service->query_neighbors(self::VECTOR_NAMESPACE, $embedding, array(
            'top_k' => $top_n + 6,
            'filter' => array(
                'post_type' => 'post',
                'post_status' => 'publish',
            ),
        ));

        if (is_wp_error($matches) || empty($matches)) {
            return array();
        }

        $related = array();
        foreach ($matches as $match) {
            $score = isset($match['score']) ? (float) $match['score'] : 0;
            if ($score < $min_score) {
                continue;
            }

            $match_post_id = 0;
            if (!empty($match['metadata']['post_id'])) {
                $match_post_id = absint($match['metadata']['post_id']);
            } elseif (!empty($match['id']) && preg_match('/post_(\d+)/', (string) $match['id'], $id_match)) {
                $match_post_id = absint($id_match[1]);
            }

            if ($match_post_id <= 0 || $match_post_id === $post_id) {
                continue;
            }

            $related_post = get_post($match_post_id);
            if (!$related_post || $related_post->post_status !== 'publish') {
                continue;
            }

            $related[] = array(
                'post_id' => $match_post_id,
                'title' => get_the_title($match_post_id),
                'score' => round($score, 4),
                'permalink' => get_permalink($match_post_id),
            );

            if (count($related) >= $top_n) {
                break;
            }
        }

        return $related;
    }

    /**
     * Replace text occurrences with internal anchor links.
     *
     * @param string $content Source HTML content.
     * @param array  $related_posts Related post rows.
     * @param int    $max_links Max links to insert.
     * @return string
     */
    private function inject_internal_links($content, $related_posts, $max_links) {
        $max_links = max(1, min(20, absint($max_links)));
        if (trim((string) $content) === '' || empty($related_posts)) {
            return $content;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous_libxml_state = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="aips-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $text_nodes = $xpath->query('//div[@id="aips-root"]//text()[normalize-space(.) != "" and not(ancestor::a)]');

        $inserted = 0;
        $used_targets = array();

        foreach ($text_nodes as $text_node) {
            if ($inserted >= $max_links) {
                break;
            }

            $text_value = $text_node->nodeValue;
            if ($text_value === '') {
                continue;
            }

            foreach ($related_posts as $related) {
                if ($inserted >= $max_links) {
                    break;
                }

                $title = isset($related['post_title']) ? (string) $related['post_title'] : (isset($related['title']) ? (string) $related['title'] : '');
                $url = isset($related['permalink']) ? (string) $related['permalink'] : '';
                $key = strtolower($title);

                if ($title === '' || $url === '' || isset($used_targets[$key])) {
                    continue;
                }

                $pattern = '/\b(' . preg_quote($title, '/') . ')\b/i';
                if (!preg_match($pattern, $text_value)) {
                    continue;
                }

                $replacement = '<a href="' . esc_url($url) . '">$1</a>';
                $new_html = preg_replace($pattern, $replacement, $text_value, 1);
                if (!$new_html || $new_html === $text_value) {
                    continue;
                }

                $fragment = $dom->createDocumentFragment();
                if (!$fragment->appendXML($new_html)) {
                    continue;
                }
                $text_node->parentNode->replaceChild($fragment, $text_node);

                $used_targets[$key] = true;
                $inserted++;
                break;
            }
        }

        $root = $dom->getElementById('aips-root');
        if (!$root) {
            libxml_use_internal_errors($previous_libxml_state);
            return $content;
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        libxml_use_internal_errors($previous_libxml_state);

        return $html;
    }

    /**
     * Load related post rows with permalink.
     *
     * @param array $related_post_ids IDs.
     * @return array
     */
    private function load_related_posts_by_ids($related_post_ids) {
        $related_post_ids = array_values(array_filter(array_map('absint', (array) $related_post_ids)));
        if (empty($related_post_ids)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'post__in' => $related_post_ids,
            'orderby' => 'post__in',
            'numberposts' => count($related_post_ids),
        ));

        $rows = array();
        foreach ($posts as $post) {
            $rows[] = array(
                'post_id' => (int) $post->ID,
                'post_title' => (string) $post->post_title,
                'permalink' => get_permalink($post->ID),
            );
        }

        return $rows;
    }

    /**
     * Shared admin AJAX permission checks.
     *
     * @return void
     */
    private function assert_admin_ajax() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
    }
}
