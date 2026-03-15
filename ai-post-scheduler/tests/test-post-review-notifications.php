<?php
/**
 * Tests for Post Review Notifications via AIPS_Notifications.
 *
 * Covers the deprecated AIPS_Post_Review_Notifications wrapper to ensure
 * backward compatibility is preserved, as well as the cron handler on
 * AIPS_Notifications directly.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Review_Notifications extends WP_UnitTestCase {

/** @var AIPS_Post_Review_Notifications */
private $notifications;

/** @var AIPS_Post_Review_Repository */
private $repository;

/** @var int[] */
private $test_post_ids = array();

/** @var int[] */
private $test_history_ids = array();

public function setUp(): void {
parent::setUp();
AIPS_DB_Manager::install_tables();
$this->notifications = new AIPS_Post_Review_Notifications();
$this->repository    = new AIPS_Post_Review_Repository();
}

public function tearDown(): void {
foreach ( $this->test_post_ids as $post_id ) {
wp_delete_post( $post_id, true );
}

global $wpdb;
$history_table = $wpdb->prefix . 'aips_history';
foreach ( $this->test_history_ids as $history_id ) {
$wpdb->delete( $history_table, array( 'id' => $history_id ), array( '%d' ) );
}

delete_option( 'aips_review_notifications_enabled' );
delete_option( 'aips_review_notifications_email' );

parent::tearDown();
}

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

private function create_test_post_with_history( $post_status = 'draft', $template_id = 1 ) {
$post_id = wp_insert_post( array(
'post_title'   => 'Test Draft Post ' . uniqid(),
'post_content' => 'Test content',
'post_status'  => $post_status,
'post_type'    => 'post',
) );

$this->test_post_ids[] = $post_id;

global $wpdb;
$history_table = $wpdb->prefix . 'aips_history';
$wpdb->insert( $history_table, array(
'post_id'           => $post_id,
'template_id'       => $template_id,
'status'            => 'completed',
'generated_title'   => 'Test Generated Title',
'generated_content' => 'Test generated content',
'created_at'        => current_time( 'mysql' ),
'completed_at'      => current_time( 'mysql' ),
) );

$history_id = $wpdb->insert_id;
$this->test_history_ids[] = $history_id;

return array( 'post_id' => $post_id, 'history_id' => $history_id );
}

// -----------------------------------------------------------------------
// Deprecated wrapper: send_review_notification_email()
// -----------------------------------------------------------------------

/**
 * Deprecated wrapper must not send when notifications are disabled.
 */
public function test_notifications_not_sent_when_disabled() {
update_option( 'aips_review_notifications_enabled', 0 );
update_option( 'aips_review_notifications_email', 'test@example.com' );

$this->create_test_post_with_history( 'draft', 1 );

$GLOBALS['phpmailer']->mock_sent = array();

$this->notifications->send_review_notification_email();

$this->assertEmpty( $GLOBALS['phpmailer']->mock_sent );
}

/**
 * Deprecated wrapper must not send when there are no draft posts.
 */
public function test_notifications_not_sent_when_no_drafts() {
update_option( 'aips_review_notifications_enabled', 1 );
update_option( 'aips_review_notifications_email', 'test@example.com' );

$GLOBALS['phpmailer']->mock_sent = array();

$this->notifications->send_review_notification_email();

$this->assertEmpty( $GLOBALS['phpmailer']->mock_sent );
}

/**
 * Deprecated wrapper must not send with an invalid email address.
 */
public function test_invalid_email_prevents_sending() {
update_option( 'aips_review_notifications_enabled', 1 );
update_option( 'aips_review_notifications_email', 'not-an-email' );

$this->create_test_post_with_history( 'draft', 1 );

$GLOBALS['phpmailer']->mock_sent = array();

$this->notifications->send_review_notification_email();

$this->assertEmpty( $GLOBALS['phpmailer']->mock_sent );
}

/**
 * Email message should include "Posts Awaiting Review" when drafts exist.
 */
public function test_email_message_format() {
update_option( 'aips_review_notifications_enabled', 1 );
update_option( 'aips_review_notifications_email', 'test@example.com' );

$this->create_test_post_with_history( 'draft', 1 );
$this->create_test_post_with_history( 'draft', 1 );

$GLOBALS['phpmailer']->mock_sent = array();

$this->notifications->send_review_notification_email();

$this->assertCount( 1, $GLOBALS['phpmailer']->mock_sent );

$body = $GLOBALS['phpmailer']->mock_sent[0]['body'];

$this->assertStringContainsString( 'Posts Awaiting Review', $body );
$this->assertStringContainsString( 'Review Posts', $body );
$this->assertStringContainsString( admin_url( 'admin.php?page=aips-generated-posts#aips-pending-review' ), $body );
}

// -----------------------------------------------------------------------
// Cron scheduling (not coupled to class logic)
// -----------------------------------------------------------------------

public function test_cron_job_scheduling() {
wp_clear_scheduled_hook( 'aips_send_review_notifications' );
wp_schedule_event( time(), 'daily', 'aips_send_review_notifications' );

$timestamp = wp_next_scheduled( 'aips_send_review_notifications' );
$this->assertNotFalse( $timestamp );
$this->assertGreaterThanOrEqual( time(), $timestamp );

wp_clear_scheduled_hook( 'aips_send_review_notifications' );
}

public function test_cron_job_clearing() {
wp_schedule_event( time(), 'daily', 'aips_send_review_notifications' );
$this->assertNotFalse( wp_next_scheduled( 'aips_send_review_notifications' ) );

wp_clear_scheduled_hook( 'aips_send_review_notifications' );
$this->assertFalse( wp_next_scheduled( 'aips_send_review_notifications' ) );
}
}
