<?php
/**
 * Notifications Controller
 *
 * Handles AJAX requests for the Notifications admin page.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notifications_Controller
 *
 * Manages AJAX endpoints for viewing and managing admin notifications.
 */
class AIPS_Notifications_Controller {

	/**
	 * @var AIPS_Notifications_Repository Notifications repository.
	 */
	private $repository;

	/**
	 * Constructor. Registers all AJAX hooks.
	 *
	 * @param AIPS_Notifications_Repository|null $repository Optional repository instance.
	 */
	public function __construct($repository = null) {
		$this->repository = $repository instanceof AIPS_Notifications_Repository
			? $repository
			: new AIPS_Notifications_Repository();

		add_action('wp_ajax_aips_get_notifications_list',      array($this, 'ajax_get_notifications_list'));
		add_action('wp_ajax_aips_mark_notification_read',      array($this, 'ajax_mark_notification_read'));
		add_action('wp_ajax_aips_mark_notification_unread',    array($this, 'ajax_mark_notification_unread'));
		add_action('wp_ajax_aips_delete_notification',         array($this, 'ajax_delete_notification'));
		add_action('wp_ajax_aips_bulk_notifications_action',   array($this, 'ajax_bulk_notifications_action'));
		add_action('wp_ajax_aips_mark_all_notifications_read', array($this, 'ajax_mark_all_notifications_read'));
	}

	/**
	 * AJAX: Get paginated notifications list.
	 *
	 * @return void
	 */
	public function ajax_get_notifications_list() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$args = array(
			'page'     => isset($_POST['page'])     ? absint($_POST['page'])                                    : 1,
			'per_page' => isset($_POST['per_page']) ? absint($_POST['per_page'])                                : 20,
			'level'    => isset($_POST['level'])    ? sanitize_key(wp_unslash($_POST['level']))                 : '',
			'type'     => isset($_POST['type'])     ? sanitize_text_field(wp_unslash($_POST['type']))           : '',
			'is_read'  => isset($_POST['is_read'])  ? (int) $_POST['is_read']                                   : -1,
			'search'   => isset($_POST['search'])   ? sanitize_text_field(wp_unslash($_POST['search']))         : '',
			'orderby'  => isset($_POST['orderby'])  ? sanitize_key(wp_unslash($_POST['orderby']))               : 'created_at',
			'order'    => isset($_POST['order'])    ? sanitize_key(wp_unslash($_POST['order']))                 : 'DESC',
		);

		$result  = $this->repository->get_paginated($args);
		$summary = $this->repository->get_summary_counts();

		wp_send_json_success(array(
			'items'   => $result['items'],
			'total'   => $result['total'],
			'pages'   => $result['pages'],
			'page'    => $args['page'],
			'summary' => $summary,
		));
	}

	/**
	 * AJAX: Mark a single notification as read.
	 *
	 * @return void
	 */
	public function ajax_mark_notification_read() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid notification ID.', 'ai-post-scheduler')));
		}

		if ($this->repository->mark_as_read($id)) {
			wp_send_json_success(array('message' => __('Notification marked as read.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to update notification.', 'ai-post-scheduler')));
		}
	}

	/**
	 * AJAX: Mark a single notification as unread.
	 *
	 * @return void
	 */
	public function ajax_mark_notification_unread() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid notification ID.', 'ai-post-scheduler')));
		}

		if ($this->repository->mark_as_unread($id)) {
			wp_send_json_success(array('message' => __('Notification marked as unread.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to update notification.', 'ai-post-scheduler')));
		}
	}

	/**
	 * AJAX: Delete a single notification.
	 *
	 * @return void
	 */
	public function ajax_delete_notification() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid notification ID.', 'ai-post-scheduler')));
		}

		if ($this->repository->delete_notification($id)) {
			wp_send_json_success(array('message' => __('Notification deleted.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to delete notification.', 'ai-post-scheduler')));
		}
	}

	/**
	 * AJAX: Bulk action on multiple notifications.
	 *
	 * Supported bulk_action values: mark_read, mark_unread, delete.
	 *
	 * @return void
	 */
	public function ajax_bulk_notifications_action() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$bulk_action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
		$ids_raw     = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
		$ids         = array_filter(array_map('absint', $ids_raw));

		if (empty($ids)) {
			wp_send_json_error(array('message' => __('No notifications selected.', 'ai-post-scheduler')));
		}

		$allowed_actions = array('mark_read', 'mark_unread', 'delete');
		if (!in_array($bulk_action, $allowed_actions, true)) {
			wp_send_json_error(array('message' => __('Invalid bulk action.', 'ai-post-scheduler')));
		}

		$success = false;
		switch ($bulk_action) {
			case 'mark_read':
				$success = $this->repository->bulk_mark_as_read($ids);
				break;
			case 'mark_unread':
				$success = $this->repository->bulk_mark_as_unread($ids);
				break;
			case 'delete':
				$success = $this->repository->bulk_delete($ids);
				break;
		}

		if ($success) {
			wp_send_json_success(array(
				'message' => __('Bulk action applied successfully.', 'ai-post-scheduler'),
				'count'   => count($ids),
			));
		} else {
			wp_send_json_error(array('message' => __('Bulk action failed.', 'ai-post-scheduler')));
		}
	}

	/**
	 * AJAX: Mark all notifications as read.
	 *
	 * @return void
	 */
	public function ajax_mark_all_notifications_read() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		if ($this->repository->mark_all_as_read()) {
			wp_send_json_success(array('message' => __('All notifications marked as read.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to mark all notifications as read.', 'ai-post-scheduler')));
		}
	}
}
