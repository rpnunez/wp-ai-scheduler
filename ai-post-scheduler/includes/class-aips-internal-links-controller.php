<?php
/**
 * Internal Links Controller
 *
 * Registers wp_ajax_* hooks, handles request/response lifecycle, and renders
 * the Internal Links admin page. Business logic is delegated to
 * AIPS_Internal_Links_Service; persistence to AIPS_Embeddings_Repository.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Controller
 */
class AIPS_Internal_Links_Controller {

	/**
	 * @var AIPS_Internal_Links_Service
	 */
	private $service;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repository;

	/**
	 * @var AIPS_Pinecone_Client
	 */
	private $pinecone_client;

	/**
	 * Initialize the controller and register AJAX hooks.
	 */
	public function __construct() {
		$this->service               = new AIPS_Internal_Links_Service();
		$this->embeddings_repository = new AIPS_Embeddings_Repository();
		$this->pinecone_client       = new AIPS_Pinecone_Client();

		add_action('wp_ajax_aips_index_single_post',      array($this, 'ajax_index_single_post'));
		add_action('wp_ajax_aips_bulk_index_posts',        array($this, 'ajax_bulk_index_posts'));
		add_action('wp_ajax_aips_find_related_posts',      array($this, 'ajax_find_related_posts'));
		add_action('wp_ajax_aips_preview_links',           array($this, 'ajax_preview_links'));
		add_action('wp_ajax_aips_save_links',              array($this, 'ajax_save_links'));
		add_action('wp_ajax_aips_get_index_status',        array($this, 'ajax_get_index_status'));
		add_action('wp_ajax_aips_test_pinecone_connection', array($this, 'ajax_test_pinecone_connection'));
		add_action('wp_ajax_aips_get_indexing_progress',   array($this, 'ajax_get_indexing_progress'));
		add_action('wp_ajax_aips_search_posts_for_linking', array($this, 'ajax_search_posts_for_linking'));
	}

	// -----------------------------------------------------------------------
	// Page render
	// -----------------------------------------------------------------------

	/**
	 * Render the Internal Links admin page.
	 */
	public function render_page() {
		$total_published = (int) wp_count_posts('post')->publish;
		$total_indexed   = $this->embeddings_repository->count_by_status(AIPS_Embeddings_Repository::STATUS_INDEXED);
		$total_pending   = $this->embeddings_repository->count_by_status(AIPS_Embeddings_Repository::STATUS_PENDING);
		$total_error     = $this->embeddings_repository->count_by_status(AIPS_Embeddings_Repository::STATUS_ERROR);

		$pinecone_configured = $this->pinecone_client->is_configured();

		$default_top_n     = (int) get_option('aips_internal_links_top_n', 10);
		$default_min_score = (float) get_option('aips_internal_links_min_score', 0.75);

		include AIPS_PLUGIN_DIR . 'templates/admin/internal-links.php';
	}

	// -----------------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------------

	/**
	 * Index a single post's embedding immediately.
	 */
	public function ajax_index_single_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		$result = $this->service->index_post($post_id);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		$record = $this->embeddings_repository->get_by_post_id($post_id);

		wp_send_json_success(array(
			'message'      => __('Post indexed successfully.', 'ai-post-scheduler'),
			'index_status' => $record ? $record->index_status : AIPS_Embeddings_Repository::STATUS_INDEXED,
			'indexed_at'   => $record ? $record->indexed_at : current_time('mysql'),
		));
	}

	/**
	 * Queue all published posts for indexing (marks them pending; cron processes them).
	 */
	public function ajax_bulk_index_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$stats = $this->service->index_all_published();

		wp_send_json_success(array(
			'message' => sprintf(
				/* translators: 1: number of posts queued */
				__('%d posts queued for indexing. The batch worker will process them shortly.', 'ai-post-scheduler'),
				(int) $stats['queued']
			),
			'queued'  => $stats['queued'],
		));
	}

	/**
	 * Find semantically related posts for a given post.
	 */
	public function ajax_find_related_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$top_n     = isset($_POST['top_n']) ? absint($_POST['top_n']) : null;
		$min_score = isset($_POST['min_score']) ? (float) $_POST['min_score'] : null;

		// Clamp min_score to valid range
		if (!is_null($min_score)) {
			$min_score = max(0.0, min(1.0, $min_score));
		}

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		$related = $this->service->find_related_posts($post_id, $top_n, $min_score);

		if (is_wp_error($related)) {
			wp_send_json_error(array('message' => $related->get_error_message()));
		}

		wp_send_json_success(array(
			'related' => $related,
			'count'   => count($related),
		));
	}

	/**
	 * AI-rewrite post content with internal links (preview — does NOT save).
	 */
	public function ajax_preview_links() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		// Sanitize related_posts array from JS
		$raw_related = isset($_POST['related_posts']) ? (array) $_POST['related_posts'] : array();
		$related     = $this->sanitize_related_posts($raw_related);

		if (empty($related)) {
			wp_send_json_error(array('message' => __('No valid related posts provided.', 'ai-post-scheduler')));
		}

		$new_content = $this->service->inject_links($post_id, $related);

		if (is_wp_error($new_content)) {
			wp_send_json_error(array('message' => $new_content->get_error_message()));
		}

		wp_send_json_success(array(
			'content' => wp_kses_post($new_content),
		));
	}

	/**
	 * Save AI-rewritten content (with internal links) to a post.
	 */
	public function ajax_save_links() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		// The content comes from the AI preview; we re-sanitize server-side
		$new_content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

		if (empty(trim($new_content))) {
			wp_send_json_error(array('message' => __('No content provided.', 'ai-post-scheduler')));
		}

		$result = $this->service->save_links($post_id, $new_content);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success(array(
			'message'   => __('Links saved successfully.', 'ai-post-scheduler'),
			'edit_link' => get_edit_post_link($post_id, 'raw'),
		));
	}

	/**
	 * Return paginated index status data for the admin table.
	 */
	public function ajax_get_index_status() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$page     = isset($_GET['page_num']) ? absint($_GET['page_num']) : 1;
		$per_page = 20;
		$status   = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
		$search   = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

		$result = $this->embeddings_repository->get_paginated(array(
			'page'     => $page,
			'per_page' => $per_page,
			'status'   => $status,
			'search'   => $search,
		));

		$items = array();
		foreach ($result['items'] as $row) {
			$items[] = array(
				'post_id'      => (int) $row->post_id,
				'post_title'   => $row->post_title ?: __('(no title)', 'ai-post-scheduler'),
				'post_type'    => $row->post_type ?: 'post',
				'index_status' => $row->index_status,
				'indexed_at'   => $row->indexed_at,
				'error_msg'    => $row->error_msg,
				'edit_link'    => get_edit_post_link((int) $row->post_id, 'raw'),
			);
		}

		wp_send_json_success(array(
			'items'      => $items,
			'total'      => (int) $result['total'],
			'page'       => $page,
			'per_page'   => $per_page,
			'total_pages' => (int) ceil($result['total'] / $per_page),
		));
	}

	/**
	 * Test the Pinecone connection using describe_index_stats.
	 */
	public function ajax_test_pinecone_connection() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		if (!$this->pinecone_client->is_configured()) {
			wp_send_json_error(array('message' => __('Pinecone API key and index name are not configured.', 'ai-post-scheduler')));
		}

		$stats = $this->pinecone_client->describe_index_stats();

		if (is_wp_error($stats)) {
			wp_send_json_error(array('message' => $stats->get_error_message()));
		}

		$vector_count = isset($stats['totalVectorCount']) ? (int) $stats['totalVectorCount'] : 0;

		wp_send_json_success(array(
			'message'      => __('Connection successful!', 'ai-post-scheduler'),
			'vector_count' => $vector_count,
		));
	}

	/**
	 * Return pending/indexed/error counts for bulk indexing progress polling.
	 */
	public function ajax_get_indexing_progress() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array(
			'pending' => $this->embeddings_repository->count_by_status(AIPS_Embeddings_Repository::STATUS_PENDING),
			'indexed' => $this->embeddings_repository->count_by_status(AIPS_Embeddings_Repository::STATUS_INDEXED),
			'error'   => $this->embeddings_repository->count_by_status(AIPS_Embeddings_Repository::STATUS_ERROR),
		));
	}

	/**
	 * Search published posts by title for the post-selector autocomplete.
	 */
	public function ajax_search_posts_for_linking() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

		$posts = get_posts(array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		));

		$items = array();
		foreach ($posts as $post) {
			$items[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20, '…'),
			);
		}

		wp_send_json_success(array('posts' => $items));
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Sanitize and validate a related_posts array received from JavaScript.
	 *
	 * Each element must have an integer post_id, a string title, and a URL
	 * permalink, and the post must exist and be published.
	 *
	 * @param array $raw_related Raw array from $_POST.
	 * @return array Sanitized and validated related posts.
	 */
	private function sanitize_related_posts(array $raw_related) {
		$sanitized = array();

		foreach ($raw_related as $item) {
			if (!is_array($item)) {
				continue;
			}

			$post_id   = isset($item['post_id']) ? absint($item['post_id']) : 0;
			$title     = isset($item['title']) ? sanitize_text_field(wp_unslash($item['title'])) : '';
			$permalink = isset($item['permalink']) ? esc_url_raw(wp_unslash($item['permalink'])) : '';

			if (!$post_id || empty($title) || empty($permalink)) {
				continue;
			}

			// Verify the post exists and is published
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'publish') {
				continue;
			}

			$sanitized[] = array(
				'post_id'   => $post_id,
				'title'     => $title,
				'permalink' => $permalink,
			);
		}

		return $sanitized;
	}
}
