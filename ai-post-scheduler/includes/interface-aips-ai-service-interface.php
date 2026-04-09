<?php
/**
 * AI Service Interface
 *
 * Defines the contract for AI operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.1
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_AI_Service_Interface {

	/**
	 * Check AI availability.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Generate text from a prompt.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_text($prompt, $options = array());

	/**
	 * Generate structured JSON-like output from a prompt.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return array|WP_Error
	 */
	public function generate_json($prompt, $options = array());

	/**
	 * Generate an image URL from a prompt.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_image($prompt, $options = array());

	/**
	 * Return captured AI call logs.
	 *
	 * @return array
	 */
	public function get_call_log();
}
