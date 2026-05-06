<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes featured image prompts and resolves variables.
 *
 * Extracted from AIPS_Generator to adhere to Single Responsibility Principle.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.2
 */
final class AIPS_Image_Prompt_Processor {

	/**
	 * @var AIPS_Template_Processor Template processor instance.
	 */
	private $template_processor;

	/**
	 * @var AIPS_AI_Variable_Resolver AI variable resolver instance.
	 */
	private $ai_variable_resolver;

	/**
	 * @param AIPS_Template_Processor   $template_processor   Processor for template variables.
	 * @param AIPS_AI_Variable_Resolver $ai_variable_resolver Resolver for AI-specific variables.
	 */
	public function __construct( AIPS_Template_Processor $template_processor, AIPS_AI_Variable_Resolver $ai_variable_resolver ) {
		$this->template_processor   = $template_processor;
		$this->ai_variable_resolver = $ai_variable_resolver;
	}

	/**
	 * Process featured image prompt with basic template variables and AI variables.
	 *
	 * Resolves any AI variables (custom {{VariableName}} placeholders not in the
	 * system variable list) using the generated content and title as context,
	 * then processes standard template variables such as {{topic}}.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param string                  $content Generated article content.
	 * @param string                  $title   Generated post title.
	 * @return string Processed image prompt with all variables replaced.
	 */
	public function process_featured_image_prompt( AIPS_Generation_Context $context, $content = '', $title = '' ) {
		$image_prompt = $context->get_image_prompt();
		if ( empty( $image_prompt ) ) {
			return '';
		}

		$topic_str             = $context->get_topic();
		$resolved_ai_variables = array();

		if ( $this->template_processor->has_ai_variables( $image_prompt ) ) {
			$image_context         = $this->ai_variable_resolver->build_featured_image_variable_context( $context, $content, $title );
			$resolved_ai_variables = $this->ai_variable_resolver->resolve_ai_variables_for_template_string( $image_prompt, $image_context, 'ai_variables_featured_image' );
		}

		$processed_prompt = $this->template_processor->process_with_ai_variables( $image_prompt, $topic_str, $resolved_ai_variables );

		return $this->remove_unresolved_template_placeholders( $processed_prompt );
	}

	/**
	 * Remove any unresolved template placeholders from a processed prompt.
	 *
	 * This is a defensive cleanup step for public featured image prompt
	 * processing so downstream preview and generation paths never receive raw
	 * {{Variable}} tokens when AI-variable resolution is partial.
	 *
	 * @param string $prompt Processed prompt text.
	 * @return string Prompt with unresolved placeholders removed.
	 */
	public function remove_unresolved_template_placeholders( $prompt ) {
		$prompt = (string) $prompt;
		$result = preg_replace( '/\{\{[^{}]+\}\}/', '', $prompt );
		if ( $result === null ) {
			return '';
		}

		$result = preg_replace( '/\s+/', ' ', $result );

		return $result === null ? '' : trim( $result );
	}
}
