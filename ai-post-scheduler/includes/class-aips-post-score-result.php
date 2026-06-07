<?php
/**
 * Post Score Result
 *
 * Value object representing the quality-scoring result for a generated post.
 * Holds per-dimension scores, an overall score, pass/fail status, and any
 * targeted revision guidance returned by the AI scorer.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_PostScore_Result
 *
 * Immutable value object produced by AIPS_PostScore_Scorer after the AI
 * evaluates a generated post against its generation configuration.
 *
 * Dimensions scored 0-10 (higher = better), except:
 *   - fluff:              lower = better (0 = no fluff, 10 = very fluffy)
 *   - hallucination_risk: lower = better (0 = safe, 10 = high risk)
 *
 * Overall score is 0-100.
 */
class AIPS_PostScore_Result {

	/**
	 * Ordered list of all scoring dimensions.
	 */
	const DIMENSIONS = array(
		'coherence',
		'specificity',
		'originality',
		'citations_completeness',
		'reading_grade',
		'fluff',
		'hallucination_risk',
		'alignment',
	);

	/**
	 * Dimensions where a lower score is better (penalties).
	 * These are inverted when calculating the overall score.
	 */
	const PENALTY_DIMENSIONS = array( 'fluff', 'hallucination_risk' );

	/**
	 * @var array<string, int> Per-dimension scores (0-10 each).
	 */
	private $dimension_scores;

	/**
	 * @var float Overall quality score (0-100).
	 */
	private $overall_score;

	/**
	 * @var int Score threshold used to determine pass/fail.
	 */
	private $threshold;

	/**
	 * @var array<string> Targeted revision instructions when score < threshold.
	 */
	private $guidance;

	/**
	 * @var string Brief overall summary from the AI.
	 */
	private $summary;

	/**
	 * @var string Raw AI response string.
	 */
	private $raw_response;

	/**
	 * Constructor.
	 *
	 * @param array<string, int> $dimension_scores Per-dimension scores (0-10 each).
	 * @param float              $overall_score    Computed overall score (0-100).
	 * @param int                $threshold        Score threshold for pass/fail.
	 * @param array<string>      $guidance         Revision instructions; empty when passing.
	 * @param string             $summary          Brief AI summary.
	 * @param string             $raw_response     Raw JSON string from AI.
	 */
	public function __construct(
		array $dimension_scores,
		float $overall_score,
		int $threshold,
		array $guidance = array(),
		string $summary = '',
		string $raw_response = ''
	) {
		$this->dimension_scores = $dimension_scores;
		$this->overall_score    = $overall_score;
		$this->threshold        = $threshold;
		$this->guidance         = array_values( $guidance );
		$this->summary          = $summary;
		$this->raw_response     = $raw_response;
	}

	/**
	 * Get per-dimension scores.
	 *
	 * @return array<string, int>
	 */
	public function get_dimension_scores(): array {
		return $this->dimension_scores;
	}

	/**
	 * Get the score for a single dimension.
	 *
	 * @param string $dimension Dimension name.
	 * @return int|null Score 0-10, or null if the dimension is not present.
	 */
	public function get_dimension_score( string $dimension ): ?int {
		return $this->dimension_scores[ $dimension ] ?? null;
	}

	/**
	 * Get the computed overall score (0-100).
	 *
	 * @return float
	 */
	public function get_overall_score(): float {
		return $this->overall_score;
	}

	/**
	 * Get the pass/fail threshold.
	 *
	 * @return int
	 */
	public function get_threshold(): int {
		return $this->threshold;
	}

	/**
	 * Whether the score meets or exceeds the threshold.
	 *
	 * @return bool
	 */
	public function passed(): bool {
		return $this->overall_score >= $this->threshold;
	}

	/**
	 * Get targeted revision guidance strings.
	 *
	 * @return array<string>
	 */
	public function get_guidance(): array {
		return $this->guidance;
	}

	/**
	 * Get the brief overall summary.
	 *
	 * @return string
	 */
	public function get_summary(): string {
		return $this->summary;
	}

	/**
	 * Get the raw AI response string.
	 *
	 * @return string
	 */
	public function get_raw_response(): string {
		return $this->raw_response;
	}

	/**
	 * Serialize the result to an array (e.g. for post meta storage or JSON output).
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'dimension_scores' => $this->dimension_scores,
			'overall_score'    => $this->overall_score,
			'threshold'        => $this->threshold,
			'passed'           => $this->passed(),
			'guidance'         => $this->guidance,
			'summary'          => $this->summary,
		);
	}

	/**
	 * Reconstruct a result from a previously serialized array.
	 *
	 * @param array $data Serialized array from to_array().
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(array) ( $data['dimension_scores'] ?? array() ),
			(float)  ( $data['overall_score']    ?? 0.0 ),
			(int)    ( $data['threshold']         ?? AIPS_PostScore_Scorer::DEFAULT_THRESHOLD ),
			(array)  ( $data['guidance']          ?? array() ),
			(string) ( $data['summary']           ?? '' )
		);
	}
}
