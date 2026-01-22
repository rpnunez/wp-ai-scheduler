<?php
namespace AIPS\Helper;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Template Variable Processor
 *
 * Handles the processing of template variables (e.g., {{date}}, {{topic}}, {{site_name}})
 * in prompt templates, separating this concern from content generation.
 *
 * Also supports AI Variables - custom variables that are resolved dynamically by AI
 * during content generation.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */
class TemplateProcessor {

	/**
	 * Process template variables in a given string.
	 *
	 * Replaces placeholders like {{date}}, {{topic}}, {{site_name}} with actual values.
	 * Variables are extensible through the 'aips_template_variables' filter.
	 * Note: AI Variables are NOT processed here - use process_with_ai_variables() instead.
	 *
	 * @param string      $template The template string containing variables to replace.
	 * @param string|null $topic    Optional topic value for {{topic}} and {{title}} variables.
	 * @return string The processed template with variables replaced.
	 */
	public function process($template, $topic = null) {
		$variables = $this->get_variables($topic);
		return str_replace(array_keys($variables), array_values($variables), $template);
	}

	/**
	 * Process template with AI variable resolution.
	 *
	 * @param string      $template  The template string containing variables.
	 * @param string|null $topic     Optional topic value for {{topic}} variables.
	 * @param array       $ai_values Pre-resolved AI variable values (key => value).
	 * @return string The processed template with all variables replaced.
	 */
	public function process_with_ai_variables($template, $topic = null, $ai_values = array()) {
		if (!empty($ai_values)) {
			foreach ($ai_values as $var_name => $value) {
				if (is_string($value)) {
					$safe_value = sanitize_textarea_field($value);
				} else {
					$safe_value = sanitize_textarea_field((string) $value);
				}

				$template = str_replace('{{' . $var_name . '}}', $safe_value, $template);
			}
		}

		return $this->process($template, $topic);
	}

	/**
	 * Extract AI variables from a template string.
	 *
	 * @since 1.6.0
	 * @param string $template The template string to extract AI variables from.
	 * @return array Array of AI variable names (without braces).
	 */
	public function extract_ai_variables($template) {
		$ai_variables = array();
		$system_variables = $this->get_variable_names();

		preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

		if (!empty($matches[1])) {
			foreach ($matches[1] as $var_name) {
				$var_name = trim($var_name);
				if (!in_array($var_name, $system_variables, true)) {
					$ai_variables[] = $var_name;
				}
			}
		}

		return array_values(array_unique($ai_variables));
	}

	/**
	 * Check if a template contains AI variables.
	 *
	 * @param string $template The template string to check.
	 * @return bool True if the template contains AI variables.
	 */
	public function has_ai_variables($template) {
		return !empty($this->extract_ai_variables($template));
	}

	/**
	 * Build a prompt for AI to resolve AI variables.
	 *
	 * @param array  $ai_variables Array of AI variable names to resolve.
	 * @param string $context      The content context/prompt to help AI understand what values to generate.
	 * @return string The prompt to send to AI for variable resolution.
	 */
	public function build_ai_variables_prompt($ai_variables, $context) {
		if (empty($ai_variables)) {
			return '';
		}

		$variables_list = implode(', ', $ai_variables);

		$prompt = "Based on the following content context, provide creative and appropriate values for these variables: {$variables_list}\n\n";
		$prompt .= "Content Context:\n{$context}\n\n";
		$prompt .= "IMPORTANT: Respond ONLY with a JSON object containing the variable names as keys and their values. ";
		$prompt .= "Do not include any explanation or extra text. ";
		$prompt .= "Example format: {\"VariableName1\": \"Value1\", \"VariableName2\": \"Value2\"}\n\n";
		$prompt .= "Provide values that are specific, relevant, and would make sense in the context of the content. ";
		$prompt .= "For comparison articles, ensure the values are distinct from each other.";

		return $prompt;
	}

	/**
	 * Parse AI response for variable values.
	 *
	 * @param string $response     The AI response containing variable values.
	 * @param array  $ai_variables The expected AI variable names.
	 * @return array Associative array of variable names and their values.
	 */
	public function parse_ai_variables_response($response, $ai_variables) {
		$values = array();

		$response = trim($response);
		$response = preg_replace('/^```(?:json)?\s*/i', '', $response);
		$response = preg_replace('/\s*```$/', '', $response);
		$response = trim($response);

		$decoded = json_decode($response, true);

		if (is_array($decoded)) {
			foreach ($ai_variables as $var_name) {
				if (isset($decoded[$var_name])) {
					$raw_value = $decoded[$var_name];

					if (is_array($raw_value) || is_object($raw_value)) {
						$raw_value = wp_json_encode($raw_value);
					}

					$values[$var_name] = sanitize_text_field($raw_value);
				}
			}
		}

		return $values;
	}

	/**
	 * Get all available template variables and their values.
	 *
	 * @param string|null $topic Topic for {{topic}} and {{title}} variables.
	 * @return array Associative array of variable placeholders to values.
	 */
	public function get_variables($topic = null) {
		$variables = array(
			'{{date}}' => date('F j, Y'),
			'{{year}}' => date('Y'),
			'{{month}}' => date('F'),
			'{{day}}' => date('l'),
			'{{time}}' => current_time('H:i'),
			'{{site_name}}' => get_bloginfo('name'),
			'{{site_description}}' => get_bloginfo('description'),
			'{{random_number}}' => rand(1, 1000),
			'{{topic}}' => $topic ?: '',
			'{{title}}' => $topic ?: '',
		);

		return apply_filters('aips_template_variables', $variables, $topic);
	}

	/**
	 * Get variable names (without braces).
	 *
	 * @return array List of variable names.
	 */
	public function get_variable_names($topic = null) {
		$variables = $this->get_variables($topic);
		$names = array();

		foreach (array_keys($variables) as $key) {
			$names[] = str_replace(array('{{', '}}'), '', $key);
		}

		return $names;
	}

	/**
	 * Validate a template string for mismatched braces or invalid variables.
	 *
	 * @param string $template The template string to validate.
	 * @param bool   $allow_ai_variables Whether to allow AI variables.
	 * @return true|\WP_Error True if valid or WP_Error with details.
	 */
	public function validate_template($template, $allow_ai_variables = true) {
		$open_count = substr_count($template, '{{');
		$close_count = substr_count($template, '}}');

		if ($open_count !== $close_count) {
			return new \WP_Error(
				'unclosed_braces',
				__('Template has unclosed variable braces. Each {{ must have a matching }}.', 'ai-post-scheduler')
			);
		}

		if ($allow_ai_variables) {
			return true;
		}

		preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

		if (!empty($matches[1])) {
			$available_vars = $this->get_variable_names();

			foreach ($matches[1] as $var_name) {
				$var_name = trim($var_name);

				if (!in_array($var_name, $available_vars, true)) {
					return new \WP_Error(
						'invalid_variable',
						sprintf(
							__('Unknown variable: {{%s}}. Available variables: %s', 'ai-post-scheduler'),
							$var_name,
							implode(', ', array_map(function($v) {
								return '{{' . $v . '}}';
							}, $available_vars))
						)
					);
				}
			}
		}

		return true;
	}
}
