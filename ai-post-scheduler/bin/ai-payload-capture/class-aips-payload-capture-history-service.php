<?php
/**
 * AI payload capture: history service decorator.
 *
 * Wraps the real AIPS_History_Service so every history container created
 * during a scenario is tracked, regardless of whether the run then succeeds,
 * returns a WP_Error, or throws. AIPS_Generator writes its "post_generation"
 * history row before any content is generated, so a failed run still leaves
 * one behind; AIPS_Payload_Capture's own cleanup needs the row's ID directly
 * (there is no post ID to clean up by when generation never created a post).
 *
 * Dev tooling only: loaded by bin/ai-payload-capture.php, never by the plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Payload_Capture_History_Service implements AIPS_History_Service_Interface {

	/** @var AIPS_History_Service_Interface */
	private $inner;

	/** @var int[] History IDs created since the last reset. */
	private $created_ids = array();

	public function __construct(AIPS_History_Service_Interface $inner) {
		$this->inner = $inner;
	}

	/**
	 * Clear the tracked ID list, e.g. at the start of a scenario.
	 *
	 * @return void
	 */
	public function reset_tracking() {
		$this->created_ids = array();
	}

	/**
	 * History IDs created since the last reset_tracking() call.
	 *
	 * @return int[]
	 */
	public function get_created_ids() {
		return $this->created_ids;
	}

	public function create($type, $metadata = array()) {
		$container = $this->inner->create($type, $metadata);

		if (is_object($container) && method_exists($container, 'get_id')) {
			$id = (int) $container->get_id();

			if ($id > 0) {
				$this->created_ids[] = $id;
			}
		}

		return $container;
	}

	public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
		return $this->inner->get_activity_feed($limit, $offset, $filters);
	}

	public function post_has_history_and_completed($post_id) {
		return $this->inner->post_has_history_and_completed($post_id);
	}

	public function get_by_id($history_id) {
		return $this->inner->get_by_id($history_id);
	}

	public function update_history_record($history_id, $data) {
		return $this->inner->update_history_record($history_id, $data);
	}

	public function find_incomplete($type, $metadata = array()) {
		return $this->inner->find_incomplete($type, $metadata);
	}
}
