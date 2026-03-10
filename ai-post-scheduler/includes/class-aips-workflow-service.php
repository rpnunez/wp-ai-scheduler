<?php
/**
 * Workflow Service
 *
 * Encapsulates business logic for AI Post Scheduler workflows,
 * including status management, workflow retrieval, and history integration.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Workflow_Service
 *
 * Handles all business logic for workflows: CRUD operations via the repository,
 * status validation, and updating history/review records with workflow metadata.
 */
class AIPS_Workflow_Service {

	const STATUS_GENERATED        = 'generated';
	const STATUS_NEEDS_REVIEW     = 'needs_review';
	const STATUS_APPROVED         = 'approved';
	const STATUS_READY_TO_PUBLISH = 'ready_to_publish';

	/**
	 * @var AIPS_Workflow_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Workflow_Repository|null $repo Optional repository instance for dependency injection.
	 */
	public function __construct(AIPS_Workflow_Repository $repo = null) {
		$this->repo = $repo ?: new AIPS_Workflow_Repository();
	}

	/**
	 * Return all stored workflows.
	 *
	 * @return array
	 */
	public function get_all_workflows() {
		return $this->repo->get_all();
	}

	/**
	 * Return a single workflow by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function get_workflow($id) {
		return $this->repo->get_by_id($id);
	}

	/**
	 * Return the repository instance.
	 *
	 * @return AIPS_Workflow_Repository
	 */
	public function get_repo() {
		return $this->repo;
	}

	/**
	 * Return the map of status keys to translated labels.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			self::STATUS_GENERATED        => __('Generated', 'ai-post-scheduler'),
			self::STATUS_NEEDS_REVIEW     => __('Needs Review', 'ai-post-scheduler'),
			self::STATUS_APPROVED         => __('Approved', 'ai-post-scheduler'),
			self::STATUS_READY_TO_PUBLISH => __('Ready to Publish', 'ai-post-scheduler'),
		);
	}

	/**
	 * Return the translated label for a given status key.
	 *
	 * @param string $key
	 * @return string
	 */
	public static function get_status_label($key) {
		$statuses = self::get_statuses();
		return isset($statuses[$key]) ? $statuses[$key] : $key;
	}

	/**
	 * Return the message configuration array for a given message key.
	 *
	 * @param string $key
	 * @return array|null
	 */
	public static function get_message_for_key($key) {
		$map = array(
			'workflow_created'       => array('text' => __('Workflow created.', 'ai-post-scheduler'),           'type' => 'success'),
			'workflow_updated'       => array('text' => __('Workflow updated.', 'ai-post-scheduler'),           'type' => 'success'),
			'workflow_deleted'       => array('text' => __('Workflow deleted.', 'ai-post-scheduler'),           'type' => 'success'),
			'workflow_name_required' => array('text' => __('Workflow name is required.', 'ai-post-scheduler'), 'type' => 'error'),
			'workflow_not_found'     => array('text' => __('Workflow not found.', 'ai-post-scheduler'),         'type' => 'error'),
			'workflow_save_failed'   => array('text' => __('Unable to save workflow.', 'ai-post-scheduler'),    'type' => 'error'),
			'workflow_delete_failed' => array('text' => __('Unable to delete workflow.', 'ai-post-scheduler'),  'type' => 'error'),
		);
		return isset($map[$key]) ? $map[$key] : null;
	}

	/**
	 * Normalize a status value, falling back to STATUS_GENERATED for unknown values.
	 *
	 * @param string $status
	 * @return string
	 */
	public function sanitize_status($status) {
		$valid = array_keys(self::get_statuses());
		return in_array($status, $valid, true) ? $status : self::STATUS_GENERATED;
	}

	/**
	 * Update the workflow metadata on a history/review entry.
	 *
	 * @param int         $history_id
	 * @param string|null $status
	 * @param int|null    $workflow_id
	 * @return bool
	 */
	public function set_history_workflow($history_id, $status = null, $workflow_id = null) {
		if (!$history_id) {
			return false;
		}

		if ($status !== null) {
			$status = $this->sanitize_status($status);
		}

		$repo = new AIPS_Post_Review_Repository();
		return $repo->update_workflow_status($history_id, $status, $workflow_id);
	}
}
