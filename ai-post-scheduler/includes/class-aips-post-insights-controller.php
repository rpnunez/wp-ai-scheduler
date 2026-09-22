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
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repo;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config|null                       $config
	 * @param AIPS_History_Repository_Interface|null $history_repo
	 * @param AIPS_Embeddings_Repository|null        $embeddings_repo
	 * @param AIPS_Relationships_Repository|null     $relationships_repo
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_History_Repository_Interface $history_repo = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null
	) {
		$container = AIPS_Container::get_instance();
		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->history_repo       = $history_repo ?: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());
		$this->embeddings_repo    = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());

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

		$container = AIPS_Container::get_instance();
		/** @var AIPS_Post_Clusters_Service $clusters_service */
		$clusters_service = $container->has(AIPS_Post_Clusters_Service::class)
			? $container->make(AIPS_Post_Clusters_Service::class)
			: new AIPS_Post_Clusters_Service();

		$success = $clusters_service->set_pillar_post($cluster_id, $post_id);

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
		global $wpdb;

		$post = get_post($post_id);
		if (!$post) {
			return array();
		}

		// 1. Embedding status
		$emb_row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, dimensions, created_at FROM {$wpdb->prefix}aips_embeddings WHERE post_id = %d LIMIT 1",
			$post_id
		));

		$is_indexed = !empty($emb_row);
		$embedding_info = array(
			'is_indexed'  => $is_indexed,
			'dimensions'  => $is_indexed ? (int) $emb_row->dimensions : 0,
			'indexed_at'  => $is_indexed ? $emb_row->created_at : null,
			'indexed_str' => $is_indexed ? human_time_diff(strtotime($emb_row->created_at), time()) . ' ' . __('ago', 'ai-post-scheduler') : __('Not indexed', 'ai-post-scheduler'),
		);

		// 2. Generation Context & History
		$history = $this->history_repo->get_by_post_id($post_id);
		$history_info = null;

		if ($history) {
			$author_name = '';
			if (!empty($history->author_id)) {
				$auth = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}aips_authors WHERE id = %d", $history->author_id));
				if ($auth) {
					$author_name = $auth->name;
				}
			}

			$template_title = '';
			if (!empty($history->template_id)) {
				$tpl = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}aips_templates WHERE id = %d", $history->template_id));
				if ($tpl) {
					$template_title = $tpl->title;
				}
			}

			$topic_title = '';
			if (!empty($history->topic_id)) {
				$top = $wpdb->get_row($wpdb->prepare("SELECT topic FROM {$wpdb->prefix}aips_author_topics WHERE id = %d", $history->topic_id));
				if ($top) {
					$topic_title = $top->topic;
				}
			}

			$history_url = AIPS_Admin_Menu_Helper::get_page_url('history', array(
				'post_id'    => $post_id,
				'history_id' => (int) $history->id,
			));

			$history_info = array(
				'id'              => (int) $history->id,
				'author_id'       => (int) $history->author_id,
				'author_name'     => $author_name,
				'template_id'     => (int) $history->template_id,
				'template_title'  => $template_title,
				'topic_id'        => (int) $history->topic_id,
				'topic_title'     => $topic_title,
				'created_at'      => $history->created_at,
				'created_str'     => human_time_diff(strtotime($history->created_at), time()) . ' ' . __('ago', 'ai-post-scheduler'),
				'tokens_used'     => (int) $history->tokens_used,
				'cost'            => (float) $history->cost,
				'creation_method' => $history->creation_method,
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

		// 4. Top Semantic Duplicate Candidates
		$rel_table = $wpdb->prefix . 'aips_relationships';
		$raw_duplicates = $wpdb->get_results($wpdb->prepare(
			"SELECT 
				CASE WHEN post_id_1 = %d THEN post_id_2 ELSE post_id_1 END AS matched_id,
				similarity_score
			 FROM {$rel_table}
			 WHERE (post_id_1 = %d OR post_id_2 = %d)
			   AND relation_type = 'similar'
			 ORDER BY similarity_score DESC
			 LIMIT 5",
			$post_id,
			$post_id,
			$post_id
		));

		$top_duplicates = array();
		$max_similarity = 0.0;

		foreach ($raw_duplicates as $d) {
			$m_id = (int) $d->matched_id;
			$m_post = get_post($m_id);
			if (!$m_post) {
				continue;
			}

			$sim = (float) $d->similarity_score;
			if ($sim > $max_similarity) {
				$max_similarity = $sim;
			}

			$sim_pct = round($sim * 100);
			$risk_level = 'low';
			$risk_label = __('Low Risk', 'ai-post-scheduler');
			if ($sim >= 0.90) {
				$risk_level = 'critical';
				$risk_label = __('Critical Risk', 'ai-post-scheduler');
			} elseif ($sim >= 0.80) {
				$risk_level = 'high';
				$risk_label = __('High Risk', 'ai-post-scheduler');
			} elseif ($sim >= 0.65) {
				$risk_level = 'medium';
				$risk_label = __('Moderate Risk', 'ai-post-scheduler');
			}

			$top_duplicates[] = array(
				'post_id'        => $m_id,
				'title'          => get_the_title($m_id),
				'url'            => get_permalink($m_id),
				'edit_url'       => get_edit_post_link($m_id, ''),
				'post_date'      => get_the_date('', $m_id),
				'similarity'     => $sim,
				'similarity_pct' => $sim_pct,
				'risk_level'     => $risk_level,
				'risk_label'     => $risk_label,
			);
		}

		$overall_risk = 'clean';
		$overall_label = __('Clean', 'ai-post-scheduler');
		if ($max_similarity >= 0.90) {
			$overall_risk = 'critical';
			$overall_label = __('Critical Risk', 'ai-post-scheduler');
		} elseif ($max_similarity >= 0.80) {
			$overall_risk = 'high';
			$overall_label = __('High Risk', 'ai-post-scheduler');
		} elseif ($max_similarity >= 0.65) {
			$overall_risk = 'medium';
			$overall_label = __('Moderate Risk', 'ai-post-scheduler');
		}

		return array(
			'post_id'         => $post_id,
			'title'           => get_the_title($post_id),
			'post_type'       => $post->post_type,
			'post_date'       => get_the_date('', $post_id),
			'embedding'       => $embedding_info,
			'history'         => $history_info,
			'cluster'         => $cluster_info,
			'top_duplicates'  => $top_duplicates,
			'max_similarity'  => $max_similarity,
			'max_similarity_pct' => round($max_similarity * 100),
			'overall_risk'    => $overall_risk,
			'overall_label'   => $overall_label,
		);
	}
}
