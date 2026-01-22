<?php
namespace AIPS\Generation\Context;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class TopicContext
 *
 * Wraps an Author and Topic pair to provide them as a Generation Context.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */
class TopicContext implements GenerationContext {

	/**
	 * @var object Author object.
	 */
	private $author;

	/**
	 * @var object Topic object.
	 */
	private $topic;

	/**
	 * @var string Expanded context from similar topics.
	 */
	private $expanded_context;

	/**
	 * Constructor.
	 *
	 * @param object $author            Author object.
	 * @param object $topic             Topic object.
	 * @param string $expanded_context  Optional expanded context from similar topics.
	 */
	public function __construct($author, $topic, $expanded_context = '') {
		$this->author = $author;
		$this->topic = $topic;
		$this->expanded_context = $expanded_context;
	}

	/**
	 * Get the context type identifier.
	 *
	 * @return string Context type 'topic'.
	 */
	public function get_type() {
		return 'topic';
	}

	/**
	 * Get the topic ID.
	 *
	 * @return int Topic ID.
	 */
	public function get_id() {
		return $this->topic->id;
	}

	/**
	 * Get the context name (author name + topic title).
	 *
	 * @return string Context name.
	 */
	public function get_name() {
		return $this->author->name . ': ' . $this->topic->topic_title;
	}

	/**
	 * Get the content prompt for AI generation.
	 *
	 * @return string Content generation prompt.
	 */
	public function get_content_prompt() {
		$prompt = "Write a comprehensive blog post about: {$this->topic->topic_title}\n\nField/Niche: {$this->author->field_niche}";

		if (!empty($this->expanded_context)) {
			$prompt .= "\n\n" . $this->expanded_context;
		}

		return $prompt;
	}

	/**
	 * Get the title prompt for AI title generation.
	 *
	 * @return string Title generation prompt.
	 */
	public function get_title_prompt() {
		return $this->topic->topic_title;
	}

	/**
	 * Get the image prompt for featured image generation.
	 *
	 * @return string|null Image prompt.
	 */
	public function get_image_prompt() {
		return $this->topic->topic_title;
	}

	/**
	 * Check if featured image generation is enabled.
	 *
	 * @return bool True if enabled in author settings.
	 */
	public function should_generate_featured_image() {
		return !empty($this->author->generate_featured_image);
	}

	/**
	 * Get the featured image source.
	 *
	 * @return string Image source type from author settings.
	 */
	public function get_featured_image_source() {
		return isset($this->author->featured_image_source) ? $this->author->featured_image_source : 'ai_prompt';
	}

	/**
	 * Get Unsplash keywords.
	 *
	 * @return string Empty string (not applicable for topic context).
	 */
	public function get_unsplash_keywords() {
		return '';
	}

	/**
	 * Get media library image IDs.
	 *
	 * @return string Empty string (not applicable for topic context).
	 */
	public function get_media_library_ids() {
		return '';
	}

	/**
	 * Get post status.
	 *
	 * @return string Post status from author settings.
	 */
	public function get_post_status() {
		return isset($this->author->post_status) ? $this->author->post_status : 'draft';
	}

	/**
	 * Get post category.
	 *
	 * @return int|string Post category from author settings.
	 */
	public function get_post_category() {
		return isset($this->author->post_category) ? $this->author->post_category : '';
	}

	/**
	 * Get post tags.
	 *
	 * @return string Post tags from author settings.
	 */
	public function get_post_tags() {
		return isset($this->author->post_tags) ? $this->author->post_tags : '';
	}

	/**
	 * Get post author ID.
	 *
	 * @return int Post author ID from author settings.
	 */
	public function get_post_author() {
		return isset($this->author->post_author) ? $this->author->post_author : get_current_user_id();
	}

	/**
	 * Get article structure ID.
	 *
	 * @return int|null Article structure ID from author settings or null.
	 */
	public function get_article_structure_id() {
		return isset($this->author->article_structure_id) ? $this->author->article_structure_id : null;
	}

	/**
	 * Get voice ID.
	 *
	 * @return int|null Always null for topic context.
	 */
	public function get_voice_id() {
		return null;
	}

	/**
	 * Get topic string.
	 *
	 * @return string|null Topic or null.
	 */
	public function get_topic() {
		return $this->topic->topic_title;
	}

	/**
	 * Get author ID (for topic context).
	 *
	 * @return int Author ID.
	 */
	public function get_author_id() {
		return $this->author->id;
	}

	/**
	 * Get topic ID (alias of get_id()).
	 *
	 * @return int Topic ID.
	 */
	public function get_topic_id() {
		return $this->topic->id;
	}

	/**
	 * Get the author object.
	 *
	 * @return object Author object.
	 */
	public function get_author() {
		return $this->author;
	}

	/**
	 * Get the topic object.
	 *
	 * @return object Topic object.
	 */
	public function get_topic_object() {
		return $this->topic;
	}

	/**
	 * Get the expanded context.
	 *
	 * @return string Expanded context string.
	 */
	public function get_expanded_context() {
		return $this->expanded_context;
	}

	/**
	 * Get all context data as an array.
	 *
	 * @return array Context data.
	 */
	public function to_array() {
		$data = array(
			'type' => $this->get_type(),
			'id' => $this->get_id(),
			'name' => $this->get_name(),
			'author_id' => $this->author->id,
			'author_name' => $this->author->name,
			'topic_title' => $this->topic->topic_title,
			'post_status' => $this->get_post_status(),
			'post_category' => $this->get_post_category(),
			'post_tags' => $this->get_post_tags(),
			'post_author' => $this->get_post_author(),
		);

		if (!empty($this->expanded_context)) {
			$data['expanded_context'] = $this->expanded_context;
		}

		if ($this->get_article_structure_id()) {
			$data['article_structure_id'] = $this->get_article_structure_id();
		}

		return $data;
	}
}
