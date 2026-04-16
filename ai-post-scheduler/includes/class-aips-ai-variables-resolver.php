<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AIPS_AI_Variables_Resolver
 *
 * Responsible for parsing prompts for AI variables and calling the AI
 * to resolve them based on the generated content context. Extracted
 * from AIPS_Generator to enforce Separation of Concerns.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_AI_Variables_Resolver {

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * @var callable Callback to invoke the AI service for content generation.
	 */
	private $generation_callback;

	/**
	 * @var AIPS_Generation_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Template_Processor $template_processor  The template processor.
	 * @param callable                $generation_callback Callback to invoke AI; must accept ($prompt, $options, $type).
	 * @param AIPS_Generation_Logger  $logger              The generation logger.
	 */
	public function __construct( AIPS_Template_Processor $template_processor, callable $generation_callback, AIPS_Generation_Logger $logger ) {
		$this->template_processor  = $template_processor;
		$this->generation_callback = $generation_callback;
		$this->logger              = $logger;
	}

	/**
	 * Resolve AI Variables based on template, voice, and generated content.
	 *
	 * @param object      $template Template object.
	 * @param string      $content  Generated article content.
	 * @param object|null $voice    Optional voice object.
	 * @return array Associative array of resolved AI variable values.
	 */
	public function resolve( $template, $content, $voice = null ) {
		$context = new AIPS_Template_Context( $template, $voice, null );
		return $this->resolve_from_context( $context, $content );
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
	public function resolve_from_context( $context, $content ) {
		// Get the title prompt from context.
		$title_prompt = $context->get_title_prompt();

		// For template contexts with voice, voice takes precedence.
		if ( $context->get_type() === 'template' && $context->get_voice_id() ) {
			$voice_obj = $context->get_voice();
			if ( $voice_obj && ! empty( $voice_obj->title_prompt ) ) {
				$title_prompt = $voice_obj->title_prompt;
			}
		}

		// Extract AI variables from the title prompt.
		$ai_variables = $this->template_processor->extract_ai_variables( $title_prompt );

		if ( empty( $ai_variables ) ) {
			return array();
		}

		// Build context from content prompt and generated content.
		$context_str  = 'Content Prompt: ' . $context->get_content_prompt() . "\n\n";
		$context_str .= 'Generated Article Content:' . "\n" . $this->smart_truncate_content( $content, 2000 );

		// Build the prompt to resolve AI variables.
		$resolve_prompt = $this->template_processor->build_ai_variables_prompt( $ai_variables, $context_str );

		// Call AI to resolve the variables.
		$options = array();
		$result  = ( $this->generation_callback )( $resolve_prompt, $options, 'ai_variables' );

		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'Failed to resolve AI variables: ' . $result->get_error_message(), 'warning' );
			return array();
		}

		// Parse the AI response to extract variable values.
		$resolved_values = $this->template_processor->parse_ai_variables_response( $result, $ai_variables );

		if ( empty( $resolved_values ) ) {
			$this->logger->log(
				'AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.',
				'warning',
				array(
					'variables'    => $ai_variables,
					'raw_response' => $result,
				)
			);
		} else {
			$this->logger->log(
				'Resolved AI variables',
				'info',
				array(
					'variables' => $ai_variables,
					'resolved'  => $resolved_values,
				)
			);
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
	private function smart_truncate_content( $content, $max_length = 2000 ) {
		$content_length = mb_strlen( $content );

		// If content fits within limit, return as-is.
		if ( $content_length <= $max_length ) {
			return $content;
		}

		// Define separator and calculate its length.
		$separator        = "\n\n[...]\n\n";
		$separator_length = mb_strlen( $separator );

		// Ensure minimum length to avoid negative values.
		$min_length = $separator_length + 40; // At least 20 chars on each end.
		if ( $max_length < $min_length ) {
			$max_length = $min_length;
		}

		// Calculate how much to take from each end.
		$available_length = $max_length - $separator_length;
		$start_length     = (int) ( $available_length * 0.6 );
		$end_length       = $available_length - $start_length;

		$start_content = mb_substr( $content, 0, $start_length );
		$end_content   = mb_substr( $content, -$end_length );

		return $start_content . $separator . $end_content;
	}
}
