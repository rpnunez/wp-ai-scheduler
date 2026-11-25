<?php
/**
 * Excerpt Post Component
 *
 * Owns initial post excerpt generation, combined metadata turn participation,
 * length truncation, and AI Edit excerpt regeneration.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Excerpt_Post_Component
 */
class AIPS_Excerpt_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_Prompt_Builder_Post_Excerpt|null
	 */
	private $excerpt_prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null                   $generator              Optional generator instance.
	 * @param AIPS_Prompt_Builder_Post_Excerpt|null $excerpt_prompt_builder Optional excerpt prompt builder.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_Prompt_Builder_Post_Excerpt $excerpt_prompt_builder = null
	) {
		parent::__construct($generator);
		$this->excerpt_prompt_builder = $excerpt_prompt_builder;
	}

	/**
	 * Resolve the excerpt prompt builder lazily.
	 *
	 * @return AIPS_Prompt_Builder_Post_Excerpt
	 */
	private function get_excerpt_prompt_builder(): AIPS_Prompt_Builder_Post_Excerpt {
		if ($this->excerpt_prompt_builder === null) {
			$this->excerpt_prompt_builder = new AIPS_Prompt_Builder_Post_Excerpt(new AIPS_Template_Processor());
		}

		return $this->excerpt_prompt_builder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_excerpt';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Excerpt', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_METADATA;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function participates_in_metadata_turn(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_metadata_turn_schema(): array {
		return array(
			'excerpt' => array(
				'type'        => 'string',
				'description' => 'A concise 1-2 sentence excerpt (40-60 words) summarizing the post.',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$builder = $this->get_excerpt_prompt_builder();
		$voice   = ($context->get_type() === 'template') ? $context->get_voice() : null;
		$topic   = $context->get_topic();

		if ($session !== null) {
			return $builder->build_followup($voice, $topic, $context);
		}

		return $builder->build('', '', $voice, $topic, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$generator = $this->get_generator();
		$title     = isset($options['title']) ? (string) $options['title'] : '';
		$content   = isset($options['content']) ? (string) $options['content'] : '';
		$voice     = ($context->get_type() === 'template') ? $context->get_voice() : null;
		$topic     = $context->get_topic();
		$ai_options = $this->extract_provider_options($options);

		if ($session !== null) {
			$generator->set_conversation($session);
		}

		$result = $generator->generate_excerpt_text($title, $content, $voice, $topic, $ai_options, $context);

		if ($session !== null) {
			$generator->set_conversation(null);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		return trim((string) $result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$generator = $this->get_generator();
		$post_id   = absint($target_id);
		$title     = isset($options['current_title']) ? (string) $options['current_title'] : '';
		$content   = isset($options['current_content']) ? (string) $options['current_content'] : '';

		if ($title === '' && $post_id) {
			$title = (string) get_the_title($post_id);
		}
		if ($content === '' && $post_id) {
			$content = (string) get_post_field('post_content', $post_id);
		}

		$voice      = ($context->get_type() === 'template') ? $context->get_voice() : null;
		$topic_str  = $context->get_topic();
		$ai_options = $this->merge_directive_option($this->extract_provider_options($options), $directive);

		$generator->set_conversation($session);
		$result = $generator->generate_excerpt_text($title, $content, $voice, $topic_str, $ai_options, $context);
		$generator->set_conversation(null);

		if (is_wp_error($result)) {
			return $result;
		}

		return trim((string) $result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		$ai_service = $this->resolve_variant_ai_service($options);
		if (!$ai_service->is_available()) {
			return new WP_Error('ai_service_unavailable', __('AI service is currently unavailable.', 'ai-post-scheduler'));
		}

		$source = $this->resolve_post_variant_source($target_id, $options);
		if (is_wp_error($source)) {
			return $source;
		}

		$post  = $source['post'];
		$count = max(1, min(10, $count));

		$prompt  = "You are an expert editorial writer for WordPress.\n\n";
		$prompt .= 'Post Title: "' . esc_attr($post->post_title) . "\"\n";
		$prompt .= "Post Content Sample:\n" . $source['content_sample'] . "\n\n";
		$prompt .= "Generate {$count} compelling 40-to-60-word excerpt variations summarizing this post.\n";
		$prompt .= "Return ONLY a valid JSON array of {$count} strings: [\"Excerpt 1\", \"Excerpt 2\", \"Excerpt 3\"]. No extra text.";

		$response = $ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		return $this->parse_json_variants_response((string) $response, $count);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0) {
			return false;
		}

		$excerpt = $this->sanitize_value($value);
		$updated = wp_update_post(array(
			'ID'           => $post_id,
			'post_excerpt' => $excerpt,
		), true);

		return !is_wp_error($updated) && (int) $updated === $post_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$post_id = absint($target_id);
		if ($post_id <= 0) {
			return false;
		}

		$excerpt = (string) get_post_field('post_excerpt', $post_id);
		return '' !== trim(wp_strip_all_tags($excerpt));
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return AIPS_Generator::truncate_excerpt(sanitize_textarea_field((string) $value));
	}
}
