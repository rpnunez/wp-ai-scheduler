<?php
/**
 * Integration Field Prompt Builder
 *
 * Assembles the AI prompt used to generate third-party plugin field values
 * (e.g. ACF fields). Mirrors the shape of AIPS_Prompt_Builder_Post_Content:
 * process template variables, then let plugins adjust the result via a
 * filter. Supports both a single-field prompt (used as a fallback when a
 * batch call fails) and a batched prompt covering every mapped field in one
 * call, so generating N fields costs one AI round-trip instead of N.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integration_Field_Prompt_Builder {

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * @param AIPS_Template_Processor|null $template_processor Optional template processor.
	 */
	public function __construct($template_processor = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
	}

	/**
	 * Build the prompt for a single field.
	 *
	 * Used as the per-field fallback when a batched generate_json() call
	 * fails (see AIPS_Integration_Manager::apply_generatable_batch()).
	 *
	 * @param array                    $field_def     Field definition as returned by AIPS_Integration_Interface::get_fields().
	 * @param AIPS_Generation_Context  $context       Generation context driving this post.
	 * @param string                   $custom_prompt Optional per-field custom prompt (may contain template variables).
	 * @return string
	 */
	public function build($field_def, $context, $custom_prompt = '') {
		do_action('aips_before_build_integration_field_prompt', $field_def, $context);

		$topic = $context instanceof AIPS_Generation_Context ? $context->get_topic() : null;
		$label = isset($field_def['label']) ? $field_def['label'] : '';
		$instruction = $this->resolve_field_instruction($field_def, $topic, $custom_prompt);

		$prompt = sprintf(
			/* translators: 1: field label, 2: generation instruction. */
			__("Field: %1\$s\n%2\$s", 'ai-post-scheduler'),
			$label,
			$instruction
		);

		return apply_filters('aips_integration_field_prompt', $prompt, $field_def, $context);
	}

	/**
	 * Build a single prompt covering every field in the batch, instructing
	 * the AI to return one JSON object keyed by field key.
	 *
	 * @param array<int, array{mapping: object, field_def: array}> $items List of {mapping, field_def} pairs
	 *                                                                     (mapping carries field_key/custom_prompt; field_def carries label/instructions).
	 * @param AIPS_Generation_Context                               $context Generation context driving this post.
	 * @return string
	 */
	public function build_batch($items, $context) {
		do_action('aips_before_build_integration_batch_prompt', $items, $context);

		$topic = $context instanceof AIPS_Generation_Context ? $context->get_topic() : null;

		$field_blocks = array();
		$example_pairs = array();

		foreach ($items as $item) {
			$mapping = $item['mapping'];
			$field_def = $item['field_def'];
			$label = isset($field_def['label']) ? $field_def['label'] : '';
			$instruction = $this->resolve_field_instruction($field_def, $topic, isset($mapping->custom_prompt) ? $mapping->custom_prompt : '');

			$field_blocks[] = sprintf(
				/* translators: 1: field key, 2: field label, 3: generation instruction. */
				__("Field key: %1\$s\nLabel: %2\$s\nInstructions: %3\$s", 'ai-post-scheduler'),
				$mapping->field_key,
				$label,
				$instruction
			);

			$example_pairs[] = sprintf('  "%s": "..."', $mapping->field_key);
		}

		$prompt = __("Generate content for each of the following fields:\n\n", 'ai-post-scheduler');
		$prompt .= implode("\n\n", $field_blocks);
		$prompt .= "\n\n";
		$prompt .= __("Respond with a single JSON object. Each key must be exactly one of the field keys given above (not the label), and each value must be the generated content for that field as a plain string — no nested objects or markdown formatting.\n\n", 'ai-post-scheduler');
		$prompt .= __("Example format:\n", 'ai-post-scheduler');
		$prompt .= "{\n" . implode(",\n", $example_pairs) . "\n}";

		return apply_filters('aips_integration_batch_prompt', $prompt, $items, $context);
	}

	/**
	 * Resolve the generation instruction for one field: an explicit
	 * per-field custom prompt (saved on the mapping row) wins; falls back to
	 * the field's own "instructions" text (e.g. ACF's field help text) when
	 * no custom prompt was set, and finally to a generic instruction built
	 * from the field label so every mapped field always gets a usable prompt.
	 *
	 * @param array       $field_def     Field definition (must include 'label', may include 'instructions').
	 * @param string|null $topic         Topic string for template-variable processing.
	 * @param string      $custom_prompt Optional per-field custom prompt (may contain template variables).
	 * @return string
	 */
	private function resolve_field_instruction($field_def, $topic, $custom_prompt = '') {
		$label = isset($field_def['label']) ? $field_def['label'] : '';
		$instructions = isset($field_def['instructions']) ? $field_def['instructions'] : '';

		if (!empty($custom_prompt)) {
			return $this->template_processor->process($custom_prompt, $topic);
		}

		if (!empty($instructions)) {
			return $instructions;
		}

		return sprintf(
			/* translators: %s: field label. */
			__('Write the content for the "%s" field.', 'ai-post-scheduler'),
			$label
		);
	}
}
