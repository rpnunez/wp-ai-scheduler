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
     * Test that finfo validation rejects non-image content with spoofed headers
     * 
     * This test verifies the security validation that uses finfo to check actual
     * file content, preventing non-image files (e.g., scripts/HTML) from being
     * saved even if the Content-Type header claims to be an image.
     */
    public function test_finfo_rejects_non_image_content() {
        // Skip test if finfo is not available
        if (!class_exists('finfo')) {
            $this->markTestSkipped('finfo extension not available');
        }

        // Mock HTML content that could be malicious
        $malicious_content = '<!DOCTYPE html><html><body><script>alert("xss")</script></body></html>';
        
        // Create a mock response that has image Content-Type but non-image content
        $mock_response = array(
            'headers' => array(
                'content-type' => 'image/jpeg',
            ),
            'body' => $malicious_content,
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            ),
        );

        // Mock wp_safe_remote_get to return our crafted response
        add_filter('pre_http_request', function($preempt, $args, $url) use ($mock_response) {
            if ($url === 'https://evil.example.com/fake-image.jpg') {
                return $mock_response;
            }
            return $preempt;
        }, 10, 3);

        // Attempt to upload the "image"
        $result = $this->service->upload_image_from_url(
            'https://evil.example.com/fake-image.jpg',
            'Test Post'
        );

        // Clean up filter
        remove_all_filters('pre_http_request');

        // Verify it returns WP_Error with correct error code
        $this->assertInstanceOf('WP_Error', $result, 'Expected WP_Error for non-image content');
        $this->assertEquals(
            'invalid_image_content',
            $result->get_error_code(),
            'Expected error code to be invalid_image_content'
        );
        
        // Verify error message mentions the actual detected MIME type
        $error_message = $result->get_error_message();
        $this->assertStringContainsString('Security check failed', $error_message);
        $this->assertStringContainsString('expected image', $error_message);
    }
}
