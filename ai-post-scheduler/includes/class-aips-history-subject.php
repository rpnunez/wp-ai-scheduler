<?php
/**
 * History Subject value object.
 *
 * Identifies the entity a history event is about (a post, topic, author,
 * schedule, etc.). Guaranteeing a subject on every canonical event means
 * consumers no longer have to guess which serialized block a subject id lives
 * in.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Subject
 *
 * Immutable {type, id, label} triple describing an event's subject.
 */
final class AIPS_History_Subject {

	const TYPE_POST          = 'post';
	const TYPE_TOPIC         = 'topic';
	const TYPE_TAXONOMY_ITEM = 'taxonomy_item';
	const TYPE_AUTHOR        = 'author';
	const TYPE_SCHEDULE      = 'schedule';
	const TYPE_TEMPLATE      = 'template';
	const TYPE_CAMPAIGN      = 'campaign';
	const TYPE_BATCH         = 'batch';
	const TYPE_JOB           = 'job';
	const TYPE_EMBEDDING     = 'embedding';
	const TYPE_NOTIFICATION  = 'notification';
	const TYPE_NONE          = 'none';

	/**
	 * @var string Subject type (one of the TYPE_* constants).
	 */
	private $type;

	/**
	 * @var int Subject identifier (0 when not applicable).
	 */
	private $id;

	/**
	 * @var string Optional human-readable label.
	 */
	private $label;

	/**
	 * @param string $type  Subject type.
	 * @param int    $id    Subject identifier.
	 * @param string $label Optional human-readable label.
	 */
	public function __construct($type, $id = 0, $label = '') {
		$this->type  = (string) $type;
		$this->id    = absint($id);
		$this->label = (string) $label;
	}

	/**
	 * Named constructor for a "no subject" event.
	 *
	 * @return self
	 */
	public static function none() {
		return new self(self::TYPE_NONE, 0, '');
	}

	/**
	 * Named constructor from a type and id.
	 *
	 * @param string $type  Subject type.
	 * @param int    $id    Subject identifier.
	 * @param string $label Optional label.
	 * @return self
	 */
	public static function of($type, $id, $label = '') {
		return new self($type, $id, $label);
	}

	/**
	 * @return string
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * @return int
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function label() {
		return $this->label;
	}

	/**
	 * Whether this subject carries a usable identifier.
	 *
	 * @return bool
	 */
	public function has_id() {
		return $this->id > 0;
	}

	/**
	 * Return the canonical metadata key that stores this subject's id on a
	 * history container (e.g. 'post_id', 'topic_id', 'author_id'). Returns an
	 * empty string for subject types the container has no dedicated column for.
	 *
	 * @return string
	 */
	public function container_meta_key() {
		$map = array(
			self::TYPE_POST     => 'post_id',
			self::TYPE_TOPIC    => 'topic_id',
			self::TYPE_AUTHOR   => 'author_id',
			self::TYPE_TEMPLATE => 'template_id',
			self::TYPE_CAMPAIGN => 'campaign_id',
		);

		return isset($map[$this->type]) ? $map[$this->type] : '';
	}

	/**
	 * Serialize to a plain array for inclusion in an event's context block.
	 *
	 * @return array{type: string, id: int, label: string}
	 */
	public function to_array() {
		return array(
			'type'  => $this->type,
			'id'    => $this->id,
			'label' => $this->label,
		);
	}
}
