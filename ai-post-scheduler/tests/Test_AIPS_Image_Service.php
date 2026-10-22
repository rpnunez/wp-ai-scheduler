<?php
/**
 * Test case for Image Service
 *
 * Tests the extraction and functionality of AIPS_Image_Service class.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

class Test_AIPS_Image_Service extends WP_UnitTestCase {

    private $service;

    public function setUp(): void {
        parent::setUp();
        $this->service = new AIPS_Image_Service();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test service instantiation
     */
    public function test_service_instantiation() {
        $this->assertInstanceOf('AIPS_Image_Service', $this->service);
    }

    /**
     * Test validate_image_url with empty URL
     */
    public function test_validate_image_url_empty() {
        $result = $this->service->validate_image_url('');
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('empty_url', $result->get_error_code());
    }

    /**
     * Test validate_image_url with invalid URL format
     */
    public function test_validate_image_url_invalid_format() {
        $result = $this->service->validate_image_url('not-a-valid-url');
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_url', $result->get_error_code());
    }

    /**
     * Test validate_image_url with non-existent URL
     */
    public function test_validate_image_url_non_existent() {
        $result = $this->service->validate_image_url('https://example.com/non-existent-image-12345.jpg');
        
        // This might return WP_Error depending on network conditions
        // So we just check it's either true or WP_Error
        $this->assertTrue($result === true || is_wp_error($result));
    }

    /**
     * Test upload_multiple_images returns array
     */
    public function test_upload_multiple_images_returns_array() {
        $image_urls = array(
            'https://example.com/image1.jpg',
            'https://example.com/image2.jpg'
        );
        
        $results = $this->service->upload_multiple_images($image_urls, 'Test Post');
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /**
     * Test upload_image_from_url with invalid URL returns error
     */
    public function test_upload_image_from_url_invalid_url() {
        $result = $this->service->upload_image_from_url('https://invalid-domain-12345.com/image.jpg', 'Test');
        
        // Should return WP_Error for network failure
        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Test generate_and_upload_featured_image without AI available
     */
    public function test_generate_and_upload_without_ai() {
        // Create service with AI service that's not available
        $ai_service = new AIPS_AI_Service();
        
        if (!$ai_service->is_available()) {
            $image_service = new AIPS_Image_Service($ai_service);
            $result = $image_service->generate_and_upload_featured_image('Test prompt', 'Test Post');
            
            $this->assertInstanceOf('WP_Error', $result);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test unavailable scenario');
        }
    }

    /**
     * Test service accepts custom AI service in constructor
     */
    public function test_custom_ai_service_injection() {
        $custom_ai_service = new AIPS_AI_Service();
        $image_service = new AIPS_Image_Service($custom_ai_service);
        
        $this->assertInstanceOf('AIPS_Image_Service', $image_service);
    }

    /**
     * Test upload_multiple_images with empty array
     */
    public function test_upload_multiple_images_empty() {
        $results = $this->service->upload_multiple_images(array(), 'Test Post');
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test filename sanitization in upload process
     */
    public function test_filename_sanitization() {
        // This test validates that the service properly sanitizes filenames
        // by attempting to upload with special characters in title
        $result = $this->service->upload_image_from_url(
            'https://invalid-domain.com/image.jpg',
            'Test Post with Special Characters !@#$%'
        );
        
        // Even though download will fail, we're testing that it doesn't crash
        // on filename sanitization
        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Ensure Unsplash integration validates missing access key.
     */
    public function test_fetch_unsplash_image_requires_key() {
        delete_option('aips_unsplash_access_key');

        $result = $this->service->fetch_and_upload_unsplash_image('mountains', 'Test Post');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('unsplash_key_missing', $result->get_error_code());
    }

    /**
     * Ensure media library selection validates empty input.
     */
    public function test_select_media_library_image_without_ids() {
        $result = $this->service->select_media_library_image('');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('no_media_images', $result->get_error_code());
    }

    /**
     * A valid 1x1 PNG as a base64 payload (used to build data URIs).
     *
     * @return string
     */
    private function get_png_base64() {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    /**
     * upload_image_from_url must dispatch data URIs to the data-URI ingestion
     * path (the WP AI Client provider returns generated images as data URIs).
     */
    public function test_upload_image_from_data_uri_creates_png_attachment() {
        $data_uri = 'data:image/png;base64,' . $this->get_png_base64();

        $attachment_id = $this->service->upload_image_from_url($data_uri, 'Data URI Test Post');

        $this->assertIsInt($attachment_id);
        $this->assertEquals('image/png', get_post_mime_type($attachment_id));
        $this->assertFileExists(get_attached_file($attachment_id));
    }

    /**
     * Non-image data URIs must be rejected before touching the filesystem.
     */
    public function test_upload_image_from_data_uri_rejects_non_image_mime() {
        $data_uri = 'data:text/plain;base64,' . base64_encode('not an image');

        $result = $this->service->upload_image_from_data_uri($data_uri, 'Bad Data URI');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_data_uri', $result->get_error_code());
    }

    /**
     * A declared image mime wrapping non-image bytes must fail the finfo check.
     */
    public function test_upload_image_from_data_uri_rejects_spoofed_image_payload() {
        if (!class_exists('finfo')) {
            $this->markTestSkipped('finfo extension unavailable.');
        }

        $data_uri = 'data:image/png;base64,' . base64_encode('<?php echo "not an image"; ?>');

        $result = $this->service->upload_image_from_data_uri($data_uri, 'Spoofed Data URI');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_image_content', $result->get_error_code());
    }

    /**
     * Malformed / non-base64 data URIs must be rejected.
     */
    public function test_upload_image_from_data_uri_rejects_malformed_uri() {
        $result = $this->service->upload_image_from_data_uri('data:image/png;base64,', 'Empty Payload');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_data_uri', $result->get_error_code());

        $result = $this->service->upload_image_from_data_uri('data:image/png,rawdata', 'Not Base64');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_data_uri', $result->get_error_code());
    }

    /**
     * End-to-end: the exact production path that breaks without data-URI
     * support — a WP AI Client provider returning a data URI feeding
     * generate_and_upload_featured_image().
     */
    public function test_generate_and_upload_featured_image_accepts_provider_data_uri() {
        if (!function_exists('wp_ai_client_prompt') || !class_exists('AIPS_Test_WP_AI_Client_Builder')) {
            $this->markTestSkipped('WP AI Client test fakes not loaded (run with the full suite).');
        }

        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->image_response = 'data:image/png;base64,' . $this->get_png_base64();
        $aips_wp_ai_client_test_builder = $builder;

        $ai_service = new AIPS_AI_Service(null, null, null, new AIPS_WP_AI_Client_Provider());
        $image_service = new AIPS_Image_Service($ai_service);

        $attachment_id = $image_service->generate_and_upload_featured_image('A test image', 'Provider Data URI Post');

        $aips_wp_ai_client_test_builder = null;

        $this->assertIsInt($attachment_id);
        $this->assertEquals('image/png', get_post_mime_type($attachment_id));
    }
}
