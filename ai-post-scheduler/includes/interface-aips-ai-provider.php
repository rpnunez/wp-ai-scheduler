<?php
/**
 * AI Provider Interface
 *
 * Defines the contract that all AI providers must implement.
 * This allows the plugin to support multiple AI backends (Meow Apps AI Engine, custom APIs, etc.).
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_AI_Provider
 *
 * Contract for all AI providers in the system.
 * Providers must implement text generation, structured JSON generation, and image generation.
 */
interface AIPS_AI_Provider {

	/**
	 * Generate text content using AI.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options (model, max_tokens, temperature, etc.).
	 * @return string|WP_Error The generated text content or WP_Error on failure.
	 */
	public function generate_text($prompt, $options = array());

	/**
	 * Generate structured JSON data using AI.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options (model, max_tokens, temperature, etc.).
	 * @return array|WP_Error The parsed JSON data as an array, or WP_Error on failure.
	 */
	public function generate_json($prompt, $options = array());

	/**
	 * Generate an image using AI.
	 *
	 * @param string $prompt  The image generation prompt.
	 * @param array  $options Optional. AI generation options (model, size, quality, etc.).
	 * @return string|WP_Error The image URL or WP_Error on failure.
	 */
	public function generate_image($prompt, $options = array());

	/**
	 * Generate an embedding vector for text.
	 *
	 * @param string $text    The text to generate an embedding for.
	 * @param array  $options Optional. Embedding generation options.
	 * @return array|WP_Error The embedding vector or WP_Error on failure.
	 */
	public function generate_embedding($text, $options = array());

	/**
	 * Check if the AI provider is available and properly configured.
	 *
	 * @return bool True if the provider is available, false otherwise.
	 */
	public function is_available();

	/**
	 * Get the provider name.
	 *
	 * @return string The human-readable provider name.
	 */
	public function get_name();

	/**
	 * Get the provider identifier.
	 *
	 * @return string The provider identifier (e.g., 'ai-engine', 'custom').
	 */
	public function get_identifier();
}
