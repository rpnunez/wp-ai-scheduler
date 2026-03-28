<?php
/**
 * History Type Constants
 *
 * Defines constants for different types of history entries in the unified history system.
 * These constants are used to categorize history entries for filtering and display.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Type
 *
 * Provides type constants for the unified history system.
 * Used to categorize all history entries including logs, errors, AI calls, etc.
 */
class AIPS_History_Type {
	
	/**
	 * General log entry
	 */
	const LOG = 1;
	
	/**
	 * Error entry
	 */
	const ERROR = 2;
	
	/**
	 * Warning entry
	 */
	const WARNING = 3;
	
	/**
	 * Info entry
	 */
	const INFO = 4;
	
	/**
	 * AI request entry (prompt sent to AI)
	 */
	const AI_REQUEST = 5;
	
	/**
	 * AI response entry (response received from AI)
	 */
	const AI_RESPONSE = 6;
	
	/**
	 * Debug entry
	 */
	const DEBUG = 7;
	
	/**
	 * Activity entry (high-level events like post published, schedule completed)
	 */
	const ACTIVITY = 8;
	
	/**
	 * Generation session metadata
	 */
	const SESSION_METADATA = 9;
	
	/**
	 * Get human-readable label for a history type
	 *
	 * @param int $type History type constant
	 * @return string Human-readable label
	 */
	public static function get_label($type) {
		$labels = array(
			self::LOG => __('Log', 'ai-post-scheduler'),
			self::ERROR => __('Error', 'ai-post-scheduler'),
			self::WARNING => __('Warning', 'ai-post-scheduler'),
			self::INFO => __('Info', 'ai-post-scheduler'),
			self::AI_REQUEST => __('AI Request', 'ai-post-scheduler'),
			self::AI_RESPONSE => __('AI Response', 'ai-post-scheduler'),
			self::DEBUG => __('Debug', 'ai-post-scheduler'),
			self::ACTIVITY => __('Activity', 'ai-post-scheduler'),
			self::SESSION_METADATA => __('Session Metadata', 'ai-post-scheduler'),
		);
		
		return isset($labels[$type]) ? $labels[$type] : __('Unknown', 'ai-post-scheduler');
	}
	
	/**
	 * Get all history types
	 *
	 * @return array Array of type => label pairs
	 */
	public static function get_all_types() {
		return array(
			self::LOG => self::get_label(self::LOG),
			self::ERROR => self::get_label(self::ERROR),
			self::WARNING => self::get_label(self::WARNING),
			self::INFO => self::get_label(self::INFO),
			self::AI_REQUEST => self::get_label(self::AI_REQUEST),
			self::AI_RESPONSE => self::get_label(self::AI_RESPONSE),
			self::DEBUG => self::get_label(self::DEBUG),
			self::ACTIVITY => self::get_label(self::ACTIVITY),
			self::SESSION_METADATA => self::get_label(self::SESSION_METADATA),
		);
	}
	
	/**
	 * Check if a type should be shown on the Activity page
	 *
	 * @param int $type History type constant
	 * @return bool True if should be shown on Activity page
	 */
	public static function is_activity_type($type) {
		return in_array($type, array(self::ACTIVITY, self::ERROR), true);
	}
}
