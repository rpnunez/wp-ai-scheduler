<?php
/**
 * Campaign Generation Context
 *
 * Wraps sanitized campaign wizard intake parameters and environment metadata
 * as a first-class AIPS_Generation_Context for AIPS_Campaign_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Campaign_Generation_Context
 */
class AIPS_Campaign_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var array Sanitized campaign intake payload.
	 */
	private $intake;

	/**
	 * @var string[] Available schedule frequencies.
	 */
	private $available_frequencies;

	/**
	 * @var string[] Supported WordPress post types.
	 */
	private $available_post_types;

	/**
	 * @var array<string, string> Allowed output styles.
	 */
	private $available_output_styles;

	/**
	 * Constructor.
	 *
	 * @param array    $intake                  Sanitized intake payload.
	 * @param string[] $available_frequencies   Optional available frequencies.
	 * @param string[] $available_post_types    Optional available post types.
	 * @param array    $available_output_styles Optional allowed output styles.
	 */
	public function __construct(
		array $intake,
		array $available_frequencies = array(),
		array $available_post_types = array('post'),
		array $available_output_styles = array()
	) {
		$this->intake                  = $intake;
		$this->available_frequencies   = !empty($available_frequencies)
			? $available_frequencies
			: array_keys((new AIPS_Interval_Calculator())->get_intervals());
		$this->available_post_types    = $available_post_types;
		$this->available_output_styles = $available_output_styles;
	}

	/**
	 * Return sanitized intake payload.
	 *
	 * @return array
	 */
	public function get_intake(): array {
		return $this->intake;
	}

	/**
	 * Return available schedule frequencies.
	 *
	 * @return string[]
	 */
	public function get_available_frequencies(): array {
		return $this->available_frequencies;
	}

	/**
	 * Return available post types.
	 *
	 * @return string[]
	 */
	public function get_available_post_types(): array {
		return $this->available_post_types;
	}

	/**
	 * Return allowed output styles.
	 *
	 * @return array
	 */
	public function get_available_output_styles(): array {
		return $this->available_output_styles;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'campaigns';
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
		return !empty($this->intake['topic_niche']) ? sanitize_text_field((string) $this->intake['topic_niche']) : 'campaign';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_topic() {
		return !empty($this->intake['topic_niche']) ? sanitize_text_field((string) $this->intake['topic_niche']) : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_post_type() {
		return !empty($this->intake['post_type']) ? sanitize_key((string) $this->intake['post_type']) : 'post';
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'                    => $this->get_type(),
			'intake'                  => $this->intake,
			'available_frequencies'   => $this->available_frequencies,
			'available_post_types'    => $this->available_post_types,
			'available_output_styles' => $this->available_output_styles,
		);
	}
}
