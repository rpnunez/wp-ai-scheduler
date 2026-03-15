<?php
/**
 * Partial Generation Email Notifications — Deprecated wrapper
 *
 * @deprecated 1.9.0 Use AIPS_Notifications::partial_generation() instead.
 *                   All logic has been centralised in AIPS_Notifications.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
exit;
}

/**
 * Class AIPS_Partial_Generation_Notifications
 *
 * @deprecated 1.9.0 Use AIPS_Notifications directly.
 */
class AIPS_Partial_Generation_Notifications {

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
 * Send an email when a post is created with missing generated components.
 *
 * @deprecated 1.9.0 Use AIPS_Notifications::partial_generation() instead.
 *
 * @param int                     $post_id             Post ID.
 * @param array                   $component_statuses  Per-component status map.
 * @param AIPS_Generation_Context $context             Generation context.
 * @param int                     $history_id          Related history ID.
 * @return void
 */
public function send_partial_generation_notification($post_id, $component_statuses, $context, $history_id = 0) {
$this->notifications->partial_generation(absint($post_id), (array) $component_statuses, $context, (int) $history_id);
}
}
