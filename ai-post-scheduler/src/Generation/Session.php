<?php
namespace AIPS\Generation;

use AIPS\Generation\Context\GenerationContext;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Generation Session Tracker
 *
 * Encapsulates runtime tracking data for a single post generation session.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */
class Session {

	/**
	 * @var string|null Timestamp when generation started (MySQL format)
	 */
	private $started_at;

	/**
	 * @var string|null Timestamp when generation completed (MySQL format)
	 */
	private $completed_at;

	/**
	 * @var array|null Template configuration used for generation (deprecated)
	 */
	private $template;

	/**
	 * @var array|null Voice configuration used for generation (deprecated)
	 */
	private $voice;

	/**
	 * @var array|null Generation context data (type, id, name, etc.)
	 */
	private $context;

	/**
	 * @var int Counter for AI calls made during generation
	 */
	private $ai_call_count;

	/**
	 * @var int Counter for errors encountered during generation
	 */
	private $error_count;

	/**
	 * @var array|null Final result of the generation (success/failure + data)
	 */
	private $result;

	/**
	 * Initialize a new generation session.
	 */
	public function __construct() {
		$this->reset();
	}

	/**
	 * Reset the session to initial state.
	 *
	 * @return void
	 */
	private function reset() {
		$this->started_at = null;
		$this->completed_at = null;
		$this->template = null;
		$this->voice = null;
		$this->context = null;
		$this->ai_call_count = 0;
		$this->error_count = 0;
		$this->result = null;
	}

	/**
	 * Start a new generation session.
	 *
	 * Supports both legacy template-based calls and new context-based calls.
	 *
	 * @param object|GenerationContext $template_or_context Template object (legacy) or Generation Context.
	 * @param object|null $voice    Optional voice object (legacy, ignored if context is provided).
	 * @return void
	 */
	public function start($template_or_context, $voice = null) {
		$this->reset();
		$this->started_at = current_time('mysql');

		if ($template_or_context instanceof GenerationContext) {
			$this->context = $template_or_context->to_array();

			if ($template_or_context->get_type() === 'template') {
				$template = $template_or_context->get_template();
				$voice_obj = $template_or_context->get_voice();

				$this->template = array(
					'id' => $template->id,
					'name' => $template->name,
					'prompt_template' => $template->prompt_template,
					'title_prompt' => isset($template->title_prompt) ? $template->title_prompt : '',
					'post_status' => $template->post_status,
					'post_category' => $template->post_category,
					'post_tags' => isset($template->post_tags) ? $template->post_tags : '',
					'post_author' => isset($template->post_author) ? $template->post_author : '',
					'post_quantity' => isset($template->post_quantity) ? $template->post_quantity : 1,
					'generate_featured_image' => isset($template->generate_featured_image) ? $template->generate_featured_image : 0,
					'image_prompt' => isset($template->image_prompt) ? $template->image_prompt : '',
				);

				if ($voice_obj) {
					$this->voice = array(
						'id' => $voice_obj->id,
						'name' => $voice_obj->name,
						'title_prompt' => isset($voice_obj->title_prompt) ? $voice_obj->title_prompt : '',
						'content_instructions' => isset($voice_obj->content_instructions) ? $voice_obj->content_instructions : '',
						'excerpt_instructions' => isset($voice_obj->excerpt_instructions) ? $voice_obj->excerpt_instructions : '',
					);
				}
			}
		} else {
			$template = $template_or_context;

			$this->template = array(
				'id' => $template->id,
				'name' => $template->name,
				'prompt_template' => $template->prompt_template,
				'title_prompt' => isset($template->title_prompt) ? $template->title_prompt : '',
				'post_status' => $template->post_status,
				'post_category' => $template->post_category,
				'post_tags' => isset($template->post_tags) ? $template->post_tags : '',
				'post_author' => isset($template->post_author) ? $template->post_author : '',
				'post_quantity' => isset($template->post_quantity) ? $template->post_quantity : 1,
				'generate_featured_image' => isset($template->generate_featured_image) ? $template->generate_featured_image : 0,
				'image_prompt' => isset($template->image_prompt) ? $template->image_prompt : '',
			);

			if ($voice) {
				$this->voice = array(
					'id' => $voice->id,
					'name' => $voice->name,
					'title_prompt' => isset($voice->title_prompt) ? $voice->title_prompt : '',
					'content_instructions' => isset($voice->content_instructions) ? $voice->content_instructions : '',
					'excerpt_instructions' => isset($voice->excerpt_instructions) ? $voice->excerpt_instructions : '',
				);
			}

			$this->context = array(
				'type' => 'template',
				'id' => $template->id,
				'name' => $template->name,
			);

			if ($voice) {
				$this->context['voice_id'] = $voice->id;
			}
		}
	}

	/**
	 * Log an AI call made during generation.
	 *
	 * @return void
	 */
	public function log_ai_call() {
		$this->ai_call_count++;
	}

	/**
	 * Add an error to the session.
	 *
	 * @return void
	 */
	public function add_error() {
		$this->error_count++;
	}

	/**
	 * Complete the session with final results.
	 *
	 * @param array $result Result data (success, post_id, error, etc.).
	 * @return void
	 */
	public function complete($result) {
		$this->completed_at = current_time('mysql');
		$this->result = $result;
	}

	/**
	 * Get the session data as an array for serialization.
	 *
	 * @return array Session data.
	 */
	public function to_array() {
		return array(
			'started_at' => $this->started_at,
			'completed_at' => $this->completed_at,
			'ai_call_count' => $this->ai_call_count,
			'error_count' => $this->error_count,
			'template' => $this->template,
			'voice' => $this->voice,
			'context' => $this->context,
			'result' => $this->result,
		);
	}

	/**
	 * Serialize the session data to JSON.
	 *
	 * @return string JSON-encoded session data.
	 */
	public function to_json() {
		return wp_json_encode($this->to_array());
	}
}
