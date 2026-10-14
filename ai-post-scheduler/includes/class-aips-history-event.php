<?php
/**
 * History Event value object.
 *
 * A typed, validated description of a single history event. Producers build one
 * of these and hand it to AIPS_History_Event_Recorder, which owns canonical
 * serialization and correlation metadata. This replaces ad hoc arrays scattered
 * across producers with a single shape.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Event
 *
 * Immutable value object carrying the canonical fields of a history event.
 */
final class AIPS_History_Event {

	/**
	 * @var string Canonical event type.
	 */
	private $type;

	/**
	 * @var string Canonical event status.
	 */
	private $status;

	/**
	 * @var string Human-readable message.
	 */
	private $message;

	/**
	 * @var AIPS_History_Subject Subject identity.
	 */
	private $subject;

	/**
	 * @var array Input payload.
	 */
	private $input;

	/**
	 * @var mixed Output payload.
	 */
	private $output;

	/**
	 * @var array Metadata / context.
	 */
	private $context;

	/**
	 * @var string|null Correlation ID.
	 */
	private $correlation_id;

	/**
	 * @var int Occurred-at Unix timestamp.
	 */
	private $occurred_at;

	/**
	 * @param string                    $type    Event type (canonical or alias).
	 * @param string                    $status  Event status (canonical or synonym).
	 * @param string                    $message Human-readable message.
	 * @param AIPS_History_Subject|null $subject Subject identity.
	 * @param array                     $input   Input payload.
	 * @param mixed                     $output  Output payload.
	 * @param array                     $context Metadata / context.
	 */
	public function __construct(
		$type,
		$status,
		$message = '',
		?AIPS_History_Subject $subject = null,
		$input = array(),
		$output = null,
		$context = array()
	) {
		$this->type           = AIPS_History_Event_Type::canonicalize($type);
		$this->status         = AIPS_History_Event_Status::canonicalize($status);
		$this->message        = (string) $message;
		$this->subject        = $subject ?: AIPS_History_Subject::none();
		$this->input          = is_array($input) ? $input : array('value' => $input);
		$this->output         = $output;
		$this->context        = is_array($context) ? $context : array();
		$this->correlation_id = null;
		$this->occurred_at    = AIPS_DateTime::now()->timestamp();
	}

	/**
	 * Named constructor for a successful outcome.
	 *
	 * @param string                    $type    Event type.
	 * @param string                    $message Human-readable message.
	 * @param AIPS_History_Subject|null $subject Subject identity.
	 * @param array                     $context Metadata / context.
	 * @param array                     $input   Input payload.
	 * @param mixed                     $output  Output payload.
	 * @return self
	 */
	public static function success($type, $message = '', ?AIPS_History_Subject $subject = null, $context = array(), $input = array(), $output = null) {
		return new self($type, AIPS_History_Event_Status::SUCCESS, $message, $subject, $input, $output, $context);
	}

	/**
	 * Named constructor for a failed outcome.
	 *
	 * @param string                    $type    Event type.
	 * @param string                    $message Human-readable message.
	 * @param AIPS_History_Subject|null $subject Subject identity.
	 * @param array                     $context Metadata / context.
	 * @param array                     $input   Input payload.
	 * @param mixed                     $output  Output payload.
	 * @return self
	 */
	public static function failure($type, $message = '', ?AIPS_History_Subject $subject = null, $context = array(), $input = array(), $output = null) {
		return new self($type, AIPS_History_Event_Status::FAILED, $message, $subject, $input, $output, $context);
	}

	/**
	 * Named constructor for a partial outcome.
	 *
	 * @param string                    $type    Event type.
	 * @param string                    $message Human-readable message.
	 * @param AIPS_History_Subject|null $subject Subject identity.
	 * @param array                     $context Metadata / context.
	 * @param array                     $input   Input payload.
	 * @param mixed                     $output  Output payload.
	 * @return self
	 */
	public static function partial($type, $message = '', ?AIPS_History_Subject $subject = null, $context = array(), $input = array(), $output = null) {
		return new self($type, AIPS_History_Event_Status::PARTIAL, $message, $subject, $input, $output, $context);
	}

	/**
	 * Return a copy with the correlation ID set.
	 *
	 * @param string|null $correlation_id Correlation identifier.
	 * @return self
	 */
	public function with_correlation_id($correlation_id) {
		$clone = clone $this;
		$clone->correlation_id = $correlation_id !== null ? (string) $correlation_id : null;
		return $clone;
	}

	/**
	 * @return string
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function message() {
		return $this->message;
	}

	/**
	 * @return AIPS_History_Subject
	 */
	public function subject() {
		return $this->subject;
	}

	/**
	 * @return array
	 */
	public function input() {
		return $this->input;
	}

	/**
	 * @return mixed
	 */
	public function output() {
		return $this->output;
	}

	/**
	 * @return array
	 */
	public function context() {
		return $this->context;
	}

	/**
	 * @return string|null
	 */
	public function correlation_id() {
		return $this->correlation_id;
	}

	/**
	 * @return int
	 */
	public function occurred_at() {
		return $this->occurred_at;
	}

	/**
	 * Whether the event carries a terminal (lifecycle-ending) status.
	 *
	 * @return bool
	 */
	public function is_terminal() {
		return AIPS_History_Event_Status::is_terminal($this->status);
	}

	/**
	 * Build the canonical `input` block written to the history log details.
	 *
	 * event_type and event_status are pinned to the front so the serialized
	 * record shape is stable and greppable. Producer-supplied input keys are
	 * merged in but never allowed to override the canonical identity keys.
	 *
	 * @return array
	 */
	public function to_details_input() {
		$input = $this->input;
		unset($input['event_type'], $input['event_status']);

		return array_merge(
			array(
				'event_type'   => $this->type,
				'event_status' => $this->status,
			),
			$input
		);
	}

	/**
	 * Build the canonical `context` block written to the history log details.
	 *
	 * The subject and correlation id are guaranteed to be present.
	 *
	 * @return array
	 */
	public function to_details_context() {
		$context = $this->context;

		$context['subject'] = $this->subject->to_array();

		if ($this->subject->has_id()) {
			$key = $this->subject->container_meta_key();
			if ($key !== '' && !isset($context[$key])) {
				$context[$key] = $this->subject->id();
			}
		}

		if ($this->correlation_id !== null && !isset($context['correlation_id'])) {
			$context['correlation_id'] = $this->correlation_id;
		}

		return $context;
	}
}
