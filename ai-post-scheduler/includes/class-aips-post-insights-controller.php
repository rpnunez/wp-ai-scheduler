<?php
/**
 * Post Insights Controller.
 *
 * Provides AJAX endpoints for querying single-post AI insights,
 * semantic duplicate matches, on-demand reindexing, and pillar post toggles.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Insights_Controller {

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Post_Insights_Repository
	 */
	private $insights_repo;

	/**
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config|null                   $config
	 * @param AIPS_Post_Insights_Repository|null $insights_repo
	 * @param AIPS_Similarity_Evaluator|null     $similarity_evaluator
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Post_Insights_Repository $insights_repo = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null
	) {
		$container                  = AIPS_Container::get_instance();
		$this->config               = $config ?: AIPS_Config::get_instance();
		$this->insights_repo        = $insights_repo ?: ($container->has(AIPS_Post_Insights_Repository::class) ? $container->make(AIPS_Post_Insights_Repository::class) : new AIPS_Post_Insights_Repository());
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());

		add_action('wp_ajax_aips_get_post_ai_insights', array($this, 'ajax_get_post_ai_insights'));
		add_action('wp_ajax_aips_reindex_single_post', array($this, 'ajax_reindex_single_post'));
		add_action('wp_ajax_aips_toggle_single_pillar', array($this, 'ajax_toggle_single_pillar'));
	}

	/**
	 * Fetch comprehensive AI insights for a single post.
	 *
	 * @return void
	 */
	public function ajax_get_post_ai_insights(): void {
		if (!check_ajax_referer('aips_insights_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::invalid_nonce();
		}

		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::forbidden();
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$post_id || !get_post($post_id)) {
			AIPS_Ajax_Response::invalid_request(__('Invalid post ID provided.', 'ai-post-scheduler'));
		}

		$payload = $this->compile_post_insights($post_id);
		AIPS_Ajax_Response::success($payload);
	}

	/**
	 * Trigger on-demand vector re-indexing for a single post.
	 *
	 * @return void
	 */
	public function ajax_reindex_single_post(): void {
		if (!check_ajax_referer('aips_insights_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::invalid_nonce();
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$post_id || !current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::forbidden();
		}

		$container = AIPS_Container::get_instance();
		/** @var AIPS_Content_Indexer_Service $indexer */
		$indexer = $container->has(AIPS_Content_Indexer_Service::class)
			? $container->make(AIPS_Content_Indexer_Service::class)
			: new AIPS_Content_Indexer_Service();

		$result = $indexer->index_post($post_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), 400);
		}

		$payload = $this->compile_post_insights($post_id);
		$payload['message'] = __('Post re-indexed with fresh vector embeddings successfully.', 'ai-post-scheduler');

		AIPS_Ajax_Response::success($payload);
	}

	/**
	 * Designate or toggle a post as the pillar for its assigned cluster.
	 *
	 * @return void
	 */
	public function ajax_toggle_single_pillar(): void {
		if (!check_ajax_referer('aips_insights_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::invalid_nonce();
		}

		$post_id    = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$cluster_id = isset($_POST['cluster_id']) ? sanitize_text_field(wp_unslash($_POST['cluster_id'])) : '';

		if (!$post_id || !current_user_can('edit_post', $post_id) || empty($cluster_id)) {
			AIPS_Ajax_Response::forbidden();
		}

		$success = $this->similarity_evaluator->set_pillar_post($cluster_id, $post_id);

		if (!$success) {
			AIPS_Ajax_Response::error(__('Failed to designate post as cluster pillar.', 'ai-post-scheduler'), 400);
		}

		$payload = $this->compile_post_insights($post_id);
		$payload['message'] = __('Pillar post updated successfully.', 'ai-post-scheduler');

		AIPS_Ajax_Response::success($payload);
	}

	/**
	 * Compile all AI insights, duplicate matches, and context for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function compile_post_insights(int $post_id): array {
		$post = get_post($post_id);
		if (!$post) {
			return array();
		}

		// 1. Embedding status via repository
		$emb_row = $this->insights_repo->get_post_embedding_status($post_id);

		$is_indexed = !empty($emb_row);
		$indexed_ts = ($is_indexed && !empty($emb_row->indexed_at)) ? (int) $emb_row->indexed_at : 0;
		$embedding_info = array(
			'is_indexed'  => $is_indexed,
			'dimensions'  => $is_indexed ? (int) $emb_row->dimensions : 0,
			'indexed_at'  => $is_indexed ? $indexed_ts : null,
			'indexed_str' => ($is_indexed && $indexed_ts > 0) ? AIPS_DateTime::fromTimestamp($indexed_ts)->toHumanDiff() : __('Not indexed', 'ai-post-scheduler'),
		);

		// 2. Generation Context & History via repository
		$history = $this->insights_repo->get_post_generation_details($post_id);
		$history_info = null;

		if ($history) {
			$history_url = AIPS_Admin_Menu_Helper::get_page_url('history', array(
				'post_id'    => $post_id,
				'history_id' => (int) $history['id'],
			));

			$history_info = array(
				'id'              => (int) $history['id'],
				'author_id'       => (int) $history['author_id'],
				'author_name'     => !empty($history['author_name']) ? $history['author_name'] : '',
				'template_id'     => (int) $history['template_id'],
				'template_title'  => !empty($history['template_title']) ? $history['template_title'] : '',
				'topic_id'        => (int) $history['topic_id'],
				'topic_title'     => !empty($history['topic_title']) ? $history['topic_title'] : '',
				'created_at'      => $history['created_at'],
				'created_str'     => !empty($history['created_at']) ? AIPS_DateTime::formatRelativeOrAbsolute($history['created_at']) : '',
				'tokens_used'     => (int) $history['tokens_used'],
				'cost'            => (float) $history['cost'],
				'creation_method' => $history['creation_method'],
				'history_url'     => $history_url,
			);
		}

		// 3. Post Cluster & Pillar Status
		$saved_clusters = (array) $this->config->get_option('aips_post_clusters', array());
		$cluster_info = null;

		foreach ($saved_clusters as $c_id => $cluster) {
			$members = isset($cluster['member_ids']) ? (array) $cluster['member_ids'] : array();
			if (in_array($post_id, $members, true)) {
				$is_pillar = isset($cluster['pillar_id']) && ((int) $cluster['pillar_id'] === $post_id);
				$cluster_info = array(
					'cluster_id'   => $c_id,
					'name'         => isset($cluster['name']) ? $cluster['name'] : __('Thematic Cluster', 'ai-post-scheduler'),
					'is_pillar'    => $is_pillar,
					'pillar_id'    => isset($cluster['pillar_id']) ? (int) $cluster['pillar_id'] : 0,
					'pillar_title' => isset($cluster['pillar_title']) ? $cluster['pillar_title'] : '',
					'post_count'   => count($members),
				);
				break;
			}
		}

		// 4. Top Semantic Duplicate Candidates via repository
		$raw_duplicates = $this->insights_repo->get_top_duplicates($post_id, 5);

		$top_duplicates = array();
		$max_similarity = 0.0;

		foreach ($raw_duplicates as $d) {
			$m_id = (int) $d->matched_id;
			$m_post = get_post($m_id);
			if (!$m_post) {
				continue;
			}

			$sim = (float) $d->similarity;
			if ($sim > $max_similarity) {
				$max_similarity = $sim;
			}

			$eval = $this->similarity_evaluator->evaluate_similarity($sim, 'post');

			$top_duplicates[] = array(
				'post_id'        => $m_id,
				'title'          => get_the_title($m_id),
				'url'            => get_permalink($m_id),
				'edit_url'       => get_edit_post_link($m_id, ''),
				'post_date'      => get_the_date('', $m_id),
				'similarity'     => $sim,
				'similarity_pct' => $eval['percentage'],
				'risk_level'     => $eval['risk_tier'],
				'risk_label'     => $eval['risk_label'],
				'badge_class'    => $eval['badge_class'],
			);
		}

		$overall_eval = $this->similarity_evaluator->evaluate_similarity($max_similarity, 'post');

		return array(
			'post_id'            => $post_id,
			'title'              => get_the_title($post_id),
			'post_type'          => $post->post_type,
			'post_date'          => get_the_date('', $post_id),
			'embedding'          => $embedding_info,
			'history'            => $history_info,
			'cluster'            => $cluster_info,
			'top_duplicates'     => $top_duplicates,
			'max_similarity'     => $max_similarity,
			'max_similarity_pct' => $overall_eval['percentage'],
			'overall_risk'       => $overall_eval['risk_tier'],
			'overall_label'      => $overall_eval['risk_label'],
			'badge_class'        => $overall_eval['badge_class'],
		);
	}
}
