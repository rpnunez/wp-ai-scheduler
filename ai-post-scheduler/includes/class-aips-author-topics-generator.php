<?php
/**
 * Author Topics Generator
 *
 * Generates topic ideas using AI for an Author based on their field/niche.
 * Delegates execution through AIPS_Generation_Pipeline and
 * AIPS_Topic_Generation_Strategy while preserving full backward compatibility
 * for legacy callers and unit tests.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Generator
 *
 * Generates AI-powered topic suggestions for authors.
 */
class AIPS_Author_Topics_Generator {

	/**
	 * @var array Selection metadata for the most recent run.
	 */
	private $last_sources_selection = array();

	/**
	 * @var AIPS_Topic_Generation_Strategy
	 */
	private $strategy;

	/**
	 * @var AIPS_Generation_Pipeline
	 */
	private $pipeline;

	/**
	 * @var object Topics repository
	 */
	private $topics_repository;

	/**
	 * @var object Feedback repository
	 */
	private $feedback_repository;

	/**
	 * Selection metadata for the most recent run; preserves the topic-array return contract.
	 *
	 * @return array
	 */
	public function get_last_sources_selection() {
		return $this->last_sources_selection;
	}

	/**
	 * Initialize the generator.
	 *
	 * @param AIPS_AI_Service_Interface|null    $ai_service            AI service instance (optional for testing).
	 * @param AIPS_Logger_Interface|null        $logger                Logger instance (optional for testing).
	 * @param object|null                       $topics_repository     Topics repository (optional for testing).
	 * @param object|null                       $logs_repository       Logs repository (optional for testing).
	 * @param object|null                       $embeddings_service    Embeddings service (optional for testing).
	 * @param object|null                       $feedback_repository   Feedback repository (optional for testing).
	 * @param object|null                       $prompt_builder        Topic prompt builder (optional for testing).
	 * @param object|null                       $deduplication_service Deduplication service (optional for testing).
	 * @param object|null                       $authors_repository    Authors repository (optional for testing).
	 * @param object|null                       $embeddings_repo       Embeddings repository (optional for testing).
	 * @param AIPS_Embeddings_Rate_Limiter|null $rate_limiter          Rate limiter (optional for testing).
	 * @param AIPS_Similarity_Evaluator|null    $similarity_evaluator  Similarity evaluator (optional for testing).
	 * @param AIPS_Content_Indexer_Service|null $indexer_service       Indexer service (optional for testing).
	 */
	public function __construct(
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
		?AIPS_Content_Indexer_Service $indexer_service = null
	) {
		$this->topics_repository   = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->feedback_repository = $feedback_repository ?: new AIPS_Feedback_Repository();

		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_topic_components(
			$ai_service,
			$this->topics_repository,
			$embeddings_service,
			$embeddings_repo,
			$rate_limiter,
			$indexer_service
		);

		$this->strategy = new AIPS_Topic_Generation_Strategy(
			$registry,
			$ai_service,
			$logger,
			$this->topics_repository,
			$logs_repository,
			$embeddings_service,
			$this->feedback_repository,
			$prompt_builder,
			$deduplication_service,
			$authors_repository,
			$embeddings_repo,
			$rate_limiter,
			$similarity_evaluator,
			$indexer_service
		);

		$this->pipeline = new AIPS_Generation_Pipeline($this->strategy, $registry);
	}

	/**
	 * Generate topics for an author and return the strongly-typed AIPS_Generation_Result DTO.
	 *
	 * @param object $author              Author object from database.
	 * @param bool   $apply_auto_approval Optional. Whether to apply author auto-approval rules. Default true.
	 * @return AIPS_Generation_Result
	 */
	public function generate_topics_result($author, bool $apply_auto_approval = true): AIPS_Generation_Result {
		$context                      = new AIPS_Author_Topic_Generation_Context($author, $apply_auto_approval);
		$result                       = $this->pipeline->execute($context);
		$this->last_sources_selection = $this->strategy->get_last_sources_selection();

		return $result;
	}

	/**
	 * Generate topics for an author.
	 *
	 * @param object $author              Author object from database.
	 * @param bool   $apply_auto_approval Optional. Whether to apply author auto-approval rules. Default true.
	 * @return array|WP_Error Array of generated topics or WP_Error on failure.
	 */
	public function generate_topics($author, $apply_auto_approval = true) {
		$result = $this->generate_topics_result($author, (bool) $apply_auto_approval);

		if ($result->is_failure()) {
			$last_error = $this->strategy->get_last_error();
			if ($last_error instanceof WP_Error) {
				return $last_error;
			}

			$errors  = $result->get_errors();
			$message = !empty($errors) ? $errors[0] : __('Topic generation failed.', 'ai-post-scheduler');
			return new WP_Error('topic_generation_failed', $message);
		}

		$data = $result->get_data();
		return is_array($data) ? $data : array();
	}

	/**
	 * Whether a pending topic should get another auto-approval pass.
	 *
	 * True when no auto rule has judged it yet, or when its niche relevance
	 * could not be measured (embeddings outage). Topics already judged by a
	 * rule (e.g. score below threshold) are left for human review.
	 *
	 * @param string|array|null $metadata Topic metadata (JSON string or decoded array).
	 * @return bool
	 */
	public static function is_approval_recheck_candidate($metadata): bool {
		$meta = is_string($metadata) && '' !== $metadata ? json_decode($metadata, true) : $metadata;
		$meta = is_array($meta) ? $meta : array();

		if (empty($meta['auto_approval_rule'])) {
			return true;
		}

		return isset($meta['auto_approval_reason']) && 'relevance_unavailable' === $meta['auto_approval_reason'];
	}

	/**
	 * Re-run auto-approval on an author's pending topics that never got a decision.
	 *
	 * Auto-approval normally runs once, when topics are created. Topics skipped
	 * then (embeddings cooldown or quota, relevance not measurable, or created
	 * while the policy was manual) would stay pending forever. This picks up to
	 * $limit of them, oldest first, and applies the author's current policy.
	 * Topics already judged by an auto rule (e.g. score below threshold) are left
	 * for human review.
	 *
	 * @param object   $author Author object.
	 * @param int|null $limit  Maximum topics to decide; null uses the filtered default (20).
	 * @return array{checked:int,approved:int,rejected:int} Counts.
	 */
	public function recheck_pending_auto_approval($author, $limit = null): array {
		return $this->strategy->recheck_pending_auto_approval($author, $limit);
	}

	/**
	 * Flag semantically similar generated topics as potential duplicates.
	 *
	 * Uses AIPS_Deduplication_Service to check candidates against both existing
	 * topics and existing published WordPress articles.
	 *
	 * @param object $author Author object.
	 * @param array  $topics Generated topic arrays.
	 * @return array Topics with updated metadata and score adjustments.
	 */
	public function apply_fuzzy_duplicate_flags($author, $topics) {
		return $this->strategy->apply_fuzzy_duplicate_flags($author, $topics);
	}

	/**
	 * Compute or retrieve cached composite reference embedding vector for an Author.
	 *
	 * @param object $author Author object.
	 * @return array|null Composite vector array or null if unavailable.
	 */
	public function get_author_composite_embedding($author): ?array {
		return $this->strategy->get_author_composite_embedding($author);
	}

	/**
	 * Apply author auto-approval rules to generated topics.
	 *
	 * @param object $author Author object.
	 * @param array  $topics List of topic arrays.
	 * @return array Processed topic arrays.
	 */
	public function apply_auto_approval_rules($author, array $topics): array {
		return $this->strategy->apply_auto_approval_rules($author, $topics);
	}

	/**
	 * Get feedback context summary for an author.
	 *
	 * @param int $author_id Author ID.
	 * @return string Summary text for inclusion in prompts.
	 */
	public function get_feedback_context($author_id) {
		$context = '';

		$approved = $this->topics_repository->get_approved_summary($author_id, 10);
		$rejected = $this->topics_repository->get_rejected_summary($author_id, 10);

		if (!empty($approved)) {
			$context .= "Approved topics:\n" . implode("\n", $approved) . "\n\n";
		}

		if (!empty($rejected)) {
			$context .= "Rejected topics:\n" . implode("\n", $rejected) . "\n\n";
		}

		$stats = $this->feedback_repository->get_reason_category_statistics($author_id);
		if (!empty($stats)) {
			$context .= "Feedback patterns:\n";
			foreach ($stats as $category => $counts) {
				$approved_count = isset($counts['approved']) ? (int) $counts['approved'] : 0;
				$rejected_count = isset($counts['rejected']) ? (int) $counts['rejected'] : 0;
				if ($rejected_count > 0 || $approved_count > 0) {
					$context .= "- {$category}: {$approved_count} approved, {$rejected_count} rejected\n";
				}
			}
		}

		return $context;
	}
}
