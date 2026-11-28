<?php
/**
 * Author Topic Generation Context
 *
 * Wraps an author entity and topic-ideation options as a first-class
 * AIPS_Generation_Context for AIPS_Topic_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topic_Generation_Context
 */
class AIPS_Author_Topic_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var object|null Author object from database.
	 */
	private $author;

	/**
	 * @var bool Whether to apply auto-approval rules.
	 */
	private $apply_auto_approval;

	/**
	 * @var string Creation method ('manual' or 'scheduled').
	 */
	private $creation_method;

	/**
	 * Constructor.
	 *
	 * @param object|null $author              Author object.
	 * @param bool        $apply_auto_approval Whether to apply auto-approval rules.
	 * @param string      $creation_method     Creation method ('manual' or 'scheduled').
	 */
	public function __construct($author, bool $apply_auto_approval = true, string $creation_method = 'manual') {
		$this->author              = $author;
		$this->apply_auto_approval = $apply_auto_approval;
		$this->creation_method     = $creation_method;
	}

	/**
	 * Return the author object.
	 *
	 * @return object|null
	 */
	public function get_author() {
		return $this->author;
	}

	/**
	 * Whether auto-approval rules should run during this topic generation.
	 *
	 * @return bool
	 */
	public function should_apply_auto_approval(): bool {
		return $this->apply_auto_approval;
	}

	/**
	 * Return the requested topic quantity for this author.
	 *
	 * @return int
	 */
	public function get_quantity(): int {
		if ($this->author && isset($this->author->topic_generation_quantity)) {
			return max(1, (int) $this->author->topic_generation_quantity);
		}

		return 5;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'author_topics';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id() {
		return ($this->author && isset($this->author->id)) ? (int) $this->author->id : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name() {
		return ($this->author && !empty($this->author->name)) ? (string) $this->author->name : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_content_prompt() {
		return ($this->author && !empty($this->author->topic_generation_prompt)) ? (string) $this->author->topic_generation_prompt : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_topic() {
		if ($this->author && !empty($this->author->field_niche)) {
			return (string) $this->author->field_niche;
		}
		if ($this->author && !empty($this->author->niche)) {
			return (string) $this->author->niche;
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_creation_method() {
		return $this->creation_method;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_include_sources() {
		return ($this->author && !empty($this->author->include_sources));
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_source_group_ids() {
		if (!$this->author || empty($this->author->source_group_ids)) {
			return array();
		}

		$raw = is_array($this->author->source_group_ids)
			? $this->author->source_group_ids
			: json_decode((string) $this->author->source_group_ids, true);

		return is_array($raw) ? array_values(array_filter(array_map('absint', $raw))) : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'                => $this->get_type(),
			'id'                  => $this->get_id(),
			'name'                => $this->get_name(),
			'niche'               => $this->get_topic(),
			'quantity'            => $this->get_quantity(),
			'apply_auto_approval' => $this->apply_auto_approval,
			'creation_method'     => $this->creation_method,
		);
	}
}
