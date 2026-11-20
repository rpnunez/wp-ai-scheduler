<?php
/**
 * Author Persona Component
 *
 * Owns structured JSON schema definition, prompt building, AI generation,
 * parsing, and enum normalization for author persona suggestions within
 * AIPS_Author_Persona_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Persona_Component
 */
class AIPS_Author_Persona_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Prompt_Builder_Authors
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null   $ai_service     Optional AI service.
	 * @param AIPS_Prompt_Builder_Authors|null $prompt_builder Optional authors prompt builder.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		$prompt_builder = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->ai_service     = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Authors();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'author_persona';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Author Persona', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'author_personas';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * JSON schema for the author suggestion array returned by the AI.
	 *
	 * @return array<string, mixed>
	 */
	public function get_json_schema(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'name'                     => array('type' => 'string'),
					'field_niche'              => array('type' => 'string'),
					'description'              => array('type' => 'string'),
					'details'                  => array('type' => 'string'),
					'keywords'                 => array('type' => 'string'),
					'voice_tone'               => array('type' => 'string'),
					'writing_style'            => array('type' => 'string'),
					'target_audience'          => array('type' => 'string'),
					'expertise_level'          => array('type' => 'string', 'enum' => array('beginner', 'intermediate', 'expert', 'thought_leader')),
					'content_goals'            => array('type' => 'string'),
					'preferred_content_length' => array('type' => 'string', 'enum' => array('short', 'medium', 'long')),
					'topic_generation_prompt'  => array('type' => 'string'),
				),
				'required' => array(
					'name',
					'field_niche',
					'description',
					'details',
					'keywords',
					'voice_tone',
					'writing_style',
					'target_audience',
					'expertise_level',
					'content_goals',
					'preferred_content_length',
					'topic_generation_prompt',
				),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$inputs = ($context instanceof AIPS_Author_Persona_Generation_Context) ? $context->get_inputs() : array();
		$count  = ($context instanceof AIPS_Author_Persona_Generation_Context) ? $context->get_count() : 3;

		return $this->prompt_builder->build($inputs, $count);
	}

	/**
	 * Build prompt using pre-merged site/user inputs and count.
	 *
	 * @param array $merged_inputs Merged inputs array.
	 * @param int   $count         Requested suggestion count.
	 * @return string
	 */
	public function build_prompt_from_inputs(array $merged_inputs, int $count): string {
		return $this->prompt_builder->build($merged_inputs, $count);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$prompt = isset($options['prompt']) ? (string) $options['prompt'] : $this->build_prompt($context, $session);

		return $this->ai_service->generate_json($prompt, array(
			'temperature' => 0.8,
			'json_schema' => $this->get_json_schema(),
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$prompt    = isset($options['prompt']) ? (string) $options['prompt'] : $this->build_prompt($context, $session);
		$directive = trim($directive);
		if ($directive !== '') {
			$prompt .= "\n\n" . sprintf(__('Follow this directive: %s', 'ai-post-scheduler'), $directive);
		}

		$options['prompt'] = $prompt;
		return $this->generate($context, $session, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return $this->generate($context, null, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		return is_array($value) && !empty($value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		if (!is_array($value)) {
			return array();
		}

		return $this->parse_suggestions($value, count($value));
	}

	/**
	 * Parse AI response into validated author suggestion arrays.
	 *
	 * @param array $json_data Parsed JSON data from AI.
	 * @param int   $count     Expected maximum number of suggestions.
	 * @return array Array of validated suggestion arrays.
	 */
	public function parse_suggestions(array $json_data, int $count): array {
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

			$expertise_level          = isset($item['expertise_level']) ? sanitize_text_field($item['expertise_level']) : '';
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
	public function normalize_expertise_level($value): string {
		$value = strtolower(trim((string) $value));

		$map = array(
			'beginner'       => 'beginner',
			'entry'          => 'beginner',
			'entry level'    => 'beginner',
			'entry-level'    => 'beginner',
			'novice'         => 'beginner',
			'intermediate'   => 'intermediate',
			'mid'            => 'intermediate',
			'mid-level'      => 'intermediate',
			'mid level'      => 'intermediate',
			'advanced'       => 'expert',
			'expert'         => 'expert',
			'thoughtleader'  => 'thought_leader',
			'thought leader' => 'thought_leader',
			'thought-leader' => 'thought_leader',
		);

		return isset($map[$value]) ? $map[$value] : '';
	}

	/**
	 * Normalize AI preferred length output to allowed database/UI values.
	 *
	 * @param string $value Raw preferred content length from AI response.
	 * @return string
	 */
	public function normalize_preferred_content_length($value): string {
		$value = strtolower(trim((string) $value));

		$map = array(
			'short'         => 'short',
			'short-form'    => 'short',
			'short form'    => 'short',
			'brief'         => 'short',
			'medium'        => 'medium',
			'medium-length' => 'medium',
			'medium length' => 'medium',
			'standard'      => 'medium',
			'long'          => 'long',
			'long-form'     => 'long',
			'long form'     => 'long',
			'in-depth'      => 'long',
			'indepth'       => 'long',
			'comprehensive' => 'long',
		);

		return isset($map[$value]) ? $map[$value] : '';
	}
}
