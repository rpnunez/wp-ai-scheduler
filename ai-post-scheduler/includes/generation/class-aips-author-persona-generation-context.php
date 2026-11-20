<?php
/**
 * Author Persona Generation Context
 *
 * Wraps site/niche inputs and requested suggestion count as a first-class
 * AIPS_Generation_Context for AIPS_Author_Persona_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Persona_Generation_Context
 */
class AIPS_Author_Persona_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var array Raw context inputs (site_niche, target_audience, content_goals, site_url, etc.).
	 */
	private $inputs;

	/**
	 * @var int Requested number of persona suggestions (clamped 1-10).
	 */
	private $count;

	/**
	 * Constructor.
	 *
	 * @param array $inputs Context inputs for author persona generation.
	 * @param int   $count  Number of suggestions to generate (1-10).
	 */
	public function __construct(array $inputs, int $count = 3) {
		$this->inputs = $inputs;
		$this->count  = max(1, min(10, $count));
	}

	/**
	 * Return raw context inputs.
	 *
	 * @return array
	 */
	public function get_inputs(): array {
		return $this->inputs;
	}

	/**
	 * Return the clamped requested count.
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
		return 'author_personas';
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
		return !empty($this->inputs['site_niche']) ? sanitize_text_field((string) $this->inputs['site_niche']) : 'author_personas';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_topic() {
		return !empty($this->inputs['site_niche']) ? sanitize_text_field((string) $this->inputs['site_niche']) : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'   => $this->get_type(),
			'inputs' => $this->inputs,
			'count'  => $this->count,
		);
	}
}
