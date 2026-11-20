<?php
/**
 * Topic Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for Author Topic
 * ideation across 4 stages (Analysis, Topic Ideation, Auto Approval,
 * Persistence), with history container tracking and partial-generation
 * detection.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Generation_Strategy
 */
class AIPS_Topic_Generation_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Author Topic Generation. */
	const STAGE_ANALYSIS       = 'analysis';
	const STAGE_TOPIC_IDEATION = 'topic_ideation';
	const STAGE_AUTO_APPROVAL  = 'auto_approval';
	const STAGE_PERSISTENCE    = 'persistence';

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var object Topics repository
	 */
	private $topics_repository;

	/**
	 * @var object Logs repository
	 */
	private $logs_repository;

	/**
	 * @var object Embeddings service
	 */
	private $embeddings_service;

	/**
	 * @var object Deduplication service
	 */
	private $deduplication_service;

	/**
	 * @var object Feedback repository
	 */
	private $feedback_repository;

	/**
	 * @var object Authors repository
	 */
	private $authors_repository;

	/**
	 * @var object Topic prompt builder
	 */
	private $prompt_builder;

	/**
	 * @var object Embeddings repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Embeddings_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Content_Indexer_Service|null
	 */
	private $indexer_service;

	/**
	 * @var array Last sources selection metadata
	 */
	private $last_sources_selection = array();

	/**
	 * @var WP_Error|null Last WP_Error encountered during a failed run
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry              Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service            Optional AI service.
	 * @param AIPS_Logger_Interface|null              $logger                Optional logger.
	 * @param object|null                             $topics_repository     Optional topics repository.
	 * @param object|null                             $logs_repository       Optional logs repository.
	 * @param object|null                             $embeddings_service    Optional embeddings service.
	 * @param object|null                             $feedback_repository   Optional feedback repository.
	 * @param object|null                             $prompt_builder        Optional prompt builder.
	 * @param object|null                             $deduplication_service Optional deduplication service.
	 * @param object|null                             $authors_repository    Optional authors repository.
	 * @param object|null                             $embeddings_repo       Optional embeddings repository.
	 * @param AIPS_Embeddings_Rate_Limiter|null       $rate_limiter          Optional rate limiter.
	 * @param AIPS_Similarity_Evaluator|null          $similarity_evaluator  Optional similarity evaluator.
	 * @param AIPS_Content_Indexer_Service|null       $indexer_service       Optional indexer service.
	 * @param AIPS_History_Service_Interface|null     $history_service       Optional history service.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		$topics_repository = null,
		$logs_repository = null,
		$embeddings_service = null,
		$feedback_repository = null,
		$prompt_builder = null,
		$deduplication_service = null,
		$authors_repository = null,
		$embeddings_repo = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null,
		?AIPS_Content_Indexer_Service $indexer_service = null,
		?AIPS_History_Service_Interface $history_service = null
	) {
		$container                   = AIPS_Container::get_instance();
		$this->ai_service            = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger                = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->history_service       = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->topics_repository     = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository       = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->authors_repository    = $authors_repository ?: new AIPS_Authors_Repository();
		$this->embeddings_repo       = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->rate_limiter          = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter());
		$this->embeddings_service    = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service($this->ai_service, $this->logger, null, $this->rate_limiter, $this->embeddings_repo));
		$this->deduplication_service = $deduplication_service ?: ($embeddings_service === null && $container->has(AIPS_Deduplication_Service::class) ? $container->make(AIPS_Deduplication_Service::class) : new AIPS_Deduplication_Service($this->embeddings_repo, null, $this->embeddings_service, null, $this->logger));
		$this->similarity_evaluator  = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator(null, $this->embeddings_repo, $this->embeddings_service));
		$this->indexer_service       = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : null);
		$this->feedback_repository   = $feedback_repository ?: new AIPS_Feedback_Repository();
		$this->prompt_builder        = $prompt_builder ?: new AIPS_Prompt_Builder_Topic(
			null,
			new AIPS_Prompt_Builder_Diversity_Injector(null, $this->topics_repository)
		);

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_topic_components(
				$this->ai_service,
				$this->topics_repository,
				$this->embeddings_service,
				$this->embeddings_repo,
				$this->rate_limiter,
				$this->indexer_service
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'author_topics';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Author Topics', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			self::STAGE_ANALYSIS,
			self::STAGE_TOPIC_IDEATION,
			self::STAGE_AUTO_APPROVAL,
			self::STAGE_PERSISTENCE,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_components(AIPS_Generation_Context $context): array {
		$ordered = array();
		foreach ($this->registry->get_by_strategy($this->get_key()) as $component) {
			if ($component->is_enabled($context)) {
				$ordered[] = $component;
			}
		}

		return $ordered;
	}

	/**
	 * {@inheritdoc}
	 */
	public function before_pipeline(AIPS_Generation_Context $context): void {
		$this->last_error             = null;
		$this->last_sources_selection = array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function after_pipeline(AIPS_Generation_Context $context, AIPS_Generation_Result $result): AIPS_Generation_Result {
		return $result;
	}

	/**
	 * Return the last WP_Error encountered during a failed run.
	 *
	 * @return WP_Error|null
	 */
	public function get_last_error(): ?WP_Error {
		return $this->last_error;
	}

	/**
	 * Return source selection metadata captured during the most recent run.
	 *
	 * @return array
	 */
	public function get_last_sources_selection(): array {
		return $this->last_sources_selection;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_run(AIPS_Generation_Context $context): AIPS_Generation_Result {
		$start = microtime(true);
		$this->last_error             = null;
		$this->last_sources_selection = array();

		$author              = ($context instanceof AIPS_Author_Topic_Generation_Context) ? $context->get_author() : null;
		$apply_auto_approval = ($context instanceof AIPS_Author_Topic_Generation_Context) ? $context->should_apply_auto_approval() : true;

		if (!$author || !isset($author->id)) {
			$error            = new WP_Error('invalid_author', __('Invalid author object provided', 'ai-post-scheduler'));
			$this->last_error = $error;
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start);
		}

		$author_name   = isset($author->name) ? (string) $author->name : '';
		$requested_qty = isset($author->topic_generation_quantity) ? max(1, (int) $author->topic_generation_quantity) : 5;
		$trigger = $context->get_creation_method() ?: 'manual';
		// This container only traces the AI exchange. The run itself is recorded by the
		// caller (e.g. AIPS_Author_Topics_Scheduler's 'author_topic_generation' row), so it
		// is stored as an event: it must not count as a post-generation run or carry the
		// 'manual'/'scheduled' creation methods those runs are classified by.
		$history       = $this->history_service->create('topic_generation', array(
			'author_id'       => (int) $author->id,
			'creation_method' => 'topic_generation',
			'record_kind'     => AIPS_History_Record_Kind::EVENT,
		));
		$history_id = ($history && $history->get_id()) ? (int) $history->get_id() : 0;

		$this->logger->log("Starting topic generation for author: {$author_name} (ID: {$author->id})", 'info', array(
			'author_id' => $author->id,
			'quantity'  => $requested_qty,
		));

		if ($history) {
			$history->record(
				'activity',
				sprintf('Starting topic generation for author: %s (requested: %d)', $author_name, $requested_qty),
				array('author_id' => (int) $author->id, 'quantity' => $requested_qty, 'trigger' => $trigger)
			);
		}

		// Stage 1: Analysis (approved/rejected summaries, feedback guidance, prompt & sources selection).
		$approved_topics   = $this->topics_repository->get_approved_summary($author->id, 10);
		$rejected_topics   = $this->topics_repository->get_rejected_summary($author->id, 10);
		$feedback_guidance = $this->build_feedback_guidance_section($author);

		$prompt    = $this->prompt_builder->build($author, $approved_topics, $rejected_topics, $feedback_guidance);
		$selection = $this->prompt_builder->get_sources_selection();
		$this->last_sources_selection = $selection;
		if ($selection) {
			$this->last_sources_selection['snapshots'] = array();
		}
		foreach ($selection['diagnostics'] ?? array() as $diagnostic) {
			$this->logger->log('Source selection warning: ' . $diagnostic['reason'], 'warning', $diagnostic);
		}

		$options = array(
			'temperature' => 0.7,
			'json_schema' => $this->get_topic_json_schema(),
		);
		if ($selection) {
			$submitted = false;
			$options['_aips_on_submission'] = function () use ($selection, &$submitted) {
				if (!$submitted) {
					$submitted = true;
					$this->last_sources_selection = $selection;
					$this->prompt_builder->record_sources_usage($selection);
				}
			};
		}

		if ($history) {
			$history->record(
				'ai_request',
				'Topic ideation prompt sent to AI',
				array('prompt' => $prompt, 'author_id' => (int) $author->id)
			);
		}

		// Stage 2: Topic Ideation.
		$response = $this->ai_service->generate_json($prompt, $options);
		if (is_wp_error($response)) {
			$this->last_error = $response;
			$this->logger->log("Failed to generate topics for author {$author->id}: " . $response->get_error_message(), 'error');
			if ($history) {
				$history->record_error('AI call failed during topic generation', array('author_id' => (int) $author->id), $response);
				$history->complete_failure($response->get_error_message());
			}

			return AIPS_Generation_Result::from_wp_error($response, microtime(true) - $start, $history_id);
		}

		if ($history) {
			$history->record('ai_response', 'AI response received for topic generation', null, $response);
		}

		$topics = $this->parse_json_topics($response, $author);
		if (empty($topics)) {
			$error            = new WP_Error('no_topics_parsed', __('Failed to parse topics from AI response', 'ai-post-scheduler'));
			$this->last_error = $error;
			$this->logger->log("No topics parsed from AI response for author {$author->id}", 'warning');
			if ($history) {
				$history->record_error('No topics could be parsed from AI response', array('author_id' => (int) $author->id));
				$history->complete_failure(__('Failed to parse topics from AI response', 'ai-post-scheduler'));
			}

			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start, $history_id);
		}

		$has_keywords = false;
		foreach ($topics as $t) {
			$meta = !empty($t['metadata']) ? json_decode((string) $t['metadata'], true) : array();
			if (is_array($meta) && !empty($meta['keywords'])) {
				$has_keywords = true;
				break;
			}
		}

		$component_statuses = array(
			'topic_title'     => true,
			'topic_prompt'    => true,
			'topic_keywords'  => $has_keywords,
			'topic_score'     => true,
			'topic_embedding' => true,
		);
		$warnings = array();
		if (!$has_keywords) {
			$warnings[] = __('Topic keywords were omitted from the AI response.', 'ai-post-scheduler');
		}

		// Stage 3: Auto Approval & Fuzzy Duplicate Detection.
		$topics = $this->deduplication_service->evaluate_topics_for_duplicates($topics, $author->id);
		if ($apply_auto_approval) {
			$embedding_degraded = false;
			$topics             = $this->apply_auto_approval_rules($author, $topics, $embedding_degraded);
			if ($embedding_degraded) {
				$component_statuses['topic_embedding'] = false;
				$warnings[]                            = __('Embeddings were unavailable or rate-limited during auto-approval; topics remained pending.', 'ai-post-scheduler');
			}
		}

		// Stage 4: Persistence.
		$saved_topics = array();
		if ($this->topics_repository->create_bulk($topics)) {
			$created_topics = $this->topics_repository->get_latest_by_author($author->id, count($topics));
			$created_topics = array_reverse($created_topics);

			foreach ($created_topics as $topic_obj) {
				$topic_arr      = (array) $topic_obj;
				$saved_topics[] = $topic_arr;

				$meta   = !empty($topic_arr['metadata']) ? json_decode($topic_arr['metadata'], true) : array();
				$status = isset($topic_arr['status']) ? $topic_arr['status'] : 'pending';

				if ($status === 'approved' && !empty($meta['auto_approved'])) {
					/* translators: %s: Auto-approval policy rule name */
					$note = isset($meta['auto_approval_note']) ? $meta['auto_approval_note'] : sprintf(__('Topic auto-approved via %s policy.', 'ai-post-scheduler'), isset($meta['auto_approval_rule']) ? $meta['auto_approval_rule'] : 'auto');
					$this->logs_repository->create(array(
						'author_topic_id' => $topic_arr['id'],
						'action'          => 'approved',
						'user_id'         => null,
						'notes'           => $note,
						'metadata'        => wp_json_encode(array(
							'source' => 'auto_rule',
							'rule'   => isset($meta['auto_approval_rule']) ? $meta['auto_approval_rule'] : 'auto',
							'reason' => isset($meta['auto_approval_reason']) ? $meta['auto_approval_reason'] : '',
						)),
					));
				} elseif ($status === 'rejected' && !empty($meta['auto_rejected'])) {
					/* translators: %s: Auto-rejection policy rule name */
					$note = isset($meta['auto_rejection_note']) ? $meta['auto_rejection_note'] : sprintf(__('Topic auto-rejected via %s policy fallback.', 'ai-post-scheduler'), isset($meta['auto_rejection_rule']) ? $meta['auto_rejection_rule'] : 'auto');
					$this->logs_repository->create(array(
						'author_topic_id' => $topic_arr['id'],
						'action'          => 'rejected',
						'user_id'         => null,
						'notes'           => $note,
						'metadata'        => wp_json_encode(array(
							'source' => 'auto_rule',
							'rule'   => isset($meta['auto_rejection_rule']) ? $meta['auto_rejection_rule'] : 'auto',
							'reason' => isset($meta['auto_rejection_reason']) ? $meta['auto_rejection_reason'] : '',
						)),
					));
				} else {
					$this->logger->log("Created topic: {$topic_arr['topic_title']}", 'info', array(
						'topic_id'  => $topic_arr['id'],
						'author_id' => $author->id,
					));
				}
			}

			$this->authors_repository->update_topic_generation_last_run($author->id, AIPS_DateTime::now()->timestamp());

			// Queue non-rejected topics for continuous background vector indexing via the embedding component.
			$embedding_component = $this->registry->get('topic_embedding', $this->get_key());
			if ($embedding_component instanceof AIPS_Topic_Embedding_Component) {
				$embedding_component->apply($saved_topics, null);
			}
		} else {
			$error            = new WP_Error('db_insert_error', __('Failed to save generated topics to database', 'ai-post-scheduler'));
			$this->last_error = $error;
			$this->logger->log("Failed to bulk create topics for author {$author->id}", 'error');
			if ($history) {
				$history->record_error('Failed to bulk create topics in database', array('author_id' => (int) $author->id));
				$history->complete_failure(__('Failed to save generated topics to database', 'ai-post-scheduler'));
			}

			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start, $history_id);
		}

		$count = count($saved_topics);
		if ($count < $requested_qty) {
			/* translators: 1: Number of generated topics, 2: Number of requested topics */
			$warnings[] = sprintf(
				__('Generated %1$d of %2$d requested topics.', 'ai-post-scheduler'),
				$count,
				$requested_qty
			);
		}

		$is_partial = !empty($warnings) || in_array(false, $component_statuses, true);
		$elapsed    = microtime(true) - $start;

		$this->logger->log("Successfully generated {$count} topics for author {$author->id}", 'info', array(
			'author_id'   => $author->id,
			'topic_count' => $count,
		));

		if ($history) {
			$completion_data = array(
				'author_id'          => (int) $author->id,
				'topic_count'        => $count,
				'requested_quantity' => $requested_qty,
				'component_statuses' => $component_statuses,
			);
			if ($is_partial) {
				$history->complete_partial(implode(' ', $warnings), $completion_data);
			} else {
				$history->complete_success($completion_data);
			}
		}

		return AIPS_Generation_Result::from_data(
			$saved_topics,
			$is_partial ? AIPS_Generation_Result::STATUS_PARTIAL : AIPS_Generation_Result::STATUS_COMPLETED,
			$component_statuses,
			$warnings,
			$history_id,
			$elapsed
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_preview_run(AIPS_Generation_Context $context) {
		$author = ($context instanceof AIPS_Author_Topic_Generation_Context) ? $context->get_author() : null;
		if (!$author || !isset($author->id)) {
			return new WP_Error('invalid_author', __('Invalid author object provided', 'ai-post-scheduler'));
		}

		$approved_topics   = $this->topics_repository->get_approved_summary($author->id, 10);
		$rejected_topics   = $this->topics_repository->get_rejected_summary($author->id, 10);
		$feedback_guidance = $this->build_feedback_guidance_section($author);
		$prompt            = $this->prompt_builder->build($author, $approved_topics, $rejected_topics, $feedback_guidance);

		$response = $this->ai_service->generate_json($prompt, array(
			'temperature' => 0.7,
			'json_schema' => $this->get_topic_json_schema(),
		));
		if (is_wp_error($response)) {
			return $response;
		}

		return $this->parse_json_topics($response, $author);
	}

	/**
	 * Compute or retrieve cached composite reference embedding vector for an Author.
	 *
	 * @param object $author Author object.
	 * @return array|null
	 */
	public function get_author_composite_embedding($author): ?array {
		$embedding_component = $this->registry->get('topic_embedding', $this->get_key());
		if ($embedding_component instanceof AIPS_Topic_Embedding_Component) {
			return $embedding_component->compute_author_composite_embedding($author);
		}

		return null;
	}

	/**
	 * Apply author auto-approval rules to generated topics.
	 *
	 * @param object    $author             Author object.
	 * @param array     $topics             List of topic arrays.
	 * @param bool|null $embedding_degraded Optional output flag set to true if embeddings degraded.
	 * @return array Processed topic arrays.
	 */
	public function apply_auto_approval_rules($author, array $topics, &$embedding_degraded = null): array {
		$embedding_degraded = false;
		$policy             = AIPS_Author_Topic_Approval_Policy::resolve($author);
		$mode               = $policy['mode'];

		if ($mode === 'manual') {
			return $topics;
		}

		$now                 = AIPS_DateTime::now()->timestamp();
		$author_baseline_vec = null;

		if ('similarity' === $mode) {
			if ($this->rate_limiter->is_in_cooldown()) {
				$cooldown_info = $this->rate_limiter->get_cooldown_status();
				$paused_until  = !empty($cooldown_info['paused_until']) ? (int) $cooldown_info['paused_until'] : 0;
				$paused_str    = $paused_until > 0 ? AIPS_DateTime::fromTimestamp($paused_until)->toMysql() : 'unknown';
				$this->logger->log(
					sprintf(
						'Author topic auto-approval skipped: Embeddings API in cooldown until %s. Reason: %s',
						$paused_str,
						$cooldown_info['reason']
					),
					'warning'
				);
				$embedding_degraded = true;
				return $topics;
			}

			$limit_check = $this->rate_limiter->check_limits();
			if (is_wp_error($limit_check)) {
				$this->logger->log(
					sprintf(
						'Author topic auto-approval skipped: Embeddings rate limit quota reached (%s).',
						$limit_check->get_error_message()
					),
					'warning'
				);
				$embedding_degraded = true;
				return $topics;
			}

			$author_baseline_vec = $this->get_author_composite_embedding($author);

			$missing_indices = array();
			$missing_texts   = array();
			foreach ($topics as $idx => $t) {
				$t_meta = isset($t['metadata']) && is_string($t['metadata'])
					? json_decode($t['metadata'], true)
					: (isset($t['metadata']) && is_array($t['metadata']) ? $t['metadata'] : array());
				$has_vec = (!empty($t['embedding']) && is_array($t['embedding']))
					|| (!empty($t_meta['embedding']) && is_array($t_meta['embedding']));
				if (!$has_vec && !empty($t['topic_title'])) {
					$missing_indices[] = $idx;
					$missing_texts[]   = (string) $t['topic_title'];
				}
			}

			if (!empty($missing_texts) && $this->embeddings_service->is_enabled()) {
				$batch_vecs = $this->embeddings_service->batch_generate_embeddings($missing_texts);
				foreach ($missing_indices as $pos => $topic_idx) {
					if (isset($batch_vecs[$pos]) && is_array($batch_vecs[$pos]) && !is_wp_error($batch_vecs[$pos])) {
						$topics[$topic_idx]['embedding'] = $batch_vecs[$pos];
					}
				}
			}
		}

		foreach ($topics as &$topic) {
			$eval_result = $this->similarity_evaluator->evaluate_author_topic_auto_approval($topic, $author, $author_baseline_vec, $policy);

			$topic['status']   = $eval_result['decision'];
			$topic['metadata'] = wp_json_encode($eval_result['meta']);

			if ('approved' === $eval_result['decision'] || 'rejected' === $eval_result['decision']) {
				$topic['reviewed_at'] = $now;
				$topic['reviewed_by'] = 0;
			} else {
				$topic['reviewed_at'] = 0;
				$topic['reviewed_by'] = null;
			}
		}
		unset($topic);

		return $topics;
	}

	/**
	 * Build feedback-derived quality guidance for the topic generation prompt.
	 *
	 * @param object $author Author object from database.
	 * @return string
	 */
	public function build_feedback_guidance_section($author): string {
		$stats = $this->feedback_repository->get_reason_category_statistics($author->id);
		if (empty($stats)) {
			return '';
		}

		$rejection_guidance = array();
		$approval_notes     = array();

		if (!empty($stats['duplicate']['rejected'])) {
			$rejection_guidance[] = 'Topics that are similar or duplicate to previous ones keep getting rejected — generate ideas with clearly distinct angles and fresh perspectives.';
		}

		if (!empty($stats['tone']['rejected'])) {
			$tone_hint            = !empty($author->voice_tone) ? " Stick to a {$author->voice_tone} tone." : '';
			$rejection_guidance[] = "Several topics were rejected for tone mismatch.{$tone_hint} Ensure suggested topics naturally lend themselves to the author's established voice and writing style.";
		}

		$field_niche = !empty($author->field_niche) ? (string) $author->field_niche : (!empty($author->niche) ? (string) $author->niche : '');

		if (!empty($stats['irrelevant']['rejected'])) {
			$rejection_guidance[] = "Multiple topics were rejected as off-topic. Stay strictly within the '{$field_niche}' space — avoid peripheral subjects that do not directly serve this niche.";
		}

		if (!empty($stats['policy']['rejected'])) {
			$rejection_guidance[] = 'Some topics were flagged for policy or content concerns. Avoid controversial, sensitive, or policy-violating subject matter entirely.';
		}

		if (!empty($stats['other']['rejected'])) {
			$rejection_guidance[] = 'Some topics were rejected for miscellaneous reasons. Prioritise well-scoped, clearly defined topics that are easy to execute.';
		}

		if (!empty($stats['duplicate']['approved'])) {
			$approval_notes[] = 'Topics with unique, specific angles tend to be approved.';
		}

		if (!empty($stats['tone']['approved'])) {
			$tone_hint        = !empty($author->voice_tone) ? " in a {$author->voice_tone} tone" : '';
			$approval_notes[] = "Topics that align well with the author's voice{$tone_hint} are consistently approved.";
		}

		if (!empty($stats['irrelevant']['approved'])) {
			$approval_notes[] = "Topics tightly focused on '{$field_niche}' are well received.";
		}

		if (empty($rejection_guidance) && empty($approval_notes)) {
			return '';
		}

		$section = "Quality guidance derived from admin feedback:\n";

		if (!empty($rejection_guidance)) {
			$section .= "Patterns to avoid:\n";
			foreach ($rejection_guidance as $note) {
				$section .= "- {$note}\n";
			}
		}

		if (!empty($approval_notes)) {
			$section .= "Patterns that work well:\n";
			foreach ($approval_notes as $note) {
				$section .= "- {$note}\n";
			}
		}

		return $section . "\n";
	}

	/**
	 * JSON schema for the topic array returned by the AI.
	 *
	 * @return array<string, mixed>
	 */
	public function get_topic_json_schema(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'title'    => array('type' => 'string'),
					'score'    => array('type' => 'integer'),
					'keywords' => array('type' => 'array', 'items' => array('type' => 'string')),
				),
				'required' => array('title', 'score', 'keywords'),
			),
		);
	}

	/**
	 * Parse topics from JSON response into database-ready arrays.
	 *
	 * @param mixed  $json_data Parsed JSON data from AI.
	 * @param object $author    Author object.
	 * @return array
	 */
	public function parse_json_topics($json_data, $author): array {
		$topics = array();

		if (!is_array($json_data)) {
			$this->logger->log("JSON data is not an array for author {$author->id}", 'warning');
			return array();
		}

		$max_qty       = isset($author->topic_generation_quantity) ? (int) $author->topic_generation_quantity : 5;
		$items         = (isset($json_data['topics']) && is_array($json_data['topics'])) ? $json_data['topics'] : $json_data;
		$title_comp    = $this->registry ? $this->registry->get('topic_title', $this->get_key()) : null;
		$score_comp    = $this->registry ? $this->registry->get('topic_score', $this->get_key()) : null;
		$keywords_comp = $this->registry ? $this->registry->get('topic_keywords', $this->get_key()) : null;

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$raw_title = !empty($item['title']) ? $item['title'] : (!empty($item['topic']) ? $item['topic'] : '');
			if (empty($raw_title)) {
				continue;
			}

			$title = ($title_comp instanceof AIPS_Topic_Title_Component)
				? $title_comp->sanitize_value($raw_title)
				: sanitize_text_field($raw_title);

			$score = ($score_comp instanceof AIPS_Topic_Score_Component)
				? $score_comp->sanitize_value(isset($item['score']) ? $item['score'] : 50)
				: (isset($item['score']) ? absint($item['score']) : 50);

			$keywords = ($keywords_comp instanceof AIPS_Topic_Keywords_Component)
				? $keywords_comp->sanitize_value(isset($item['keywords']) ? $item['keywords'] : array())
				: (isset($item['keywords']) && is_array($item['keywords']) ? AIPS_Utilities::sanitize_string_array($item['keywords']) : array());

			if (strlen($title) < 10) {
				continue;
			}

			$topics[] = array(
				'author_id'    => $author->id,
				'topic_title'  => $title,
				'topic_prompt' => '',
				'status'       => 'pending',
				'score'        => $score,
				'metadata'     => wp_json_encode(array(
					'generated_via'   => 'ai_json',
					'generation_date' => AIPS_DateTime::now()->timestamp(),
					'keywords'        => $keywords,
				)),
			);

			if (count($topics) >= $max_qty) {
				break;
			}
		}

		return $topics;
	}
}
