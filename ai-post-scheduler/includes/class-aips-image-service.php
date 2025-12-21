<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Image_Service {

    private $ai_engine;
    private $logger;

    public function __construct($ai_engine = null, $logger = null) {
        $this->ai_engine = $ai_engine;
        $this->logger = $logger ? $logger : new AIPS_Logger();
    }

    private function get_ai_engine() {
        if ($this->ai_engine === null) {
            if (class_exists('Meow_MWAI_Core')) {
                global $mwai_core;
                $this->ai_engine = $mwai_core;
            }
        }
        return $this->ai_engine;
    }

    /**
     * Generate an image using AI and attach it to the post.
     *
     * @param string $image_prompt The prompt for image generation.
     * @param string $post_title The title of the post (used for filename).
     * @return array Result containing success status, attachment_id, image_url, and error details.
     */
    public function generate_and_attach($image_prompt, $post_title) {
        $result = array(
            'success' => false,
            'attachment_id' => null,
            'image_url' => null,
            'error_code' => null,
            'error_message' => null,
        );

        $ai = $this->get_ai_engine();

        if (!$ai) {
            $result['error_code'] = 'featured_image';
            $result['error_message'] = 'AI Engine not available for image generation';
            $this->logger->log($result['error_message'], 'error');
            return $result;
        }

        try {
            $query = new Meow_MWAI_Query_Image($image_prompt);
            $response = $ai->run_query($query);

            if (!$response || empty($response->result)) {
                $result['error_code'] = 'featured_image';
                $result['error_message'] = 'Empty response from AI Engine for image generation';
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            $image_url = $response->result;

            if (is_array($image_url) && !empty($image_url[0])) {
                $image_url = $image_url[0];
            }

            $result['image_url'] = $image_url;

            if (empty($image_url)) {
                $result['error_code'] = 'featured_image';
                $result['error_message'] = 'No image URL in AI response';
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            // SECURITY FIX: Use wp_safe_remote_get to prevent SSRF
            $response_object = wp_safe_remote_get($image_url);

            if (is_wp_error($response_object)) {
                $result['error_code'] = 'image_download';
                $result['error_message'] = 'Failed to fetch image: ' . $response_object->get_error_message();
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            $response_code = wp_remote_retrieve_response_code($response_object);
            if ($response_code !== 200) {
                $result['error_code'] = 'image_download';
                $result['error_message'] = 'Failed to fetch image. HTTP Code: ' . $response_code;
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            $content_type = wp_remote_retrieve_header($response_object, 'content-type');
            if (strpos($content_type, 'image/') !== 0) {
                $result['error_code'] = 'image_content_type';
                $result['error_message'] = 'Invalid content type: ' . $content_type;
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            $image_data = wp_remote_retrieve_body($response_object);
            $post_slug = sanitize_title($post_title);
            $filename = $post_slug . '.jpg';

            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;

            if (!file_put_contents($file_path, $image_data)) {
                $result['error_code'] = 'image_save';
                $result['error_message'] = 'Failed to write image file: ' . $file_path;
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            $file_type = wp_check_filetype($filename);

            $attachment = array(
                'post_mime_type' => $file_type['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attachment_id = wp_insert_attachment($attachment, $file_path);

            if (is_wp_error($attachment_id)) {
                $result['error_code'] = 'image_attachment';
                $result['error_message'] = 'Failed to insert attachment: ' . $attachment_id->get_error_message();
                $this->logger->log($result['error_message'], 'error');
                return $result;
            }

            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);

            $this->logger->log('Featured image generated and uploaded', 'info', array(
                'attachment_id' => $attachment_id,
                'filename' => $filename
            ));

            $result['success'] = true;
            $result['attachment_id'] = $attachment_id;

            return $result;

        } catch (Exception $e) {
            $result['error_code'] = 'featured_image';
            $result['error_message'] = 'Image generation error: ' . $e->getMessage();
            $this->logger->log($result['error_message'], 'error');
            return $result;
        }
    }
}
