<?php
/**
 * Tests for partial generation notifications via AIPS_Notifications.
 *
 * Covers the deprecated AIPS_Partial_Generation_Notifications wrapper to
 * ensure backward compatibility is preserved.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Partial_Generation_Notifications extends WP_UnitTestCase {

    /** @var AIPS_Notifications_Repository */
    private $repository;

    public function setUp(): void {
        parent::setUp();
        AIPS_DB_Manager::install_tables();
        $this->repository = new AIPS_Notifications_Repository();
    }

    /**
     * Deprecated wrapper: send_partial_generation_notification() delegates
     * to AIPS_Notifications::partial_generation() and sends an email when
     * components are missing.
     */
    public function test_deprecated_wrapper_sends_email_for_missing_components() {
        update_option( 'aips_review_notifications_email', 'test@example.com' );

        $post_id = wp_insert_post( array(
            'post_title'  => 'Recovery Post',
            'post_status' => 'draft',
            'post_type'   => 'post',
        ) );

        $context = new AIPS_Template_Context( (object) array(
            'id'              => 55,
            'name'            => 'Recovery Template',
            'prompt_template' => 'Prompt',
            'post_status'     => 'draft',
            'post_category'   => 0,
        ) );

        $GLOBALS['phpmailer']->mock_sent = array();

        $wrapper = new AIPS_Partial_Generation_Notifications();
        $wrapper->send_partial_generation_notification(
            $post_id,
            array(
                'post_title'     => true,
                'post_excerpt'   => false,
                'post_content'   => true,
                'featured_image' => false,
            ),
            $context,
            77
        );

        $this->assertCount( 1, $GLOBALS['phpmailer']->mock_sent );
        $body = $GLOBALS['phpmailer']->mock_sent[0]['body'];

        $this->assertStringContainsString( 'Recovery Post', $body );
        $this->assertStringContainsString( 'Excerpt', $body );
        $this->assertStringContainsString( 'Featured Image', $body );
        $this->assertStringContainsString( admin_url( 'admin.php?page=aips-generated-posts#aips-partial-generations' ), $body );
        $this->assertStringContainsString( 'Recovery Template', $body );
        // Session ID should appear.
        $this->assertStringContainsString( '77', $body );

        wp_delete_post( $post_id, true );
        delete_option( 'aips_review_notifications_email' );
    }

    /**
     * No email is sent when all components are present.
     */
    public function test_no_email_when_all_components_succeed() {
        update_option( 'aips_review_notifications_email', 'test@example.com' );

        $post_id = wp_insert_post( array(
            'post_title'  => 'Complete Post',
            'post_status' => 'draft',
            'post_type'   => 'post',
        ) );

        $GLOBALS['phpmailer']->mock_sent = array();

        $wrapper = new AIPS_Partial_Generation_Notifications();
        $wrapper->send_partial_generation_notification(
            $post_id,
            array(
            'post_title'     => true,
            'post_excerpt'   => true,
            'post_content'   => true,
            'featured_image' => true,
            ),
            null
        );

        $this->assertEmpty( $GLOBALS['phpmailer']->mock_sent );

        wp_delete_post( $post_id, true );
        delete_option( 'aips_review_notifications_email' );
    }
}
