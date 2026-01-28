<?php
/**
 * Variable Resolution Service
 *
 * Handles the resolution of AI variables in prompts/templates.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Variable_Resolution_Service
 *
 * Encapsulates the logic for resolving dynamic AI variables.
 */
class AIPS_Variable_Resolution_Service {

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var AIPS_AI_Service
	 */
	private $ai_service;

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Logger|null             $logger
	 * @param AIPS_AI_Service|null         $ai_service
	 * @param AIPS_Template_Processor|null $template_processor
	 */
	public function __construct($logger = null, $ai_service = null, $template_processor = null) {
		$this->logger = $logger ?: new AIPS_Logger();
		$this->ai_service = $ai_service ?: new AIPS_AI_Service();
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
	}

	/**
	 * Resolve AI Variables from a generation context.
	 *
	 * Extracts AI Variables from the title prompt and uses AI to generate
	 * appropriate values based on the content context.
	 *
	 * @param AIPS_Generation_Context     $context           Generation context.
	 * @param string                      $content           Generated article content for context.
	 * @param AIPS_History_Container|null $history_container Optional history container for logging.
	 * @return array Associative array of resolved AI variable values.
	 */
	public function resolve_variables($context, $content, $history_container = null) {
		// Get the title prompt from context
		$title_prompt = $context->get_title_prompt();

		// For template contexts with voice, voice takes precedence
		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice_obj = $context->get_voice();
			if ($voice_obj && !empty($voice_obj->title_prompt)) {
				$title_prompt = $voice_obj->title_prompt;
			}
		}

		// Extract AI variables from the title prompt
		$ai_variables = $this->template_processor->extract_ai_variables($title_prompt);

		if (empty($ai_variables)) {
			return array();
		}

		// Build context from content prompt and generated content.
		// Use smart truncation to preserve context from both beginning and end of content.
		$context_str = "Content Prompt: " . $context->get_content_prompt() . "\n\n";
		$context_str .= "Generated Article Content:\n" . $this->smart_truncate_content($content, 2000);

		// Build the prompt to resolve AI variables
		$resolve_prompt = $this->template_processor->build_ai_variables_prompt($ai_variables, $context_str);

		// Call AI to resolve the variables.
		// Max tokens of 200 is sufficient for JSON responses with typical variable values.
		$options = array('max_tokens' => 200);
		$result = $this->generate_content($resolve_prompt, $options, 'ai_variables', $history_container);

		if (is_wp_error($result)) {
			$this->logger->log('Failed to resolve AI variables: ' . $result->get_error_message(), 'warning');
			return array();
		}

		// Parse the AI response to extract variable values
		$resolved_values = $this->template_processor->parse_ai_variables_response($result, $ai_variables);

		if (empty($resolved_values)) {
			// AI call succeeded but we could not extract any variable values.
			// This usually indicates invalid JSON or an unexpected response format.
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
	 * Generate content using AI with optional history logging.
	 *
	 * @param string                      $prompt            The prompt to send to AI.
	 * @param array                       $options           Optional AI generation options.
	 * @param string                      $log_type          Optional type label for logging.
	 * @param AIPS_History_Container|null $history_container Optional history container.
	 * @return string|WP_Error The generated content or WP_Error on failure.
	 */
	private function generate_content($prompt, $options = array(), $log_type = 'content', $history_container = null) {
		// Log AI request before making the call
		if ($history_container) {
			$history_container->record(
				'ai_request',
				"Requesting AI generation for {$log_type}",
				array(
					'prompt' => $prompt,
					'options' => $options,
				),
				null,
				array('component' => $log_type)
			);
		}

		$result = $this->ai_service->generate_text($prompt, $options);

		if (is_wp_error($result)) {
			// Log the error
			if ($history_container) {
				$history_container->record(
					'error',
					"AI generation failed for {$log_type}: " . $result->get_error_message(),
					array(
						'prompt' => $prompt,
						'options' => $options,
					),
					null,
					array('component' => $log_type, 'error' => $result->get_error_message())
				);
			}

			$this->logger->log($result->get_error_message(), 'error', array(
				'component' => $log_type,
				'prompt_length' => strlen($prompt)
			));
		} else {
			// Log successful AI response
			if ($history_container) {
				$history_container->record(
					'ai_response',
					"AI generation successful for {$log_type}",
					null,
					$result,
					array('component' => $log_type)
				);
			}

			$this->logger->log('Content generated successfully', 'info', array(
				'component' => $log_type,
				'prompt_length' => strlen($prompt),
				'response_length' => strlen($result)
			));
		}

		return $result;
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

		// If content fits within limit, return as-is
		if ($content_length <= $max_length) {
			return $content;
		}

		// Define separator and calculate its length
		$separator = "\n\n[...]\n\n";
		$separator_length = mb_strlen($separator);

		// Ensure minimum length to avoid negative values
		$min_length = $separator_length + 40; // At least 20 chars on each end
		if ($max_length < $min_length) {
			$max_length = $min_length;
		}

		// Calculate how much to take from each end
		// Take 60% from the beginning (introductions, key points) and 40% from the end (conclusions)
		$available_length = $max_length - $separator_length;
		$start_length = (int) ($available_length * 0.6);
		$end_length = $available_length - $start_length;

		$start_content = mb_substr($content, 0, $start_length);
		$end_content = mb_substr($content, -$end_length);

		return $start_content . $separator . $end_content;
	}
}
