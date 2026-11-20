<?php
/**
 * Abstract Generation Context
 *
 * Base implementation of AIPS_Generation_Context providing safe no-op defaults
 * for WordPress post-specific getters so non-post strategies (Topics, Author
 * Personas, Campaigns, Research) stay concise and strictly typed.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Abstract_Generation_Context
 */
abstract class AIPS_Abstract_Generation_Context implements AIPS_Generation_Context {

	/**
	 * {@inheritdoc}
	 */
	public function get_content_prompt() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title_prompt() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug_prompt() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function should_generate_slug() {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_image_prompt() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function should_generate_featured_image() {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_featured_image_source() {
		return 'ai_prompt';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_unsplash_keywords() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_media_library_ids() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function has_content_images() {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_content_images() {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_post_status() {
		return 'draft';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_post_type() {
		return 'post';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_post_category() {
		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_post_tags() {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_post_author() {
		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_article_structure_id() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_voice_id() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_voice() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_topic() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_creation_method() {
		return 'manual';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_include_sources() {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_source_group_ids() {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_affiliate_links_enabled() {
		return false;
	}
}
