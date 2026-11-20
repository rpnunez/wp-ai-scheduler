<?php
/**
 * Taxonomy Suggestion Component
 *
 * Owns AI taxonomy term suggestion generation and line-item parsing for
 * AIPS_Taxonomy_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Taxonomy_Suggestion_Component
 */
class AIPS_Taxonomy_Suggestion_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Prompt_Builder_Taxonomy
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null    $ai_service     Optional AI service.
	 * @param AIPS_Prompt_Builder_Taxonomy|null $prompt_builder Optional taxonomy prompt builder.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		$prompt_builder = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->ai_service     = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Taxonomy();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'taxonomy_suggestions';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Taxonomy Suggestions', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'taxonomy';
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
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$taxonomy_type     = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_taxonomy_type() : 'category';
		$generation_prompt = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_generation_prompt() : '';
		$post_contents     = $this->collect_post_contents($context);

		return $this->prompt_builder->build($taxonomy_type, $post_contents, $generation_prompt);
	}

	/**
	 * Collect post title/excerpt summaries for the context's post IDs.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return array<int, array{title:string,excerpt:string}>
	 */
	public function collect_post_contents(AIPS_Generation_Context $context): array {
		$post_ids = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_post_ids() : array();
		if (!empty($post_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches($post_ids, false, true);
		}

		$post_contents = array();
		foreach ($post_ids as $post_id) {
			$post = get_post($post_id);
			if ($post) {
				$post_contents[] = array(
					'title'   => $post->post_title,
					'excerpt' => $post->post_excerpt,
				);
			}
		}

		return $post_contents;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (!$this->ai_service->is_available()) {
			return new WP_Error('ai_not_available', __('AI Engine plugin is not installed or active.', 'ai-post-scheduler'));
		}

		$post_contents = $this->collect_post_contents($context);
		if (empty($post_contents)) {
			return new WP_Error('taxonomy_no_posts', __('No valid posts were found for taxonomy generation.', 'ai-post-scheduler'));
		}

		$taxonomy_type     = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_taxonomy_type() : 'category';
		$generation_prompt = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_generation_prompt() : '';
		$prompt            = $this->prompt_builder->build($taxonomy_type, $post_contents, $generation_prompt);

		try {
			$response = $this->ai_service->generate_text($prompt);
			if (is_wp_error($response)) {
				return $response;
			}

			return $this->sanitize_value($response);
		} catch (Throwable $e) {
			return new WP_Error(
				'ai_generation_failed',
				__('AI generation failed. Please try again.', 'ai-post-scheduler'),
				array('exception' => $e->getMessage())
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
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
		return is_array($value);
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
		$raw_lines = is_array($value) ? $value : explode("\n", (string) $value);
		$names     = array();

		foreach ($raw_lines as $line) {
			$line = trim((string) $line);
			$line = preg_replace('/^[\d\-\*\.\)]+\s*/', '', $line);
			$line = sanitize_text_field(trim((string) $line));
			if ($line !== '') {
				$names[] = $line;
			}
		}

		return $names;
	}
}
