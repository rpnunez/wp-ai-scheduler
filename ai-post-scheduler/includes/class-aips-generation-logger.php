<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_Generation_Logger
 *
 * @deprecated 2.1.0 Use AIPS_History_Container directly via AIPS_History_Service
 * 
 * This class has been deprecated in favor of using AIPS_History_Container directly.
 * The functionality is now built into History Container with the simplified record() method.
 * 
 * Maintained for backward compatibility only.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */
class AIPS_Generation_Logger {

    private $logger;
    private $history_repository;
    private $session;
    private $history_id;

    public function __construct($logger, $history_repository, $session) {
        $this->logger = $logger;
        $this->history_repository = $history_repository;
        $this->session = $session;
    }

    /**
     * Set the current history ID context.
     *
     * @param int $history_id
     */
    public function set_history_id($history_id) {
        $this->history_id = $history_id;
    }

    /**
     * Log an AI call to the current generation session and history log.
     *
     * @param string      $type     Type of AI call (e.g., 'title', 'content', 'excerpt', 'featured_image').
     * @param string      $prompt   The prompt sent to AI.
     * @param string|null $response The AI response, if successful.
     * @param array       $options  Options used for the call.
     * @param string|null $error    Error message, if call failed.
     * @return void
     */
    public function log_ai_call($type, $prompt, $response, $options = array(), $error = null) {
        $this->session->log_ai_call();
        if ($error) {
            $this->session->add_error();
        }

        if ($this->history_id && $this->history_repository) {
            $details = array(
                'prompt' => $prompt,
                'options' => $options,
                'response' => $response !== null ? base64_encode($response) : null,
                'error' => $error,
            );
            $this->history_repository->add_log_entry($this->history_id, $type, $details);
        }
    }

    /**
     * Log a message with optional AI data to both the logger and the session.
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, error, warning).
     * @param array  $ai_data Optional AI call data to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function log($message, $level, $ai_data = array(), $context = array()) {
        $this->logger->log($message, $level, $context);

        if (!empty($ai_data) && isset($ai_data['type']) && isset($ai_data['prompt'])) {
            $type = $ai_data['type'];
            $prompt = $ai_data['prompt'];
            $response = isset($ai_data['response']) ? $ai_data['response'] : null;
            $options = isset($ai_data['options']) ? $ai_data['options'] : array();
            $error = isset($ai_data['error']) ? $ai_data['error'] : null;

            $this->log_ai_call($type, $prompt, $response, $options, $error);
        }
    }

    /**
     * Log an error to the current generation session and history log.
     *
     * @param string $type    The type of error.
     * @param string $message The error message.
     * @return void
     */
    public function log_error($type, $message) {
        $this->session->add_error();

        if ($this->history_id) {
            $details = array(
                'message' => $message,
            );
            $this->history_repository->add_log_entry($this->history_id, $type . '_error', $details);
        }
    }

    /**
     * Log a warning message via the main logger.
     *
     * @param string $message Warning message.
     * @param array $context Context data.
     */
    public function warning($message, $context = array()) {
        // Fix potential bug where logger doesn't have warning method
        if (method_exists($this->logger, 'warning')) {
            $this->logger->warning($message, $context);
        } else {
            $this->logger->log($message, 'warning', $context);
        }
    }
}
