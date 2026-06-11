<?php
/**
 * Post Score Cron Orchestrator
 *
 * Background worker class that processes post quality-scoring and targeted
 * revisions asynchronously via WP-Cron hooks. Reconstructs generation contexts,
 * runs evaluations, records diagnostic logs, and manages quality-gated status
 * transitions.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_PostScore_Cron_Orchestrator
 *
 * Resolves history records and context factories to execute post scoring in the
 * background, avoiding execution timeouts during front-end or API generation requests.
 */
class AIPS_PostScore_Cron_Orchestrator {

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Generation_Context_Factory
	 */
	private $context_factory;

	/**
	 * @var AIPS_PostScore_Service
	 */
	private $score_service;

	/**
	 * Constructor — resolves dependency instances.
	 *
	 * @param AIPS_History_Repository|null         $history_repository History persistence store.
	 * @param AIPS_Generation_Context_Factory|null $context_factory    Factory to recreate contexts.
	 * @param AIPS_PostScore_Service|null          $score_service      Quality scoring orchestrator.
	 */
	public function __construct(
		$history_repository = null,
		$context_factory = null,
		$score_service = null
	) {
		$this->history_repository = $history_repository ?: new AIPS_History_Repository();
		$this->context_factory    = $context_factory ?: new AIPS_Generation_Context_Factory();
		$this->score_service      = $score_service ?: new AIPS_PostScore_Service();
	}

	/**
	 * Process a post evaluation task in the background.
	 *
	 * Reconstructs context, executes scoring, writes diagnostic log events,
	 * and transitions post status depending on pass/fail outcomes.
	 *
	 * @param int $post_id WordPress Post ID.
	 * @return void
	 */
	public function process_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$history = $this->history_repository->get_by_post_id( $post_id );
		if ( ! $history ) {
			$this->cleanup_meta( $post_id );
			return;
		}

		$context_payload = $this->context_factory->create_from_history_id( $history->id );
		if ( is_wp_error( $context_payload ) ) {
			$this->cleanup_meta( $post_id );
			return;
		}

		$context = $context_payload['generation_context'];

		// Run scoring and revision loop. Update post content in DB and save meta scores.
		$result = $this->score_service->score_and_revise_post( $post_id, $context );

		if ( ! is_wp_error( $result ) && $result instanceof AIPS_PostScore_Result ) {
			// Write outcome entry to history logs
			$this->history_repository->add_log_entry(
				$history->id,
				$result->passed() ? 'post_score_passed' : 'post_score_failed',
				array(
					'overall_score' => $result->get_overall_score(),
					'threshold'     => $result->get_threshold(),
					'guidance'      => $result->get_guidance(),
					'summary'       => $result->get_summary(),
				)
			);

			// Quality Gate Transition:
			if ( $result->passed() ) {
				$intended = get_post_meta( $post_id, '_aips_post_intended_status', true );
				if ( 'publish' === $intended ) {
					wp_update_post( array(
						'ID'          => $post_id,
						'post_status' => 'publish',
					) );
				}
			}
		}

		$this->cleanup_meta( $post_id );
	}

	/**
	 * Clean up background processing and publish-intent meta tags.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function cleanup_meta( int $post_id ): void {
		delete_post_meta( $post_id, '_aips_post_score_status' );
		delete_post_meta( $post_id, '_aips_post_intended_status' );
	}
}
