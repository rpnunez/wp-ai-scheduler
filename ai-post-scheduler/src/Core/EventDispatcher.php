<?php
/**
 * Event Dispatcher
 *
 * WordPress-based event dispatcher for better decoupling and extensibility.
 * Uses WordPress hooks and actions for event-driven architecture.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Event_Dispatcher
 *
 * Provides a clean interface for dispatching events throughout the plugin.
 * Makes the system more extensible by allowing third-party code to hook into events.
 */
class AIPS_Event_Dispatcher {
    
    /**
     * @var string Event namespace prefix
     */
    private $namespace = 'aips';
    
    /**
     * @var AIPS_Logger Logger instance
     */
    private $logger;
    
    /**
     * @var array Event history for debugging
     */
    private $event_history = array();
    
    /**
     * Initialize the event dispatcher.
     */
    public function __construct() {
        $this->logger = new AIPS_Logger();
    }
    
    /**
     * Dispatch an event.
     *
     * Triggers a WordPress action with the event name and data.
     * Logs the event for debugging purposes.
     *
     * @param string $event_name Event name (without namespace prefix).
     * @param array  $data       Event data to pass to listeners.
     * @param string $context    Optional. Event context for logging. Default empty.
     */
    public function dispatch($event_name, $data = array(), $context = '') {
        $full_event_name = $this->get_full_event_name($event_name);
        
        // Log the event
        $this->log_event($event_name, $data, $context);
        
        // Dispatch the action
        do_action($full_event_name, $data, $context);
    }
    
    /**
     * Add a listener for an event.
     *
     * Wrapper around WordPress add_action for consistency.
     *
     * @param string   $event_name Event name (without namespace prefix).
     * @param callable $callback   Callback function to execute.
     * @param int      $priority   Optional. Priority for execution order. Default 10.
     * @param int      $args       Optional. Number of arguments to accept. Default 2.
     * @return bool True on success.
     */
    public function listen($event_name, $callback, $priority = 10, $args = 2) {
        $full_event_name = $this->get_full_event_name($event_name);
        return add_action($full_event_name, $callback, $priority, $args);
    }
    
    /**
     * Remove a listener from an event.
     *
     * Wrapper around WordPress remove_action for consistency.
     *
     * @param string   $event_name Event name (without namespace prefix).
     * @param callable $callback   Callback function to remove.
     * @param int      $priority   Optional. Priority of the callback. Default 10.
     * @return bool True on success, false on failure.
     */
    public function remove_listener($event_name, $callback, $priority = 10) {
        $full_event_name = $this->get_full_event_name($event_name);
        return remove_action($full_event_name, $callback, $priority);
    }
    
    /**
     * Check if an event has listeners.
     *
     * @param string $event_name Event name (without namespace prefix).
     * @return bool True if event has listeners, false otherwise.
     */
    public function has_listeners($event_name) {
        $full_event_name = $this->get_full_event_name($event_name);
        return has_action($full_event_name) !== false;
    }
    
    /**
     * Get the full event name with namespace.
     *
     * @param string $event_name Event name without namespace.
     * @return string Full event name with namespace prefix.
     */
    private function get_full_event_name($event_name) {
        return $this->namespace . '_' . $event_name;
    }
    
    /**
     * Log an event for debugging.
     *
     * @param string $event_name Event name.
     * @param array  $data       Event data.
     * @param string $context    Event context.
     */
    private function log_event($event_name, $data, $context) {
        $event_record = array(
            'event' => $event_name,
            'timestamp' => current_time('mysql'),
            'context' => $context,
            'data_keys' => array_keys($data),
        );
        
        $this->event_history[] = $event_record;
        
        // Log to system logger if debugging is enabled
        if (defined('AIPS_DEBUG') && AIPS_DEBUG) {
            $this->logger->log("Event dispatched: {$event_name}", 'debug', $event_record);
        }
    }
    
    /**
     * Get the event history.
     *
     * Useful for debugging and monitoring.
     *
     * @return array Array of event records.
     */
    public function get_event_history() {
        return $this->event_history;
    }
    
    /**
     * Clear the event history.
     *
     * Resets the in-memory event history.
     */
    public function clear_event_history() {
        $this->event_history = array();
    }
    
    /**
     * Get event statistics.
     *
     * @return array {
     *     @type int   $total         Total number of events dispatched.
     *     @type array $by_event      Count by event name.
     *     @type array $by_context    Count by context.
     * }
     */
    public function get_event_statistics() {
        $stats = array(
            'total' => count($this->event_history),
            'by_event' => array(),
            'by_context' => array(),
        );
        
        foreach ($this->event_history as $record) {
            $event = $record['event'];
            $context = $record['context'];
            
            if (!isset($stats['by_event'][$event])) {
                $stats['by_event'][$event] = 0;
            }
            $stats['by_event'][$event]++;
            
            if (!empty($context)) {
                if (!isset($stats['by_context'][$context])) {
                    $stats['by_context'][$context] = 0;
                }
                $stats['by_context'][$context]++;
            }
        }
        
        return $stats;
    }
    
    // ========================================
    // Pre-defined Event Dispatch Methods
    // ========================================
    
    /**
     * Dispatch post generation started event.
     *
     * @param int    $template_id Template ID.
     * @param string $topic       Optional topic.
     */
    public function post_generation_started($template_id, $topic = '') {
        $this->dispatch('post_generation_started', array(
            'template_id' => $template_id,
            'topic' => $topic,
            'timestamp' => current_time('mysql'),
        ), 'post_generation');
    }
    
    /**
     * Dispatch post generation completed event.
     *
     * @param int   $template_id Template ID.
     * @param int   $post_id     Created post ID.
     * @param array $metadata    Optional metadata about the generation.
     */
    public function post_generation_completed($template_id, $post_id, $metadata = array()) {
        $this->dispatch('post_generation_completed', array(
            'template_id' => $template_id,
            'post_id' => $post_id,
            'metadata' => $metadata,
            'timestamp' => current_time('mysql'),
        ), 'post_generation');
    }
    
    /**
     * Dispatch post generation failed event.
     *
     * @param int      $template_id Template ID.
     * @param WP_Error $error       Error object.
     * @param array    $metadata    Optional metadata about the failure.
     */
    public function post_generation_failed($template_id, $error, $metadata = array()) {
        $this->dispatch('post_generation_failed', array(
            'template_id' => $template_id,
            'error_code' => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
            'metadata' => $metadata,
            'timestamp' => current_time('mysql'),
        ), 'post_generation');
    }
    
    /**
     * Dispatch schedule execution started event.
     *
     * @param int $schedule_id Schedule ID.
     */
    public function schedule_execution_started($schedule_id) {
        $this->dispatch('schedule_execution_started', array(
            'schedule_id' => $schedule_id,
            'timestamp' => current_time('mysql'),
        ), 'schedule_execution');
    }
    
    /**
     * Dispatch schedule execution completed event.
     *
     * @param int   $schedule_id Schedule ID.
     * @param int   $post_id     Created post ID.
     * @param array $metadata    Optional metadata.
     */
    public function schedule_execution_completed($schedule_id, $post_id, $metadata = array()) {
        $this->dispatch('schedule_execution_completed', array(
            'schedule_id' => $schedule_id,
            'post_id' => $post_id,
            'metadata' => $metadata,
            'timestamp' => current_time('mysql'),
        ), 'schedule_execution');
    }
    
    /**
     * Dispatch schedule execution failed event.
     *
     * @param int      $schedule_id Schedule ID.
     * @param WP_Error $error       Error object.
     * @param array    $metadata    Optional metadata.
     */
    public function schedule_execution_failed($schedule_id, $error, $metadata = array()) {
        $this->dispatch('schedule_execution_failed', array(
            'schedule_id' => $schedule_id,
            'error_code' => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
            'metadata' => $metadata,
            'timestamp' => current_time('mysql'),
        ), 'schedule_execution');
    }
    
    /**
     * Dispatch AI request started event.
     *
     * @param string $type    Request type (text or image).
     * @param string $prompt  The prompt sent to AI.
     * @param array  $options Request options.
     */
    public function ai_request_started($type, $prompt, $options = array()) {
        $this->dispatch('ai_request_started', array(
            'type' => $type,
            'prompt_length' => strlen($prompt),
            'options' => $options,
            'timestamp' => current_time('mysql'),
        ), 'ai_request');
    }
    
    /**
     * Dispatch AI request completed event.
     *
     * @param string $type     Request type (text or image).
     * @param string $response The AI response.
     * @param float  $duration Duration in seconds.
     */
    public function ai_request_completed($type, $response, $duration = 0) {
        $this->dispatch('ai_request_completed', array(
            'type' => $type,
            'response_length' => strlen($response),
            'duration' => $duration,
            'timestamp' => current_time('mysql'),
        ), 'ai_request');
    }
    
    /**
     * Dispatch AI request failed event.
     *
     * @param string   $type  Request type (text or image).
     * @param WP_Error $error Error object.
     */
    public function ai_request_failed($type, $error) {
        $this->dispatch('ai_request_failed', array(
            'type' => $type,
            'error_code' => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
            'timestamp' => current_time('mysql'),
        ), 'ai_request');
    }
    
    /**
     * Dispatch error event.
     *
     * Generic error event for any part of the system.
     *
     * @param string   $context  Error context (where it occurred).
     * @param WP_Error $error    Error object.
     * @param array    $metadata Optional metadata.
     */
    public function error_occurred($context, $error, $metadata = array()) {
        $this->dispatch('error_occurred', array(
            'context' => $context,
            'error_code' => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
            'metadata' => $metadata,
            'timestamp' => current_time('mysql'),
        ), 'error');
    }
}
