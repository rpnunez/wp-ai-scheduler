<?php
/**
 * Content Post Component
 *
 * Owns initial article body generation, WordPress block/HTML normalization,
 * leading title block stripping, and AI Edit content regeneration (including
 * restoration of existing generated image markup and unresolved placeholder
 * cleanup).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Post_Component
 */
class AIPS_Content_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_Prompt_Builder_Post_Content|null
	 */
	private $content_prompt_builder;

	/**
	 * @var AIPS_Prompt_Builder|null
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null                   $generator              Optional generator instance.
	 * @param AIPS_Prompt_Builder_Post_Content|null $content_prompt_builder Optional content prompt builder.
	 * @param AIPS_Prompt_Builder|null              $prompt_builder         Optional main prompt builder.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_Prompt_Builder_Post_Content $content_prompt_builder = null,
		?AIPS_Prompt_Builder $prompt_builder = null
	) {
		parent::__construct($generator);
		$this->content_prompt_builder = $content_prompt_builder;
		$this->prompt_builder         = $prompt_builder;
	}

	/**
	 * Resolve the content prompt builder lazily.
	 *
	 * @return AIPS_Prompt_Builder_Post_Content
	 */
	private function get_content_prompt_builder(): AIPS_Prompt_Builder_Post_Content {
		if ($this->content_prompt_builder === null) {
			$template_processor = new AIPS_Template_Processor();
			$structure_manager  = new AIPS_Article_Structure_Manager();
			$this->content_prompt_builder = new AIPS_Prompt_Builder_Post_Content(
				$template_processor,
				new AIPS_Prompt_Builder_Article_Structure_Section($structure_manager, null, $template_processor)
			);
		}

		return $this->content_prompt_builder;
	}

	/**
	 * Resolve the main prompt builder lazily.
	 *
	 * @return AIPS_Prompt_Builder
	 */
	private function get_prompt_builder(): AIPS_Prompt_Builder {
		if ($this->prompt_builder === null) {
			$this->prompt_builder = new AIPS_Prompt_Builder(
				new AIPS_Template_Processor(),
				new AIPS_Article_Structure_Manager()
			);
		}

		return $this->prompt_builder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_content';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Content', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_CONTENT;
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
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return $this->get_content_prompt_builder()->build($context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$generator = $this->get_generator();
		$builder   = $this->get_content_prompt_builder();
		$prompt    = $builder->build($context);

		$ai_options = $this->extract_provider_options($options);
		if (!empty($ai_options['custom_directive'])) {
			$prompt .= "\n\n" . sprintf(__('Follow this directive for the content: %s', 'ai-post-scheduler'), $ai_options['custom_directive']);
			unset($ai_options['custom_directive']);
		}

		$content_context = $this->get_prompt_builder()->build_content_context($context);
		if (!empty($content_context) && empty($ai_options['context'])) {
			$ai_options['context'] = $content_context;
		}

		$ai_options['_aips_sources_selection'] = $builder->get_sources_selection();
		$log_type = !empty($options['log_type']) ? (string) $options['log_type'] : 'content';

		$raw = $generator->generate_content($prompt, $ai_options, $log_type);
		if (is_wp_error($raw)) {
			return $raw;
		}

		if (empty($options['skip_normalization'])) {
			$raw = $generator->normalize_generated_content_for_wordpress($raw);
		}

		return $raw;
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$generator = $this->get_generator();
		$post_id   = absint($target_id);
		$history_id = isset($options['history_id']) ? absint($options['history_id']) : 0;

		// Deliberately not conversational: the component being regenerated IS the
		// article, so replaying the previous body would bias the model toward
		// reproducing it rather than writing a fresh one.
		$generator->set_conversation(null);

		$builder = $this->get_content_prompt_builder();
		$prompt  = $builder->build($context);

		$directive = trim($directive);
		if ($directive !== '') {
			$prompt .= "\n\n" . sprintf(__('Follow this directive for the content: %s', 'ai-post-scheduler'), $directive);
		}

		$ai_options = $this->extract_provider_options($options);
		unset($ai_options['custom_directive']);
		$ai_options['_aips_sources_selection'] = $builder->get_sources_selection();

		$result = $generator->generate_content($prompt, $ai_options);
		if (is_wp_error($result)) {
			return $result;
		}

		if ($post_id && $context->has_content_images()) {
			$generated_repo = new AIPS_Post_Images_Generated_Repository();
			$existing_rows  = $generated_repo->get_for_post_history($post_id, $history_id);
			if (!empty($existing_rows)) {
				$total_images = count($existing_rows);
				$title        = isset($options['current_title']) ? (string) $options['current_title'] : get_the_title($post_id);
				$images_comp  = new AIPS_Content_Images_Post_Component($generator);

				foreach ($existing_rows as $target_row) {
					$gen_id        = (int) $target_row->id;
					$attachment_id = (int) $target_row->attachment_id;
					$url           = !empty($target_row->image_url) ? $target_row->image_url : ($attachment_id ? (string) wp_get_attachment_url($attachment_id) : '');
					$config        = $images_comp->resolve_post_image_config($target_row, $context);
					$caption       = !empty($config->caption) ? $config->caption : '';

					$markup = ($attachment_id > 0 && !empty($url))
						? $generator->build_post_image_markup($gen_id, $attachment_id, $url, $title, $caption)
						: sprintf('<!-- aips-image-placeholder id="%d" -->', $gen_id);

					$result = $images_comp->replace_post_image_markup($result, $target_row, $markup, $total_images);
				}
			}
		}

		// Strip any placeholder shortcodes (e.g. [post_image index="N"]) that were not matched by configured images.
		if (empty($options['preserve_placeholders'])) {
			$result = AIPS_Post_Images_Service::strip_unresolved_placeholders($result);
		}

		return $result;
	}

	/**
	 * Rewrite an arbitrary content snippet with an editorial directive,
	 * producing 1 or 3 candidate variants.
	 *
	 * Used by AIPS_Editor_Directive_Service::apply_directive() so all editor
	 * content rewrites flow through the unified Content Post Component.
	 *
	 * @param string $content        Original text/HTML snippet.
	 * @param string $instruction    Resolved editorial directive instruction.
	 * @param int    $variants_count Number of variants to generate (1 or 3).
	 * @param array  $options        Optional overrides ('ai_service', 'post_title').
	 * @return string[]|WP_Error
	 */
	public function rewrite_with_directive(string $content, string $instruction, int $variants_count = 1, array $options = array()) {
		$ai_service = $this->resolve_variant_ai_service($options);
		if (!$ai_service->is_available()) {
			return new WP_Error('ai_service_unavailable', __('AI service is currently unavailable. Please check your AI Engine configuration.', 'ai-post-scheduler'));
		}

		$content = trim($content);
		if ('' === $content) {
			return new WP_Error('empty_content', __('No content provided for rewriting.', 'ai-post-scheduler'));
		}

		$prompt  = "You are an expert editorial assistant for WordPress.\n\n";
		$prompt .= "Instruction:\n" . $instruction . "\n\n";

		if (!empty($options['post_title'])) {
			$prompt .= 'Article Context: Post Title is "' . esc_attr((string) $options['post_title']) . "\".\n\n";
		}

		$prompt .= "Original Text to Rewrite:\n\"\"\"\n" . $content . "\n\"\"\"\n\n";

		if ($variants_count >= 3) {
			$prompt .= "Provide exactly 3 distinctly different creative variations of the rewritten text.\n";
			$prompt .= "Output MUST be a valid JSON array of 3 strings, with no additional text or formatting outside the JSON array.\n";
			$prompt .= "Example format:\n[\"Variation 1 text here\", \"Variation 2 text here\", \"Variation 3 text here\"]";

			$response = $ai_service->generate_text($prompt);
			if (is_wp_error($response)) {
				return $response;
			}

			$raw = trim((string) $response);
			if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
				$decoded = json_decode($matches[0], true);
				if (is_array($decoded) && count($decoded) > 0) {
					return array_slice(array_map('strval', array_map('trim', $decoded)), 0, 3);
				}
			}

			$lines = array_values(array_filter(array_map('trim', explode("\n", $raw))));
			if (count($lines) >= 3) {
				return array_slice($lines, 0, 3);
			}

			return array($raw);
		}

		$prompt  .= 'Return ONLY the rewritten text without conversational introductions, explanations, or quotes around the output.';
		$response = $ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		$cleaned = trim((string) $response);
		$cleaned = preg_replace('/^["\']|["\']$/u', '', $cleaned);

		return array($cleaned);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0 || !is_string($value)) {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => wp_slash($this->sanitize_value($value)),
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

		$content = (string) get_post_field('post_content', $post_id);
		return '' !== trim(wp_strip_all_tags($content));
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return is_string($value) ? wp_kses_post($value) : '';
	}
}
