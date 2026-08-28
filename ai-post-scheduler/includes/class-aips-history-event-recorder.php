<?php
/**
 * History Event Recorder.
 *
 * The single service responsible for turning an AIPS_History_Event into a
 * persisted history log entry. It owns:
 *
 *   - Canonicalization of event names and statuses (via the value object).
 *   - Consistent placement of event fields in the serialized record.
 *   - Correlation-ID attachment.
 *   - Optional enforcement of one terminal event per lifecycle container.
 *
 * Producers should route new event emission through this recorder rather than
 * hand-building record() arrays.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Event_Recorder
 */
class AIPS_History_Event_Recorder {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var AIPS_History_Service_Interface History service used to create containers.
	 */
	private $history_service;

	/**
	 * Container IDs that have already recorded a terminal event this request.
	 *
	 * @var array<int, bool>
	 */
	private $terminal_recorded = array();

	/**
	 * @param AIPS_History_Service_Interface|null $history_service Optional service.
	 */
	public function __construct(?AIPS_History_Service_Interface $history_service = null) {
		if ($history_service) {
			$this->history_service = $history_service;
			return;
		}

		$container = AIPS_Container::get_instance();
		if ($container->has(AIPS_History_Service_Interface::class)) {
			$this->history_service = $container->make(AIPS_History_Service_Interface::class);
			return;
		}

		$this->history_service = AIPS_History_Service::instance();
	}

	/**
	 * Shared singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Record an event by creating a new history container for it.
	 *
	 * The container type is the canonical event type; the subject id is stored
	 * on the container so the row links to its entity.
	 *
	 * @param AIPS_History_Event $event               Event to record.
	 * @param array              $container_metadata  Extra metadata for the container.
	 * @param string             $log_type            History log type ('activity' or 'error').
	 * @return int|false Log entry ID on success, false on failure.
	 */
	public function record(AIPS_History_Event $event, array $container_metadata = array(), $log_type = 'activity') {
		$metadata = $this->build_container_metadata($event, $container_metadata);
		$container = $this->history_service->create($event->type(), $metadata);

		if (!$container) {
			return false;
		}

		return $this->record_into($container, $event, $log_type);
	}

	/**
	 * Record an event into an existing history container.
	 *
	 * @param AIPS_History_Container $container Existing container.
	 * @param AIPS_History_Event     $event     Event to record.
	 * @param string                 $log_type  History log type ('activity' or 'error').
	 * @param bool                   $enforce_terminal When true, silently drop a
	 *        second terminal event for the same container within this request.
	 * @return int|false Log entry ID on success, false on failure or when a
	 *         terminal event was suppressed.
	 */
	public function record_into(AIPS_History_Container $container, AIPS_History_Event $event, $log_type = 'activity', $enforce_terminal = false) {
		// Attach the container's correlation id so every event in a run links up.
		$correlation_id = $container->get_correlation_id();
		if ($correlation_id === null) {
			$correlation_id = AIPS_Correlation_ID::get();
		}
		$event = $event->with_correlation_id($correlation_id);

		if ($enforce_terminal && $event->is_terminal()) {
			$container_id = (int) $container->get_id();
			if ($container_id > 0 && !empty($this->terminal_recorded[$container_id])) {
				return false;
			}
			if ($container_id > 0) {
				$this->terminal_recorded[$container_id] = true;
			}
		}

		return $container->record(
			$log_type,
			$event->message(),
			$event->to_details_input(),
			$event->output(),
			$event->to_details_context()
		);
	}

	/**
	 * Build the container metadata for a new container from an event.
	 *
	 * @param AIPS_History_Event $event              Event.
	 * @param array              $container_metadata Caller-supplied metadata.
	 * @return array
	 */
	private function build_container_metadata(AIPS_History_Event $event, array $container_metadata) {
		$subject = $event->subject();

		if ($subject->has_id()) {
			$key = $subject->container_meta_key();
			if ($key !== '' && !isset($container_metadata[$key])) {
				$container_metadata[$key] = $subject->id();
			}
		}

		if ($event->correlation_id() !== null && !isset($container_metadata['correlation_id'])) {
			$container_metadata['correlation_id'] = $event->correlation_id();
		}

		return $container_metadata;
	}
}
