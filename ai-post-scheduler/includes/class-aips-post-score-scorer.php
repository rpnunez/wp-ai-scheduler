<?php
/**
 * Post Score Scorer
 *
 * Orchestrates quality scoring and optional targeted revision for a generated
 * post. Calls the AI service with a scoring prompt assembled by
 * AIPS_Prompt_Builder_Post_Score, parses the structured JSON response, and
 * produces an AIPS_PostScore_Result value object.
 *
 * When the overall score falls below the configured threshold the scorer can
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
 * Class AIPS_PostScore_Scorer
 *
 * Usage:
 *   $scorer  = new AIPS_PostScore_Scorer();
 *   $result  = $scorer->score( $context, $content, $title );
 *
 *   if ( ! $result->passed() ) {
 *       $revised = $scorer->run_revision( $context, $content, $title, $result );
 *   }
 */
class AIPS_PostScore_Scorer {

	/**
	 * Default pass/fail threshold (out of 100).
	 */
	const DEFAULT_THRESHOLD = 70;

	/**
	 * WordPress post meta key used to persist a score result.
	 */
	const SCORE_META_KEY = '_aips_post_score';

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
	 * Run the full scoring-and-revision loop for a WordPress post.
	 *
	 * Scores the post; if it fails, attempts up to MAX_REVISIONS revision passes,
	 * updating the post in the database after each successful revision. The final
	 * score result (from the last scoring pass) is saved to post meta.
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

		$content = $post->post_content;
		$title   = $post->post_title;
		$result  = null;

		for ( $iteration = 0; $iteration <= self::MAX_REVISIONS; $iteration++ ) {
			$result = $this->score( $context, $content, $title );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( $result->passed() || $iteration === self::MAX_REVISIONS ) {
				break;
			}

			// Score failed — run a revision pass
			$revised = $this->run_revision( $context, $content, $title, $result );

			if ( is_wp_error( $revised ) || empty( trim( $revised ) ) ) {
				// Revision failed; keep current content and stop looping
				break;
			}

			// Update content for next iteration (and persist to DB)
			$content = $revised;
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $content,
			) );
		}

		if ( $result && ! is_wp_error( $result ) ) {
			$this->save_score_to_post( $post_id, $result );
		}

		return $result;
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
		$threshold = (int) apply_filters( 'aips_post_score_threshold', self::DEFAULT_THRESHOLD );

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
	 * Parse the raw AI JSON response into an AIPS_PostScore_Result.
	 *
	 * Tolerates minor JSON hygiene issues (leading/trailing whitespace, code
	 * fences) and falls back gracefully when the response cannot be decoded.
	 *
	 * @param string $raw_response Raw AI response string.
	 * @param int    $threshold    Threshold to use in the result.
	 * @return AIPS_PostScore_Result
	 */
	private function parse_ai_response( string $raw_response, int $threshold ): AIPS_PostScore_Result {
		$json = $this->extract_json( $raw_response );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			// Fallback: return a result that will pass so generation is not blocked.
			return new AIPS_PostScore_Result(
				array(),
				(float) $threshold, // neutral score
				$threshold,
				array(),
				'',
				$raw_response
			);
		}

		$raw_scores = isset( $data['scores'] ) && is_array( $data['scores'] )
			? $data['scores']
			: array();

		$dimension_scores = $this->normalise_dimension_scores( $raw_scores );
		$overall          = $this->calculate_overall_score( $dimension_scores );
		$guidance         = isset( $data['guidance'] ) && is_array( $data['guidance'] )
			? array_map( 'sanitize_text_field', $data['guidance'] )
			: array();
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
