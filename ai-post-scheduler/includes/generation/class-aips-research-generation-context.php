<?php
/**
 * Research Generation Context
 *
 * Wraps niche research parameters (trending discovery, source-grounded
 * research, freshness scoring, or topic comparison) as a first-class
 * AIPS_Generation_Context for AIPS_Research_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Research_Generation_Context
 */
class AIPS_Research_Generation_Context extends AIPS_Abstract_Generation_Context {

	/** Supported research operation modes. */
	const MODE_TRENDING        = 'trending';
	const MODE_SOURCES         = 'sources';
	const MODE_FRESHNESS       = 'freshness';
	const MODE_COMPARISON      = 'comparison';
	const MODE_GAP_SUGGESTIONS = 'gap_suggestions';

	/**
	 * @var string Operation mode.
	 */
	private $mode;

	/**
	 * @var string Niche or industry to research.
	 */
	private $niche;

	/**
	 * @var int Requested number of topics.
	 */
	private $count;

	/**
	 * @var array Focus keywords.
	 */
	private $keywords;

	/**
	 * @var int[] Source group term IDs (for MODE_SOURCES).
	 */
	private $term_ids;

	/**
	 * @var array Topic payload (for MODE_FRESHNESS).
	 */
	private $topic_payload;

	/**
	 * @var array Pair of topics (for MODE_COMPARISON).
	 */
	private $comparison_topics;

	/**
	 * Constructor.
	 *
	 * @param string $mode              One of MODE_*.
	 * @param array  $params            Mode-specific parameters.
	 */
	public function __construct(string $mode = self::MODE_TRENDING, array $params = array()) {
		$this->mode              = $mode;
		$this->niche             = isset($params['niche']) ? (string) $params['niche'] : '';
		$this->count             = isset($params['count']) ? max(1, min(50, absint($params['count']))) : 10;
		$this->keywords          = isset($params['keywords']) && is_array($params['keywords']) ? $params['keywords'] : array();
		$this->term_ids          = isset($params['term_ids']) && is_array($params['term_ids']) ? $params['term_ids'] : array();
		$this->topic_payload     = isset($params['topic']) && is_array($params['topic']) ? $params['topic'] : array();
		$this->comparison_topics = isset($params['comparison_topics']) && is_array($params['comparison_topics']) ? $params['comparison_topics'] : array();
	}

	/**
	 * Return the research operation mode.
	 *
	 * @return string
	 */
	public function get_mode(): string {
		return $this->mode;
	}

	/**
	 * Return the niche string.
	 *
	 * @return string
	 */
	public function get_niche(): string {
		return $this->niche;
	}

	/**
	 * Return the clamped topic count.
	 *
	 * @return int
	 */
	public function get_count(): int {
		return $this->count;
	}

	/**
	 * Return focus keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return $this->keywords;
	}

	/**
	 * Return source group term IDs.
	 *
	 * @return int[]
	 */
	public function get_term_ids(): array {
		return $this->term_ids;
	}

	/**
	 * Return topic payload for freshness analysis.
	 *
	 * @return array
	 */
	public function get_topic_payload(): array {
		return $this->topic_payload;
	}

	/**
	 * Return topic pair for comparison.
	 *
	 * @return array
	 */
	public function get_comparison_topics(): array {
		return $this->comparison_topics;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'research';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name() {
		return $this->niche !== '' ? $this->niche : $this->mode;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_topic() {
		return $this->niche !== '' ? $this->niche : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_source_group_ids() {
		return $this->term_ids;
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'     => $this->get_type(),
			'mode'     => $this->mode,
			'niche'    => $this->niche,
			'count'    => $this->count,
			'keywords' => $this->keywords,
			'term_ids' => $this->term_ids,
		);
	}
}
