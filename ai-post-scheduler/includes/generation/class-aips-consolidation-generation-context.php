<?php
/**
 * Consolidation Generation Context
 *
 * Wraps the keep/retire WP_Post pair and optional editorial merge instructions
 * for AIPS_Consolidation_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Consolidation_Generation_Context
 */
class AIPS_Consolidation_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var WP_Post Canonical post to keep.
	 */
	private $keep_post;

	/**
	 * @var WP_Post Post to retire/merge into keep_post.
	 */
	private $retire_post;

	/**
	 * @var string Optional custom editorial instructions.
	 */
	private $instructions;

	/**
	 * Constructor.
	 *
	 * @param WP_Post $keep_post    Post to keep.
	 * @param WP_Post $retire_post  Post to retire.
	 * @param string  $instructions Optional merge instructions.
	 */
	public function __construct(WP_Post $keep_post, WP_Post $retire_post, string $instructions = '') {
		$this->keep_post    = $keep_post;
		$this->retire_post  = $retire_post;
		$this->instructions = $instructions;
	}

	/**
	 * Return canonical post to keep.
	 *
	 * @return WP_Post
	 */
	public function get_keep_post(): WP_Post {
		return $this->keep_post;
	}

	/**
	 * Return post being retired.
	 *
	 * @return WP_Post
	 */
	public function get_retire_post(): WP_Post {
		return $this->retire_post;
	}

	/**
	 * Return optional editorial instructions.
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return $this->instructions;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'consolidation';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id() {
		return (int) $this->keep_post->ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name() {
		return (string) $this->keep_post->post_title;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_content_prompt() {
		return $this->instructions;
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'         => $this->get_type(),
			'keep_id'      => (int) $this->keep_post->ID,
			'retire_id'    => (int) $this->retire_post->ID,
			'instructions' => $this->instructions,
		);
	}
}
