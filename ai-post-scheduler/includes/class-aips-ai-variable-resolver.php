<?php
/**
 * AI Variable Resolver
 *
 * @package AIPS
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

class AIPS_AI_Variable_Resolver {
	private $ai_service;
	private $template_processor;
	private $generation_logger;
	private $content_generator_callback;

	/**
	 * Constructor.
	 *
	 * @param mixed $ai_service The AI Service instance.
	 * @param mixed $template_processor The template processor.
	 * @param mixed $generation_logger The generation logger.
	 * @param callable|null $content_generator_callback Optional callback to use the generator's full generation logic.
	 */
	public function __construct( $ai_service, $template_processor, $generation_logger, $content_generator_callback = null ) {
		$this->ai_service                 = $ai_service;
		$this->template_processor         = $template_processor;
		$this->generation_logger          = $generation_logger;
		$this->content_generator_callback = $content_generator_callback;
	}

	/**
	 * Resolve AI Variables for a template.
	 *
	 * Extracts AI Variables from the title prompt and uses AI to generate
	 * appropriate values based on the content context.
	 *
	 * @param object      $template Template object containing prompts.
	 * @param string      $content  Generated article content for context.
	 * @param object|null $voice    Optional voice object with title prompt.
	 * @return array Associative array of resolved AI variable values.
	 */
	public function resolve_ai_variables( $template, $content, $voice = null ) {
		$context = new AIPS_Template_Context( $template, $voice, null );
		return $this->resolve_ai_variables_from_context( $context, $content );
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
	public function resolve_ai_variables_from_context( $context, $content ) {
		$title_prompt = $context->get_title_prompt();

		if ( $context->get_type() === 'template' && $context->get_voice_id() ) {
			$voice_obj = $context->get_voice();
			if ( $voice_obj && ! empty( $voice_obj->title_prompt ) ) {
				$title_prompt = $voice_obj->title_prompt;
			}
		}

		if ( ! method_exists( $this->template_processor, 'extract_ai_variables' ) ) {
			return array();
		}

		$ai_variables = $this->template_processor->extract_ai_variables( $title_prompt );
		if ( empty( $ai_variables ) ) {
			return array();
		}

		$context_str  = "Content Prompt: " . $context->get_content_prompt() . "\n\n";
		$context_str .= "Generated Article Content:\n" . $this->smart_truncate_content( $content, 2000 );

		return $this->resolve_ai_variables_for_template_string( $title_prompt, $context_str, 'ai_variables' );
	}

	public function resolve_ai_variables_for_template_string( $template_string, $context_str, $log_type = 'ai_variables', $generator_callback = null ) {
		if ( ! method_exists( $this->template_processor, 'extract_ai_variables' ) ) {
			return array();
		}

		$ai_variables = $this->template_processor->extract_ai_variables( $template_string );

		if ( empty( $ai_variables ) ) {
			return array();
		}

		$resolve_prompt = $this->template_processor->build_ai_variables_prompt( $ai_variables, $context_str );

		$options = array( 'max_tokens' => 200 );
		if ( is_callable($generator_callback) ) {
		    $result = call_user_func($generator_callback, $resolve_prompt, $options, $log_type);
		} else {
		    $result  = $this->ai_service->generate_text( $resolve_prompt, $options );
		}

		if ( is_wp_error( $result ) ) {
			$this->generation_logger->log( 'Failed to resolve AI variables: ' . $result->get_error_message(), 'warning' );
			return array();
		}

		$resolved_values = $this->template_processor->parse_ai_variables_response( $result, $ai_variables );

		if ( empty( $resolved_values ) ) {
			$this->generation_logger->log( 'AI variables response contained no parsable variables. This may indicate invalid JSON or an unexpected format.', 'warning', array(
				'variables'    => $ai_variables,
				'raw_response' => $result,
				'component'    => $log_type,
			) );
		} else {
			$this->generation_logger->log( 'Resolved AI variables', 'info', array(
				'variables' => $ai_variables,
				'resolved'  => $resolved_values,
				'component' => $log_type,
			) );
		}

		return $resolved_values;
	}

	/**
	 * Build context text for featured image AI variable resolution.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param string                  $content Generated content.
	 * @param string                  $title   Generated title.
	 * @return string
	 */
	public function build_featured_image_variable_context( $context, $content = '', $title = '' ) {
		$context_parts = array();

		if ( ! empty( $context->get_content_prompt() ) ) {
			$context_parts[] = 'Content Prompt: ' . $context->get_content_prompt();
		}

		if ( ! empty( $title ) ) {
			$context_parts[] = 'Generated Post Title: ' . $title;
		}

		if ( ! empty( $content ) ) {
			$context_parts[] = "Generated Article Content:\n" . $this->smart_truncate_content( $content, 1600 );
		}

		if ( ! empty( $context->get_topic() ) ) {
			$context_parts[] = 'Topic: ' . $context->get_topic();
		}

		return implode( "\n\n", $context_parts );
	}

	/**
	 * Smart truncate content to preserve key information from both beginning and end.
	 *
	 * Instead of simply truncating from the beginning, this method takes content
	 * from both the start and end of the text to provide better context for AI
	 * variable resolution. Articles often have introductions at the start and
	 * conclusions/summaries at the end, both of which are valuable for context.
	 *
	 * @param string $content    The content to truncate.
	 * @param int    $max_length Maximum total length of the result. Minimum of 100 chars.
	 * @return string Truncated content with beginning and end preserved.
	 */
	public function smart_truncate_content( $content, $max_length = 2000 ) {
		$content_length = mb_strlen( $content );

		if ( $content_length <= $max_length ) {
			return $content;
		}

		$separator        = "\n\n[...]\n\n";
		$separator_length = mb_strlen( $separator );

		$min_length = $separator_length + 40;
		if ( $max_length < $min_length ) {
			$max_length = $min_length;
		}

		$available_length = $max_length - $separator_length;
		$start_length     = (int) ( $available_length * 0.6 );
		$end_length       = $available_length - $start_length;

		$start_content = mb_substr( $content, 0, $start_length );
		$end_content   = mb_substr( $content, -$end_length );

		return $start_content . $separator . $end_content;
	}
}