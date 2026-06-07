<?php
/**
 * Post Score Prompt Builder
 *
 * Assembles the AI prompt used to score a generated post against its
 * generation configuration (Template, Voice, Article Structure / Sections).
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Score
 *
 * Builds the AI scoring prompt by extracting the intended configuration
 * (content prompt, voice style instructions, article structure sections)
 * from the generation context and embedding the generated post for evaluation.
 *
 * The AI is asked to return a JSON object with per-dimension scores (0-10),
 * targeted revision guidance, and a brief overall summary.
 */
class AIPS_Prompt_Builder_Post_Score {

	/**
	 * @var AIPS_Article_Structure_Manager
	 */
	private $structure_manager;

	/**
	 * @param AIPS_Article_Structure_Manager|null $structure_manager Optional override.
	 */
	public function __construct( $structure_manager = null ) {
		$this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
	}

	/**
	 * Build the complete scoring prompt.
	 *
	 * @param AIPS_Generation_Context|object $context  Generation context (or legacy template object).
	 * @param string                          $content  Generated post body.
	 * @param string                          $title    Generated post title.
	 * @return string Complete prompt to send to the AI.
	 */
	public function build( $context, string $content, string $title = '' ): string {
		$config_block   = $this->build_config_block( $context );
		$content_block  = $this->build_content_block( $content, $title );
		$scoring_block  = $this->build_scoring_instructions();
		$response_block = $this->build_response_format();

		$prompt = implode( "\n\n", array_filter( array(
			$config_block,
			$content_block,
			$scoring_block,
			$response_block,
		) ) );

		/**
		 * Filter the assembled post-scoring prompt.
		 *
		 * @since 2.6.0
		 *
		 * @param string                          $prompt  Assembled prompt.
		 * @param AIPS_Generation_Context|object  $context Generation context.
		 * @param string                          $content Generated content.
		 * @param string                          $title   Generated title.
		 */
		return apply_filters( 'aips_post_score_prompt', $prompt, $context, $content, $title );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Build the configuration block describing what the post was supposed to achieve.
	 *
	 * @param AIPS_Generation_Context|object $context
	 * @return string
	 */
	private function build_config_block( $context ): string {
		$lines = array( '## Generation Configuration' );

		// Topic
		$topic = $context instanceof AIPS_Generation_Context
			? $context->get_topic()
			: ( $context->topic ?? null );

		if ( !empty( $topic ) ) {
			$lines[] = '**Topic**: ' . esc_html( $topic );
		}

		// Content prompt / intent
		$content_prompt = $context instanceof AIPS_Generation_Context
			? $context->get_content_prompt()
			: ( $context->prompt_template ?? '' );

		if ( !empty( $content_prompt ) ) {
			$lines[] = '**Content Prompt / Intent**:';
			$lines[] = $content_prompt;
		}

		// Voice style instructions
		$voice = $context instanceof AIPS_Generation_Context
			? $context->get_voice()
			: null;

		if ( $voice ) {
			if ( !empty( $voice->content_instructions ) ) {
				$lines[] = '**Voice Style Instructions**:';
				$lines[] = $voice->content_instructions;
			}
		}

		// Article structure sections
		$structure_id = $context instanceof AIPS_Generation_Context
			? $context->get_article_structure_id()
			: ( isset( $context->article_structure_id ) ? $context->article_structure_id : null );

		if ( $structure_id ) {
			$structure = $this->structure_manager->get_structure( (int) $structure_id );
			if ( !is_wp_error( $structure ) && !empty( $structure['sections'] ) ) {
				$lines[] = '**Required Article Sections**:';
				$lines[] = implode( ', ', $structure['sections'] );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the block containing the generated content for evaluation.
	 *
	 * @param string $content Generated post body.
	 * @param string $title   Generated post title.
	 * @return string
	 */
	private function build_content_block( string $content, string $title ): string {
		$lines = array( '## Generated Post to Evaluate' );

		if ( !empty( $title ) ) {
			$lines[] = '**Title**: ' . $title;
		}

		$lines[] = '**Content**:';
		$lines[] = $content;

		return implode( "\n", $lines );
	}

	/**
	 * Build the scoring instruction block.
	 *
	 * @return string
	 */
	private function build_scoring_instructions(): string {
		return '## Scoring Instructions

You are an expert content quality evaluator. Score the generated post on the following dimensions (each 0-10):

1. **coherence** (0-10): How logically structured and easy to follow is the content? Higher is better.
2. **specificity** (0-10): Does the content include concrete details and examples rather than vague generalities? Higher is better.
3. **originality** (0-10): How fresh and non-generic is the perspective? Higher is better.
4. **citations_completeness** (0-10): Are factual claims, statistics, and assertions supported with attribution or references? Higher is better.
5. **reading_grade** (0-10): Is the reading level appropriate for the target audience implied by the prompt? Higher = better fit.
6. **fluff** (0-10): How much filler, redundant, or padding content is present? **Lower is better** (0 = no fluff, 10 = very fluffy).
7. **hallucination_risk** (0-10): How likely is it that the content contains invented or unverifiable facts? **Lower is better** (0 = very safe, 10 = high risk).
8. **alignment** (0-10): How well does the content match the generation configuration (topic, prompt intent, voice, structure)? Higher is better.

Also provide:
- **guidance**: An array of short, actionable revision instructions for any dimension scoring below 7. Each instruction should be an imperative phrase (e.g., "Add concrete examples for each main claim", "Tighten the introduction to 2-3 sentences", "Cite sources for all statistics").
- **summary**: A 1-2 sentence overall assessment.';
	}

	/**
	 * Build the response format instructions.
	 *
	 * @return string
	 */
	private function build_response_format(): string {
		return '## Response Format

Respond with ONLY valid JSON in the following structure (no markdown code fences, no explanations):

{
  "scores": {
    "coherence": <0-10>,
    "specificity": <0-10>,
    "originality": <0-10>,
    "citations_completeness": <0-10>,
    "reading_grade": <0-10>,
    "fluff": <0-10>,
    "hallucination_risk": <0-10>,
    "alignment": <0-10>
  },
  "guidance": [
    "Revision instruction 1",
    "Revision instruction 2"
  ],
  "summary": "Brief overall assessment."
}';
	}
}
