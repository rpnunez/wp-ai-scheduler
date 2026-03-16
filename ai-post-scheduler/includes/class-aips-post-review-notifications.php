<?php
/**
 * Post Review Email Notifications — Deprecated wrapper
 *
 * @deprecated 1.9.0 Use AIPS_Notifications::posts_awaiting_review() instead.
 *                   All logic has been centralised in AIPS_Notifications.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPS_Post_Review_Notifications
 *
 * @deprecated 1.9.0 Use AIPS_Notifications directly.
 */
class AIPS_Post_Review_Notifications {

	/**
	 * @var AIPS_Notifications
	 */
	private $notifications;

	/**
	 * Initialize the notification handler.
	 *
	 * @deprecated 1.9.0
	 */
	public function __construct() {
		$this->notifications = new AIPS_Notifications();
	}

	/**
	 * Send the daily review notification email.
	 *
	 * @deprecated 1.9.0 Use AIPS_Notifications::handle_review_notifications_cron() instead.
	 *
	 * @return void
	 */
	public function send_review_notification_email() {
		$this->notifications->handle_review_notifications_cron();
	}
}
