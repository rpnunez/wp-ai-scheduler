<?php
/**
 * AI Variables Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_AI_Variables {

	public function build($ai_variables, $context) {
		if (empty($ai_variables)) {
			return '';
		}

		$variables_list = implode(', ', $ai_variables);

		$prompt  = "Based on the following content context, provide creative and appropriate values for these variables: {$variables_list}\n\n";
		$prompt .= "Content Context:\n{$context}\n\n";
		$prompt .= 'IMPORTANT: Respond ONLY with a JSON object containing the variable names as keys and their values. ';
		$prompt .= 'Do not include any explanation or extra text. ';
		$prompt .= "Example format: {\"VariableName1\": \"Value1\", \"VariableName2\": \"Value2\"}\n\n";
		$prompt .= 'Provide values that are specific, relevant, and would make sense in the context of the content. ';
		$prompt .= 'For comparison articles, ensure the values are distinct from each other.';

		return $prompt;
	}
}
