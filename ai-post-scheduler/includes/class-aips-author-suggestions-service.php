<?php
/**
 * Author Suggestions Service
 *
 * Uses AI to generate author profile suggestions based on site/blog context inputs.
 * Admins can review suggestions and import them as ready-to-use author profiles.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Suggestions_Service
 *
 * Generates AI-powered author profile suggestions based on a site's niche,
 * target audience and content goals. Each suggestion is a complete author
 * profile (name, niche, description, details, keywords, voice_tone,
 * writing_style, target_audience, expertise_level, content_goals,
 * preferred_content_length, topic_generation_prompt) that can be imported
 * directly from the Authors UI.
 */
class AIPS_Author_Suggestions_Service {

	/**
	 * @var AIPS_AI_Service AI service for making API calls.
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger Logger instance.
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service History service for recording activity.
	 */
	private $history_service;

	/**
	 * @var AIPS_Prompt_Builder_Authors Prompt builder for author suggestions.
	 */
	private $prompt_builder;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_AI_Service|null             $ai_service      AI service instance (optional for testing).
	 * @param AIPS_Logger|null                 $logger          Logger instance (optional for testing).
	 * @param AIPS_History_Service|null        $history_service History service (optional for testing).
	 * @param AIPS_Prompt_Builder_Authors|null $prompt_builder  Prompt builder (optional for testing).
	 */
	public function __construct($ai_service = null, $logger = null, $history_service = null, $prompt_builder = null) {
		$this->ai_service      = $ai_service ?: new AIPS_AI_Service();
		$this->logger          = $logger ?: new AIPS_Logger();
		$this->history_service = $history_service ?: new AIPS_History_Service();
		$this->prompt_builder  = $prompt_builder ?: new AIPS_Prompt_Builder_Authors();
	}

	/**
	 * Generate author profile suggestions using AI.
	 *
	 * @param array $inputs {
	 *     Context inputs for suggestion generation.
	 *
	 *     @type string $site_niche      The site/blog niche or primary topic. Required.
	 *     @type string $target_audience Description of the intended readers. Optional.
	 *     @type string $content_goals   What the content aims to achieve. Optional.
	 *     @type string $site_url        URL of the site (used for context only). Optional.
	 * }
	 * @param int   $count Number of author suggestions to generate (1–10). Default 3.
	 * @return array|WP_Error Array of suggestion arrays, or WP_Error on failure.
	 */
	public function suggest_authors(array $inputs, $count = 3) {
		$count = max(1, min(10, (int) $count));

		// Fall back to site-wide content settings when a field is not provided in $inputs.
		$site_ctx = AIPS_Site_Context::get();

		$site_niche = !empty($inputs['site_niche']) ? sanitize_text_field($inputs['site_niche']) : $site_ctx['niche'];
		if (empty($site_niche)) {
			return new WP_Error('missing_niche', __('Site niche is required to generate author suggestions.', 'ai-post-scheduler'));
		}

		// Merge site defaults into inputs so the prompt builder has a complete picture.
		$merged_inputs = array_merge(
			array(
				'target_audience' => $site_ctx['target_audience'],
				'content_goals'   => $site_ctx['content_goals'],
				'brand_voice'     => $site_ctx['brand_voice'],
				'site_url'        => '',
			),
			$inputs,
			array('site_niche' => $site_niche)
		);

		// ---- History container ----
		$history = $this->history_service->create(
			'author_suggestion',
			array(
				'niche' => $site_niche,
				'count' => $count,
			)
		);

		$prompt = $this->prompt_builder->build($merged_inputs, $count);

		$history->record(
			'activity',
			sprintf('Generating %d author suggestion(s) for niche: %s', $count, $site_niche),
			array('count' => $count, 'niche' => $site_niche)
		);

		$history->record(
			'ai_request',
			'Author suggestion prompt sent to AI',
			array(
				'prompt'   => $prompt,
				'options'  => array('max_tokens' => 2000, 'temperature' => 0.8),
			)
		);

		$this->logger->log(
			"Generating {$count} author suggestion(s) for niche: {$site_niche}",
			'info',
			array('niche' => $site_niche, 'count' => $count)
		);

		$response = $this->ai_service->generate_json($prompt, array(
			'max_tokens'  => 2000,
			'temperature' => 0.8,
		));

		if (is_wp_error($response)) {
			$error_msg = $response->get_error_message();

			$history->record_error(
				'AI call failed during author suggestion generation',
				array('error_code' => $response->get_error_code()),
				$response
			);
			$history->complete_failure($error_msg);

			$this->logger->log('Author suggestions AI call failed: ' . $error_msg, 'error');
			return $response;
		}

		$history->record(
			'ai_response',
			'AI response received for author suggestions',
			null,
			$response
		);

		$suggestions = $this->parse_suggestions($response, $count);

		if (empty($suggestions)) {
			$history->record_error('No author suggestions could be parsed from the AI response.');
			$history->complete_failure('No suggestions parsed');

			$this->logger->log('No author suggestions parsed from AI response.', 'warning');
			return new WP_Error('no_suggestions_parsed', __('Could not parse author suggestions from the AI response. Please try again.', 'ai-post-scheduler'));
		}

		$suggestion_count = count($suggestions);

		$history->record(
			'activity',
			sprintf('Successfully generated %d author suggestion(s).', $suggestion_count),
			null,
			array('count' => $suggestion_count)
		);
		$history->complete_success(array('count' => $suggestion_count));

		$this->logger->log(
			'Generated ' . $suggestion_count . ' author suggestion(s).',
			'info',
			array('count' => $suggestion_count)
		);

		return $suggestions;
	}

	/**
	 * Parse AI response into validated author suggestion arrays.
	 *
	 * @param array $json_data Parsed JSON data from AI.
	 * @param int   $count     Expected maximum number of suggestions.
	 * @return array Array of validated suggestion arrays.
	 */
	private function parse_suggestions(array $json_data, $count) {
		$suggestions = array();

		foreach ($json_data as $item) {
			if (!is_array($item)) {
				continue;
			}

			$name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
			if (empty($name)) {
				continue;
			}

			$field_niche = isset($item['field_niche']) ? sanitize_text_field($item['field_niche']) : '';
			if (empty($field_niche)) {
				continue;
			}

			$expertise_level = isset($item['expertise_level']) ? sanitize_text_field($item['expertise_level']) : '';
			$preferred_content_length = isset($item['preferred_content_length']) ? sanitize_text_field($item['preferred_content_length']) : '';

			$suggestions[] = array(
				'name'                     => $name,
				'field_niche'              => $field_niche,
				'description'              => isset($item['description']) ? sanitize_textarea_field($item['description']) : '',
				'details'                  => isset($item['details']) ? sanitize_textarea_field($item['details']) : '',
				'keywords'                 => isset($item['keywords']) ? sanitize_text_field($item['keywords']) : '',
				'voice_tone'               => isset($item['voice_tone']) ? sanitize_text_field($item['voice_tone']) : '',
				'writing_style'            => isset($item['writing_style']) ? sanitize_text_field($item['writing_style']) : '',
				'target_audience'          => isset($item['target_audience']) ? sanitize_text_field($item['target_audience']) : '',
				'expertise_level'          => $this->normalize_expertise_level($expertise_level),
				'content_goals'            => isset($item['content_goals']) ? sanitize_textarea_field($item['content_goals']) : '',
				'preferred_content_length' => $this->normalize_preferred_content_length($preferred_content_length),
				'topic_generation_prompt'  => isset($item['topic_generation_prompt']) ? sanitize_textarea_field($item['topic_generation_prompt']) : '',
			);

			if (count($suggestions) >= $count) {
				break;
			}
		}

		return $suggestions;
	}

	/**
	 * Normalize AI expertise level output to allowed database/UI values.
	 *
	 * @param string $value Raw expertise level from AI response.
	 * @return string
	 */
	private function normalize_expertise_level($value) {
		$value = strtolower(trim((string) $value));

		$map = array(
			'beginner'      => 'beginner',
			'entry'         => 'beginner',
			'entry level'   => 'beginner',
			'entry-level'   => 'beginner',
			'novice'        => 'beginner',
			'intermediate'  => 'intermediate',
			'mid'           => 'intermediate',
			'mid-level'     => 'intermediate',
			'mid level'     => 'intermediate',
			'advanced'      => 'expert',
			'expert'        => 'expert',
			'thoughtleader' => 'thought_leader',
			'thought leader'=> 'thought_leader',
			'thought-leader'=> 'thought_leader',
		);

		if (isset($map[$value])) {
			return $map[$value];
		}

		return '';
	}

	/**
	 * Normalize AI preferred length output to allowed database/UI values.
	 *
	 * @param string $value Raw preferred content length from AI response.
	 * @return string
	 */
	private function normalize_preferred_content_length($value) {
		$value = strtolower(trim((string) $value));

		$map = array(
			'short'           => 'short',
			'short-form'      => 'short',
			'short form'      => 'short',
			'brief'           => 'short',
			'medium'          => 'medium',
			'medium-length'   => 'medium',
			'medium length'   => 'medium',
			'standard'        => 'medium',
			'long'            => 'long',
			'long-form'       => 'long',
			'long form'       => 'long',
			'in-depth'        => 'long',
			'indepth'         => 'long',
			'comprehensive'   => 'long',
		);

		if (isset($map[$value])) {
			return $map[$value];
		}

		return '';
	}
}
