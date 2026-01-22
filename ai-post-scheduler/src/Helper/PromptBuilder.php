<?php
namespace AIPS\Helper;

use AIPS\Generation\Context\GenerationContext;
use AIPS_Article_Structure_Manager;

if (!defined('ABSPATH')) {
	exit;
}

class PromptBuilder {

	private $template_processor;
	private $structure_manager;

	public function __construct($template_processor = null, $structure_manager = null) {
		$this->template_processor = $template_processor ?: new TemplateProcessor();
		$this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
	}

	/**
	 * Builds the complete content prompt based on context.
	 *
	 * @param object|GenerationContext $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null $topic    The topic for the post (legacy, may be null if using context).
	 * @param object|null $voice    Optional voice object (legacy, may be null if using context).
	 * @return string The constructed prompt.
	 */
	public function build_content_prompt($template_or_context, $topic = null, $voice = null) {
		if ($template_or_context instanceof GenerationContext) {
			$context = $template_or_context;

			do_action('aips_before_build_content_prompt', $context, null);

			$processed_prompt = $context->get_content_prompt();

			$article_structure_id = $context->get_article_structure_id();
			$topic_str = $context->get_topic();

			if ($article_structure_id && $topic_str) {
				$structured_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic_str);

				if (!is_wp_error($structured_prompt)) {
					$processed_prompt = $structured_prompt;
				} else {
					$processed_prompt = $this->template_processor->process($processed_prompt, $topic_str);
				}
			} elseif ($topic_str) {
				$processed_prompt = $this->template_processor->process($processed_prompt, $topic_str);
			}

			if ($context->get_type() === 'template' && $context->get_voice_id()) {
				$voice_obj = method_exists($context, 'get_voice') ? $context->get_voice() : null;
				if ($voice_obj && !empty($voice_obj->content_instructions)) {
					$voice_instructions = $this->template_processor->process($voice_obj->content_instructions, $topic_str);
					$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
				}
			}

			$content_prompt = apply_filters('aips_content_prompt', $processed_prompt, $context, $topic_str);

			return $content_prompt;
		}

		$template = $template_or_context;

		do_action('aips_before_build_content_prompt', $template, $topic);

		$article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

		if ($article_structure_id) {
			$processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);

			if (is_wp_error($processed_prompt)) {
				$processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
			}
		} else {
			$processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
		}

		if ($voice) {
			$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
			$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
		}

		$content_prompt = $processed_prompt;

		$content_prompt = apply_filters('aips_content_prompt', $content_prompt, $template, $topic);

		return $content_prompt;
	}

	/**
	 * Builds an auxiliary context string for AI Engine queries.
	 *
	 * @param object|GenerationContext $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null $topic    The topic for the post (legacy).
	 * @param object|null $voice    Optional voice object (legacy).
	 * @return string Context string (may be empty).
	 */
	public function build_content_context($template_or_context, $topic = null, $voice = null) {
		$context_parts = array();

		if ($template_or_context instanceof GenerationContext) {
			$context = $template_or_context;
			$topic_str = $context->get_topic();

			if ($context->get_type() === 'template' && $context->get_voice_id()) {
				$voice_obj = method_exists($context, 'get_voice') ? $context->get_voice() : null;
				if ($voice_obj && !empty($voice_obj->content_instructions)) {
					$context_parts[] = $this->template_processor->process($voice_obj->content_instructions, $topic_str);
				}
			}

			$context_parts[] = $this->get_output_instructions();

			$context_parts = apply_filters('aips_content_context_parts', $context_parts, $context, $topic_str, null);
		} else {
			$template = $template_or_context;

			if ($voice && !empty($voice->content_instructions)) {
				$context_parts[] = $this->template_processor->process($voice->content_instructions, $topic);
			}

			$context_parts[] = $this->get_output_instructions();

			$context_parts = apply_filters('aips_content_context_parts', $context_parts, $template, $topic, $voice);
		}

		$context_parts = array_filter(
			array_map('trim', $context_parts),
			function($part) {
				return !empty($part);
			}
		);

		return implode("\n\n", $context_parts);
	}

	/**
	 * Builds the complete prompt for title generation.
	 *
	 * @param object|GenerationContext $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null $topic    Optional topic to be injected into prompts (legacy).
	 * @param object|null $voice    Optional voice object with overrides (legacy).
	 * @param string      $content  Generated article content used as context.
	 * @return string The complete title generation prompt.
	 */
	public function build_title_prompt($template_or_context, $topic = null, $voice = null, $content = '') {
		$title_instructions = '';

		if ($template_or_context instanceof GenerationContext) {
			$context = $template_or_context;
			$topic_str = $context->get_topic();

			if ($context->get_type() === 'template' && $context->get_voice_id()) {
				$voice_obj = method_exists($context, 'get_voice') ? $context->get_voice() : null;
				if ($voice_obj && !empty($voice_obj->title_prompt)) {
					$title_instructions = $this->template_processor->process($voice_obj->title_prompt, $topic_str);
				}
			}

			if (empty($title_instructions)) {
				$title_prompt = $context->get_title_prompt();
				if (!empty($title_prompt)) {
					$title_instructions = $this->template_processor->process($title_prompt, $topic_str);
				}
			}

			$prompt = "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else. Here are your instructions:\n\n";

			if (!empty($title_instructions)) {
				$prompt .= $title_instructions . "\n\n";
			}

			$prompt .= "Here is the content:\n\n" . $content;

			$prompt = apply_filters('aips_title_prompt', $prompt, $context, $topic_str, null, $content);

			return $prompt;
		}

		$template = $template_or_context;

		if ($voice && !empty($voice->title_prompt)) {
			$title_instructions = $this->template_processor->process($voice->title_prompt, $topic);
		} elseif (!empty($template->title_prompt)) {
			$title_instructions = $this->template_processor->process($template->title_prompt, $topic);
		}

		$prompt = "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else. Here are your instructions:\n\n";

		if (!empty($title_instructions)) {
			$prompt .= $title_instructions . "\n\n";
		}

		$prompt .= "Here is the content:\n\n" . $content;

		$prompt = apply_filters('aips_title_prompt', $prompt, $template, $topic, $voice, $content);

		return $prompt;
	}

	/**
	 * Builds the complete prompt for excerpt generation.
	 *
	 * @param string      $title   Title of the generated article.
	 * @param string      $content The article content to summarize.
	 * @param object|null $voice   Optional voice object with excerpt instructions (legacy).
	 * @param string|null $topic   Optional topic to be injected into prompts (legacy).
	 * @return string The complete excerpt generation prompt.
	 */
	public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
		$excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

		if ($voice && !empty($voice->excerpt_instructions)) {
			$voice_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
			$excerpt_prompt .= $voice_instructions . "\n\n";
		}

		$excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
		$excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
		$excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";

		$excerpt_prompt = apply_filters('aips_excerpt_prompt', $excerpt_prompt, $title, $content, $voice, $topic);

		return $excerpt_prompt;
	}

	/**
	 * Builds voice-specific excerpt instructions (legacy method for backward compatibility).
	 *
	 * @deprecated Use build_excerpt_prompt() instead
	 * @param object|null $voice
	 * @param string|null $topic
	 * @return string|null
	 */
	public function build_excerpt_instructions($voice, $topic) {
		if ($voice && !empty($voice->excerpt_instructions)) {
			return $this->template_processor->process($voice->excerpt_instructions, $topic);
		}
		return null;
	}

	/**
	 * Standard output instructions for article formatting.
	 *
	 * @return string
	 */
	private function get_output_instructions() {
		return 'Output the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.';
	}
}
