<?php
/**
 * Ability Enrichment Service
 *
 * Optional post-generation enrichment driven by WordPress Abilities. After a
 * post has been generated and saved, each registered enrichment step may call
 * an installed ability (for example the WordPress AI plugin's
 * `ai/alt-text-generation`) and write the result back to the post.
 *
 * Design rules:
 *  - Opt-in: nothing runs unless `aips_enable_ability_enrichment` is on.
 *  - Never affects generation: every failure is logged and swallowed.
 *  - Never bypasses WP_Ability permission checks: steps run as a real user
 *    (the current user if they can edit the post, otherwise the configured
 *    service user) for the duration of the step only.
 *  - Payloads are trimmed to the ability's registered input schema, so the
 *    scheduler never sends keys an ability does not declare.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Ability_Enrichment_Service {

	/**
	 * Slug of the WordPress AI alt-text ability.
	 */
	const ALT_TEXT_ABILITY = 'ai/alt-text-generation';

	/**
	 * Slug of the WordPress AI content classification ability.
	 */
	const CLASSIFICATION_ABILITY = 'ai/content-classification';

	/**
	 * Taxonomies the classification step may fill.
	 */
	const CLASSIFICATION_TAXONOMIES = array('category', 'post_tag');

	/**
	 * Slugs of the Yoast SEO abilities used by the quality-gate steps.
	 */
	const SEO_SCORE_ABILITY         = 'yoast-seo/get-seo-scores';
	const READABILITY_SCORE_ABILITY = 'yoast-seo/get-readability-scores';

	/**
	 * Post meta keys the scores are recorded under, and the one that flags a post.
	 */
	const SEO_SCORE_META         = '_aips_seo_score';
	const READABILITY_SCORE_META = '_aips_readability_score';
	const QUALITY_FLAG_META      = '_aips_quality_flag';

	/**
	 * @var AIPS_Ability_Service
	 */
	private $abilities;

	/**
	 * @var AIPS_Ability_Catalog_Service
	 */
	private $catalog;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_Ability_Enrichment_Queue_Repository
	 */
	private $queue;

	/**
	 * Wakes the enrichment background process after a post is queued.
	 *
	 * @var callable|null
	 */
	private $kick;

	/**
	 * @param AIPS_Ability_Service|null                       $abilities Ability adapter.
	 * @param AIPS_Ability_Catalog_Service|null               $catalog   Ability catalog.
	 * @param AIPS_Logger_Interface|null                      $logger    Logger.
	 * @param AIPS_Ability_Enrichment_Queue_Repository|null   $queue     Queue of posts waiting for enrichment.
	 * @param callable|null                                   $kick      Wakes the background process; defaults to the registered process.
	 */
	public function __construct(
		?AIPS_Ability_Service $abilities = null,
		?AIPS_Ability_Catalog_Service $catalog = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Ability_Enrichment_Queue_Repository $queue = null,
		?callable $kick = null
	) {
		$container       = AIPS_Container::get_instance();
		$this->abilities = $abilities ?: $container->make(AIPS_Ability_Service::class);
		$this->catalog   = $catalog ?: $container->make(AIPS_Ability_Catalog_Service::class);
		$this->logger    = $logger ?: ($container->has(AIPS_Logger_Interface::class)
			? $container->make(AIPS_Logger_Interface::class)
			: AIPS_Logger::instance());
		$this->queue     = $queue ?: new AIPS_Ability_Enrichment_Queue_Repository();
		$this->kick      = $kick;
	}

	/**
	 * Whether post-generation enrichment is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) AIPS_Config::get_instance()->get_option('aips_enable_ability_enrichment');
	}

	/**
	 * `aips_post_generated` listener. Never throws.
	 *
	 * Only queues the post and wakes the background process: the ability calls
	 * (which are usually AI calls) must not run inside the generation request
	 * or the cron slice that generated the post.
	 *
	 * @param int $post_id Generated post ID.
	 * @return void
	 */
	public function handle_post_generated($post_id) {
		$post_id = absint($post_id);

		if ($post_id <= 0 || !self::is_enabled()) {
			return;
		}

		try {
			$this->queue->enqueue($post_id, get_current_user_id());

			$kicked = $this->wake_process();

			if (is_wp_error($kicked)) {
				$this->logger->log('Ability enrichment could not be started: ' . $kicked->get_error_message(), 'warning', array('post_id' => $post_id));
			}
		} catch (Throwable $e) {
			$this->logger->log('Ability enrichment could not queue the post: ' . $e->getMessage(), 'error', array('post_id' => $post_id));
		}
	}

	/**
	 * Wake the enrichment background process.
	 *
	 * @return true|WP_Error
	 */
	private function wake_process() {
		if ($this->kick) {
			$result = call_user_func($this->kick);

			return is_wp_error($result) ? $result : true;
		}

		$process = AIPS_Container::get_instance()->make(AIPS_Background_Process_Manager::class)->get(AIPS_Ability_Enrichment_Process::KEY);

		return $process instanceof AIPS_Ability_Enrichment_Process
			? $process->kick()
			: new WP_Error('ability_enrichment_process_missing', __('The enrichment background process is not available.', 'ai-post-scheduler'));
	}

	/**
	 * Run every enrichment step against a post, now.
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $user_id User to run as when they may edit the post (the user who
	 *                          triggered the generation); falls back to the current user and
	 *                          then to the configured service user.
	 * @return array<string,string> Step key => outcome (`applied`, `skipped:<reason>` or `error:<code>`).
	 */
	public function enrich_post($post_id, $user_id = null) {
		$post_id = absint($post_id);
		$results = array();

		if ($post_id <= 0 || !get_post($post_id)) {
			return $results;
		}

		/**
		 * Filters the enrichment steps run after a post is generated.
		 *
		 * @param array<string,callable> $steps   Step key => callable( int $post_id ): string|WP_Error.
		 * @param int                    $post_id Generated post ID.
		 */
		$steps = apply_filters('aips_ability_enrichment_steps', array(
			'alt_text'       => array($this, 'step_featured_image_alt_text'),
			'classification' => array($this, 'step_classification'),
			'seo_score'      => array($this, 'step_seo_score'),
			'readability'    => array($this, 'step_readability_score'),
		), $post_id);

		$principal = $this->resolve_principal($post_id, (int) $user_id);

		if (!$principal) {
			$this->logger->log('Ability enrichment skipped: no user with permission to edit the post.', 'warning', array('post_id' => $post_id));
			return array('*' => 'skipped:no_principal');
		}

		$previous_user_id = get_current_user_id();
		wp_set_current_user($principal);

		try {
			foreach ((array) $steps as $key => $callback) {
				if (!is_callable($callback)) {
					continue;
				}

				try {
					$outcome = call_user_func($callback, $post_id);
				} catch (Throwable $e) {
					// One broken step must not stop the steps after it.
					$outcome = new WP_Error('step_exception', $e->getMessage());
				}

				if (is_wp_error($outcome)) {
					$results[$key] = 'error:' . $outcome->get_error_code();
					$this->logger->log('Ability enrichment step failed.', 'warning', array(
						'post_id' => $post_id,
						'step'    => $key,
						'code'    => $outcome->get_error_code(),
						'message' => $outcome->get_error_message(),
					));
					continue;
				}

				$results[$key] = (string) $outcome;
			}
		} finally {
			wp_set_current_user($previous_user_id);
		}

		return $results;
	}

	/**
	 * Generate alt text for the post's featured image when it has none.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Outcome string, or WP_Error when the ability fails.
	 */
	public function step_featured_image_alt_text($post_id) {
		$attachment_id = (int) get_post_thumbnail_id($post_id);

		if ($attachment_id <= 0) {
			return 'skipped:no_featured_image';
		}

		if ('' !== trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true))) {
			return 'skipped:alt_text_present';
		}

		if (true !== $this->abilities->is_available(self::ALT_TEXT_ABILITY)) {
			return 'skipped:ability_unavailable';
		}

		$payload = $this->build_payload(self::ALT_TEXT_ABILITY, array(
			'attachment_id' => $attachment_id,
			'image_url'     => (string) wp_get_attachment_url($attachment_id),
			'context'       => get_the_title($post_id),
		), array('attachment_id', 'image_url'));

		$response = $this->abilities->invoke(self::ALT_TEXT_ABILITY, $payload);

		if (is_wp_error($response)) {
			return $response;
		}

		if (is_array($response) && !empty($response['is_decorative'])) {
			return 'skipped:decorative';
		}

		$alt = $this->extract_string($response, array('alt_text', 'alt'));

		if ('' === $alt) {
			return new WP_Error('ability_enrichment_empty_output', __('The alt text ability returned no alt text.', 'ai-post-scheduler'));
		}

		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));

		return 'applied';
	}

	/**
	 * Suggest and assign tags and categories with `ai/content-classification`.
	 *
	 * Only taxonomies the post type supports and that the post has no real
	 * terms in yet are filled, so terms chosen by the template or the author
	 * are never overwritten. Terms are only ever appended. By default only
	 * terms that already exist are assigned; the classification args filter
	 * can switch to `allow_new`, in which case new terms are created only if
	 * the run user may create terms.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Outcome string, or WP_Error when every attempted taxonomy failed.
	 */
	public function step_classification($post_id) {
		$post_type  = get_post_type($post_id);
		$taxonomies = array_values(array_filter(
			self::CLASSIFICATION_TAXONOMIES,
			function ($taxonomy) use ($post_type) {
				return $post_type && is_object_in_taxonomy($post_type, $taxonomy);
			}
		));

		if (empty($taxonomies)) {
			return 'skipped:no_supported_taxonomy';
		}

		if (true !== $this->abilities->is_available(self::CLASSIFICATION_ABILITY)) {
			return 'skipped:ability_unavailable';
		}

		/**
		 * Filters the classification request settings.
		 *
		 * @param array $args {
		 *     @type string $strategy        `existing_only` (default) or `allow_new`.
		 *     @type int    $max_suggestions Terms requested per taxonomy, 1-10. Default 5.
		 *     @type float  $min_confidence  Suggestions below this confidence are ignored. Default 0.5.
		 * }
		 * @param int   $post_id Generated post ID.
		 */
		$args = wp_parse_args(
			(array) apply_filters('aips_ability_enrichment_classification_args', array(), $post_id),
			array(
				'strategy'        => 'existing_only',
				'max_suggestions' => 5,
				'min_confidence'  => 0.5,
			)
		);

		$args['strategy']        = 'allow_new' === $args['strategy'] ? 'allow_new' : 'existing_only';
		$args['max_suggestions'] = max(1, min(10, (int) $args['max_suggestions']));
		$args['min_confidence']  = (float) $args['min_confidence'];

		$assigned = array();
		$errors   = array();
		$attempts = 0;

		foreach ($taxonomies as $taxonomy) {
			if ($this->post_has_terms($post_id, $taxonomy)) {
				continue;
			}

			$attempts++;

			$payload = $this->build_payload(self::CLASSIFICATION_ABILITY, array(
				'post_id'         => $post_id,
				'taxonomy'        => $taxonomy,
				'strategy'        => $args['strategy'],
				'max_suggestions' => $args['max_suggestions'],
			), array('post_id'));

			$response = $this->abilities->invoke(self::CLASSIFICATION_ABILITY, $payload);

			if (is_wp_error($response)) {
				// With existing_only and no matching term on the site the real ability answers
				// `no_results`: nothing to suggest, not a failure.
				if ('no_results' !== $response->get_error_code()) {
					$errors[$taxonomy] = $response;
				}
				continue;
			}

			$term_ids = $this->resolve_suggested_terms($response, $taxonomy, $args);

			if (!empty($term_ids)) {
				wp_set_object_terms($post_id, $term_ids, $taxonomy, true);
				$assigned[$taxonomy] = count($term_ids);
			}
		}

		if (!empty($assigned)) {
			// Partial failures are logged; the step still counts as applied.
			foreach ($errors as $taxonomy => $error) {
				$this->logger->log('Classification failed for one taxonomy.', 'warning', array(
					'post_id'  => $post_id,
					'taxonomy' => $taxonomy,
					'code'     => $error->get_error_code(),
				));
			}

			return 'applied';
		}

		if (!empty($errors)) {
			return reset($errors);
		}

		return 0 === $attempts ? 'skipped:terms_present' : 'skipped:no_suggestions';
	}

	/**
	 * Whether the post already has real (non-default) terms in a taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private function post_has_terms($post_id, $taxonomy) {
		$term_ids = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));

		if (is_wp_error($term_ids)) {
			return false;
		}

		$term_ids = array_map('intval', $term_ids);

		if ('category' === $taxonomy) {
			$term_ids = array_diff($term_ids, array((int) get_option('default_category')));
		}

		return !empty($term_ids);
	}

	/**
	 * Turn classification suggestions into term IDs.
	 *
	 * @param mixed  $response Normalized ability response.
	 * @param string $taxonomy Taxonomy the suggestions are for.
	 * @param array  $args     Normalized classification settings.
	 * @return int[] Term IDs to assign.
	 */
	private function resolve_suggested_terms($response, $taxonomy, array $args) {
		$suggestions = (is_array($response) && isset($response['suggestions']) && is_array($response['suggestions']))
			? $response['suggestions']
			: array();

		$term_ids = array();

		foreach (array_slice($suggestions, 0, $args['max_suggestions']) as $suggestion) {
			$name = (is_array($suggestion) && isset($suggestion['term']) && is_string($suggestion['term']))
				? trim(sanitize_text_field($suggestion['term']))
				: '';

			if ('' === $name) {
				continue;
			}

			if (isset($suggestion['confidence']) && is_numeric($suggestion['confidence']) && (float) $suggestion['confidence'] < $args['min_confidence']) {
				continue;
			}

			$existing = get_term_by('name', $name, $taxonomy);

			if ($existing) {
				$term_ids[] = (int) $existing->term_id;
				continue;
			}

			if ('allow_new' !== $args['strategy']) {
				continue;
			}

			$taxonomy_object = get_taxonomy($taxonomy);

			if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->edit_terms)) {
				continue;
			}

			$insert_args = array();

			if (is_taxonomy_hierarchical($taxonomy) && !empty($suggestion['parent']) && is_string($suggestion['parent'])) {
				$parent = get_term_by('name', sanitize_text_field($suggestion['parent']), $taxonomy);

				if ($parent) {
					$insert_args['parent'] = (int) $parent->term_id;
				}
			}

			$created = wp_insert_term($name, $taxonomy, $insert_args);

			if (!is_wp_error($created)) {
				$term_ids[] = (int) $created['term_id'];
			}
		}

		return array_values(array_unique($term_ids));
	}

	/**
	 * Pick the user steps run as: the user who triggered the generation, then
	 * the current user, when they may edit the post; otherwise the configured
	 * service user. Each candidate is re-validated on every run.
	 *
	 * @param int $post_id   Post ID.
	 * @param int $preferred User who triggered the generation (0 when unknown).
	 * @return int User ID, or 0 when nobody qualifies.
	 */
	private function resolve_principal($post_id, $preferred = 0) {
		if ($preferred > 0 && get_userdata($preferred) && user_can($preferred, 'edit_post', $post_id)) {
			return $preferred;
		}

		$current = get_current_user_id();

		if ($current > 0 && user_can($current, 'edit_post', $post_id)) {
			return $current;
		}

		/**
		 * Filters the service user for post-generation ability enrichment.
		 *
		 * @param int $user_id Configured service user ID (default from settings).
		 * @param int $post_id Generated post ID.
		 */
		$service_id = (int) apply_filters(
			'aips_ability_enrichment_user_id',
			(int) AIPS_Config::get_instance()->get_option('aips_ability_enrichment_user_id'),
			$post_id
		);

		if ($service_id > 0 && get_userdata($service_id) && user_can($service_id, 'edit_post', $post_id)) {
			return $service_id;
		}

		return 0;
	}

	/**
	 * Trim a candidate payload to the keys the ability's input schema declares.
	 *
	 * When the schema declares none of the preferred keys, the first preferred
	 * key is kept so the ability can still report its own validation error.
	 * With no schema available, only the preferred keys are sent.
	 *
	 * @param string   $slug      Ability slug.
	 * @param array    $candidate Candidate payload.
	 * @param string[] $preferred Keys to fall back to when the schema is unknown.
	 * @return array
	 */
	private function build_payload($slug, array $candidate, array $preferred) {
		$schema     = $this->catalog->get_input_schema($slug);
		$properties = (is_array($schema) && isset($schema['properties']) && is_array($schema['properties']))
			? array_keys($schema['properties'])
			: array();

		if (empty($properties)) {
			return array_intersect_key($candidate, array_flip(array_slice($preferred, 0, 1)));
		}

		$payload = array_intersect_key($candidate, array_flip($properties));

		return empty($payload) ? array_intersect_key($candidate, array_flip(array_slice($preferred, 0, 1))) : $payload;
	}

	/**
	 * Record the Yoast SEO score of the post and flag it when it is below the threshold.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Outcome string, or WP_Error when the ability fails.
	 */
	public function step_seo_score($post_id) {
		return $this->run_score_step(
			$post_id,
			self::SEO_SCORE_ABILITY,
			self::SEO_SCORE_META,
			array('seo_score', 'score', 'content'),
			'aips_ability_enrichment_min_seo_score',
			'seo'
		);
	}

	/**
	 * Record the Yoast readability score of the post and flag it when it is below the threshold.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Outcome string, or WP_Error when the ability fails.
	 */
	public function step_readability_score($post_id) {
		return $this->run_score_step(
			$post_id,
			self::READABILITY_SCORE_ABILITY,
			self::READABILITY_SCORE_META,
			array('readability_score', 'score', 'content'),
			'aips_ability_enrichment_min_readability_score',
			'readability'
		);
	}

	/**
	 * Call a score ability, record the score, and flag the post below a threshold.
	 *
	 * The ability's input and output shapes are not assumed: the payload is
	 * trimmed to the declared input schema (falling back to `post_id`), and the
	 * score is looked for under a few common keys, in a list entry for this
	 * post, or as a bare number. A response without a score from 0 to 100 is an
	 * error, not a silent skip.
	 *
	 * @param int      $post_id       Post ID.
	 * @param string   $ability       Ability slug.
	 * @param string   $meta_key      Post meta the score is recorded under.
	 * @param string[] $score_keys    Keys that may hold the score.
	 * @param string   $threshold_opt Option holding the flag threshold.
	 * @param string   $kind          `seo` or `readability`.
	 * @return string|WP_Error
	 */
	private function run_score_step($post_id, $ability, $meta_key, array $score_keys, $threshold_opt, $kind) {
		if (true !== $this->abilities->is_available($ability)) {
			return 'skipped:ability_unavailable';
		}

		$payload = $this->build_payload($ability, array(
			'post_id'  => $post_id,
			'id'       => $post_id,
			'post_ids' => array($post_id),
		), array('post_id'));

		$response = $this->abilities->invoke($ability, $payload);

		if (is_wp_error($response)) {
			return $response;
		}

		$score = $this->extract_score($response, (int) $post_id, $score_keys);

		if (null === $score) {
			return new WP_Error('ability_enrichment_no_score', __('The score ability returned no score between 0 and 100.', 'ai-post-scheduler'));
		}

		update_post_meta($post_id, $meta_key, $score);

		$threshold = max(0, min(100, (int) AIPS_Config::get_instance()->get_option($threshold_opt)));

		if ($threshold <= 0 || $score >= $threshold) {
			return 'applied';
		}

		update_post_meta($post_id, self::QUALITY_FLAG_META, array(
			'kind'      => $kind,
			'score'     => $score,
			'threshold' => $threshold,
		));

		/**
		 * Filters whether a published or scheduled post flagged by a quality gate is moved back to draft
		 * so it appears in Pending Review. Drafts and pending posts are already in review. Default false.
		 *
		 * @param bool   $demote  Whether to move the post back to draft.
		 * @param int    $post_id Post ID.
		 * @param string $kind    `seo` or `readability`.
		 * @param int    $score   Recorded score.
		 */
		if (apply_filters('aips_ability_enrichment_demote_flagged', false, $post_id, $kind, $score) && in_array(get_post_status($post_id), array('publish', 'future'), true)) {
			wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
		}

		/**
		 * Fires when a quality-gate step flags a post that scored below its threshold.
		 *
		 * @param int    $post_id   Post ID.
		 * @param string $kind      `seo` or `readability`.
		 * @param int    $score     Recorded score.
		 * @param int    $threshold Configured minimum.
		 */
		do_action('aips_ability_enrichment_post_flagged', $post_id, $kind, $score, $threshold);

		return 'applied:flagged';
	}

	/**
	 * Find the score for a post in an ability response.
	 *
	 * @param mixed    $response Normalized response.
	 * @param int      $post_id  Post ID.
	 * @param string[] $keys     Keys that may hold the score.
	 * @param int      $depth    Recursion depth.
	 * @return int|null Score from 0 to 100, or null when there is none.
	 */
	private function extract_score($response, $post_id, array $keys, $depth = 0) {
		if (is_numeric($response)) {
			return ($response >= 0 && $response <= 100) ? (int) round((float) $response) : null;
		}

		if (!is_array($response) || $depth > 4) {
			return null;
		}

		foreach ($keys as $key) {
			if (isset($response[$key]) && is_numeric($response[$key])) {
				return $this->extract_score($response[$key], $post_id, $keys, $depth + 1);
			}
		}

		foreach (array($post_id, (string) $post_id) as $id_key) {
			if (isset($response[$id_key])) {
				$found = $this->extract_score($response[$id_key], $post_id, $keys, $depth + 1);

				if (null !== $found) {
					return $found;
				}
			}
		}

		foreach ($response as $item) {
			if (!is_array($item)) {
				continue;
			}

			$item_id = $item['post_id'] ?? ($item['id'] ?? ($item['ID'] ?? null));

			if (null !== $item_id && (int) $item_id !== $post_id) {
				continue;
			}

			$found = $this->extract_score($item, $post_id, $keys, $depth + 1);

			if (null !== $found) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Read the first non-empty string among the given keys from an ability response.
	 *
	 * @param mixed    $response Normalized response.
	 * @param string[] $keys     Candidate keys.
	 * @return string
	 */
	private function extract_string($response, array $keys) {
		if (!is_array($response)) {
			return is_string($response) ? trim($response) : '';
		}

		foreach ($keys as $key) {
			if (isset($response[$key]) && is_string($response[$key]) && '' !== trim($response[$key])) {
				return trim($response[$key]);
			}
		}

		return '';
	}
}
