<?php
/**
 * REST Editor Controller
 *
 * Exposes REST API endpoints for the Gutenberg block editor semantic link
 * inserter, anchor suggestion sidebar, and SEO link graph metrics.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_REST_Editor_Controller
 *
 * Manages REST routes under the /aips/v1/editor namespace.
 */
class AIPS_REST_Editor_Controller extends WP_REST_Controller {

	/**
	 * Namespace for AIPS REST endpoints.
	 *
	 * @var string
	 */
	protected $namespace = 'aips/v1';

	/**
	 * Resource route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'editor';

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Internal_Link_Inserter_Service
	 */
	private $inserter_service;

	/**
	 * @var AIPS_Link_Graph_Service
	 */
	private $link_graph_service;

	/**
	 * @var AIPS_Content_Links_Repository
	 */
	private $links_repo;

	/**
	 * Initialize the controller and its dependencies.
	 *
	 * @param AIPS_Relationships_Repository|null     $relationships_repo Relationships repository.
	 * @param AIPS_Embeddings_Repository|null        $embeddings_repo    Embeddings repository.
	 * @param AIPS_Embeddings_Service|null           $embeddings_service Embeddings service.
	 * @param AIPS_Internal_Link_Inserter_Service|null $inserter_service   Link inserter service.
	 * @param AIPS_Link_Graph_Service|null           $link_graph_service Link graph service.
	 * @param AIPS_Content_Links_Repository|null     $links_repo         Content links repository.
	 */
	public function __construct(
		$relationships_repo = null,
		$embeddings_repo = null,
		$embeddings_service = null,
		$inserter_service = null,
		$link_graph_service = null,
		$links_repo = null
	) {
		$container                = AIPS_Container::get_instance();
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->embeddings_repo    = $embeddings_repo    ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service());
		$this->inserter_service   = $inserter_service   ?: ($container->has(AIPS_Internal_Link_Inserter_Service::class) ? $container->make(AIPS_Internal_Link_Inserter_Service::class) : new AIPS_Internal_Link_Inserter_Service());
		$this->link_graph_service = $link_graph_service ?: ($container->has(AIPS_Link_Graph_Service::class) ? $container->make(AIPS_Link_Graph_Service::class) : new AIPS_Link_Graph_Service());
		$this->links_repo         = $links_repo         ?: ($container->has(AIPS_Content_Links_Repository::class) ? $container->make(AIPS_Content_Links_Repository::class) : new AIPS_Content_Links_Repository());
	}

	/**
	 * Register the REST routes for the editor sidebar.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/link-suggestions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'get_link_suggestions'),
					'permission_callback' => array($this, 'check_editor_permissions'),
					'args'                => array(
						'post_id' => array(
							'description'       => __('Current post ID being edited, if saved.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'content' => array(
							'description'       => __('Active draft or block text content.', 'ai-post-scheduler'),
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
							'default'           => '',
						),
						'limit' => array(
							'description'       => __('Maximum suggestions to return.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'default'           => 5,
						),
						'min_similarity' => array(
							'description'       => __('Minimum cosine similarity threshold.', 'ai-post-scheduler'),
							'type'              => 'number',
							'sanitize_callback' => function ($val) {
								return (float) $val;
							},
							'default'           => 0.60,
						),
						'query' => array(
							'description'       => __('Search keyword or topic override.', 'ai-post-scheduler'),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
						'target_post_type' => array(
							'description'       => __('Filter suggestions by post type.', 'ai-post-scheduler'),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'default'           => '',
						),
						'sort_by' => array(
							'description'       => __('Sorting criteria: similarity or seo_opportunity.', 'ai-post-scheduler'),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'default'           => 'similarity',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/find-anchors',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'find_anchors'),
					'permission_callback' => array($this, 'check_editor_permissions'),
					'args'                => array(
						'source_content' => array(
							'description'       => __('Draft or block content to analyze.', 'ai-post-scheduler'),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'wp_kses_post',
						),
						'target_post_id' => array(
							'description'       => __('Target post ID to link toward.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'post_id' => array(
							'description'       => __('Source post ID if editing an existing post.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'anchor_text' => array(
							'description'       => __('Optional preferred anchor phrase.', 'ai-post-scheduler'),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
						'limit' => array(
							'description'       => __('Number of anchor locations to return.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'default'           => 3,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post-seo-metrics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array($this, 'get_post_seo_metrics'),
					'permission_callback' => array($this, 'check_editor_permissions'),
					'args'                => array(
						'post_id' => array(
							'description'       => __('Post ID to inspect.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/link-graph-modal-data',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array($this, 'get_link_graph_modal_data'),
					'permission_callback' => array($this, 'check_editor_permissions'),
					'args'                => array(
						'post_id' => array(
							'description'       => __('Current post ID.', 'ai-post-scheduler'),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'target_ids' => array(
							'description'       => __('Target post IDs to include in local topology.', 'ai-post-scheduler'),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for editor actions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public function check_editor_permissions($request) {
		$post_id = absint($request->get_param('post_id'));

		if ($post_id > 0) {
			if (!current_user_can('edit_post', $post_id)) {
				return new WP_Error(
					'rest_forbidden',
					__('You do not have permission to edit this post.', 'ai-post-scheduler'),
					array('status' => rest_authorization_required_code())
				);
			}
			return true;
		}

		if (!current_user_can('edit_posts')) {
			return new WP_Error(
				'rest_forbidden',
				__('You do not have permission to edit posts.', 'ai-post-scheduler'),
				array('status' => rest_authorization_required_code())
			);
		}

		return true;
	}

	/**
	 * Retrieve top semantically related internal link suggestions with SEO metrics.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_link_suggestions($request) {
		$post_id          = absint($request->get_param('post_id'));
		$content          = (string) $request->get_param('content');
		$query            = trim((string) $request->get_param('query'));
		$target_post_type = sanitize_key((string) $request->get_param('target_post_type'));
		$sort_by          = sanitize_key((string) $request->get_param('sort_by') ?: 'similarity');
		$limit            = max(1, min(20, (int) $request->get_param('limit')));
		$min_similarity   = max(0.0, min(1.0, (float) $request->get_param('min_similarity')));

		// Fallback: If content is empty but post_id is provided, retrieve source post content
		if (empty($content) && empty($query) && $post_id > 0) {
			$source_post = get_post($post_id);
			if ($source_post && !empty($source_post->post_content)) {
				$content = $source_post->post_content;
			}
		}

		// Detect already linked URLs in active content
		$existing_urls = array();
		if (!empty($content)) {
			if (preg_match_all('/href=[\'"]([^\'"]+)[\'"]/i', $content, $href_matches)) {
				$existing_urls = array_map('esc_url_raw', $href_matches[1]);
			}
		}

		$candidates = array();

		// Priority 1: Check precomputed relationships if post exists AND no custom search query override
		if ($post_id > 0 && empty($query)) {
			$related_rows = $this->relationships_repo->get_related('post', $post_id, $limit * 3, $min_similarity);

			if (!empty($related_rows)) {
				foreach ($related_rows as $row) {
					$target_id   = (int) $row->target_id;
					$target_post = get_post($target_id);

					if (!$target_post || 'publish' !== $target_post->post_status) {
						continue;
					}

					if (!empty($target_post_type) && $target_post->post_type !== $target_post_type) {
						continue;
					}

					$similarity     = (float) $row->similarity;
					$similarity_pct = (int) round($similarity * 100);
					$excerpt        = !empty($target_post->post_excerpt) ? $target_post->post_excerpt : wp_trim_words($target_post->post_content, 20);

					$candidates[] = array(
						'id'             => $target_id,
						'title'          => html_entity_decode(get_the_title($target_id), ENT_QUOTES, 'UTF-8'),
						'url'            => get_permalink($target_id),
						'post_type'      => $target_post->post_type,
						'similarity'     => $similarity,
						'similarity_pct' => $similarity_pct,
						'excerpt'        => wp_strip_all_tags($excerpt),
						'is_precomputed' => true,
					);
				}
			}
		}

		// Priority 2: On-the-fly vector similarity when query is provided, or no precomputed links, or drafting new content
		$text_to_embed = !empty($query) ? $query : wp_strip_all_tags($content);
		if (empty($candidates) && !empty($text_to_embed)) {
			if (strlen($text_to_embed) >= 3 && $this->embeddings_service->is_embeddings_supported()) {
				$draft_embedding = $this->embeddings_service->generate_embedding(substr($text_to_embed, 0, 1500));

				if (!is_wp_error($draft_embedding) && is_array($draft_embedding)) {
					$supported_types = !empty($target_post_type)
						? array($target_post_type)
						: apply_filters('aips_editor_indexable_post_types', array('post', 'page'));

					$candidate_rows  = $this->embeddings_repo->get_all_for_similarity('post', $supported_types, 'publish');
					$vector_candidates = array();

					foreach ($candidate_rows as $c_row) {
						$c_id = (int) $c_row->object_id;
						if ($post_id > 0 && $c_id === $post_id) {
							continue;
						}

						$c_vec = json_decode($c_row->embedding, true);
						if (!empty($c_vec)) {
							$vector_candidates[] = array(
								'id'        => $c_id,
								'embedding' => $c_vec,
							);
						}
					}

					$nearest = $this->embeddings_service->find_nearest_neighbors($draft_embedding, $vector_candidates, $limit * 3);

					foreach ($nearest as $item) {
						$t_id = (int) $item['id'];
						$sim  = (float) $item['similarity'];

						if ($sim < $min_similarity) {
							continue;
						}

						$target_post = get_post($t_id);
						if (!$target_post || 'publish' !== $target_post->post_status) {
							continue;
						}

						if (!empty($target_post_type) && $target_post->post_type !== $target_post_type) {
							continue;
						}

						$similarity_pct = (int) round($sim * 100);
						$excerpt        = !empty($target_post->post_excerpt) ? $target_post->post_excerpt : wp_trim_words($target_post->post_content, 20);

						$candidates[] = array(
							'id'             => $t_id,
							'title'          => html_entity_decode(get_the_title($t_id), ENT_QUOTES, 'UTF-8'),
							'url'            => get_permalink($t_id),
							'post_type'      => $target_post->post_type,
							'similarity'     => $sim,
							'similarity_pct' => $similarity_pct,
							'excerpt'        => wp_strip_all_tags($excerpt),
							'is_precomputed' => false,
						);
					}
				}
			}
		}

		// Priority 3: Text search fallback when vector similarity yields no results or embeddings are unavailable
		if (empty($candidates)) {
			$search_args = array(
				'post_type'      => !empty($target_post_type) ? $target_post_type : apply_filters('aips_editor_indexable_post_types', array('post', 'page')),
				'post_status'    => 'publish',
				'posts_per_page' => $limit * 2,
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			if (!empty($query)) {
				$search_args['s'] = $query;
			} elseif (!empty($content)) {
				$words = array_filter(explode(' ', preg_replace('/[^\p{L}\p{N}\s]/u', '', wp_strip_all_tags($content))), function ($w) {
					return mb_strlen($w) >= 4;
				});
				if (!empty($words)) {
					$search_args['s'] = implode(' ', array_slice(array_unique($words), 0, 4));
				}
			}

			if ($post_id > 0) {
				$search_args['post__not_in'] = array($post_id);
			}

			$fallback_query = new WP_Query($search_args);
			if ($fallback_query->have_posts()) {
				foreach ($fallback_query->posts as $f_post) {
					$f_id    = (int) $f_post->ID;
					$excerpt = !empty($f_post->post_excerpt) ? $f_post->post_excerpt : wp_trim_words($f_post->post_content, 20);

					$candidates[] = array(
						'id'             => $f_id,
						'title'          => html_entity_decode(get_the_title($f_id), ENT_QUOTES, 'UTF-8'),
						'url'            => get_permalink($f_id),
						'post_type'      => $f_post->post_type,
						'similarity'     => 0.50,
						'similarity_pct' => 50,
						'excerpt'        => wp_strip_all_tags($excerpt),
						'is_precomputed' => false,
					);
				}
			}
		}

		// Batch enrich with SEO Link Graph metrics
		$target_ids     = array_column($candidates, 'id');
		$inbound_counts = !empty($target_ids) ? $this->links_repo->get_inbound_counts($target_ids) : array();

		$enriched_suggestions = array();
		foreach ($candidates as $cand) {
			$t_id          = $cand['id'];
			$t_url         = $cand['url'];
			$inbound_cnt   = (int) ($inbound_counts[$t_id] ?? 0);
			$is_orphan     = ($inbound_cnt === 0);
			$already_linked = in_array($t_url, $existing_urls, true);

			$cross_link = ($post_id > 0)
				? $this->link_graph_service->get_cross_link_relationship($post_id, $t_id)
				: array('is_direct' => false, 'is_two_hop' => false, 'is_co_cited' => false, 'hop_distance' => 0);

			// Calculate SEO opportunity score
			// Base: similarity (e.g. 0.85)
			// Boost: orphan (+0.35), low-inbound (+0.15), already-linked penalty (-0.30)
			$opportunity_score = $cand['similarity'];
			if ($is_orphan) {
				$opportunity_score += 0.35;
			} elseif ($inbound_cnt <= 2) {
				$opportunity_score += 0.15;
			}
			if ($already_linked) {
				$opportunity_score -= 0.30;
			}

			$cand['inbound_count']      = $inbound_cnt;
			$cand['is_orphan']          = $is_orphan;
			$cand['is_already_linked']  = $already_linked;
			$cand['cross_link']         = $cross_link;
			$cand['opportunity_score']  = $opportunity_score;

			$enriched_suggestions[] = $cand;
		}

		// Sort results
		if ('seo_opportunity' === $sort_by) {
			usort($enriched_suggestions, function ($a, $b) {
				if ($a['opportunity_score'] == $b['opportunity_score']) {
					return 0;
				}
				return ($a['opportunity_score'] > $b['opportunity_score']) ? -1 : 1;
			});
		}

		$final_suggestions = array_slice($enriched_suggestions, 0, $limit);

		return rest_ensure_response(array(
			'success'     => true,
			'suggestions' => $final_suggestions,
			'count'       => count($final_suggestions),
		));
	}

	/**
	 * Retrieve SEO Link Metrics for the active post being edited.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_post_seo_metrics($request) {
		$post_id = absint($request->get_param('post_id'));

		$metrics = $this->link_graph_service->calculate_post_seo_metrics($post_id);

		return rest_ensure_response(array(
			'success' => true,
			'metrics' => $metrics,
		));
	}

	/**
	 * Retrieve local micro-topology data for the mini link graph modal.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_link_graph_modal_data($request) {
		$post_id     = absint($request->get_param('post_id'));
		$target_raw  = (string) $request->get_param('target_ids');
		$target_ids  = array_filter(array_map('absint', explode(',', $target_raw)));

		$nodes = array();
		$links = array();
		$node_map = array();

		// Add source node
		if ($post_id > 0) {
			$source_post = get_post($post_id);
			if ($source_post) {
				$nodes[] = array(
					'id'        => $post_id,
					'title'     => html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'),
					'type'      => 'active_draft',
					'is_source' => true,
				);
				$node_map[$post_id] = true;
			}
		}

		// Add target suggestion nodes
		foreach ($target_ids as $t_id) {
			if (isset($node_map[$t_id])) {
				continue;
			}
			$t_post = get_post($t_id);
			if ($t_post) {
				$nodes[] = array(
					'id'        => $t_id,
					'title'     => html_entity_decode(get_the_title($t_id), ENT_QUOTES, 'UTF-8'),
					'type'      => 'suggestion',
					'is_source' => false,
				);
				$node_map[$t_id] = true;
			}
		}

		// Compute edges between the collected nodes in a single batch query
		$all_node_ids = array_keys($node_map);
		$links        = $this->links_repo->get_edges_between_nodes($all_node_ids);

		return rest_ensure_response(array(
			'success' => true,
			'data'    => array(
				'nodes' => $nodes,
				'links' => $links,
			),
		));
	}

	/**
	 * Find natural anchor phrase opportunities for a target post within source text.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response Response object.
	 */
	public function find_anchors($request) {
		$source_content = (string) $request->get_param('source_content');
		$target_post_id = absint($request->get_param('target_post_id'));
		$anchor_text    = sanitize_text_field((string) $request->get_param('anchor_text'));
		$limit          = max(1, min(5, (int) $request->get_param('limit')));

		if (empty($source_content)) {
			return new WP_Error(
				'empty_content',
				__('No source content provided for anchor extraction.', 'ai-post-scheduler'),
				array('status' => 400)
			);
		}

		if (!$target_post_id) {
			return new WP_Error(
				'invalid_target',
				__('Invalid target post ID.', 'ai-post-scheduler'),
				array('status' => 400)
			);
		}

		$result = $this->inserter_service->find_insertion_locations_for_text(
			$source_content,
			$target_post_id,
			$anchor_text,
			$limit
		);

		if (is_wp_error($result)) {
			// Preserve the error's own status when present (e.g. 400/404 from the
			// inserter service); default to 500 only when no status is attached.
			$error_data = $result->get_error_data();
			if (!is_array($error_data) || !isset($error_data['status'])) {
				$result->add_data(array('status' => 500));
			}
			return $result;
		}

		return rest_ensure_response(array(
			'success' => true,
			'data'    => $result,
		));
	}
}
