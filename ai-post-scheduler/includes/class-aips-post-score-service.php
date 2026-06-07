<?php
/**
 * Post Score Service
 *
 * Orchestrates quality scoring and optional targeted revision for a generated
 * post. Calls the AI service with a scoring prompt assembled by
 * AIPS_Prompt_Builder_Post_Score, parses the structured JSON response, and
 * produces an AIPS_PostScore_Result value object.
 *
 * When the overall score falls below the configured threshold the service can
 * optionally generate a revised version of the post content using the
 * AI-provided guidance, and update the WordPress post.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_PostScore_Service
 *
 * Usage:
 *   $service = new AIPS_PostScore_Service();
 *   $result  = $service->score( $context, $content, $title );
 *
 *   if ( ! $result->passed() ) {
 *       $revised = $service->run_revision( $context, $content, $title, $result );
 *   }
 */
class AIPS_PostScore_Service {

	/**
	 * Default pass/fail threshold (out of 100).
	 */
	const DEFAULT_THRESHOLD = 70;

	/**
	 * WordPress post meta key used to persist a score result.
	 */
	const SCORE_META_KEY = '_aips_post_score';

	/**
	 * WordPress post meta key used to persist revision count from generation.
	 */
	const REVISION_COUNT_META_KEY = '_aips_post_score_revision_count';

	/**
	 * Maximum number of revision iterations to prevent infinite loops.
	 */
	const MAX_REVISIONS = 2;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Prompt_Builder_Post_Score
	 */
	private $prompt_builder;

	/**
	 * @param AIPS_AI_Service_Interface|null      $ai_service     Optional AI service override.
	 * @param AIPS_Prompt_Builder_Post_Score|null $prompt_builder Optional prompt-builder override.
	 */
	public function __construct( $ai_service = null, $prompt_builder = null ) {
		if ( $ai_service ) {
			$this->ai_service = $ai_service;
		} else {
			$container = AIPS_Container::get_instance();
			$this->ai_service = $container->has( AIPS_AI_Service_Interface::class )
				? $container->make( AIPS_AI_Service_Interface::class )
				: new AIPS_AI_Service();
		}

		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Post_Score();
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Score generated post content against its generation configuration.
	 *
	 * @param AIPS_Generation_Context|object $context Generation context (or legacy template object).
	 * @param string                          $content Generated post body.
	 * @param string                          $title   Generated post title.
	 * @return AIPS_PostScore_Result|WP_Error Score result or error on AI/parse failure.
	 */
	public function score( $context, string $content, string $title = '' ) {
		$prompt    = $this->prompt_builder->build( $context, $content, $title );
		$threshold = $this->get_threshold();

		$response = $this->ai_service->generate_text( $prompt, array( 'request_type' => 'post_score' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_ai_response( (string) $response, $threshold );
	}

	/**
	 * Score a post by WordPress post ID.
	 *
	 * Retrieves the post content and title from the database. If a generation
	 * context is not provided, a minimal context object is constructed from
	 * any context stored in the post's score meta.
	 *
	 * @param int                                      $post_id WordPress post ID.
	 * @param AIPS_Generation_Context|object|null      $context Optional generation context.
	 * @return AIPS_PostScore_Result|WP_Error Score result or error.
	 */
	public function score_post( int $post_id, $context = null ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'ai-post-scheduler' ) );
		}

		if ( ! $context ) {
			$context = $this->make_minimal_context( $post );
		}

		$result = $this->score( $context, $post->post_content, $post->post_title );

		if ( ! is_wp_error( $result ) ) {
			$this->save_score_to_post( $post_id, $result );
		}

		return $result;
	}

	/**
	 * Run a targeted AI revision pass on generated content.
	 *
	 * Builds a revision prompt that incorporates the guidance strings from the
	 * score result and asks the AI to rewrite the post content. Does NOT update
	 * the WordPress post — the caller is responsible for persisting changes.
	 *
	 * @param AIPS_Generation_Context|object $context      Generation context.
	 * @param string                          $content      Current post body to revise.
	 * @param string                          $title        Current post title.
	 * @param AIPS_PostScore_Result           $score_result Score result with revision guidance.
	 * @return string|WP_Error Revised post body, or WP_Error on failure.
	 */
	public function run_revision( $context, string $content, string $title, AIPS_PostScore_Result $score_result ) {
		$guidance = $score_result->get_guidance();

		if ( empty( $guidance ) ) {
			return $content;
		}

		$prompt = $this->build_revision_prompt( $context, $content, $title, $guidance );

		/**
		 * Filter the revision prompt before sending it to AI.
		 *
		 * @since 2.6.0
		 *
		 * @param string                          $prompt       Revision prompt.
		 * @param AIPS_Generation_Context|object  $context      Generation context.
		 * @param string                          $content      Original content.
		 * @param AIPS_PostScore_Result           $score_result Score result.
		 */
		$prompt = apply_filters( 'aips_post_score_revision_prompt', $prompt, $context, $content, $score_result );

		return $this->ai_service->generate_text( $prompt, array( 'request_type' => 'post_score_revision' ) );
	}

	/**
	 * Run the full scoring-and-revision loop for in-memory post content.
	 *
	 * Scores the draft; if it fails, attempts up to MAX_REVISIONS targeted
	 * revision passes. The returned array contains the final content, final
	 * score result, and revision metadata. This method does not persist changes.
	 *
	 * @param AIPS_Generation_Context|object $context Generation context.
	 * @param string                          $content Draft post body.
	 * @param string                          $title   Draft post title.
	 * @return array|WP_Error Array with content/result/revision_count/revised, or error.
	 */
	public function score_and_revise_content( $context, string $content, string $title = '' ) {
		$result         = null;
		$revision_count = 0;

		for ( $iteration = 0; $iteration <= self::MAX_REVISIONS; $iteration++ ) {
			$result = $this->score( $context, $content, $title );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( $result->passed() || $iteration === self::MAX_REVISIONS ) {
				break;
			}

			$revised = $this->run_revision( $context, $content, $title, $result );

			if ( is_wp_error( $revised ) || empty( trim( $revised ) ) ) {
				break;
			}

			$content = (string) $revised;
			$revision_count++;
		}

		return array(
			'content'        => $content,
			'result'         => $result,
			'revision_count' => $revision_count,
			'revised'        => $revision_count > 0,
		);
	}

	/**
	 * Score and optionally revise generated draft content for a generation flow.
	 *
	 * This reusable orchestration wrapper is intentionally non-fatal for
	 * generation: scoring failures are logged and returned in the payload while
	 * the original content is preserved. Consumers can pass the active history
	 * container and generation logger so this service owns post-score observability
	 * instead of duplicating it in individual generators.
	 *
	 * @param AIPS_Generation_Context|object $context           Generation context.
	 * @param string                          $content           Draft post body.
	 * @param string                          $title             Draft post title.
	 * @param object|null                     $history           Optional history container with record().
	 * @param object|null                     $generation_logger Optional generation logger with warning().
	 * @return array{content:string,result:?AIPS_PostScore_Result,revision_count:int,revised:bool,error:?WP_Error,enabled:bool}
	 */
	public function process_generated_draft( $context, string $content, string $title = '', $history = null, $generation_logger = null ): array {
		$payload = $this->build_generation_payload( $content );
		$payload['enabled'] = $this->is_auto_scoring_enabled( $context, $content, $title );

		if ( ! $payload['enabled'] || '' === trim( wp_strip_all_tags( (string) $content ) ) ) {
			return $payload;
		}

		$revision = $this->score_and_revise_content( $context, $content, $title );

		if ( is_wp_error( $revision ) ) {
			$payload['error'] = $revision;
			$this->log_generation_warning( $generation_logger, $context, $revision );
			return $payload;
		}

		$payload = array_merge( $payload, $revision );

		if ( isset( $payload['result'] ) && $payload['result'] instanceof AIPS_PostScore_Result ) {
			$this->record_generation_history( $history, $payload['result'], (int) $payload['revision_count'] );
		}

		return $payload;
	}

	/**
	 * Persist score metadata from a generated-draft processing payload.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $payload Payload returned by process_generated_draft().
	 * @return void
	 */
	public function save_generation_score_to_post( int $post_id, array $payload ): void {
		if ( isset( $payload['result'] ) && $payload['result'] instanceof AIPS_PostScore_Result ) {
			$this->save_score_to_post( $post_id, $payload['result'] );
			update_post_meta( $post_id, self::REVISION_COUNT_META_KEY, (int) ( $payload['revision_count'] ?? 0 ) );
		}
	}

	/**
	 * Run the full scoring-and-revision loop for a WordPress post.
	 *
	 * Scores the post; if it fails, attempts up to MAX_REVISIONS revision passes,
	 * updating the post in the database after successful revisions. The final
	 * score result is saved to post meta.
	 *
	 * @param int                                 $post_id WordPress post ID.
	 * @param AIPS_Generation_Context|object|null $context Optional generation context.
	 * @return AIPS_PostScore_Result|WP_Error Final score result or error.
	 */
	public function score_and_revise_post( int $post_id, $context = null ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'ai-post-scheduler' ) );
		}

		if ( ! $context ) {
			$context = $this->make_minimal_context( $post );
		}

		$revision = $this->score_and_revise_content( $context, $post->post_content, $post->post_title );

		if ( is_wp_error( $revision ) ) {
			return $revision;
		}

		if ( ! empty( $revision['revised'] ) ) {
			$updated_post_id = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $revision['content'],
				),
				true
			);

			if ( is_wp_error( $updated_post_id ) ) {
				return $updated_post_id;
			}

			if ( ! $updated_post_id ) {
				return new WP_Error( 'post_score_revision_update_failed', __( 'Unable to update post content with the revised draft.', 'ai-post-scheduler' ) );
			}
		}

		$this->save_generation_score_to_post( $post_id, $revision );

		return $revision['result'];
	}

	/**
	 * Retrieve the configured score threshold (0-100).
	 *
	 * Can be filtered via `aips_post_score_threshold`.
	 *
	 * @return int
	 */
	public function get_threshold(): int {
		/**
		 * Filter the post-scoring pass/fail threshold.
		 *
		 * @since 2.6.0
		 *
		 * @param int $threshold Threshold (0-100). Default 70.
		 */
		$config_default = AIPS_Config::get_instance()->get_option( 'aips_post_score_threshold' );
		$threshold      = (int) apply_filters( 'aips_post_score_threshold', $config_default );

		return max( 0, min( 100, $threshold ) );
	}

	/**
	 * Persist a score result to post meta.
	 *
	 * @param int                   $post_id Post ID.
	 * @param AIPS_PostScore_Result $result  Score result.
	 * @return void
	 */
	public function save_score_to_post( int $post_id, AIPS_PostScore_Result $result ): void {
		update_post_meta( $post_id, self::SCORE_META_KEY, $result->to_array() );
	}

	/**
	 * Retrieve a previously stored score result from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return AIPS_PostScore_Result|null Score result or null if not set.
	 */
	public function get_score_from_post( int $post_id ): ?AIPS_PostScore_Result {
		$data = get_post_meta( $post_id, self::SCORE_META_KEY, true );

		if ( empty( $data ) || ! is_array( $data ) ) {
			return null;
		}

		return AIPS_PostScore_Result::from_array( $data );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Build the default generation payload shape.
	 *
	 * @param string $content Draft content.
	 * @return array
	 */
	private function build_generation_payload( string $content ): array {
		return array(
			'content'        => $content,
			'result'         => null,
			'revision_count' => 0,
			'revised'        => false,
			'error'          => null,
			'enabled'        => false,
		);
	}

	/**
	 * Determine whether generation-time auto scoring is enabled.
	 *
	 * @param AIPS_Generation_Context|object $context Generation context.
	 * @param string                          $content Draft post body.
	 * @param string                          $title   Draft post title.
	 * @return bool
	 */
	private function is_auto_scoring_enabled( $context, string $content, string $title ): bool {
		$config_enabled = (bool) AIPS_Config::get_instance()->get_option( 'aips_post_score_auto_enabled' );

		/**
		 * Filter whether post scoring should run automatically during generation.
		 *
		 * @since 2.6.0
		 *
		 * @param bool                            $enabled Default comes from the aips_post_score_auto_enabled option.
		 * @param AIPS_Generation_Context|object  $context Generation context.
		 * @param string                          $content Draft post body.
		 * @param string                          $title   Draft post title.
		 */
		return (bool) apply_filters( 'aips_post_score_auto_enabled', $config_enabled, $context, $content, $title );
	}

	/**
	 * Record post-score outcome on the active history container, when supplied.
	 *
	 * @param object|null           $history        History container with record().
	 * @param AIPS_PostScore_Result $result         Final score result.
	 * @param int                   $revision_count Revision count.
	 * @return void
	 */
	private function record_generation_history( $history, AIPS_PostScore_Result $result, int $revision_count ): void {
		if ( ! $history || ! method_exists( $history, 'record' ) ) {
			return;
		}

		$history->record(
			$result->passed() ? 'post_score_passed' : 'post_score_failed',
			$result->passed() ? 'Post quality score met the threshold' : 'Post quality score remained below the threshold',
			array(
				'overall_score'  => $result->get_overall_score(),
				'threshold'      => $result->get_threshold(),
				'revision_count' => $revision_count,
				'guidance'       => $result->get_guidance(),
			),
			null,
			array( 'component' => 'post_score' )
		);
	}

	/**
	 * Log a non-fatal generation scoring warning, when a logger is supplied.
	 *
	 * @param object|null $generation_logger Generation logger with warning() or log().
	 * @param object      $context           Generation context.
	 * @param WP_Error    $error             Scoring error.
	 * @return void
	 */
	private function log_generation_warning( $generation_logger, $context, WP_Error $error ): void {
		if ( ! $generation_logger ) {
			return;
		}

		$context_data = array(
			'context_type' => is_object( $context ) && method_exists( $context, 'get_type' ) ? $context->get_type() : '',
			'context_id'   => is_object( $context ) && method_exists( $context, 'get_id' ) ? $context->get_id() : 0,
			'error'        => $error->get_error_message(),
		);

		if ( method_exists( $generation_logger, 'warning' ) ) {
			$generation_logger->warning( 'Post scoring failed; continuing with original generated content.', $context_data );
			return;
		}

		if ( method_exists( $generation_logger, 'log' ) ) {
			$generation_logger->log( 'Post scoring failed; continuing with original generated content.', 'warning', array(), $context_data );
		}
	}

	/**
	 * Parse the raw AI JSON response into an AIPS_PostScore_Result.
	 *
	 * Tolerates minor JSON hygiene issues (leading/trailing whitespace, code
	 * fences) and falls back gracefully when the response cannot be decoded.
	 *
	 * @param string $raw_response Raw AI response string.
	 * @param int    $threshold    Threshold to use in the result.
	 * @return AIPS_PostScore_Result|WP_Error
	 */
	private function parse_ai_response( string $raw_response, int $threshold ) {
		$json = $this->extract_json( $raw_response );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			return new WP_Error(
				'post_score_invalid_response',
				__( 'The AI post scoring response was not valid JSON.', 'ai-post-scheduler' ),
				array(
					'raw_response' => $raw_response,
					'json_error'   => json_last_error_msg(),
				)
			);
		}

		$raw_scores = isset( $data['scores'] ) && is_array( $data['scores'] )
			? $data['scores']
			: array();

		$dimension_scores = $this->normalise_dimension_scores( $raw_scores );
		$overall          = $this->calculate_overall_score( $dimension_scores );
		$guidance         = isset( $data['guidance'] ) && is_array( $data['guidance'] )
			? array_filter( array_map( 'sanitize_text_field', $data['guidance'] ) )
			: array();

		if ( $overall < $threshold && empty( $guidance ) ) {
			$guidance = $this->build_default_guidance( $dimension_scores );
		}

		$summary          = isset( $data['summary'] ) ? sanitize_textarea_field( (string) $data['summary'] ) : '';

		return new AIPS_PostScore_Result(
			$dimension_scores,
			$overall,
			$threshold,
			$guidance,
			$summary,
			$raw_response
		);
	}

	/**
	 * Build deterministic targeted guidance when the AI omits guidance.
	 *
	 * @param array<string, int> $dimension_scores Normalised per-dimension scores.
	 * @return array<string> Targeted revision instructions.
	 */
	private function build_default_guidance( array $dimension_scores ): array {
		$guidance_map = array(
			'coherence'              => __( 'Tighten the structure so each section follows logically from the previous one.', 'ai-post-scheduler' ),
			'specificity'            => __( 'Add concrete examples, named scenarios, and implementation details for the main claims.', 'ai-post-scheduler' ),
			'originality'            => __( 'Add a fresher angle, counterpoints, or field-tested observations that reduce generic advice.', 'ai-post-scheduler' ),
			'citations_completeness' => __( 'Add citations or source attributions for statistics, factual claims, and external assertions.', 'ai-post-scheduler' ),
			'reading_grade'          => __( 'Adjust sentence length and vocabulary so the reading level matches the intended audience.', 'ai-post-scheduler' ),
			'fluff'                  => __( 'Reduce repetition, filler phrases, and broad introductory padding.', 'ai-post-scheduler' ),
			'hallucination_risk'     => __( 'Remove unverifiable claims or qualify them with clear evidence and sourcing.', 'ai-post-scheduler' ),
			'alignment'              => __( 'Refocus the draft on the requested topic, voice, structure, and prompt intent.', 'ai-post-scheduler' ),
		);

		$guidance = array();

		foreach ( AIPS_PostScore_Result::DIMENSIONS as $dimension ) {
			if ( ! isset( $dimension_scores[ $dimension ] ) || ! isset( $guidance_map[ $dimension ] ) ) {
				continue;
			}

			$is_penalty_dimension = in_array( $dimension, AIPS_PostScore_Result::PENALTY_DIMENSIONS, true );
			$needs_revision       = $is_penalty_dimension ? $dimension_scores[ $dimension ] > 3 : $dimension_scores[ $dimension ] < 7;

			if ( $needs_revision ) {
				$guidance[] = $guidance_map[ $dimension ];
			}
		}

		if ( empty( $guidance ) ) {
			$guidance[] = __( 'Add concrete examples, tighten the introduction, reduce repetition, and qualify unsupported claims.', 'ai-post-scheduler' );
		}

		return $guidance;
	}

	/**
	 * Strip JSON from a raw AI response that may include code fences.
	 *
	 * @param string $raw Raw AI response.
	 * @return string Cleaned JSON string.
	 */
	private function extract_json( string $raw ): string {
		$raw = trim( $raw );

		// Remove ```json ... ``` code fences
		if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $raw, $matches ) ) {
			return $matches[1];
		}

		// Return the first { ... } block if it exists
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );

		if ( $start !== false && $end !== false && $end > $start ) {
			return substr( $raw, $start, $end - $start + 1 );
		}

		return $raw;
	}

	/**
	 * Clamp and cast each dimension score to int 0-10.
	 *
	 * @param array $raw_scores Decoded scores from AI response.
	 * @return array<string, int>
	 */
	private function normalise_dimension_scores( array $raw_scores ): array {
		$normalised = array();

		foreach ( AIPS_PostScore_Result::DIMENSIONS as $dim ) {
			$value = isset( $raw_scores[ $dim ] ) ? (int) round( (float) $raw_scores[ $dim ] ) : 5;
			$normalised[ $dim ] = max( 0, min( 10, $value ) );
		}

		return $normalised;
	}

	/**
	 * Calculate the overall 0-100 score from per-dimension scores.
	 *
	 * Penalty dimensions (fluff, hallucination_risk) are inverted:
	 * their contribution is (10 - score) so that a lower raw value gives a
	 * higher effective contribution.
	 *
	 * @param array<string, int> $dimension_scores Normalised per-dimension scores.
	 * @return float Overall score 0-100.
	 */
	private function calculate_overall_score( array $dimension_scores ): float {
		if ( empty( $dimension_scores ) ) {
			return 0.0;
		}

		$penalty_set = array_flip( AIPS_PostScore_Result::PENALTY_DIMENSIONS );
		$sum         = 0;
		$count       = 0;

		foreach ( $dimension_scores as $dim => $score ) {
			$effective = isset( $penalty_set[ $dim ] ) ? ( 10 - $score ) : $score;
			$sum      += $effective;
			$count++;
		}

		if ( $count === 0 ) {
			return 0.0;
		}

		// Average effective score (0-10) → scale to 0-100
		return round( ( $sum / $count ) * 10, 1 );
	}

	/**
	 * Build the revision prompt for a targeted rewrite pass.
	 *
	 * @param AIPS_Generation_Context|object $context  Generation context.
	 * @param string                          $content  Current post body.
	 * @param string                          $title    Current post title.
	 * @param array<string>                   $guidance Revision instructions.
	 * @return string
	 */
	private function build_revision_prompt( $context, string $content, string $title, array $guidance ): string {
		$topic = $context instanceof AIPS_Generation_Context
			? $context->get_topic()
			: ( $context->topic ?? '' );

		$lines = array();

		$lines[] = 'You are a professional content editor. Revise the blog post below by applying ALL of the listed improvements.';

		if ( ! empty( $topic ) ) {
			$lines[] = '';
			$lines[] = '**Topic**: ' . esc_html( $topic );
		}

		$lines[] = '';
		$lines[] = '## Original Post';

		if ( ! empty( $title ) ) {
			$lines[] = '**Title**: ' . $title;
		}

		$lines[] = '';
		$lines[] = $content;

		$lines[] = '';
		$lines[] = '## Required Improvements';
		$lines[] = 'Apply ALL of the following improvements to the post:';
		$lines[] = '';

		foreach ( $guidance as $i => $instruction ) {
			$lines[] = ( $i + 1 ) . '. ' . $instruction;
		}

		$lines[] = '';
		$lines[] = 'Return ONLY the revised post content (no title, no explanations). Maintain the original length and structure unless a specific improvement requires changing it.';

		return implode( "\n", $lines );
	}

	/**
	 * Create a minimal stub context from a WordPress post object.
	 *
	 * Used when no generation context is available (e.g. for manual re-scoring).
	 *
	 * @param WP_Post $post WordPress post.
	 * @return object Minimal context object with a get_topic() method.
	 */
	private function make_minimal_context( WP_Post $post ): object {
		return new class( $post ) {
			private $post;
			public function __construct( WP_Post $post ) { $this->post = $post; }
			public function get_type() { return 'manual'; }
			public function get_id() { return $this->post->ID; }
			public function get_name() { return $this->post->post_title; }
			public function get_content_prompt() { return ''; }
			public function get_title_prompt() { return ''; }
			public function get_image_prompt() { return null; }
			public function should_generate_featured_image() { return false; }
			public function get_featured_image_source() { return ''; }
			public function get_unsplash_keywords() { return ''; }
			public function get_media_library_ids() { return ''; }
			public function get_post_status() { return $this->post->post_status; }
			public function get_post_type() { return $this->post->post_type; }
			public function get_post_category() { return 0; }
			public function get_post_tags() { return ''; }
			public function get_post_author() { return (int) $this->post->post_author; }
			public function get_article_structure_id() { return null; }
			public function get_voice_id() { return null; }
			public function get_voice() { return null; }
			public function get_topic() { return $this->post->post_title; }
			public function get_creation_method() { return 'manual'; }
			public function get_include_sources() { return false; }
			public function get_source_group_ids() { return array(); }
			public function to_array() {
				return array(
					'type'    => 'manual',
					'id'      => $this->post->ID,
					'topic'   => $this->post->post_title,
				);
			}
		};
	}
}
