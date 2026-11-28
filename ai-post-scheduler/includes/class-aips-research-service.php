<?php
/**
 * Research Service
 *
 * Handles automated topic research and trend analysis using AI.
 * Delegates execution through AIPS_Generation_Pipeline and
 * AIPS_Research_Generation_Strategy while preserving full backward compatibility.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Research_Service
 */
class AIPS_Research_Service {

	/**
	 * @var AIPS_Research_Generation_Strategy
	 */
	private $strategy;

	/**
	 * @var AIPS_Generation_Pipeline
	 */
	private $pipeline;

	/**
	 * Initialize the Research Service.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service instance for dependency injection.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
		$container = AIPS_Container::get_instance();
		$logger    = $container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger();

		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_research_components($ai_service, $logger);

		$this->strategy = new AIPS_Research_Generation_Strategy($registry, $ai_service, $logger);
		$this->pipeline = new AIPS_Generation_Pipeline($this->strategy, $registry);
	}

	/**
	 * Research trending topics and return the strongly-typed AIPS_Generation_Result DTO.
	 *
	 * @param string $niche    The niche or industry to research.
	 * @param int    $count    Number of topics to return (default 10).
	 * @param array  $keywords Optional keywords to focus the research.
	 * @return AIPS_Generation_Result
	 */
	public function research_trending_topics_result($niche, int $count = 10, array $keywords = array()): AIPS_Generation_Result {
		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_TRENDING,
			array(
				'niche'    => (string) $niche,
				'count'    => $count,
				'keywords' => $keywords,
			)
		);

		return $this->pipeline->execute($context);
	}

	/**
	 * Research trending topics in a specific niche.
	 *
	 * @param string $niche    The niche or industry to research.
	 * @param int    $count    Number of topics to return (default 10).
	 * @param array  $keywords Optional keywords to focus the research.
	 * @return array|WP_Error Array of topics with scores, or WP_Error on failure.
	 */
	public function research_trending_topics($niche, $count = 10, $keywords = array()) {
		$result = $this->research_trending_topics_result($niche, (int) $count, is_array($keywords) ? $keywords : array());

		if ($result->is_failure()) {
			$last_error = $this->strategy->get_last_error();
			if ($last_error instanceof WP_Error) {
				return $last_error;
			}

			$errors  = $result->get_errors();
			$message = !empty($errors) ? $errors[0] : __('Research failed.', 'ai-post-scheduler');
			return new WP_Error('research_failed', $message);
		}

		$data = $result->get_data();
		return is_array($data) ? $data : array();
	}

	/**
	 * Get the top N topics from a list of researched topics.
	 *
	 * @param array $topics All researched topics.
	 * @param int   $count  Number of top topics to return.
	 * @return array Top N topics by score.
	 */
	public function get_top_topics($topics, $count = 5) {
		if (!is_array($topics) || empty($topics)) {
			return array();
		}

		return array_slice($topics, 0, absint($count));
	}

	/**
	 * Analyze topic freshness based on keywords and temporal indicators.
	 *
	 * @param array $topic Topic data with keywords.
	 * @return array Freshness analysis with score and reasons.
	 */
	public function analyze_topic_freshness($topic) {
		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_FRESHNESS,
			array('topic' => is_array($topic) ? $topic : array())
		);

		$result = $this->pipeline->execute($context);
		$data   = $result->get_data();

		return is_array($data) ? $data : array(
			'score'      => 50,
			'indicators' => array(),
			'is_fresh'   => false,
		);
	}

	/**
	 * Compare two topics for relevance based on scores and keywords.
	 *
	 * @param array $topic1 First topic.
	 * @param array $topic2 Second topic.
	 * @return int Comparison result (-1, 0, 1).
	 */
	public function compare_topics($topic1, $topic2) {
		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_COMPARISON,
			array(
				'comparison_topics' => array(
					is_array($topic1) ? $topic1 : array(),
					is_array($topic2) ? $topic2 : array(),
				),
			)
		);

		$result = $this->pipeline->execute($context);
		return (int) $result->get_data();
	}

	/**
	 * Research topics from sources and return a typed AIPS_Generation_Result DTO.
	 *
	 * @param int[]  $term_ids Source group term IDs to include.
	 * @param string $niche    Niche or topic area to guide the research.
	 * @param int    $count    Number of topics to generate (default 10).
	 * @param array  $keywords Optional focus keywords.
	 * @return AIPS_Generation_Result
	 */
	public function research_from_sources_result(array $term_ids, $niche, int $count = 10, array $keywords = array()): AIPS_Generation_Result {
		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_SOURCES,
			array(
				'term_ids' => $term_ids,
				'niche'    => (string) $niche,
				'count'    => $count,
				'keywords' => $keywords,
			)
		);

		return $this->pipeline->execute($context);
	}

	/**
	 * Research topics using pre-fetched source content as context.
	 *
	 * @param int[]  $term_ids Source group term IDs to include.
	 * @param string $niche    Niche or topic area to guide the research.
	 * @param int    $count    Number of topics to generate (default 10).
	 * @param array  $keywords Optional focus keywords.
	 * @return array|WP_Error Array of topic objects, or WP_Error on failure.
	 */
	public function research_from_sources(array $term_ids, $niche, $count = 10, $keywords = array()) {
		$result = $this->research_from_sources_result($term_ids, $niche, (int) $count, is_array($keywords) ? $keywords : array());

		if ($result->is_failure()) {
			$last_error = $this->strategy->get_last_error();
			if ($last_error instanceof WP_Error) {
				return $last_error;
			}

			$errors  = $result->get_errors();
			$message = !empty($errors) ? $errors[0] : __('Source-based research failed.', 'ai-post-scheduler');
			return new WP_Error('research_failed', $message);
		}

		$data = $result->get_data();
		return is_array($data) ? $data : array();
	}
}
