<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves AI Variables from generation contexts and templates.
 *
 * Extracted from AIPS_Generator to adhere to Single Responsibility Principle.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.2
 */
final class AIPS_AI_Variable_Resolver {

	/**
	 * @var AIPS_Template_Processor Template processor instance.
	 */
	private $template_processor;

	/**
	 * @var AIPS_Generation_Logger Generation logger instance.
	 */
	private $generation_logger;

	/**
	 * @var callable Callback representing generate_content; avoids a circular dependency.
	 */
	private $content_generator_callback;

	/**
	 * @var AIPS_Content_Normalizer Content normalizer for context truncation.
	 */
	private $content_normalizer;

	/**
	 * @param AIPS_Template_Processor $template_processor         Processor for template variables.
	 * @param AIPS_Generation_Logger  $generation_logger          Logger for generation flow.
	 * @param callable                $content_generator_callback Callback representing generate_content.
	 * @param AIPS_Content_Normalizer $content_normalizer         Content normalizer for truncation.
	 */
	public function __construct(
		AIPS_Template_Processor $template_processor,
		AIPS_Generation_Logger $generation_logger,
		callable $content_generator_callback,
		AIPS_Content_Normalizer $content_normalizer
	) {
		$this->template_processor         = $template_processor;
		$this->generation_logger          = $generation_logger;
		$this->content_generator_callback = $content_generator_callback;
		$this->content_normalizer         = $content_normalizer;
	}

	/**
	 * Resolve AI Variables from a generation context.
	 *
	 * Extracts AI Variables from the title prompt and uses AI to generate
	 * appropriate values based on the content context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param string                  $content Generated article content for context.
	 * @return array Associative array of resolved AI variable values.
	 */
	public function resolve_ai_variables_from_context( AIPS_Generation_Context $context, $content ) {
		$title_prompt = $context->get_title_prompt();

		if ( $context->get_type() === 'template' && $context->get_voice_id() ) {
			$voice_obj = $context->get_voice();
			if ( $voice_obj && ! empty( $voice_obj->title_prompt ) ) {
				$title_prompt = $voice_obj->title_prompt;
			}
		}

		$ai_variables = $this->template_processor->extract_ai_variables( $title_prompt );
		if ( empty( $ai_variables ) ) {
			return array();
		}

		$context_str  = 'Content Prompt: ' . $context->get_content_prompt() . "\n\n";
		$context_str .= "Generated Article Content:\n" . $this->content_normalizer->smart_truncate_content( $content, 2000 );

		return $this->resolve_ai_variables_for_template_string( $title_prompt, $context_str, 'ai_variables' );
	}

	/**
	 * Build context text for featured image AI variable resolution.
	 *
	 * Owned here because building this context is fundamentally about preparing
	 * the input for AI variable resolution, not about image-prompt processing.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param string                  $content Generated content.
	 * @param string                  $title   Generated title.
	 * @return string Formatted context string for image prompts.
	 */
	public function build_featured_image_variable_context( AIPS_Generation_Context $context, $content = '', $title = '' ) {
		$context_parts = array();

		if ( ! empty( $context->get_content_prompt() ) ) {
			$context_parts[] = 'Content Prompt: ' . $context->get_content_prompt();
		}

		if ( ! empty( $title ) ) {
			$context_parts[] = 'Generated Post Title: ' . $title;
		}

		if ( ! empty( $content ) ) {
			$context_parts[] = "Generated Article Content:\n" . $this->content_normalizer->smart_truncate_content( $content, 1600 );
		}

		if ( ! empty( $context->get_topic() ) ) {
			$context_parts[] = 'Topic: ' . $context->get_topic();
		}

		return implode( "\n\n", $context_parts );
	}

	/**
	 * Resolve AI variables for a template string using context text.
	 *
	 * @param string $template_string Template that may include AI variables.
	 * @param string $context_str     Context used to resolve variable values.
	 * @param string $log_type        Log component label for observability.
	 * @return array Associative array of resolved AI variable values.
	 */
	public function resolve_ai_variables_for_template_string( $template_string, $context_str, $log_type = 'ai_variables' ) {
		$ai_variables = $this->template_processor->extract_ai_variables( $template_string );
		if ( empty( $ai_variables ) ) {
			return array();
		}

		$resolve_prompt = $this->template_processor->build_ai_variables_prompt( $ai_variables, $context_str );
		$options        = array( 'max_tokens' => 200 );
		$result         = call_user_func( $this->content_generator_callback, $resolve_prompt, $options, $log_type );

		if ( is_wp_error( $result ) ) {
			$this->generation_logger->log( 'Failed to resolve AI variables: ' . $result->get_error_message(), 'warning' );
			return array();
		}

		$resolved_values = $this->template_processor->parse_ai_variables_response( $result, $ai_variables );

		if ( empty( $resolved_values ) ) {
			$this->generation_logger->log(
				'AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.',
				'warning',
				array(
					'variables'    => $ai_variables,
					'raw_response' => $result,
					'component'    => $log_type,
				)
			);
		} else {
			$this->generation_logger->log(
				'Resolved AI variables',
				'info',
				array(
					'variables' => $ai_variables,
					'resolved'  => $resolved_values,
					'component' => $log_type,
				)
			);
		}

		return $resolved_values;
	}
}
