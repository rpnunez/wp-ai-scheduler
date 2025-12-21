<?php
/**
 * Image Service
 *
 * Handles image generation and upload operations for WordPress media library.
 * Separates image handling from content generation logic.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Image_Service
 *
 * Provides image generation and upload capabilities for AI-generated posts.
 * Handles downloading images from URLs and creating WordPress attachments.
 */
class AIPS_Image_Service {
    
    /**
     * @var AIPS_AI_Service AI Service instance for image generation
     */
    private $ai_service;
    
    /**
     * @var AIPS_Logger Logger instance
     */
    private $logger;
    
    /**
     * Initialize the Image Service.
     *
     * @param AIPS_AI_Service|null $ai_service Optional AI Service instance. Creates new if not provided.
     */
    public function __construct($ai_service = null) {
        $this->ai_service = $ai_service ? $ai_service : new AIPS_AI_Service();
        $this->logger = new AIPS_Logger();
    }
    
    /**
     * Generate and upload a featured image from an AI prompt.
     *
     * Uses AI to generate an image based on the prompt, then downloads and uploads
     * it to the WordPress media library.
     *
     * @param string $image_prompt The prompt to use for image generation.
     * @param string $post_title   The post title to use for the image filename.
     * @return int|WP_Error The attachment ID on success, WP_Error on failure.
     */
    public function generate_and_upload_featured_image($image_prompt, $post_title) {
        $image_url = $this->ai_service->generate_image($image_prompt);
        
        if (is_wp_error($image_url)) {
            return $image_url;
        }
        
        return $this->upload_image_from_url($image_url, $post_title);
    }
    
    /**
     * Upload an image from a URL to WordPress media library.
     *
     * Downloads an image from a given URL and creates a WordPress attachment.
     * Performs security validation on the response.
     *
     * @param string $image_url  The URL of the image to download.
     * @param string $post_title The post title to use for the image filename.
     * @return int|WP_Error The attachment ID on success, WP_Error on failure.
     */
    public function upload_image_from_url($image_url, $post_title) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // SECURITY: Use wp_safe_remote_get to prevent SSRF attacks
        $response_object = wp_safe_remote_get($image_url);

        if (is_wp_error($response_object)) {
            $error = new WP_Error(
                'image_download_failed',
                sprintf(__('Failed to fetch image: %s', 'ai-post-scheduler'), $response_object->get_error_message())
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }

        // Validate HTTP response code
        $response_code = wp_remote_retrieve_response_code($response_object);
        if ($response_code !== 200) {
            $error = new WP_Error(
                'image_download_failed',
                sprintf(__('Failed to fetch image. HTTP Code: %d', 'ai-post-scheduler'), $response_code)
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }

        // Validate content type is an image
        $content_type = wp_remote_retrieve_header($response_object, 'content-type');
        if (strpos($content_type, 'image/') !== 0) {
            $error = new WP_Error(
                'invalid_content_type',
                sprintf(__('Invalid content type: %s. Expected an image.', 'ai-post-scheduler'), $content_type)
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }

        $image_data = wp_remote_retrieve_body($response_object);
        
        if (empty($image_data)) {
            $error = new WP_Error(
                'empty_image_data',
                __('Downloaded image has no content.', 'ai-post-scheduler')
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }
        
        $post_slug = sanitize_title($post_title);
        $filename = $post_slug . '.jpg';
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // Write image data to file
        if (!file_put_contents($file_path, $image_data)) {
            $error = new WP_Error(
                'image_save_failed',
                sprintf(__('Failed to write image file: %s', 'ai-post-scheduler'), $file_path)
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }
        
        $attachment_id = $this->create_attachment($file_path, $filename);
        
        if (is_wp_error($attachment_id)) {
            // Clean up the file if attachment creation failed
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            return $attachment_id;
        }
        
        $this->logger->log('Featured image uploaded successfully', 'info', array(
            'attachment_id' => $attachment_id,
            'filename' => $filename
        ));
        
        return $attachment_id;
    }
    
    /**
     * Create a WordPress attachment from a file.
     *
     * @param string $file_path The full path to the image file.
     * @param string $filename  The filename to use for the attachment.
     * @return int|WP_Error The attachment ID on success, WP_Error on failure.
     */
    private function create_attachment($file_path, $filename) {
        $file_type = wp_check_filetype($filename);
        
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (is_wp_error($attachment_id)) {
            $error = new WP_Error(
                'attachment_insert_failed',
                sprintf(__('Failed to insert attachment: %s', 'ai-post-scheduler'), $attachment_id->get_error_message())
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }
        
        // Generate attachment metadata
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        return $attachment_id;
    }
    
    /**
     * Download and upload multiple images.
     *
     * Useful for batch operations or galleries.
     *
     * @param array  $image_urls  Array of image URLs to download.
     * @param string $post_title  The post title to use as base for filenames.
     * @return array Array of attachment IDs or WP_Error objects.
     */
    public function upload_multiple_images($image_urls, $post_title) {
        $results = array();
        
        foreach ($image_urls as $index => $image_url) {
            $filename = $post_title . '-' . ($index + 1);
            $results[] = $this->upload_image_from_url($image_url, $filename);
        }
        
        return $results;
    }
    
    /**
     * Check if an image URL is valid and accessible.
     *
     * @param string $image_url The URL to validate.
     * @return bool|WP_Error True if valid, WP_Error if not.
     */
    public function validate_image_url($image_url) {
        if (empty($image_url)) {
            return new WP_Error('empty_url', __('Image URL is empty.', 'ai-post-scheduler'));
        }
        
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Image URL is not valid.', 'ai-post-scheduler'));
        }
        
        // Use HEAD request to check if URL is accessible
        $response = wp_safe_remote_head($image_url);
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'url_not_accessible',
                sprintf(__('Image URL is not accessible: %s', 'ai-post-scheduler'), $response->get_error_message())
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'url_not_accessible',
                sprintf(__('Image URL returned HTTP %d.', 'ai-post-scheduler'), $response_code)
            );
        }
        
        return true;
    }
}
