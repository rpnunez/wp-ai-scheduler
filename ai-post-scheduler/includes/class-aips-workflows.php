<?php
/**
 * Workflows – Backward-compatibility shim.
 *
 * All logic has been moved to AIPS_Workflow_Service (business logic)
 * and AIPS_Workflow_Controller (HTTP / AJAX handling).
 * This class is retained so that any third-party code that still
 * references AIPS_Workflows::* continues to work.
 *
 * @deprecated since 1.7.5 – use AIPS_Workflow_Service / AIPS_Workflow_Controller instead.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Workflows
 *
 * Thin shim that proxies calls to AIPS_Workflow_Service and AIPS_Workflow_Controller.
 *
 * @deprecated since 1.7.5
 */
class AIPS_Workflows {

	/** @deprecated use AIPS_Workflow_Service::STATUS_GENERATED */
	const STATUS_GENERATED = 'generated';

	/** @deprecated use AIPS_Workflow_Service::STATUS_NEEDS_REVIEW */
	const STATUS_NEEDS_REVIEW = 'needs_review';

	/** @deprecated use AIPS_Workflow_Service::STATUS_APPROVED */
	const STATUS_APPROVED = 'approved';

	/** @deprecated use AIPS_Workflow_Service::STATUS_READY_TO_PUBLISH */
	const STATUS_READY_TO_PUBLISH = 'ready_to_publish';

	/**
	 * @deprecated use AIPS_Workflow_Service::get_statuses()
	 * @return array
	 */
	public static function get_statuses() {
		return AIPS_Workflow_Service::get_statuses();
	}

	/**
	 * @deprecated use AIPS_Workflow_Service::get_status_label()
	 * @param string $key
	 * @return string
	 */
	public static function get_status_label($key) {
		return AIPS_Workflow_Service::get_status_label($key);
	}

	/**
	 * @deprecated use AIPS_Workflow_Service::get_message_for_key()
	 * @param string $key
	 * @return array|null
	 */
	public static function get_message_for_key($key) {
		return AIPS_Workflow_Service::get_message_for_key($key);
	}

	/**
	 * @deprecated instantiate AIPS_Workflow_Controller directly instead.
	 * @return array
	 */
	public static function get_all_workflows() {
		$service = new AIPS_Workflow_Service();
		return $service->get_all_workflows();
	}

	/**
	 * @deprecated use AIPS_Workflow_Service::get_workflow()
	 * @param int $id
	 * @return object|null
	 */
	public static function get_workflow($id) {
		$service = new AIPS_Workflow_Service();
		return $service->get_workflow($id);
	}

	/**
	 * @deprecated use AIPS_Workflow_Service::set_history_workflow()
	 * @param int         $history_id
	 * @param string|null $status
	 * @param int|null    $workflow_id
	 * @return bool
	 */
	public static function set_history_workflow($history_id, $status = null, $workflow_id = null) {
		$service = new AIPS_Workflow_Service();
		return $service->set_history_workflow($history_id, $status, $workflow_id);
	}
}
