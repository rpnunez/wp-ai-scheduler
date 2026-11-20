<?php
/**
 * Planner Topic Generation Context
 *
 * Wraps bulk planner niche and requested topic count for
 * AIPS_Planner_Topic_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Planner_Topic_Generation_Context
 */
class AIPS_Planner_Topic_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var string Niche or topic seed.
	 */
	private $niche;

	/**
	 * @var int Requested number of topic ideas.
	 */
	private $count;

	/**
	 * Constructor.
	 *
	 * @param string $niche Niche or topic seed.
	 * @param int    $count Number of topics to generate (1-50, default 10).
	 */
	public function __construct(string $niche, int $count = 10) {
		$this->niche = sanitize_text_field($niche);
		$this->count = ($count >= 1 && $count <= 50) ? $count : 10;
	}

	/**
	 * Return the niche seed.
	 *
	 * @return string
	 */
	public function get_niche(): string {
		return $this->niche;
	}

	/**
	 * Return requested topic count.
	 *
	 * @return int
	 */
	public function get_count(): int {
		return $this->count;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'planner_topics';
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
		return $this->niche;
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
	public function to_array() {
		return array(
			'type'  => $this->get_type(),
			'niche' => $this->niche,
			'count' => $this->count,
		);
	}
}
