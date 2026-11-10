<?php
/**
 * Author Topics Controller
 *
 * Handles AJAX requests for author topic management by delegating to dedicated
 * query, mutation, and job handlers.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Controller
 *
 * Manages AJAX endpoints for topic approval workflow.
 */
class AIPS_Author_Topics_Controller {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var AIPS_Author_Topics_Repository Repository for topics
	 */
	private $repository;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository Repository for logs
	 */
	private $logs_repository;

	/**
	 * @var AIPS_Feedback_Repository Repository for feedback
	 */
	private $feedback_repository;

	/**
	 * @var AIPS_Author_Post_Generator Post generator
	 */
	private $post_generator;

	/**
	 * @var AIPS_Topic_Penalty_Service Penalty service
	 */
	private $penalty_service;

	/**
	 * @var AIPS_History_Service_Interface Service for history logging
	 */
	private $history_service;

	/**
	 * @var AIPS_Similarity_Evaluator Service for topic expansion and similarity evaluation
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_History_Repository_Interface Repository for history data
	 */
	private $history_repository;

	/**
	 * @var AIPS_Bulk_Generator_Service Shared bulk generation harness
	 */
	private $bulk_generator_service;

	/**
	 * @var AIPS_Job_Scheduler Job scheduler service
	 */
	private $job_scheduler;

	/**
	 * @var AIPS_Embeddings_Repository Embeddings repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Relationships_Repository Relationships repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Author_Topics_Query_Handler
	 */
	private $query_handler;

	/**
	 * @var AIPS_Author_Topics_Mutation_Handler
	 */
	private $mutation_handler;

	/**
	 * @var AIPS_Author_Topics_Job_Handler
	 */
	private $job_handler;

	/**
	 * Initialize the controller.
	 *
	 * @param AIPS_Similarity_Evaluator|null         $similarity_evaluator   Similarity evaluator.
	 * @param AIPS_History_Repository_Interface|null $history_repository    History repository.
	 * @param AIPS_Bulk_Generator_Service|null       $bulk_generator_service Bulk generator service.
	 * @param AIPS_Job_Scheduler|null                $job_scheduler          Job scheduler service.
	 * @param AIPS_Embeddings_Repository|null        $embeddings_repo        Embeddings repository.
	 * @param AIPS_Relationships_Repository|null     $relationships_repo     Relationships repository.
	 */
	public function __construct(
		$similarity_evaluator = null,
		?AIPS_History_Repository_Interface $history_repository = null,
		$bulk_generator_service = null,
		?AIPS_Job_Scheduler $job_scheduler = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null
	) {
		$container = AIPS_Container::get_instance();
		$this->repository             = new AIPS_Author_Topics_Repository();
		$this->logs_repository        = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository    = new AIPS_Feedback_Repository();
		$this->post_generator         = new AIPS_Author_Post_Generator();
		$this->penalty_service        = new AIPS_Topic_Penalty_Service();
		$this->history_service        = $container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service();
		$this->similarity_evaluator   = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());
		$this->history_repository     = $history_repository ?: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());
		$this->bulk_generator_service = $bulk_generator_service ?: new AIPS_Bulk_Generator_Service($this->history_service);
		$this->job_scheduler          = $job_scheduler ?: new AIPS_Job_Scheduler();
		$this->embeddings_repo        = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo     = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());

		$this->query_handler = new AIPS_Author_Topics_Query_Handler(
			$this->repository,
			$this->logs_repository,
			$this->feedback_repository,
			$this->similarity_evaluator,
			$this->history_repository
		);

		$this->mutation_handler = new AIPS_Author_Topics_Mutation_Handler(
			$this->repository,
			$this->logs_repository,
			$this->feedback_repository,
			$this->penalty_service,
			$this->history_service,
			$this->embeddings_repo,
			$this->relationships_repo
		);

		$this->job_handler = new AIPS_Author_Topics_Job_Handler(
			$this->repository,
			$this->post_generator,
			$this->history_service,
			$this->bulk_generator_service
		);

		// Canonical hook registration
		AIPS_Ajax_Registry::register_controller_actions($this);
	}

	// --- Query endpoints ---

	public function ajax_get_topic_logs() {
		return $this->query_handler->get_topic_logs();
	}

	public function ajax_get_topic_feedback() {
		return $this->query_handler->get_topic_feedback();
	}

	public function ajax_get_similar_topics() {
		return $this->query_handler->get_similar_topics();
	}

	public function ajax_suggest_related_topics() {
		return $this->query_handler->suggest_related_topics();
	}

	public function ajax_get_generation_queue() {
		return $this->query_handler->get_generation_queue();
	}

	public function ajax_get_bulk_generate_estimate() {
		return $this->query_handler->get_bulk_generate_estimate();
	}

	// --- Mutation endpoints ---

	public function ajax_approve_topic() {
		return $this->mutation_handler->approve_topic();
	}

	public function ajax_reject_topic() {
		return $this->mutation_handler->reject_topic();
	}

	public function ajax_edit_topic() {
		return $this->mutation_handler->edit_topic();
	}

	public function ajax_save_author_topic() {
		return $this->mutation_handler->save_author_topic();
	}

	public function ajax_delete_topic() {
		return $this->mutation_handler->delete_topic();
	}

	public function ajax_bulk_approve_topics() {
		return $this->mutation_handler->bulk_approve_topics();
	}

	public function ajax_bulk_reject_topics() {
		return $this->mutation_handler->bulk_reject_topics();
	}

	public function ajax_bulk_delete_topics() {
		return $this->mutation_handler->bulk_delete_topics();
	}

	public function ajax_bulk_delete_feedback() {
		return $this->mutation_handler->bulk_delete_feedback();
	}

	public function ajax_delete_generated_post() {
		return $this->mutation_handler->delete_generated_post();
	}

	// --- Job endpoints ---

	public function ajax_generate_post_from_topic() {
		return $this->job_handler->generate_post_from_topic();
	}

	public function ajax_regenerate_post() {
		return $this->job_handler->regenerate_post();
	}

	public function ajax_compute_topic_embeddings() {
		return $this->job_handler->compute_topic_embeddings();
	}

	public function ajax_bulk_generate_from_queue() {
		return $this->job_handler->bulk_generate_from_queue();
	}

	public function ajax_bulk_generate_topics() {
		return $this->job_handler->bulk_generate_topics();
	}
}
