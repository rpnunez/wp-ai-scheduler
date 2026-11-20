<?php
/**
 * Slug Post Component
 *
 * Owns post URL slug generation, combined metadata turn participation,
 * sanitize_title fallback telemetry, and AI Edit slug regeneration.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Slug_Post_Component
 */
class AIPS_Slug_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_Prompt_Builder_Post_Slug|null
	 */
	private $slug_prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null                $generator           Optional generator instance.
	 * @param AIPS_Prompt_Builder_Post_Slug|null $slug_prompt_builder Optional slug prompt builder.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_Prompt_Builder_Post_Slug $slug_prompt_builder = null
	) {
		parent::__construct($generator);
		$this->slug_prompt_builder = $slug_prompt_builder;
	}

	/**
	 * Resolve the slug prompt builder lazily.
	 *
	 * @return AIPS_Prompt_Builder_Post_Slug
	 */
	private function get_slug_prompt_builder(): AIPS_Prompt_Builder_Post_Slug {
		if ($this->slug_prompt_builder === null) {
			$this->slug_prompt_builder = new AIPS_Prompt_Builder_Post_Slug(new AIPS_Template_Processor());
		}

		return $this->slug_prompt_builder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_slug';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Slug', 'ai-post-scheduler');
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
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return (bool) $context->should_generate_slug();
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		// Optional: a failed slug falls back to sanitize_title($title) without forcing draft downgrade.
		return false;
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
			'slug' => array(
				'type'        => 'string',
				'description' => 'SEO-friendly URL slug (lowercase alphanumeric and hyphens only).',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$builder = $this->get_slug_prompt_builder();
		if ($session !== null) {
			return $builder->build_followup($context);
		}

		return $builder->build($context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$generator  = $this->get_generator();
		$content    = isset($options['content']) ? (string) $options['content'] : '';
		$title      = isset($options['title']) ? (string) $options['title'] : '';
		$ai_options = $this->extract_provider_options($options);

		if ($session !== null) {
			$generator->set_conversation($session);
		}

		$success = false;
		$result  = $generator->generate_slug_from_context($context, $content, $title, $ai_options, $success);

		if ($session !== null) {
			$generator->set_conversation(null);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		return $this->sanitize_value($result);
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

		$ai_options = $this->merge_directive_option($this->extract_provider_options($options), $directive);

		// Explicit AI Edit calls regenerate even when the template or author has
		// generate_slug turned off.
		$success = false;
		if ($session !== null) {
			$generator->set_conversation($session);
			$result = $generator->generate_slug_from_context($context, '', $title, $ai_options, $success);
			$generator->set_conversation(null);
		} else {
			$result = $generator->generate_slug_from_context($context, $content, $title, $ai_options, $success);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		$slug = trim((string) $result);
		if ($slug === '') {
			return new WP_Error('slug_generation_failed', __('Failed to generate a valid slug.', 'ai-post-scheduler'));
		}

		return $slug;
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

		$prompt  = "You are an expert SEO editor for WordPress permalinks.\n\n";
		$prompt .= 'Current Post Title: "' . esc_attr($post->post_title) . "\"\n";
		$prompt .= 'Current Post Slug: "' . esc_attr($post->post_name) . "\"\n";
		$prompt .= "Post Content Sample:\n" . $source['content_sample'] . "\n\n";
		$prompt .= "Generate {$count} SEO URL slug variations for this post.\n";
		$prompt .= "Each slug must be 3 to 6 words, lowercase, with words separated by hyphens, and must omit stop words.\n";
		$prompt .= "Return ONLY a valid JSON array of {$count} strings: [\"slug-one\", \"slug-two\", \"slug-three\"]. No extra text.";

		$response = $ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		$variants = $this->parse_json_variants_response((string) $response, $count);
		$variants = array_values(array_filter(array_map('sanitize_title', $variants)));
		if (empty($variants)) {
			return new WP_Error('slug_generation_failed', __('Failed to generate a valid slug.', 'ai-post-scheduler'));
		}

		return $variants;
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0) {
			return false;
		}

		$slug = $this->sanitize_value($value);
		if ($slug === '') {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'        => $post_id,
			'post_name' => $slug,
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

		$post = get_post($post_id);
		return $post && '' !== trim((string) $post->post_name);
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		$slug = trim((string) $value);
		$slug = preg_replace('/^["\']|["\']$/', '', $slug);
		return sanitize_title($slug);
	}
}
