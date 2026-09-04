<?php
/**
 * REST controller for authors.
 *
 * Routes:
 *   GET    /aips/v1/authors                           List authors (?active_only=)
 *   GET    /aips/v1/authors/{id}                      Fetch one author
 *   POST   /aips/v1/authors                           Create an author
 *   PUT    /aips/v1/authors/{id}                      Update an author
 *   DELETE /aips/v1/authors/{id}                      Delete an author (+ cascade)
 *   GET    /aips/v1/authors/{id}/topics?status=...    Author topics reporter
 *   GET    /aips/v1/authors/{id}/posts                Author generated posts reporter
 *   GET    /aips/v1/authors/{id}/feedback             Author feedback reporter
 *   POST   /aips/v1/authors/suggest                   AI-generated author suggestions
 *   GET    /aips/v1/author-topics/{id}/posts          Posts generated for one topic
 *
 * `aips_generate_topics_now` stays on admin-ajax (long-running AI call).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Authors_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Authors_Repository */
	private $repository;

	/** @var AIPS_Author_Topics_Repository */
	private $topics_repository;

	/** @var AIPS_Author_Topic_Logs_Repository */
	private $logs_repository;

	/** @var AIPS_Feedback_Repository */
	private $feedback_repository;

	protected $rest_base = 'authors';

	public function __construct() {
		parent::__construct();
		$this->repository          = new AIPS_Authors_Repository();
		$this->topics_repository   = new AIPS_Author_Topics_Repository();
		$this->logs_repository     = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'active_only' => array('type' => 'boolean', 'default' => false),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->author_args(true),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/suggest', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'suggest'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => array(
				'site_niche'      => array('type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'),
				'target_audience' => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
				'content_goals'   => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
				'site_url'        => array('type' => 'string', 'default' => '', 'format' => 'uri', 'sanitize_callback' => 'esc_url_raw'),
				'count'           => array('type' => 'integer', 'default' => 3, 'minimum' => 1, 'maximum' => 20),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->author_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/topics', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_author_topics'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => array_merge($this->id_arg(), array(
				'status' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			)),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/posts', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_author_posts'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->id_arg(),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/feedback', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_author_feedback'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->id_arg(),
		));

		register_rest_route($this->namespace, '/author-topics/(?P<id>[\d]+)/posts', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_topic_posts'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->id_arg(),
		));
	}

	// -------------------------------------------------------------------------
	// Authors CRUD
	// -------------------------------------------------------------------------

	public function get_items($request) {
		return $this->respond(array(
			'authors' => $this->repository->get_all((bool) $request->get_param('active_only')),
		));
	}

	public function get_item($request) {
		$author = $this->repository->get_by_id((int) $request['id']);
		if (!$author) {
			return $this->error_not_found(__('Author', 'ai-post-scheduler'));
		}
		return $this->respond(array('author' => $author));
	}

	public function create_item($request) {
		$data = $this->collect_input($request);

		$now = AIPS_DateTime::now()->timestamp();
		$data['topic_generation_next_run'] = $now;
		$data['post_generation_next_run']  = $now;

		$id = $this->repository->create($data);
		if (!$id) {
			return $this->error_server(__('Failed to save author.', 'ai-post-scheduler'));
		}
		return $this->respond_created(array(
			'author_id' => (int) $id,
			'author'    => $this->repository->get_by_id($id),
			'message'   => __('Author saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Author', 'ai-post-scheduler'));
		}
		if (!$this->repository->update($id, $this->collect_input($request))) {
			return $this->error_server(__('Failed to save author.', 'ai-post-scheduler'));
		}
		return $this->respond(array(
			'author_id' => $id,
			'author'    => $this->repository->get_by_id($id),
			'message'   => __('Author saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Author', 'ai-post-scheduler'));
		}

		$topics    = $this->topics_repository->get_by_author($id);
		$topic_ids = array_map(function ($t) { return (int) $t->id; }, $topics);
		if (!empty($topic_ids)) {
			$this->logs_repository->delete_by_topic_ids($topic_ids);
		}
		$this->topics_repository->delete_by_author($id);

		if (!$this->repository->delete($id)) {
			return $this->error_server(__('Failed to delete author.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Author deleted successfully.', 'ai-post-scheduler')));
	}

	// -------------------------------------------------------------------------
	// Reporters
	// -------------------------------------------------------------------------

	public function get_author_topics($request) {
		$author_id = (int) $request['id'];
		if (!$this->repository->get_by_id($author_id)) {
			return $this->error_not_found(__('Author', 'ai-post-scheduler'));
		}

		$status = (string) $request->get_param('status');
		$status_filter = ('' === $status || 'posts_generated' === $status) ? null : $status;
		$topics = $this->topics_repository->get_by_author($author_id, $status_filter);

		$status_counts = $this->topics_repository->get_status_counts($author_id);
		$status_counts['posts_generated'] = (int) $this->logs_repository->count_generated_posts_by_author($author_id);

		$topic_ids = array_map(function ($t) { return (int) $t->id; }, $topics);
		$latest_feedback_by_topic = $this->feedback_repository->get_latest_by_topics($topic_ids);

		foreach ($topics as $topic) {
			$logs = $this->logs_repository->get_by_topic($topic->id);
			$post_count = 0;
			$topic->post_generated_at = null;
			foreach ($logs as $log) {
				if ('post_generated' === $log->action && $log->post_id) {
					$post_count++;
					if (null === $topic->post_generated_at && isset($log->created_at)) {
						$topic->post_generated_at = absint($log->created_at);
					}
				}
			}
			$topic->post_count = $post_count;

			$topic->last_feedback = null;
			if (isset($latest_feedback_by_topic[(int) $topic->id])) {
				$feedback = $latest_feedback_by_topic[(int) $topic->id];
				$topic->last_feedback = array(
					'action'          => $feedback->action,
					'reason_category' => $feedback->reason_category,
					'reason'          => $feedback->reason,
					'created_at'      => $feedback->created_at,
				);
			}

			$topic->potential_duplicate = false;
			$topic->duplicate_match     = '';
			if (!empty($topic->metadata)) {
				$metadata = json_decode($topic->metadata, true);
				if (is_array($metadata) && !empty($metadata['potential_duplicate'])) {
					$topic->potential_duplicate = true;
					$topic->duplicate_match     = isset($metadata['duplicate_match']) ? (string) $metadata['duplicate_match'] : '';
				}
			}
		}

		if ('approved' === $status) {
			$topics = array_values(array_filter($topics, function ($t) {
				return 'approved' === $t->status && (int) $t->post_count === 0;
			}));
		} elseif ('rejected' === $status) {
			$topics = array_values(array_filter($topics, function ($t) {
				return 'rejected' === $t->status && (int) $t->post_count === 0;
			}));
		} elseif ('posts_generated' === $status) {
			$topics = array_values(array_filter($topics, function ($t) {
				return (int) $t->post_count > 0;
			}));
		}

		return $this->respond(array(
			'topics'        => $topics,
			'status_counts' => $status_counts,
		));
	}

	public function get_author_posts($request) {
		$author_id = (int) $request['id'];
		if (!$this->repository->get_by_id($author_id)) {
			return $this->error_not_found(__('Author', 'ai-post-scheduler'));
		}

		$posts    = $this->logs_repository->get_generated_posts_by_author($author_id);
		$post_ids = array();
		foreach ($posts as $post) {
			if ($post->post_id) {
				$post_ids[] = (int) $post->post_id;
			}
		}
		if (!empty($post_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique($post_ids), false, true);
		}

		foreach ($posts as $post) {
			if ($post->post_id) {
				$wp_post = get_post($post->post_id);
				if ($wp_post) {
					$post->post_title  = $wp_post->post_title;
					$post->post_status = $wp_post->post_status;
					$post->post_url    = esc_url_raw((string) get_permalink($wp_post->ID));
					$post->edit_url    = esc_url_raw((string) get_edit_post_link($wp_post->ID, 'raw'));
				}
			}
		}

		return $this->respond(array('posts' => $posts));
	}

	public function get_author_feedback($request) {
		$author_id = (int) $request['id'];
		if (!$this->repository->get_by_id($author_id)) {
			return $this->error_not_found(__('Author', 'ai-post-scheduler'));
		}
		$feedback = $this->feedback_repository->get_by_author($author_id);
		foreach ($feedback as $item) {
			if ($item->user_id) {
				$user = get_userdata($item->user_id);
				$item->user_name = $user ? $user->display_name : __('Unknown', 'ai-post-scheduler');
			} else {
				$item->user_name = __('System', 'ai-post-scheduler');
			}
		}
		return $this->respond(array('feedback' => $feedback));
	}

	public function get_topic_posts($request) {
		$topic_id = (int) $request['id'];
		$topic    = $this->topics_repository->get_by_id($topic_id);
		if (!$topic) {
			return $this->error_not_found(__('Topic', 'ai-post-scheduler'));
		}

		$logs     = $this->logs_repository->get_by_topic($topic_id, 200);
		$post_ids = array();
		foreach ($logs as $log) {
			if ('post_generated' === $log->action && $log->post_id) {
				$post_ids[] = (int) $log->post_id;
			}
		}
		if (!empty($post_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique($post_ids), false, true);
		}

		$posts = array();
		foreach ($logs as $log) {
			if ('post_generated' === $log->action && $log->post_id) {
				$wp_post = get_post($log->post_id);
				if ($wp_post) {
					$view_url = 'publish' === $wp_post->post_status
						? get_permalink($wp_post->ID)
						: get_preview_post_link($wp_post->ID);

					$posts[] = array(
						'post_id'            => $log->post_id,
						'post_title'         => $wp_post->post_title,
						'post_status'        => $wp_post->post_status,
						'post_excerpt'       => wp_strip_all_tags(get_the_excerpt($wp_post->ID)),
						'featured_image_url' => esc_url_raw((string) get_the_post_thumbnail_url($wp_post->ID, 'medium')),
						'date_generated'     => $log->created_at,
						'date_published'     => 'publish' === $wp_post->post_status ? $wp_post->post_date : null,
						'post_url'           => $view_url ? esc_url_raw((string) $view_url) : '',
						'edit_url'           => esc_url_raw((string) get_edit_post_link($wp_post->ID, 'raw')),
					);
				}
			}
		}

		return $this->respond(array(
			'topic' => $topic,
			'posts' => $posts,
		));
	}

	// -------------------------------------------------------------------------
	// Suggestions
	// -------------------------------------------------------------------------

	public function suggest($request) {
		$service     = new AIPS_Author_Suggestions_Service();
		$suggestions = $service->suggest_authors(array(
			'site_niche'      => (string) $request->get_param('site_niche'),
			'target_audience' => (string) $request->get_param('target_audience'),
			'content_goals'   => (string) $request->get_param('content_goals'),
			'site_url'        => (string) $request->get_param('site_url'),
		), (int) $request->get_param('count'));

		if (is_wp_error($suggestions)) {
			return $this->error('aips_invalid_request', $suggestions->get_error_message(), 400);
		}

		do_action('aips_author_suggestions_generated', array(
			'count'      => count($suggestions),
			'site_niche' => (string) $request->get_param('site_niche'),
			'user_id'    => get_current_user_id(),
		));

		return $this->respond(array(
			'suggestions' => $suggestions,
			'message'     => sprintf(
				/* translators: %d: number of author suggestions generated */
				_n('%d author suggestion generated.', '%d author suggestions generated.', count($suggestions), 'ai-post-scheduler'),
				count($suggestions)
			),
		));
	}

	// -------------------------------------------------------------------------
	// Input
	// -------------------------------------------------------------------------

	private function collect_input($request) {
		$source_groups = (array) $request->get_param('source_group_ids');
		return array(
			'name'                                => (string) $request->get_param('name'),
			'field_niche'                         => (string) $request->get_param('field_niche'),
			'description'                         => (string) $request->get_param('description'),
			'keywords'                            => (string) $request->get_param('keywords'),
			'details'                             => (string) $request->get_param('details'),
			'article_structure_id'                => $request->get_param('article_structure_id') ? absint($request->get_param('article_structure_id')) : null,
			'topic_generation_prompt'             => (string) $request->get_param('topic_generation_prompt'),
			'topic_generation_frequency'          => (string) ($request->get_param('topic_generation_frequency') ?: 'weekly'),
			'topic_generation_quantity'           => absint($request->get_param('topic_generation_quantity')) ?: 5,
			'post_generation_frequency'           => (string) ($request->get_param('post_generation_frequency') ?: 'daily'),
			'post_status'                         => (string) ($request->get_param('post_status') ?: 'draft'),
			'post_category'                       => $request->get_param('post_category') ? absint($request->get_param('post_category')) : null,
			'post_tags'                           => (string) $request->get_param('post_tags'),
			'post_author'                         => absint($request->get_param('post_author')) ?: get_current_user_id(),
			'generate_featured_image'             => $request->get_param('generate_featured_image') ? 1 : 0,
			'featured_image_source'               => (string) ($request->get_param('featured_image_source') ?: 'ai_prompt'),
			'voice_tone'                          => (string) $request->get_param('voice_tone'),
			'writing_style'                       => (string) $request->get_param('writing_style'),
			'target_audience'                     => (string) $request->get_param('target_audience'),
			'expertise_level'                     => (string) $request->get_param('expertise_level'),
			'content_goals'                       => (string) $request->get_param('content_goals'),
			'excluded_topics'                     => (string) $request->get_param('excluded_topics'),
			'preferred_content_length'            => (string) $request->get_param('preferred_content_length'),
			'language'                            => (string) ($request->get_param('language') ?: 'en'),
			'max_posts_per_topic'                 => max(1, absint($request->get_param('max_posts_per_topic'))),
			'manual_post_generation_quantity'     => min(AIPS_Author_Post_Generator::MAX_POSTS_PER_RUN, max(1, absint($request->get_param('manual_post_generation_quantity')))),
			'scheduled_post_generation_quantity'  => min(AIPS_Author_Post_Generator::MAX_POSTS_PER_RUN, max(1, absint($request->get_param('scheduled_post_generation_quantity')))),
			'include_sources'                     => $request->get_param('include_sources') ? 1 : 0,
			'affiliate_links_enabled'             => $request->get_param('affiliate_links_enabled') ? 1 : 0,
			'source_group_ids'                    => wp_json_encode(array_map('absint', $source_groups)),
			'is_active'                           => $request->get_param('is_active') ? 1 : 0,
		);
	}

	private function author_args($required) {
		return array(
			'name'                                => array('type' => 'string', 'required' => (bool) $required, 'sanitize_callback' => 'sanitize_text_field'),
			'field_niche'                         => array('type' => 'string', 'required' => (bool) $required, 'sanitize_callback' => 'sanitize_text_field'),
			'description'                         => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
			'keywords'                            => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'details'                             => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
			'article_structure_id'                => array('type' => array('integer', 'null'), 'default' => null),
			'topic_generation_prompt'             => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
			'topic_generation_frequency'          => array('type' => 'string', 'default' => 'weekly', 'sanitize_callback' => 'sanitize_text_field'),
			'topic_generation_quantity'           => array('type' => 'integer', 'default' => 5, 'minimum' => 1),
			'post_generation_frequency'           => array('type' => 'string', 'default' => 'daily', 'sanitize_callback' => 'sanitize_text_field'),
			'post_status'                         => array('type' => 'string', 'default' => 'draft', 'sanitize_callback' => 'sanitize_text_field'),
			'post_category'                       => array('type' => array('integer', 'null'), 'default' => null),
			'post_tags'                           => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'post_author'                         => array('type' => 'integer', 'default' => 0, 'minimum' => 0),
			'generate_featured_image'             => array('type' => 'boolean', 'default' => false),
			'featured_image_source'               => array('type' => 'string', 'default' => 'ai_prompt', 'sanitize_callback' => 'sanitize_text_field'),
			'voice_tone'                          => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'writing_style'                       => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'target_audience'                     => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'expertise_level'                     => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'content_goals'                       => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
			'excluded_topics'                     => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
			'preferred_content_length'            => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'language'                            => array('type' => 'string', 'default' => 'en', 'sanitize_callback' => 'sanitize_text_field'),
			'max_posts_per_topic'                 => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
			'manual_post_generation_quantity'     => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
			'scheduled_post_generation_quantity'  => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
			'include_sources'                     => array('type' => 'boolean', 'default' => false),
			'affiliate_links_enabled'             => array('type' => 'boolean', 'default' => false),
			'source_group_ids'                    => array('type' => 'array', 'default' => array(), 'items' => array('type' => 'integer')),
			'is_active'                           => array('type' => 'boolean', 'default' => false),
		);
	}
}
