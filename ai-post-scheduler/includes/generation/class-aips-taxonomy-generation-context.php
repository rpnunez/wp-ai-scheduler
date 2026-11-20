<?php
/**
 * Taxonomy Generation Context
 *
 * Wraps taxonomy generation inputs (taxonomy type, source post IDs, and
 * optional custom generation prompt) for AIPS_Taxonomy_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Taxonomy_Generation_Context
 */
class AIPS_Taxonomy_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var string Taxonomy type ('category' or 'post_tag').
	 */
	private $taxonomy_type;

	/**
	 * @var int[] Source post IDs.
	 */
	private $post_ids;

	/**
	 * @var string Optional custom prompt instructions.
	 */
	private $generation_prompt;

	/**
	 * Constructor.
	 *
	 * @param string $taxonomy_type     Taxonomy type ('category' or 'post_tag').
	 * @param int[]  $post_ids          Array of post IDs.
	 * @param string $generation_prompt Optional generation prompt.
	 */
	public function __construct(string $taxonomy_type, array $post_ids, string $generation_prompt = '') {
		$this->taxonomy_type     = sanitize_key($taxonomy_type);
		$this->post_ids          = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
		$this->generation_prompt = $generation_prompt;
	}

	/**
	 * Return taxonomy type.
	 *
	 * @return string
	 */
	public function get_taxonomy_type(): string {
		return $this->taxonomy_type;
	}

	/**
	 * Return source post IDs.
	 *
	 * @return int[]
	 */
	public function get_post_ids(): array {
		return $this->post_ids;
	}

	/**
	 * Return optional generation prompt.
	 *
	 * @return string
	 */
	public function get_generation_prompt(): string {
		return $this->generation_prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'taxonomy';
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
		return $this->taxonomy_type;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_content_prompt() {
		return $this->generation_prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'              => $this->get_type(),
			'taxonomy_type'     => $this->taxonomy_type,
			'post_ids'          => $this->post_ids,
			'generation_prompt' => $this->generation_prompt,
		);
	}
}
