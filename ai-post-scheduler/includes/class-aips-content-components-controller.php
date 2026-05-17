<?php
/**
 * Content Components Controller
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Components_Controller
 */
class AIPS_Content_Components_Controller {

	/**
	 * Minimum title length for QA pass.
	 */
	private const MIN_TITLE_LENGTH = 3;

	/**
	 * Minimum content length for QA pass.
	 */
	private const MIN_CONTENT_LENGTH = 40;

	/**
	 * @var AIPS_Content_Components_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Content_Component_Rules_Repository
	 */
	private $rules_repository;

	/**
	 * @var AIPS_Content_Component_Example_Service
	 */
	private $example_service;

	/**
	 * @var AIPS_Content_Component_Rule_Summary_Service
	 */
	private $rule_summary_service;

	/**
	 * @var AIPS_Content_Component_Matcher_Service
	 */
	private $matcher_service;

	/**
	 * @var AIPS_Content_Component_Injection_Service
	 */
	private $injection_service;

	/**
	 * @var AIPS_Content_Component_Analytics_Repository
	 */
	private $analytics_repository;

	/**
	 * Initialize controller.
	 */
	public function __construct() {
		$this->repository           = new AIPS_Content_Components_Repository();
		$this->rules_repository     = new AIPS_Content_Component_Rules_Repository();
		$this->example_service      = new AIPS_Content_Component_Example_Service();
		$this->rule_summary_service = new AIPS_Content_Component_Rule_Summary_Service();
		$this->matcher_service      = new AIPS_Content_Component_Matcher_Service();
		$this->injection_service    = new AIPS_Content_Component_Injection_Service();
		$this->analytics_repository = new AIPS_Content_Component_Analytics_Repository();

		add_action( 'wp_ajax_aips_get_content_components', array( $this, 'ajax_get_content_components' ) );
		add_action( 'wp_ajax_aips_get_content_component', array( $this, 'ajax_get_content_component' ) );
		add_action( 'wp_ajax_aips_save_content_component', array( $this, 'ajax_save_content_component' ) );
		add_action( 'wp_ajax_aips_delete_content_component', array( $this, 'ajax_delete_content_component' ) );
		add_action( 'wp_ajax_aips_toggle_content_component_active', array( $this, 'ajax_toggle_content_component_active' ) );
		add_action( 'wp_ajax_aips_validate_content_component', array( $this, 'ajax_validate_content_component' ) );
		add_action( 'wp_ajax_aips_get_content_component_examples', array( $this, 'ajax_get_content_component_examples' ) );
		add_action( 'wp_ajax_aips_content_components_dry_run', array( $this, 'ajax_content_components_dry_run' ) );
		add_action( 'wp_ajax_aips_content_components_backfill_preview', array( $this, 'ajax_content_components_backfill_preview' ) );
		add_action( 'wp_ajax_aips_content_components_backfill_apply', array( $this, 'ajax_content_components_backfill_apply' ) );
	}

	/**
	 * Authorize AJAX request.
	 *
	 * @return void
	 */
	private function authorize() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Get all content components.
	 *
	 * @return void
	 */
	public function ajax_get_content_components() {
		$this->authorize();

		AIPS_Ajax_Response::success(
			array(
				'components' => $this->normalize_components($this->repository->get_all(false)),
				'counts'     => $this->repository->get_counts(),
			)
		);
	}

	/**
	 * Get one content component.
	 *
	 * @return void
	 */
	public function ajax_get_content_component() {
		$this->authorize();

		$component_id = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		if ($component_id < 1) {
			AIPS_Ajax_Response::error(__('Invalid content component ID.', 'ai-post-scheduler'));
		}

		$component = $this->repository->get_by_id($component_id);
		if (!$component) {
			AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array('component' => $this->normalize_component($component)));
	}

	/**
	 * Save content component.
	 *
	 * @return void
	 */
	public function ajax_save_content_component() {
		$this->authorize();

		$component_id  = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		$title         = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
		$description   = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$component_type = isset($_POST['component_type']) ? sanitize_key(wp_unslash($_POST['component_type'])) : 'custom';
		$content       = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
		$is_active     = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
		$rules         = $this->sanitize_rules(isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '');

		if ($title === '') {
			AIPS_Ajax_Response::error(__('A title is required.', 'ai-post-scheduler'));
		}

		if ($this->repository->title_exists($title, $component_id)) {
			AIPS_Ajax_Response::error(__('A content component with that title already exists.', 'ai-post-scheduler'));
		}

		$qa_result = $this->evaluate_quality($title, $content, $rules);

		$data = array(
			'title'          => $title,
			'slug'           => sanitize_title($title),
			'description'    => $description,
			'status'         => $is_active ? 'active' : 'draft',
			'component_type' => $component_type,
			'content_mode'   => 'html',
			'content'        => $content,
			'content_payload'=> $content,
			'media_payload'  => array(),
			'cta_payload'    => array(),
			'rules_json'     => $rules,
			'qa_status'      => $qa_result['status'],
			'qa_notes'       => $qa_result['notes'],
			'is_active'      => $is_active ? 1 : 0,
		);

		if ($component_id > 0) {
			$existing = $this->repository->get_by_id($component_id);
			if (!$existing) {
				AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
			}

			$result = $this->repository->update($component_id, $data);
			if ($result === false) {
				AIPS_Ajax_Response::error(__('Failed to update content component.', 'ai-post-scheduler'));
			}

			$this->rules_repository->upsert_legacy_rule_for_component($component_id, $rules, $is_active ? true : false, 100);

			$component = $this->repository->get_by_id($component_id);
			AIPS_Ajax_Response::success(
				array(
					'component_id' => $component_id,
					'component'    => $this->normalize_component($component),
					'counts'       => $this->repository->get_counts(),
				),
				__('Content component updated.', 'ai-post-scheduler')
			);
		}

		$new_id = $this->repository->create($data);
		if (!$new_id) {
			AIPS_Ajax_Response::error(__('Failed to create content component.', 'ai-post-scheduler'));
		}

		$this->rules_repository->upsert_legacy_rule_for_component($new_id, $rules, $is_active ? true : false, 100);

		$component = $this->repository->get_by_id($new_id);
		AIPS_Ajax_Response::success(
			array(
				'component_id' => $new_id,
				'component'    => $this->normalize_component($component),
				'counts'       => $this->repository->get_counts(),
			),
			__('Content component created.', 'ai-post-scheduler')
		);
	}

	/**
	 * Delete content component.
	 *
	 * @return void
	 */
	public function ajax_delete_content_component() {
		$this->authorize();

		$component_id = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		if ($component_id < 1) {
			AIPS_Ajax_Response::error(__('Invalid content component ID.', 'ai-post-scheduler'));
		}

		$existing = $this->repository->get_by_id($component_id);
		if (!$existing) {
			AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($component_id);
		if ($result === false || $result < 1) {
			AIPS_Ajax_Response::error(__('Failed to delete content component.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(
			array(
				'counts' => $this->repository->get_counts(),
			),
			__('Content component deleted.', 'ai-post-scheduler')
		);
	}

	/**
	 * Toggle content component active status.
	 *
	 * @return void
	 */
	public function ajax_toggle_content_component_active() {
		$this->authorize();

		$component_id = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		$is_active    = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

		if ($component_id < 1) {
			AIPS_Ajax_Response::error(__('Invalid content component ID.', 'ai-post-scheduler'));
		}

		$existing = $this->repository->get_by_id($component_id);
		if (!$existing) {
			AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
		}

		if ($this->repository->set_active($component_id, $is_active) === false) {
			AIPS_Ajax_Response::error(__('Failed to update content component status.', 'ai-post-scheduler'));
		}

		$component = $this->repository->get_by_id($component_id);

		AIPS_Ajax_Response::success(
			array(
				'component' => $this->normalize_component($component),
				'counts'    => $this->repository->get_counts(),
			),
			__('Content component status updated.', 'ai-post-scheduler')
		);
	}

	/**
	 * Validate content component quality.
	 *
	 * @return void
	 */
	public function ajax_validate_content_component() {
		$this->authorize();

		$title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
		$content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
		$rules   = $this->sanitize_rules(isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '');

		$qa_result = $this->evaluate_quality($title, $content, $rules);

		AIPS_Ajax_Response::success(
			array(
				'qa_status'    => $qa_result['status'],
				'qa_notes'     => $qa_result['notes'],
				'messages'     => $qa_result['messages'],
				'rule_summary' => $this->rule_summary_service->summarize(
					array(
						'title' => $title,
					),
					$rules
				),
			)
		);
	}

	/**
	 * Return five random starter examples for the add-new flow.
	 *
	 * @return void
	 */
	public function ajax_get_content_component_examples() {
		$this->authorize();

		AIPS_Ajax_Response::success(
			array(
				'examples' => $this->example_service->get_random_examples(5),
			)
		);
	}

	/**
	 * Dry-run a component match/injection simulation.
	 *
	 * @return void
	 */
	public function ajax_content_components_dry_run() {
		$this->authorize();

		$post_id      = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$draft_body   = isset($_POST['draft_body']) ? wp_kses_post(wp_unslash($_POST['draft_body'])) : '';
		$post_type    = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
		$author_id    = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$region       = isset($_POST['region']) ? sanitize_text_field(wp_unslash($_POST['region'])) : '';
		$locale       = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : get_locale();
		$categories   = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
		$tags         = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
		$persona      = isset($_POST['author_persona']) ? sanitize_text_field(wp_unslash($_POST['author_persona'])) : '';
		$current_title = isset($_POST['current_title']) ? sanitize_text_field(wp_unslash($_POST['current_title'])) : '';
		$current_type = isset($_POST['current_component_type']) ? sanitize_key(wp_unslash($_POST['current_component_type'])) : '';
		$current_content = isset($_POST['current_content']) ? wp_kses_post(wp_unslash($_POST['current_content'])) : '';
		$current_rules = $this->sanitize_rules(isset($_POST['current_rules']) ? wp_unslash($_POST['current_rules']) : '');

		$post = null;
		if ($post_id > 0) {
			$post = get_post($post_id);
			if (!$post instanceof WP_Post) {
				AIPS_Ajax_Response::error(__('Post not found for dry run.', 'ai-post-scheduler'));
			}
		}

		if ($post instanceof WP_Post) {
			$content = '' !== trim($draft_body) ? $draft_body : (string) $post->post_content;
			$context = AIPS_Content_Component_Run_Context::from_post(
				$post,
				array(
					'content'    => $content,
					'region'     => $region,
					'locale'     => $locale,
					'is_dry_run' => true,
				)
			);
		} else {
			$context = new AIPS_Content_Component_Run_Context(
				array(
					'post_id'         => 0,
					'post_type'       => $post_type,
					'post_status'     => 'draft',
					'author_id'       => $author_id,
					'author_persona'  => $persona,
					'topic'           => '',
					'category_tokens' => $this->csv_tokens($categories),
					'tag_tokens'      => $this->csv_tokens($tags),
					'locale'          => $locale,
					'region'          => '' !== $region ? strtoupper($region) : 'US',
					'content'         => $draft_body,
					'content_length'  => mb_strlen(wp_strip_all_tags($draft_body)),
					'has_headings'    => preg_match('/<h[1-6][^>]*>/i', $draft_body) ? true : false,
					'has_h2'          => preg_match('/<h2[^>]*>/i', $draft_body) ? true : false,
					'policy_tags'     => array(),
					'run_timestamp'   => AIPS_DateTime::now()->timestamp(),
					'site_timezone'   => wp_timezone_string(),
					'is_dry_run'      => true,
				)
			);
			$content = $draft_body;
		}

		$evaluation = $this->matcher_service->evaluate_components_detailed($context);
		$prepared   = $this->injection_service->prepare_content(
			$content,
			$context,
			array(
				'strip_existing_markers' => true,
			)
		);

		if ('' !== $current_title && '' !== $current_content) {
			$current_component = (object) array(
				'id'              => 0,
				'title'           => $current_title,
				'component_type'  => $current_type ? $current_type : 'custom',
				'content_mode'    => 'html',
				'content'         => $current_content,
				'content_payload' => $current_content,
				'status'          => 'active',
			);

			$normalized_rule = $this->rules_repository->normalize_legacy_payload($current_rules);
			$current_rule = array(
				'id'               => 0,
				'component_id'     => 0,
				'priority'         => 1000,
				'placement'        => $normalized_rule['placement'],
				'frequency_mode'   => $normalized_rule['frequency_mode'],
				'max_occurrences'  => $normalized_rule['max_occurrences'],
				'conditions_json'  => $normalized_rule['conditions_json'],
				'exclusions_json'  => $normalized_rule['exclusions_json'],
				'date_window_json' => $normalized_rule['date_window_json'],
				'enabled'          => 1,
			);

			$current_evaluation = $this->matcher_service->evaluate_component_rule($current_component, $current_rule, $context);
			if (!empty($current_evaluation['matched'])) {
				$prepared = $this->injection_service->prepare_manual_component(
					$content,
					$current_component,
					$current_rule,
					$context,
					array(
						'strip_existing_markers' => true,
					)
				);

				array_unshift(
					$evaluation['matched'],
					array(
						'component' => $current_component,
						'rule'      => $current_rule,
						'priority'  => 1000,
					)
				);
			} else {
				array_unshift(
					$evaluation['rejected'],
					array(
						'component' => $current_component,
						'rule'      => $current_rule,
						'reason'    => isset($current_evaluation['reason']) ? $current_evaluation['reason'] : __('Not matched.', 'ai-post-scheduler'),
					)
				);
			}
		}

		foreach ( (array) $evaluation['matched'] as $match ) {
			$this->analytics_repository->record_dry_run( (int) $match['component']->id, true );
		}

		foreach ( (array) $evaluation['rejected'] as $rejection ) {
			$this->analytics_repository->record_dry_run( (int) $rejection['component']->id, false );
		}

		AIPS_Ajax_Response::success(
			array(
				'matched_components'  => $this->normalize_dry_run_matches($evaluation['matched']),
				'rejected_components' => $this->normalize_dry_run_rejections($evaluation['rejected']),
				'before_content'      => $prepared['base'],
				'after_content'       => $prepared['content'],
				'preview_html'        => $this->highlight_component_markers($prepared['content']),
				'diff_summary'        => sprintf(
					/* translators: 1: matched component count, 2: rejected component count */
					__('Matched %1$d component(s) and rejected %2$d component(s).', 'ai-post-scheduler'),
					count($evaluation['matched']),
					count($evaluation['rejected'])
				),
			)
		);
	}

	/**
	 * Preview how many existing posts match and would change if backfilled.
	 *
	 * @return void
	 */
	public function ajax_content_components_backfill_preview() {
		$this->authorize();

		$posts = $this->resolve_backfill_posts();
		if (empty($posts)) {
			AIPS_Ajax_Response::success(array(
				'total_posts'     => 0,
				'matched_posts'   => 0,
				'would_update'    => 0,
				'preview'         => array(),
			));
		}

		$preview       = array();
		$matched_posts = 0;
		$would_update  = 0;

		foreach ($posts as $post) {
			$context = AIPS_Content_Component_Run_Context::from_post(
				$post,
				array(
					'content'    => (string) $post->post_content,
					'is_dry_run' => true,
				)
			);

			$evaluation = $this->matcher_service->evaluate_components_detailed($context);
			$prepared   = $this->injection_service->prepare_content(
				(string) $post->post_content,
				$context,
				array(
					'strip_existing_markers' => true,
				)
			);

			if (!empty($evaluation['matched'])) {
				$matched_posts++;
			}
			if ((string) $prepared['content'] !== (string) $post->post_content) {
				$would_update++;
			}

			$preview[] = array(
				'post_id'           => (int) $post->ID,
				'post_title'        => (string) get_the_title($post),
				'matched_count'     => count($evaluation['matched']),
				'rejected_count'    => count($evaluation['rejected']),
				'will_update'       => (string) $prepared['content'] !== (string) $post->post_content,
			);
		}

		AIPS_Ajax_Response::success(array(
			'total_posts'     => count($posts),
			'matched_posts'   => $matched_posts,
			'would_update'    => $would_update,
			'preview'         => $preview,
		));
	}

	/**
	 * Apply content component backfill to existing posts.
	 *
	 * @return void
	 */
	public function ajax_content_components_backfill_apply() {
		$this->authorize();

		if (!AIPS_Config::get_instance()->is_feature_enabled('content_components_engine', true)) {
			AIPS_Ajax_Response::error(__('Content Components engine is currently disabled.', 'ai-post-scheduler'));
		}

		$posts = $this->resolve_backfill_posts();
		if (empty($posts)) {
			AIPS_Ajax_Response::success(array(
				'total_posts' => 0,
				'updated'     => 0,
				'skipped'     => 0,
				'failed'      => 0,
				'details'     => array(),
			));
		}

		$run_id   = AIPS_Correlation_ID::generate();
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;
		$details  = array();

		foreach ($posts as $post) {
			$context = AIPS_Content_Component_Run_Context::from_post(
				$post,
				array(
					'content' => (string) $post->post_content,
				)
			);

			$prepared = $this->injection_service->prepare_content(
				(string) $post->post_content,
				$context,
				array(
					'strip_existing_markers' => true,
				)
			);

			if ((string) $prepared['content'] === (string) $post->post_content) {
				$skipped++;
				$details[] = array(
					'post_id' => (int) $post->ID,
					'status'  => 'skipped',
					'reason'  => 'unchanged',
				);
				continue;
			}

			$save_result = wp_update_post(
				array(
					'ID'           => (int) $post->ID,
					'post_content' => (string) $prepared['content'],
				),
				true
			);

			if (is_wp_error($save_result)) {
				$failed++;
				$details[] = array(
					'post_id' => (int) $post->ID,
					'status'  => 'failed',
					'reason'  => $save_result->get_error_message(),
				);
				continue;
			}

			$this->injection_service->record_injections_from_content((int) $post->ID, (string) $prepared['content'], $run_id, false);
			$updated++;
			$details[] = array(
				'post_id' => (int) $post->ID,
				'status'  => 'updated',
				'matched' => count((array) $prepared['plan']),
			);
		}

		AIPS_Ajax_Response::success(array(
			'run_id'      => $run_id,
			'total_posts' => count($posts),
			'updated'     => $updated,
			'skipped'     => $skipped,
			'failed'      => $failed,
			'details'     => $details,
		));
	}

	/**
	 * Resolve posts targeted by backfill preview/apply requests.
	 *
	 * @return array<int,WP_Post>
	 */
	private function resolve_backfill_posts() {
		$post_ids_raw = isset($_POST['post_ids']) ? sanitize_text_field(wp_unslash($_POST['post_ids'])) : '';
		$limit        = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
		$post_type    = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';

		$limit = max(1, min(200, $limit));
		$ids   = array_values(array_filter(array_map('absint', explode(',', $post_ids_raw))));

		if (!empty($ids)) {
			$posts = get_posts(array(
				'post_type'      => $post_type,
				'post_status'    => array('publish', 'draft', 'future'),
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => $limit,
			));

			return is_array($posts) ? $posts : array();
		}

		$posts = get_posts(array(
			'post_type'      => $post_type,
			'post_status'    => array('publish', 'draft', 'future'),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'posts_per_page' => $limit,
		));

		return is_array($posts) ? $posts : array();
	}

	/**
	 * Sanitize rules payload.
	 *
	 * @param string $rules_raw JSON string.
	 * @return array
	 */
	private function sanitize_rules($rules_raw) {
		$decoded = json_decode($rules_raw, true);
		if (!is_array($decoded)) {
			$decoded = array();
		}

		$logic = isset($decoded['logic']) ? sanitize_key($decoded['logic']) : 'and';
		if ($logic !== 'or') {
			$logic = 'and';
		}

		$action = isset($decoded['action']) ? sanitize_key($decoded['action']) : 'add_at_end';

		$conditions = array();
		if (isset($decoded['conditions']) && is_array($decoded['conditions'])) {
			foreach ($decoded['conditions'] as $condition) {
				if (!is_array($condition)) {
					continue;
				}

				$values = array();
				if (isset($condition['values']) && is_array($condition['values'])) {
					foreach ($condition['values'] as $value) {
						$value = sanitize_text_field((string) $value);
						if ($value !== '') {
							$values[] = $value;
						}
					}
				}

				$conditions[] = array(
					'field'    => isset($condition['field']) ? sanitize_key($condition['field']) : 'category',
					'operator' => isset($condition['operator']) ? sanitize_key($condition['operator']) : 'is',
					'values'   => $values,
				);
			}
		}

		return array(
			'logic'      => $logic,
			'action'     => $action,
			'conditions' => $conditions,
			'date_window'=> isset($decoded['date_window']) && is_array($decoded['date_window'])
				? array(
					'start'    => isset($decoded['date_window']['start']) ? sanitize_text_field((string) $decoded['date_window']['start']) : '',
					'end'      => isset($decoded['date_window']['end']) ? sanitize_text_field((string) $decoded['date_window']['end']) : '',
					'timezone' => isset($decoded['date_window']['timezone']) ? sanitize_text_field((string) $decoded['date_window']['timezone']) : wp_timezone_string(),
				)
				: array(),
		);
	}

	/**
	 * Evaluate quality gate.
	 *
	 * @param string $title Component title.
	 * @param string $content Component content.
	 * @param array  $rules Rules payload.
	 * @return array
	 */
	private function evaluate_quality($title, $content, $rules) {
		$messages = array();

		if (mb_strlen(trim((string) $title)) < self::MIN_TITLE_LENGTH) {
			$messages[] = __('Title should be at least 3 characters.', 'ai-post-scheduler');
		}

		if (mb_strlen(trim(wp_strip_all_tags((string) $content))) < self::MIN_CONTENT_LENGTH) {
			$messages[] = __('Content should include enough detail for insertion quality.', 'ai-post-scheduler');
		}

		$rules_count = isset($rules['conditions']) && is_array($rules['conditions']) ? count($rules['conditions']) : 0;
		if ($rules_count < 1) {
			$messages[] = __('Add at least one rule condition for auto-insertion.', 'ai-post-scheduler');
		}

		$status = empty($messages) ? 'passed' : 'needs_review';
		$notes  = empty($messages) ? __('QA gate passed.', 'ai-post-scheduler') : implode(' ', $messages);

		return array(
			'status'   => $status,
			'notes'    => $notes,
			'messages' => $messages,
		);
	}

	/**
	 * Normalize one component for API responses.
	 *
	 * @param object $component Raw DB component.
	 * @return array
	 */
	private function normalize_component($component) {
		$rules = json_decode((string) $component->rules_json, true);
		if (!is_array($rules)) {
			$rules = array();
		}

		return array(
			'id'             => (int) $component->id,
			'title'          => (string) $component->title,
			'description'    => (string) $component->description,
			'component_type' => (string) $component->component_type,
			'content'        => (string) $component->content,
			'rules'          => $rules,
			'qa_status'      => (string) $component->qa_status,
			'qa_notes'       => (string) $component->qa_notes,
			'is_active'      => (int) $component->is_active,
			'status'         => isset($component->status) ? (string) $component->status : ((int) $component->is_active === 1 ? 'active' : 'draft'),
			'rule_summary'   => $this->rule_summary_service->summarize(
				array(
					'title' => (string) $component->title,
				),
				$rules
			),
			'analytics'      => $this->get_component_analytics((int) $component->id),
			'created_at'     => (int) $component->created_at,
			'updated_at'     => (int) $component->updated_at,
		);
	}

	/**
	 * Normalize list of components for API responses.
	 *
	 * @param array $components Raw DB components.
	 * @return array
	 */
	private function normalize_components($components) {
		$normalized = array();
		foreach ((array) $components as $component) {
			$normalized[] = $this->normalize_component($component);
		}
		return $normalized;
	}

	/**
	 * @param string $csv CSV-ish user input.
	 * @return string[]
	 */
	private function csv_tokens($csv) {
		return array_values(
			array_filter(
				array_map(
					static function ($value) {
						return strtolower(trim(sanitize_text_field((string) $value)));
					},
					explode(',', (string) $csv)
				)
			)
		);
	}

	/**
	 * Highlight saved marker comments for preview output.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private function highlight_component_markers($html) {
		$html = preg_replace(
			'/<!--\s*aips:component:start:(\d+):([a-f0-9]{64})\s*-->/i',
			'<div class="aips-dry-run-highlight" data-component-id="$1">',
			(string) $html
		);

		return preg_replace(
			'/<!--\s*aips:component:end:(\d+):([a-f0-9]{64})\s*-->/i',
			'</div>',
			(string) $html
		);
	}

	/**
	 * @param array $matches Detailed matcher payload.
	 * @return array
	 */
	private function normalize_dry_run_matches($matches) {
		$normalized = array();
		foreach ((array) $matches as $match) {
			$component = $match['component'];
			$normalized[] = array(
				'id'          => (int) $component->id,
				'title'       => (string) $component->title,
				'type'        => (string) $component->component_type,
				'placement'   => (string) ($match['rule']['placement'] ?? 'end_of_post'),
				'priority'    => (int) ($match['rule']['priority'] ?? 0),
				'rule_summary'=> $this->rule_summary_service->summarize(
					array('title' => (string) $component->title),
					$this->legacy_rules_from_rule_record($match['rule'])
				),
			);
		}

		return $normalized;
	}

	/**
	 * @param array $rejections Detailed matcher payload.
	 * @return array
	 */
	private function normalize_dry_run_rejections($rejections) {
		$normalized = array();
		foreach ((array) $rejections as $rejection) {
			$component = $rejection['component'];
			$normalized[] = array(
				'id'     => (int) $component->id,
				'title'  => (string) $component->title,
				'type'   => (string) $component->component_type,
				'reason' => isset($rejection['reason']) ? (string) $rejection['reason'] : __('Not matched.', 'ai-post-scheduler'),
			);
		}

		return $normalized;
	}

	/**
	 * Convert a Phase 1 rule row back into the simpler legacy UI payload shape.
	 *
	 * @param array $rule Rule record.
	 * @return array
	 */
	private function legacy_rules_from_rule_record($rule) {
		$placement = isset($rule['placement']) ? (string) $rule['placement'] : 'end_of_post';
		$action = 'add_at_end';
		if (0 === strpos($placement, 'after_intro')) {
			$action = 'prepend_intro';
		} elseif (0 === strpos($placement, 'before_content')) {
			$action = 'add_before_first_heading';
		} elseif (0 === strpos($placement, 'after_nth_h2')) {
			$action = 'add_middle_paragraph';
		} elseif (0 === strpos($placement, 'before_conclusion')) {
			$action = 'replace_summary';
		}

		return array(
			'logic'      => isset($rule['conditions_json']['logic']) ? $rule['conditions_json']['logic'] : 'and',
			'action'     => $action,
			'conditions' => isset($rule['conditions_json']['conditions']) ? $rule['conditions_json']['conditions'] : array(),
			'date_window'=> isset($rule['date_window_json']) ? $rule['date_window_json'] : array(),
		);
	}

	/**
	 * @param int $component_id Component ID.
	 * @return array
	 */
	private function get_component_analytics($component_id) {
		$usage_map = $this->analytics_repository->get_usage_map();
		return isset($usage_map[$component_id]) ? $usage_map[$component_id] : array(
			'impressions' => 0,
			'injections'  => 0,
			'regeneration_reinjections' => 0,
			'matched_count' => 0,
			'skipped_conflict_count' => 0,
			'skipped_exclusion_count' => 0,
			'dry_run_matches' => 0,
			'dry_run_total' => 0,
			'dry_run_match_rate' => 0,
			'last_seen_at' => 0,
			'unique_posts' => 0,
			'last_injected_at' => 0,
		);
	}
}
