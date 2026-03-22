<?php
/**
 * Editions Controller
 *
 * AJAX management for editorial package editions.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Editions_Controller {

	/**
	 * @var AIPS_Editions_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Editions_Repository|null $repository Repository.
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: new AIPS_Editions_Repository();

		add_action('wp_ajax_aips_get_editions', array($this, 'ajax_get_editions'));
		add_action('wp_ajax_aips_get_edition', array($this, 'ajax_get_edition'));
		add_action('wp_ajax_aips_save_edition', array($this, 'ajax_save_edition'));
		add_action('wp_ajax_aips_delete_edition', array($this, 'ajax_delete_edition'));
	}

	/**
	 * Get all editions.
	 *
	 * @return void
	 */
	public function ajax_get_editions() {
		$this->guard_request();
		wp_send_json_success(array('editions' => $this->repository->get_all()));
	}

	/**
	 * Get a single edition.
	 *
	 * @return void
	 */
	public function ajax_get_edition() {
		$this->guard_request();

		$edition_id = isset($_POST['edition_id']) ? absint($_POST['edition_id']) : 0;
		$edition = $this->repository->get_by_id($edition_id);
		if (!$edition) {
			wp_send_json_error(array('message' => __('Edition not found.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('edition' => $edition));
	}

	/**
	 * Save an edition.
	 *
	 * @return void
	 */
	public function ajax_save_edition() {
		$this->guard_request();

		$slot_keys = isset($_POST['slot_key']) ? (array) $_POST['slot_key'] : array();
		$slot_labels = isset($_POST['slot_label']) ? (array) $_POST['slot_label'] : array();
		$slot_notes = isset($_POST['slot_notes']) ? (array) $_POST['slot_notes'] : array();
		$slot_sourcing = isset($_POST['slot_sourcing_status']) ? (array) $_POST['slot_sourcing_status'] : array();
		$slot_topics = isset($_POST['slot_assigned_topic']) ? (array) $_POST['slot_assigned_topic'] : array();
		$slot_templates = isset($_POST['slot_template_id']) ? (array) $_POST['slot_template_id'] : array();
		$slot_schedules = isset($_POST['slot_schedule_id']) ? (array) $_POST['slot_schedule_id'] : array();
		$slot_posts = isset($_POST['slot_post_id']) ? (array) $_POST['slot_post_id'] : array();

		$slots = array();
		foreach ($slot_keys as $index => $slot_key) {
			$slots[] = array(
				'slot_key' => $slot_key,
				'slot_label' => isset($slot_labels[$index]) ? $slot_labels[$index] : '',
				'notes' => isset($slot_notes[$index]) ? $slot_notes[$index] : '',
				'sourcing_status' => isset($slot_sourcing[$index]) ? $slot_sourcing[$index] : 'ready',
				'assigned_topic' => isset($slot_topics[$index]) ? $slot_topics[$index] : '',
				'template_id' => isset($slot_templates[$index]) ? $slot_templates[$index] : 0,
				'schedule_id' => isset($slot_schedules[$index]) ? $slot_schedules[$index] : 0,
				'post_id' => isset($slot_posts[$index]) ? $slot_posts[$index] : 0,
				'sort_order' => $index,
			);
		}

		$edition_id = $this->repository->save(
			array(
				'id' => isset($_POST['edition_id']) ? absint($_POST['edition_id']) : 0,
				'name' => isset($_POST['name']) ? wp_unslash($_POST['name']) : '',
				'theme' => isset($_POST['theme']) ? wp_unslash($_POST['theme']) : '',
				'cadence' => isset($_POST['cadence']) ? wp_unslash($_POST['cadence']) : '',
				'target_publish_date' => isset($_POST['target_publish_date']) ? wp_unslash($_POST['target_publish_date']) : '',
				'required_slots' => isset($_POST['required_slots']) ? absint($_POST['required_slots']) : 0,
				'owner' => isset($_POST['owner']) ? wp_unslash($_POST['owner']) : '',
				'channel_type' => isset($_POST['channel_type']) ? wp_unslash($_POST['channel_type']) : '',
				'is_active' => isset($_POST['is_active']) ? 1 : 0,
			),
			$slots
		);

		if (!$edition_id) {
			wp_send_json_error(array('message' => __('Failed to save edition.', 'ai-post-scheduler')));
		}

		$edition = $this->repository->get_by_id($edition_id);
		wp_send_json_success(array(
			'message' => __('Edition saved successfully.', 'ai-post-scheduler'),
			'edition' => $edition,
		));
	}

	/**
	 * Delete an edition.
	 *
	 * @return void
	 */
	public function ajax_delete_edition() {
		$this->guard_request();

		$edition_id = isset($_POST['edition_id']) ? absint($_POST['edition_id']) : 0;
		if (!$edition_id) {
			wp_send_json_error(array('message' => __('Invalid edition ID.', 'ai-post-scheduler')));
		}

		$result = $this->repository->delete($edition_id);
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to delete edition.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Edition deleted.', 'ai-post-scheduler')));
	}

	/**
	 * Shared nonce/capability checks.
	 *
	 * @return void
	 */
	private function guard_request() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
	}
}
