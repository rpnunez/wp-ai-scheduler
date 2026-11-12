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
	 * The reserved `_aips_on_submission` option is a one-shot callable. Services
	 * invoke it immediately before their first provider transport attempt, after
	 * local guards, including for a subsequently failed request. Strip it from
	 * provider parameters and logs; retries and JSON fallbacks share one callback.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_text($prompt, $options = array());

	/**
	 * Generate structured JSON-like output from a prompt.
	 * Supports the same reserved submission callback contract as generate_text().
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
	 * Generate an embedding vector for a text string.
	 *
	 * @param string $text Text to embed.
	 * @param array  $options Request options.
	 * @return array|WP_Error
	 */
	public function generate_embedding($text, $options = array());

	/**
	 * Whether the active provider can generate embeddings.
	 *
	 * @return bool
	 */
	public function supports_embeddings();

	/**
	 * Whether the active provider can replay conversation history.
	 *
	 * @return bool
	 */
	public function supports_conversation();

	/**
	 * Return captured AI call logs.
	 *
	 * @return array
	 */
	public function get_call_log();
}
