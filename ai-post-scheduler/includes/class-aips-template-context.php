<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Template_Context
 *
 * Wraps a Template object to provide it as a Generation Context.
 * This allows templates to work with the refactored Generator architecture.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */
class AIPS_Template_Context implements AIPS_Generation_Context {

	/**
	 * @var object Template object.
	 */
	private $template;

	/**
	 * @var object|null Optional voice object.
	 */
	private $voice;

	/**
	 * @var string|null Optional topic string.
	 */
	private $topic;

	/**
	 * @var string|null Optional creation method (manual or scheduled).
	 */
	private $creation_method;

	/**
	 * Constructor.
	 *
	 * @param object      $template Template object.
	 * @param object|null $voice    Optional voice object.
	 * @param string|null $topic    Optional topic string.
	 * @param string|null $creation_method Optional creation method.
	 */
	public function __construct($template, $voice = null, $topic = null, $creation_method = null) {
		$this->template = $template;
		$this->voice = $voice;
		$this->topic = $topic;
		$this->creation_method = $creation_method;
	}

	/**
	 * Get the context type identifier.
	 *
	 * @return string Context type 'template'.
	 */
	public function get_type() {
		return 'template';
	}

	/**
	 * Get the template ID.
	 *
	 * @return int Template ID.
	 */
	public function get_id() {
		return $this->template->id;
	}

	/**
	 * Get the template name.
	 *
	 * @return string Template name.
	 */
	public function get_name() {
		return $this->template->name;
	}

	/**
	 * Get the content prompt.
	 *
	 * @return string Content prompt from template.
	 */
	public function get_content_prompt() {
		return $this->template->prompt_template;
	}

	/**
	 * Get the title prompt.
	 *
	 * @return string Title prompt from voice or template.
	 */
	public function get_title_prompt() {
		if ($this->voice && !empty($this->voice->title_prompt)) {
			return $this->voice->title_prompt;
		}
		return isset($this->template->title_prompt) ? $this->template->title_prompt : '';
	}

	/**
	 * Get the image prompt.
	 *
	 * @return string|null Image prompt or null.
	 */
	public function get_image_prompt() {
		return isset($this->template->image_prompt) ? $this->template->image_prompt : null;
	}

	/**
	 * Check if featured image generation is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public function should_generate_featured_image() {
		return !empty($this->template->generate_featured_image);
	}

	/**
	 * Get the featured image source.
	 *
	 * @return string Image source type.
	 */
	public function get_featured_image_source() {
		return isset($this->template->featured_image_source) ? $this->template->featured_image_source : 'ai_prompt';
	}

	/**
	 * Get Unsplash keywords.
	 *
	 * @return string Unsplash keywords.
	 */
	public function get_unsplash_keywords() {
		return isset($this->template->featured_image_unsplash_keywords) ? $this->template->featured_image_unsplash_keywords : '';
	}

	/**
	 * Get media library image IDs.
	 *
	 * @return string Media library IDs.
	 */
	public function get_media_library_ids() {
		return isset($this->template->featured_image_media_ids) ? $this->template->featured_image_media_ids : '';
	}

	/**
	 * Get post status.
	 *
	 * @return string Post status.
	 */
	public function get_post_status() {
		return $this->template->post_status;
	}

	/**
	 * Get post category.
	 *
	 * @return int|string Post category ID(s).
	 */
	public function get_post_category() {
		return $this->template->post_category;
	}

	/**
	 * Get post tags.
	 *
	 * @return string Post tags.
	 */
	public function get_post_tags() {
		return isset($this->template->post_tags) ? $this->template->post_tags : '';
	}

	/**
	 * Get post author ID.
	 *
	 * @return int Post author ID.
	 */
	public function get_post_author() {
		return isset($this->template->post_author) ? $this->template->post_author : get_current_user_id();
	}

	/**
	 * Get article structure ID.
	 *
	 * @return int|null Article structure ID or null.
	 */
	public function get_article_structure_id() {
		return isset($this->template->article_structure_id) ? $this->template->article_structure_id : null;
	}

	/**
	 * Get voice ID.
	 *
	 * @return int|null Voice ID or null.
	 */
	public function get_voice_id() {
		return $this->voice ? $this->voice->id : null;
	}

	/**
	 * Get topic string.
	 *
	 * @return string|null Topic or null.
	 */
	public function get_topic() {
		return $this->topic;
	}

	/**
	 * Get the underlying template object for backward compatibility.
	 *
	 * @return object Template object.
	 */
	public function get_template() {
		return $this->template;
	}

	/**
	 * Get the voice object if available.
	 *
	 * @return object|null Voice object or null.
	 */
	public function get_voice() {
		return $this->voice;
	}

	/**
	 * Get the creation method if set.
	 *
	 * @return string|null Creation method ('manual' or 'scheduled') or null.
	 */
	public function get_creation_method() {
		return $this->creation_method;
	}

	/**
	 * Get post quantity.
	 *
	 * @return int Post quantity.
	 */
	public function get_post_quantity() {
		return isset($this->template->post_quantity) ? $this->template->post_quantity : 1;
	}

	/**
	 * Magic getter for backward compatibility.
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 */
	public function __get($name) {
		if (method_exists($this, 'get_' . $name)) {
			return $this->{'get_' . $name}();
		}
		if (isset($this->template->$name)) {
			return $this->template->$name;
		}
		return null;
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
			'content_prompt' => $this->get_content_prompt(),
			'title_prompt' => $this->get_title_prompt(),
			'post_status' => $this->get_post_status(),
			'post_category' => $this->get_post_category(),
			'post_tags' => $this->get_post_tags(),
			'post_author' => $this->get_post_author(),
		);

		if ($this->get_topic()) {
			$data['topic'] = $this->get_topic();
		}

		if ($this->get_voice_id()) {
			$data['voice_id'] = $this->get_voice_id();
		}

		if ($this->get_article_structure_id()) {
			$data['article_structure_id'] = $this->get_article_structure_id();
		}

		return $data;
	}
}
