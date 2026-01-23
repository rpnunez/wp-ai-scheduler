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
     * Fetch an Unsplash image by keywords and upload it to the media library.
     *
     * @param string $keywords   Keywords to search Unsplash.
     * @param string $post_title Post title used for the attachment name.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    public function fetch_and_upload_unsplash_image($keywords, $post_title) {
        $image_url = $this->fetch_unsplash_image_url($keywords);

        if (is_wp_error($image_url)) {
            return $image_url;
        }

        return $this->upload_image_from_url($image_url, $post_title);
    }

    /**
     * Select a media library image from a list of IDs.
     *
     * When multiple IDs are provided, a random one is chosen to add variation.
     *
     * @param string|array $media_ids Comma-separated IDs or array of IDs.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    public function select_media_library_image($media_ids) {
        if (empty($media_ids)) {
            return new WP_Error('no_media_images', __('No media library images were provided.', 'ai-post-scheduler'));
        }

        if (!is_array($media_ids)) {
            $media_ids = explode(',', $media_ids);
        }

        $sanitized_ids = array_filter(array_map('absint', $media_ids));
        $valid_ids = array();

        foreach ($sanitized_ids as $id) {
            if ($id && function_exists('wp_attachment_is_image') && wp_attachment_is_image($id)) {
                $valid_ids[] = $id;
            }
        }

        if (empty($valid_ids)) {
            return new WP_Error('invalid_media_images', __('No valid image attachments were found in the selected media items.', 'ai-post-scheduler'));
        }

        return $valid_ids[array_rand($valid_ids)];
    }

    /**
     * Retrieve an Unsplash image URL using the configured API key.
     *
     * @param string $keywords Keywords to search for.
     * @return string|WP_Error Image URL on success, WP_Error on failure.
     */
    private function fetch_unsplash_image_url($keywords) {
        $access_key = trim(get_option('aips_unsplash_access_key', ''));

        if (empty($access_key)) {
            return new WP_Error('unsplash_key_missing', __('Unsplash access key is not configured.', 'ai-post-scheduler'));
        }

        if (empty($keywords)) {
            return new WP_Error('unsplash_keywords_missing', __('Please provide keywords to search for an Unsplash image.', 'ai-post-scheduler'));
        }

        $keywords = sanitize_text_field($keywords);

        $endpoint = add_query_arg(
            array(
                'query' => $keywords,
                'client_id' => $access_key,
                'orientation' => 'landscape',
            ),
            'https://api.unsplash.com/photos/random'
        );

        $response = wp_safe_remote_get($endpoint, array('timeout' => 15));

        if (is_wp_error($response)) {
            $this->logger->log($response->get_error_message(), 'error');
            return new WP_Error('unsplash_request_failed', __('Failed to contact Unsplash API.', 'ai-post-scheduler'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->logger->log('Unsplash API returned HTTP ' . $status_code, 'error');
            return new WP_Error('unsplash_http_error', sprintf(__('Unsplash API returned HTTP %d.', 'ai-post-scheduler'), $status_code));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !is_array($body)) {
            return new WP_Error('unsplash_invalid_response', __('Invalid response from Unsplash API.', 'ai-post-scheduler'));
        }

        $image_url = '';

        if (isset($body['urls']['regular'])) {
            $image_url = $body['urls']['regular'];
        } elseif (isset($body['urls']['full'])) {
            $image_url = $body['urls']['full'];
        }

        if (empty($image_url)) {
            return new WP_Error('unsplash_image_missing', __('Unsplash did not return a usable image URL.', 'ai-post-scheduler'));
        }

        return esc_url_raw($image_url);
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

        // SECURITY: Verify actual file content is an image using finfo if available
        // This prevents saving non-image files (e.g. scripts) even if Content-Type header is spoofed
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $real_mime = $finfo->buffer($image_data);

            if (strpos($real_mime, 'image/') !== 0) {
                $error = new WP_Error(
                    'invalid_image_content',
                    sprintf(__('Security check failed: Content appears to be %s, expected image.', 'ai-post-scheduler'), $real_mime)
                );
                $this->logger->log($error->get_error_message(), 'error');
                return $error;
            }
        }
        
        $post_slug = sanitize_title($post_title);

        // Determine correct extension based on MIME type
        $mime_type = isset($real_mime) ? $real_mime : $content_type;
        $extension = 'jpg'; // Default

        $mime_map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        );

        if (isset($mime_map[$mime_type])) {
            $extension = $mime_map[$mime_type];
        }

        $filename = $post_slug . '.' . $extension;
        
        // Use wp_upload_bits to handle file creation and uniqueness
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if (!empty($upload['error'])) {
            $error = new WP_Error(
                'image_save_failed',
                sprintf(__('Failed to write image file: %s', 'ai-post-scheduler'), $upload['error'])
            );
            $this->logger->log($error->get_error_message(), 'error');
            return $error;
        }
        
        $file_path = $upload['file'];
        // Update filename to the actual saved filename (which might have -1, -2 suffix)
        $filename = basename($file_path);

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
