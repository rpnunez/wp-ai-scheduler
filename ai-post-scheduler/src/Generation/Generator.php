<?php
namespace AIPS\Generation;

use AIPS\Generation\Context\GenerationContext;
use AIPS\Generation\Context\TemplateContext;
use AIPS\Helper\PostCreator;
use AIPS\Helper\PromptBuilder;
use AIPS\Helper\TemplateProcessor;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Generator
 *
 * Responsible for orchestrating the AI content generation pipeline:
 * - Building prompts
 * - Calling the AI service for text and images
 * - Creating posts and updating history
 * - Tracking a per-request generation session for observability
 */
class Generator {

	private $ai_service;
	private $logger;

	/**
	 * @var Session Current generation session tracker.
	 *
	 * This tracks runtime details of a single post generation attempt.
	 * It is ephemeral (exists only during the current request) and is
	 * serialized to JSON for storage in the History database table.
	 */
	private $current_session;

	private $template_processor;
	private $image_service;
	private $structure_manager;
	private $post_creator;
	private $history_repository;
	private $prompt_builder;
	private $history_id;

	/**
	 * Constructor.
	 *
	 * @param object|null $logger
	 * @param object|null $ai_service
	 * @param object|null $template_processor
	 * @param object|null $image_service
	 * @param object|null $structure_manager
	 * @param object|null $post_creator
	 * @param object|null $history_repository
	 * @param object|null $prompt_builder
	 */
	public function __construct(
		$logger = null,
		$ai_service = null,
		$template_processor = null,
		$image_service = null,
		$structure_manager = null,
		$post_creator = null,
		$history_repository = null,
		$prompt_builder = null
	) {
		$this->logger = $logger ?: new \AIPS_Logger();
		$this->ai_service = $ai_service ?: new \AIPS\Service\AI();
		$this->template_processor = $template_processor ?: new TemplateProcessor();
		$this->image_service = $image_service ?: new \AIPS\Service\Image($this->ai_service);
		$this->structure_manager = $structure_manager ?: new \AIPS_Article_Structure_Manager();
		$this->post_creator = $post_creator ?: new PostCreator();
		$this->history_repository = $history_repository ?: new \AIPS\Repository\History();
		$this->prompt_builder = $prompt_builder ?: new PromptBuilder($this->template_processor, $this->structure_manager);

		$this->current_session = new Session();
	}

	/**
	 * Log an AI call to the current generation session and history log.
	 *
	 * @param string      $type     Type of AI call (e.g., 'title', 'content', 'excerpt', 'featured_image').
	 * @param string      $prompt   The prompt sent to AI.
	 * @param string|null $response The AI response, if successful.
	 * @param array       $options  Options used for the call.
	 * @param string|null $error    Error message, if call failed.
	 * @return void
	 */
	private function log_ai_call($type, $prompt, $response, $options = array(), $error = null) {
		$this->current_session->log_ai_call();
		if ($error) {
			$this->current_session->add_error();
		}

		if ($this->history_id) {
			$details = array(
				'prompt' => $prompt,
				'options' => $options,
				'response' => base64_encode($response),
				'error' => $error,
			);
			$this->history_repository->add_log_entry($this->history_id, $type, $details);
		}
	}

	/**
	 * Log a message with optional AI data to both the logger and the session.
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level (info, error, warning).
	 * @param array  $ai_data Optional AI call data to log.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	private function log($message, $level, $ai_data = array(), $context = array()) {
		$this->logger->log($message, $level, $context);

		if (!empty($ai_data) && isset($ai_data['type']) && isset($ai_data['prompt'])) {
			$type = $ai_data['type'];
			$prompt = $ai_data['prompt'];
			$response = isset($ai_data['response']) ? $ai_data['response'] : null;
			$options = isset($ai_data['options']) ? $ai_data['options'] : array();
			$error = isset($ai_data['error']) ? $ai_data['error'] : null;

			$this->log_ai_call($type, $prompt, $response, $options, $error);
		}
	}

	/**
	 * Check if AI is available in the configured AI service.
	 *
	 * @return bool True if AI Engine is available, false otherwise.
	 */
	public function is_available() {
		return $this->ai_service->is_available();
	}

	/**
	 * Generate content using AI.
	 *
	 * @param string $prompt   The prompt to send to AI.
	 * @param array  $options  Optional AI generation options.
	 * @param string $log_type Optional type label for logging.
	 * @return string|WP_Error The generated content or WP_Error on failure.
	 */
	public function generate_content($prompt, $options = array(), $log_type = 'content') {
		$result = $this->ai_service->generate_text($prompt, $options);

		if (is_wp_error($result)) {
			$this->log($result->get_error_message(), 'error', array(
				'type' => $log_type,
				'prompt' => $prompt,
				'options' => $options,
				'error' => $result->get_error_message()
			));
		} else {
			$this->log('Content generated successfully', 'info', array(
				'type' => $log_type,
				'prompt' => $prompt,
				'response' => $result,
				'options' => $options
			), array(
				'prompt_length' => strlen($prompt),
				'response_length' => strlen($result)
			));
		}

		return $result;
	}

	/**
	 * Resolve AI Variables for a template.
	 *
	 * @param object      $template Template object containing prompts.
	 * @param string      $content  Generated article content for context.
	 * @param object|null $voice    Optional voice object with title prompt.
	 * @return array Associative array of resolved AI variable values.
	 */
	public function resolve_ai_variables($template, $content, $voice = null) {
		$context = new TemplateContext($template, $voice, null);
		return $this->resolve_ai_variables_from_context($context, $content);
	}

	/**
	 * Resolve AI Variables from a generation context.
	 *
	 * @param GenerationContext $context Generation context.
	 * @param string            $content Generated article content for context.
	 * @return array Associative array of resolved AI variable values.
	 */
	private function resolve_ai_variables_from_context($context, $content) {
		$title_prompt = $context->get_title_prompt();

		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice_obj = $context->get_voice();
			if ($voice_obj && !empty($voice_obj->title_prompt)) {
				$title_prompt = $voice_obj->title_prompt;
			}
		}

		$ai_variables = $this->template_processor->extract_ai_variables($title_prompt);

		if (empty($ai_variables)) {
			return array();
		}

		$context_str = "Content Prompt: " . $context->get_content_prompt() . "\n\n";
		$context_str .= "Generated Article Content:\n" . $this->smart_truncate_content($content, 2000);

		$resolve_prompt = $this->template_processor->build_ai_variables_prompt($ai_variables, $context_str);

		$options = array('max_tokens' => 200);
		$result = $this->generate_content($resolve_prompt, $options, 'ai_variables');

		if (is_wp_error($result)) {
			$this->logger->log('Failed to resolve AI variables: ' . $result->get_error_message(), 'warning');
			return array();
		}

		$resolved_values = $this->template_processor->parse_ai_variables_response($result, $ai_variables);

		if (empty($resolved_values)) {
			$this->logger->log('AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.', 'warning', array(
				'variables' => $ai_variables,
				'raw_response' => $result,
			));
		} else {
			$this->logger->log('Resolved AI variables', 'info', array(
				'variables' => $ai_variables,
				'resolved'   => $resolved_values,
			));
		}

		return $resolved_values;
	}

	/**
	 * Smart truncate content to preserve key information from both beginning and end.
	 *
	 * @param string $content    The content to truncate.
	 * @param int    $max_length Maximum total length of the result. Minimum of 100 chars.
	 * @return string Truncated content with beginning and end preserved.
	 */
	private function smart_truncate_content($content, $max_length = 2000) {
		$content_length = mb_strlen($content);

		if ($content_length <= $max_length) {
			return $content;
		}

		$separator = "\n\n[...]\n\n";
		$separator_length = mb_strlen($separator);

		$min_length = $separator_length + 40;
		if ($max_length < $min_length) {
			$max_length = $min_length;
		}

		$available_length = $max_length - $separator_length;
		$start_length = (int) ($available_length * 0.6);
		$end_length = $available_length - $start_length;

		$start_content = mb_substr($content, 0, $start_length);
		$end_content = mb_substr($content, -$end_length);

		return $start_content . $separator . $end_content;
	}

	/**
	 * Generate a post title based on the generated content, template, and optional voice/topic.
	 *
	 * @param object      $template Template object containing prompts and settings.
	 * @param object|null $voice    Optional voice object with overrides.
	 * @param string|null $topic    Optional topic to be injected into prompts.
	 * @param string      $content  Generated article content used as context.
	 * @param array       $options  AI options (e.g., model, max_tokens override).
	 * @param array       $ai_variables Optional resolved AI variables.
	 * @return string|WP_Error      Generated title string or WP_Error on failure.
	 */
	public function generate_title($template, $voice = null, $topic = null, $content = '', $options = array(), $ai_variables = array()) {
		$context = new TemplateContext($template, $voice, $topic);
		return $this->generate_title_from_context($context, $content, $ai_variables, $options);
	}

	/**
	 * Generate a post title from a generation context.
	 *
	 * @param GenerationContext $context      Generation context.
	 * @param string            $content      Generated article content used as context.
	 * @param array             $ai_variables Optional resolved AI variables.
	 * @param array             $options      AI options (e.g., model, max_tokens override).
	 * @return string|WP_Error Generated title string or WP_Error on failure.
	 */
	private function generate_title_from_context($context, $content = '', $ai_variables = array(), $options = array()) {
		$prompt = $this->prompt_builder->build_title_prompt($context, null, null, $content);

		$options['max_tokens'] = 100;

		$result = $this->generate_content($prompt, $options, 'title');

		if (is_wp_error($result)) {
			return $result;
		}

		$title = trim($result);

		$title = preg_replace('/^["\']|["\']$/', '', $title);

		return $title;
	}

	/**
	 * Generate an excerpt (short summary) for a post.
	 *
	 * @param string      $title   Title of the generated article.
	 * @param string      $content The article content to summarize.
	 * @param object|null $voice   Optional voice object with excerpt instructions.
	 * @param string|null $topic   Optional topic to be injected into prompts.
	 * @param array       $options AI options.
	 * @return string Short excerpt string (max 160 chars). Empty string on failure.
	 */
	public function generate_excerpt($title, $content, $voice = null, $topic = null, $options = array()) {
		$excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($title, $content, $voice, $topic);

		$options['max_tokens'] = 150;

		$result = $this->generate_content($excerpt_prompt, $options, 'excerpt');

		if (is_wp_error($result)) {
			return '';
		}

		$excerpt = trim($result);
		$excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

		return substr($excerpt, 0, 160);
	}

	/**
	 * Generate an excerpt from a generation context.
	 *
	 * @param string            $title   Title of the generated article.
	 * @param string            $content The article content to summarize.
	 * @param GenerationContext $context Generation context.
	 * @param array             $options AI options.
	 * @return string Short excerpt string (max 160 chars). Empty string on failure.
	 */
	private function generate_excerpt_from_context($title, $content, $context, $options = array()) {
		$voice_obj = null;
		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice_obj = $context->get_voice();
		}

		$topic_str = $context->get_topic();

		$excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($title, $content, $voice_obj, $topic_str);

		$options['max_tokens'] = 150;

		$result = $this->generate_content($excerpt_prompt, $options, 'excerpt');

		if (is_wp_error($result)) {
			return '';
		}

		$excerpt = trim($result);
		$excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

		return substr($excerpt, 0, 160);
	}

	/**
	 * Main entry point to generate a post from a context (template, topic, etc.).
	 *
	 * @param object|GenerationContext $template_or_context Template object (legacy) or Generation Context.
	 * @param object|null $voice Optional voice object with overrides (legacy).
	 * @param string|null $topic Optional topic to be injected into prompts (legacy).
	 * @return int|WP_Error ID of created post or WP_Error on failure.
	 */
	public function generate_post($template_or_context, $voice = null, $topic = null) {
		if ($template_or_context instanceof GenerationContext) {
			return $this->generate_post_from_context($template_or_context);
		}

		$template = $template_or_context;
		$context = new TemplateContext($template, $voice, $topic);
		return $this->generate_post_from_context($context);
	}

	/**
	 * Generate a post from a Generation Context.
	 *
	 * @param GenerationContext $context Generation context.
	 * @return int|WP_Error ID of created post or WP_Error on failure.
	 */
	private function generate_post_from_context($context) {
		do_action('aips_post_generation_started', $context->get_id(), $context->get_topic() ? $context->get_topic() : '');

		$this->current_session->start($context);

		$this->history_id = $this->history_repository->create(array(
			'template_id' => $context->get_type() === 'template' ? $context->get_id() : null,
			'status' => 'processing',
			'prompt' => $context->get_content_prompt(),
		));

		if (!$this->history_id) {
			$this->logger->log('Failed to create history record', 'error');
		}

		$content_prompt = $this->prompt_builder->build_content_prompt($context);

		$content_context = $this->prompt_builder->build_content_context($context);
		$content_options = array();
		if (!empty($content_context)) {
			$content_options['context'] = $content_context;
		}

		$content = $this->generate_content($content_prompt, $content_options, 'content');

		if (is_wp_error($content)) {
			$this->current_session->complete(array(
				'success' => false,
				'error' => $content->get_error_message(),
			));

			if ($this->history_id) {
				$this->history_repository->update($this->history_id, array(
					'status' => 'failed',
					'error_message' => $content->get_error_message(),
					'completed_at' => current_time('mysql'),
				));
			}

			do_action('aips_post_generation_failed', $context->get_id(), $content->get_error_message(), $context->get_topic());

			return $content;
		}

		$ai_variables = $this->resolve_ai_variables_from_context($context, $content);

		$title = $this->generate_title_from_context($context, $content, $ai_variables);

		$has_unresolved_placeholders = false;
		if (!is_wp_error($title) && is_string($title)) {
			if (strpos($title, '{{') !== false && strpos($title, '}}') !== false) {
				$has_unresolved_placeholders = true;

				if (!empty($this->logger)) {
					$this->logger->warning(
						'Generated title contains unresolved AI variables; falling back to safe default title.',
						array(
							'context_type' => $context->get_type(),
							'context_id' => $context->get_id(),
							'topic'       => $context->get_topic(),
						)
					);
				}
			}
		}

		if (is_wp_error($title) || $has_unresolved_placeholders) {
			$base_title = __('AI Generated Post', 'ai-post-scheduler');
			$topic_str = $context->get_topic();
			if (!empty($topic_str)) {
				$base_title .= ': ' . mb_substr($topic_str, 0, 50) . (mb_strlen($topic_str) > 50 ? '...' : '');
			}
			$title = $base_title . ' - ' . date('Y-m-d H:i:s');
		}

		$excerpt_content = mb_substr($content, 0, 6000);
		$excerpt = $this->generate_excerpt_from_context($title, $excerpt_content, $context);

		$post_creation_data = array(
			'title' => $title,
			'content' => $content,
			'excerpt' => $excerpt,
			'context' => $context,
			'focus_keyword' => $context->get_topic() ? $context->get_topic() : $title,
			'meta_description' => $excerpt,
			'seo_title' => $title,
		);

		do_action('aips_post_generation_before_post_create', $post_creation_data);

		$post_id = $this->post_creator->create_post($post_creation_data);

		if (is_wp_error($post_id)) {
			$this->current_session->complete(array(
				'success' => false,
				'error' => $post_id->get_error_message(),
				'generated_title' => $title,
				'generated_content' => $content,
				'generated_excerpt' => $excerpt,
			));

			if ($this->history_id) {
				$this->history_repository->update($this->history_id, array(
					'status' => 'failed',
					'error_message' => $post_id->get_error_message(),
					'generated_title' => $title,
					'generated_content' => $content,
					'completed_at' => current_time('mysql'),
				));
			}

			return $post_id;
		}

		$featured_image_id = $this->set_featured_image_from_context($context, $post_id, $title);

		$this->current_session->complete(array(
			'success' => true,
			'post_id' => $post_id,
			'generated_title' => $title,
			'generated_content' => $content,
			'generated_excerpt' => $excerpt,
			'featured_image_id' => $featured_image_id,
		));

		if ($this->history_id) {
			$this->history_repository->update($this->history_id, array(
				'post_id' => $post_id,
				'status' => 'completed',
				'generated_title' => $title,
				'generated_content' => $content,
				'completed_at' => current_time('mysql'),
			));
		}

		$this->logger->log('Post generated successfully', 'info', array(
			'post_id' => $post_id,
			'context_type' => $context->get_type(),
			'context_id' => $context->get_id(),
			'title' => $title
		));

		if ($context->get_type() === 'template') {
			$template_obj = $context->get_template();
			do_action('aips_post_generated', $post_id, $template_obj, $this->history_id);
		} else {
			do_action('aips_post_generated', $post_id, $context, $this->history_id);
		}

		$this->history_id = null;
		return $post_id;
	}

	/**
	 * Generate or select and set the featured image for a post.
	 *
	 * @param object $template Template object containing image settings.
	 * @param int    $post_id  ID of the post to attach the image to.
	 * @param string $title    Title of the generated post, used as image alt text/context.
	 * @param string|null $topic Optional topic used when processing prompts.
	 * @return int|null ID of the featured image attachment or null on failure/disabled.
	 */
	private function set_featured_image($template, $post_id, $title, $topic = null) {
		$context = new TemplateContext($template, null, $topic);
		return $this->set_featured_image_from_context($context, $post_id, $title);
	}

	/**
	 * Generate or select and set the featured image for a post from a context.
	 *
	 * @param GenerationContext $context Generation context.
	 * @param int               $post_id ID of the post to attach the image to.
	 * @param string            $title   Title of the generated post, used as image alt text/context.
	 * @return int|null ID of the featured image attachment or null on failure/disabled.
	 */
	private function set_featured_image_from_context($context, $post_id, $title) {
		$featured_image_id = null;

		if (!$context->should_generate_featured_image()) {
			return null;
		}

		$featured_image_source = $context->get_featured_image_source();
		$allowed_sources = array('ai_prompt', 'unsplash', 'media_library');

		if (!in_array($featured_image_source, $allowed_sources, true)) {
			$featured_image_source = 'ai_prompt';
		}

		$featured_image_result = null;

		if ($featured_image_source === 'unsplash') {
			$keywords = $context->get_unsplash_keywords();
			$topic_str = $context->get_topic();

			$processed_keywords = $this->template_processor->process($keywords, $topic_str);
			$featured_image_result = $this->image_service->fetch_and_upload_unsplash_image($processed_keywords, $title);

			if (!is_wp_error($featured_image_result)) {
				$featured_image_id = $featured_image_result;

				$this->post_creator->set_featured_image($post_id, $featured_image_id);
			}
		} elseif ($featured_image_source === 'media_library') {
			$media_ids = $context->get_media_library_ids();
			$featured_image_result = $this->image_service->select_media_library_image($media_ids);

			if (!is_wp_error($featured_image_result)) {
				$this->post_creator->set_featured_image($post_id, $featured_image_result);

				$featured_image_id = $featured_image_result;
			}
		} elseif ($context->get_image_prompt()) {
			$image_prompt = $context->get_image_prompt();
			$topic_str = $context->get_topic();
			$processed_image_prompt = $this->template_processor->process($image_prompt, $topic_str);
			$featured_image_result = $this->image_service->generate_and_upload_featured_image($processed_image_prompt, $title);

			if (!is_wp_error($featured_image_result)) {
				$featured_image_id = $featured_image_result;

				$this->post_creator->set_featured_image($post_id, $featured_image_id);

				$this->log_ai_call('featured_image', $processed_image_prompt, null, array('featured_image_id' => $featured_image_id));
			}
		} else {
			$featured_image_result = new \WP_Error('missing_image_prompt', __('Image prompt is required to generate a featured image.', 'ai-post-scheduler'));
		}

		if (is_wp_error($featured_image_result)) {
			$this->logger->log('Featured image handling failed: ' . $featured_image_result->get_error_message(), 'error');

			$this->log_error('featured_image', $featured_image_result->get_error_message());
		}

		return $featured_image_id;
	}

	/**
	 * Log an error to the current generation session and history log.
	 *
	 * @param string $type    The type of error.
	 * @param string $message The error message.
	 * @return void
	 */
	private function log_error($type, $message) {
		$this->current_session->add_error();

		if ($this->history_id) {
			$details = array(
				'message' => $message,
			);
			$this->history_repository->add_log_entry($this->history_id, $type . '_error', $details);
		}
	}
}
