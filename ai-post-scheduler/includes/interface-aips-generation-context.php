<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Generation_Context
 *
 * Defines the contract for all generation context types.
 * A context represents the source and configuration for generating a post,
 * which could be a Template, a Topic, a Research Result, or any future source.
 *
 * This interface decouples the Generator from specific context implementations,
 * allowing it to work with any context type that provides the necessary data.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */
interface AIPS_Generation_Context {

	/**
	 * Get the context type identifier.
	 *
	 * @return string Context type (e.g., 'template', 'topic', 'research_result').
	 */
	public function get_type();

	/**
	 * Get the context identifier (e.g., template ID, topic ID).
	 *
	 * @return int|string|null Context identifier or null if not applicable.
	 */
	public function get_id();

	/**
	 * Get the context name/label for display purposes.
	 *
	 * @return string Context name.
	 */
	public function get_name();

	/**
	 * Get the content prompt for AI content generation.
	 *
	 * @return string Content generation prompt.
	 */
	public function get_content_prompt();

	/**
	 * Get the title prompt for AI title generation.
	 *
	 * @return string Title generation prompt.
	 */
	public function get_title_prompt();

	/**
	 * Get the image prompt for featured image generation.
	 *
	 * @return string|null Image prompt or null if not applicable.
	 */
	public function get_image_prompt();

	/**
	 * Check if featured image generation is enabled.
	 *
	 * @return bool True if featured image should be generated.
	 */
	public function should_generate_featured_image();

	/**
	 * Get the featured image source type.
	 *
	 * @return string Image source ('ai_prompt', 'unsplash', 'media_library').
	 */
	public function get_featured_image_source();

	/**
	 * Get Unsplash keywords for featured image (if source is 'unsplash').
	 *
	 * @return string Unsplash keywords.
	 */
	public function get_unsplash_keywords();

	/**
	 * Get media library image IDs (if source is 'media_library').
	 *
	 * @return string Comma-separated media IDs.
	 */
	public function get_media_library_ids();

	/**
	 * Get the post status for the generated post.
	 *
	 * @return string Post status ('draft', 'publish', etc.).
	 */
	public function get_post_status();

	/**
	 * Get the post category ID(s).
	 *
	 * @return int|string Category ID or comma-separated IDs.
	 */
	public function get_post_category();

	/**
	 * Get the post tags.
	 *
	 * @return string Comma-separated tags.
	 */
	public function get_post_tags();

	/**
	 * Get the post author ID.
	 *
	 * @return int Post author ID.
	 */
	public function get_post_author();

	/**
	 * Get the article structure ID (if applicable).
	 *
	 * @return int|null Article structure ID or null if not applicable.
	 */
	public function get_article_structure_id();

	/**
	 * Get the voice ID (if applicable).
	 *
	 * @return int|null Voice ID or null if not applicable.
	 */
	public function get_voice_id();

	/**
	 * Get the voice object (if applicable).
	 *
	 * @return object|null Voice object or null if not applicable.
	 */
	public function get_voice();

	/**
	 * Get the topic string for this context (if applicable).
	 *
	 * @return string|null Topic string or null if not applicable.
	 */
	public function get_topic();

	/**
	 * Get all context data as an array for serialization/storage.
	 *
	 * @return array Context data array.
	 */
	public function to_array();
}
