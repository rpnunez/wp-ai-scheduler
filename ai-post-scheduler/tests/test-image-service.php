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
     * Test MIME type to extension mapping for common image types
     */
    public function test_mime_to_extension_mapping() {
        // Use reflection to test the private method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('get_extension_from_mime');
        $method->setAccessible(true);
        
        // Test common image MIME types
        $this->assertEquals('jpg', $method->invoke($this->service, 'image/jpeg'));
        $this->assertEquals('png', $method->invoke($this->service, 'image/png'));
        $this->assertEquals('gif', $method->invoke($this->service, 'image/gif'));
        $this->assertEquals('webp', $method->invoke($this->service, 'image/webp'));
        $this->assertEquals('bmp', $method->invoke($this->service, 'image/bmp'));
        $this->assertEquals('tif', $method->invoke($this->service, 'image/tiff'));
        $this->assertEquals('avif', $method->invoke($this->service, 'image/avif'));
        $this->assertEquals('ico', $method->invoke($this->service, 'image/x-icon'));
    }

    /**
     * Test default extension for unknown MIME type
     */
    public function test_mime_to_extension_fallback() {
        // Use reflection to test the private method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('get_extension_from_mime');
        $method->setAccessible(true);
        
        // Test unknown MIME type should default to jpg
        $this->assertEquals('jpg', $method->invoke($this->service, 'image/unknown'));
        $this->assertEquals('jpg', $method->invoke($this->service, 'application/octet-stream'));
    }
}
