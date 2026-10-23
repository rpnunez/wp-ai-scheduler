<?php
/**
 * Catalog Enrichment Prompt Builder
 *
 * Assembles specialized, conversion-focused AI prompts for WooCommerce product
 * catalog enrichment (short descriptions, benefit bullets, full descriptions, SEO copy).
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Prompt_Builder_Catalog_Enrichment {

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Template_Processor|null $template_processor
	 */
	public function __construct( $template_processor = null ) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
	}

	/**
	 * Build system prompt for e-commerce copywriting.
	 *
	 * @param string $tone Tone and voice instructions.
	 * @return string
	 */
	public function build_system_prompt( $tone = 'persuasive and benefits-focused' ) {
		$system_prompt = "You are an expert e-commerce copywriter and conversion rate optimization specialist. Write compelling, concise, and benefit-driven e-commerce copy that drives purchases while maintaining accuracy.";
		return apply_filters( 'aips_catalog_enrichment_system_prompt', $system_prompt, $tone );
	}

	/**
	 * Build user prompt for product copy generation.
	 *
	 * @param array  $product      Normalized product payload.
	 * @param string $target_field Target field: 'short_description', 'full_description', or meta field.
	 * @param string $tone         Desired tone.
	 * @return string
	 */
	public function build_user_prompt( $product, $target_field = 'short_description', $tone = 'persuasive and benefits-focused' ) {
		$title = isset( $product['title'] ) ? $product['title'] : '';

		// Format product attributes into a readable specification summary
		$attr_summary = '';
		if ( ! empty( $product['attributes'] ) && is_array( $product['attributes'] ) ) {
			foreach ( $product['attributes'] as $name => $val ) {
				$attr_summary .= "- {$name}: {$val}\n";
			}
		}

		$user_prompt = "Product Title: {$title}\n";
		if ( ! empty( $attr_summary ) ) {
			$user_prompt .= "Specifications:\n{$attr_summary}\n";
		}

		if ( 'short_description' === $target_field ) {
			$user_prompt .= "Task: Write a high-converting short product description (2 to 3 sentences, plus 3 to 4 bullet points highlighting key benefits) for the above-the-fold buy section. Tone: {$tone}.";
		} elseif ( 'full_description' === $target_field ) {
			$user_prompt .= "Task: Write an engaging, detailed product description formatted in clean HTML paragraphs and bullet points. Highlight problems this solves, key features, and ideal use cases. Tone: {$tone}.";
		} else {
			$user_prompt .= "Task: Write high-converting product copy for {$target_field}. Tone: {$tone}.";
		}

		return apply_filters( 'aips_catalog_enrichment_user_prompt', $user_prompt, $product, $target_field, $tone );
	}

	/**
	 * Build complete prompt pair (system and user prompt).
	 *
	 * @param array  $product
	 * @param string $target_field
	 * @param string $tone
	 * @return array{system_prompt: string, user_prompt: string}
	 */
	public function build( $product, $target_field = 'short_description', $tone = 'persuasive and benefits-focused' ) {
		return array(
			'system_prompt' => $this->build_system_prompt( $tone ),
			'user_prompt'   => $this->build_user_prompt( $product, $target_field, $tone ),
		);
	}
}
