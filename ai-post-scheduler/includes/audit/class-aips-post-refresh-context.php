<?php
/**
 * Post Refresh Generation Context
 *
 * Adapts an existing published WP_Post into an AIPS_Generation_Context so the
 * Autonomous Post Refresher can drive the standard generation pipeline without
 * requiring an Author or Topic row.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Refresh_Context implements AIPS_Generation_Context {

	/**
	 * @var WP_Post Source published post being refreshed.
	 */
	private $post;

	/**
	 * @var int Maximum characters of source content injected into the prompt.
	 */
	private $content_char_limit;

	/**
	 * Constructor.
	 *
	 * @param WP_Post $post               Published post to refresh.
	 * @param int     $content_char_limit Max characters of existing content used as prompt grounding.
	 */
	public function __construct(WP_Post $post, int $content_char_limit = 12000) {
		$this->post = $post;
		$this->content_char_limit = max(500, $content_char_limit);
	}

	/**
	 * Get the source post being refreshed.
	 *
	 * @return WP_Post
	 */
	public function get_source_post() {
		return $this->post;
	}

	public function get_type() {
		return 'post_refresh';
	}

	public function get_id() {
		return (int) $this->post->ID;
	}

	public function get_name() {
		/* translators: %s: post title. */
		return sprintf(__('Refresh: %s', 'ai-post-scheduler'), $this->post->post_title);
	}

	/**
	 * Build the refresh prompt from the existing article body.
	 *
	 * @return string
	 */
	public function get_content_prompt() {
		$existing = wp_strip_all_tags($this->post->post_content);

		if (function_exists('mb_substr')) {
			$existing = mb_substr($existing, 0, $this->content_char_limit);
		} else {
			$existing = substr($existing, 0, $this->content_char_limit);
		}

		$prompt  = "Review and refresh the following published article.\n";
		$prompt .= "Update outdated references and statistics, improve clarity and readability, ";
		$prompt .= "and preserve the core structure, intent, and factual claims of the original.\n";
		$prompt .= "Return the full refreshed article body only.\n\n";
		$prompt .= "Title: {$this->post->post_title}\n\n";
		$prompt .= "Current content:\n" . $existing;

		/**
		 * Filter the prompt used by the Autonomous Post Refresher.
		 *
		 * @param string  $prompt Refresh prompt.
		 * @param WP_Post $post   Source published post.
		 */
		return (string) apply_filters('aips_post_refresh_content_prompt', $prompt, $this->post);
	}

	public function get_title_prompt() {
		return $this->post->post_title;
	}

	public function get_image_prompt() {
		return null;
	}

	/**
	 * Refreshes never regenerate the featured image; the live post keeps its own.
	 *
	 * @return bool
	 */
	public function should_generate_featured_image() {
		return false;
	}

	public function get_featured_image_source() {
		return '';
	}

	public function get_unsplash_keywords() {
		return '';
	}

	public function get_media_library_ids() {
		return '';
	}

	/**
	 * Staging revisions are always drafts awaiting editorial approval.
	 *
	 * @return string
	 */
	public function get_post_status() {
		return 'draft';
	}

	public function get_post_type() {
		return $this->post->post_type ? sanitize_key($this->post->post_type) : 'post';
	}

	public function get_post_category() {
		return wp_get_post_categories($this->post->ID);
	}

	public function get_post_tags() {
		$tags = wp_get_post_tags($this->post->ID, array('fields' => 'names'));
		return is_wp_error($tags) ? '' : implode(', ', $tags);
	}

	public function get_post_author() {
		return (int) $this->post->post_author;
	}

	public function get_article_structure_id() {
		return null;
	}

	public function get_voice_id() {
		return null;
	}

	public function get_voice() {
		return null;
	}

	public function get_topic() {
		return $this->post->post_title;
	}

	public function get_creation_method() {
		return 'post_refresh';
	}

	public function get_include_sources() {
		return false;
	}

	public function get_source_group_ids() {
		return array();
	}

	public function get_affiliate_links_enabled() {
		return false;
	}

	public function to_array() {
		return array(
			'type'            => $this->get_type(),
			'id'              => $this->get_id(),
			'name'            => $this->get_name(),
			'topic'           => $this->get_topic(),
			'content_prompt'  => $this->get_content_prompt(),
			'title_prompt'    => $this->get_title_prompt(),
			'post_status'     => $this->get_post_status(),
			'post_type'       => $this->get_post_type(),
			'post_category'   => $this->get_post_category(),
			'post_tags'       => $this->get_post_tags(),
			'post_author'     => $this->get_post_author(),
			'creation_method' => $this->get_creation_method(),
			'source_post_id'  => (int) $this->post->ID,
		);
	}
}
