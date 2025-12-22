<?php
/**
 * Test case for Image Service Collision
 *
 * Tests specifically for file overwrite behavior in AIPS_Image_Service.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.1
 */

class Test_AIPS_Image_Service_Collision extends WP_UnitTestCase {

    private $service;

    public function setUp() {
        parent::setUp();
        $this->service = new AIPS_Image_Service();
    }

    /**
     * Test that identical titles produce unique filenames.
     * This simulates the condition where two posts have the same title
     * and ensures we don't overwrite the first image.
     */
    public function test_image_filename_collision_avoidance() {
        // We can't easily mock file system operations in this environment without runkit/uopz.
        // However, we can verify that wp_upload_bits (if used) would handle it.
        // Since we can't inspect the internal method calls easily, this test
        // relies on the side-effect of creating attachments.

        // 1. Mock a successful HTTP response for wp_safe_remote_get
        add_filter( 'pre_http_request', array( $this, 'mock_http_response' ), 10, 3 );

        $title = 'Duplicate Title';

        // 2. Upload first image
        $attachment_id_1 = $this->service->upload_image_from_url( 'http://example.com/image.jpg', $title );
        $this->assertNotWPError( $attachment_id_1, 'First upload failed' );

        $file_1 = get_attached_file( $attachment_id_1 );
        $this->assertNotEmpty( $file_1 );

        // 3. Upload second image with SAME title
        $attachment_id_2 = $this->service->upload_image_from_url( 'http://example.com/image.jpg', $title );
        $this->assertNotWPError( $attachment_id_2, 'Second upload failed' );

        $file_2 = get_attached_file( $attachment_id_2 );
        $this->assertNotEmpty( $file_2 );

        // 4. Verify they are NOT the same file path
        $this->assertNotEquals( $file_1, $file_2, 'Filenames should be unique even with same post title' );

        // Clean up
        wp_delete_attachment( $attachment_id_1, true );
        wp_delete_attachment( $attachment_id_2, true );

        remove_filter( 'pre_http_request', array( $this, 'mock_http_response' ) );
    }

    /**
     * Mock HTTP response for image download
     */
    public function mock_http_response( $preempt, $args, $url ) {
        return array(
            'response' => array(
                'code' => 200,
                'message' => 'OK',
            ),
            'headers' => array(
                'content-type' => 'image/jpeg',
            ),
            'body' => 'fake_image_binary_data',
        );
    }
}
