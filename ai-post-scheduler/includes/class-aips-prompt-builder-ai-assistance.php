<?php
/**
 * AI Assistance Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_AI_Assistance {

	public function build(array $field_config) {
		$lines = array(
			'You are a helpful assistant for an AI content creation WordPress plugin.',
			'Help fill in a form field for an AI author persona.',
			'',
			'Field: ' . ( $field_config['field_name'] ?? '' ),
			'Purpose: ' . ( $field_config['description'] ?? '' ),
			'How it influences AI content generation: ' . ( $field_config['influence'] ?? '' ),
			'Current value: ' . ( $field_config['current_value'] ?? '' ),
		);

		if (!empty($field_config['author_name'])) {
			$lines[] = 'Author Name: ' . $field_config['author_name'];
		}

		if (!empty($field_config['field_niche'])) {
			$lines[] = 'Author Niche: ' . $field_config['field_niche'];
		}

		$lines[] = '';
		$lines[] = 'Respond with ONLY the suggested value for this field. No explanation, no quotes, no prefix. Expected format: ' . ( $field_config['expected_response'] ?? '' );

		return implode("\n", $lines);
	}
}
