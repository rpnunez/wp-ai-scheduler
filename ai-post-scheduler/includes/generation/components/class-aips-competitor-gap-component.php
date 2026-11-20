<?php
/**
 * Competitor & Semantic Gap Component
 *
 * Identifies semantic content gaps and missing internal-link bridge topics
 * for a given post/cluster within AIPS_Research_Generation_Strategy (migrating
 * AIPS_Similarity_Evaluator::generate_gap_suggestions()).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Competitor_Gap_Component
 */
class AIPS_Competitor_Gap_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'competitor_gap';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Content Gap Suggestions', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'research';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		if ($context instanceof AIPS_Research_Generation_Context) {
			return $context->get_mode() === AIPS_Research_Generation_Context::MODE_GAP_SUGGESTIONS;
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$payload      = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_topic_payload() : array();
		$post_title   = isset($payload['post_title']) ? (string) $payload['post_title'] : '';
		$post_excerpt = isset($payload['post_excerpt']) ? (string) $payload['post_excerpt'] : '';
		$cluster_name = isset($payload['cluster_name']) ? (string) $payload['cluster_name'] : '';

		$prompt  = "You are an expert SEO editorial strategist.\n";
		$prompt .= "Analyze this article to identify content gaps and missing bridge topics:\n";
		$prompt .= "Article Title: \"{$post_title}\"\n";
		$prompt .= "Excerpt / Summary: \"{$post_excerpt}\"\n";
		if ($cluster_name !== '') {
			$prompt .= "Post Cluster Theme: \"{$cluster_name}\"\n";
		}
		$prompt .= "\nGenerate 3 to 5 high-value complementary article topic suggestions that would:\n";
		$prompt .= "1. Form strong internal link bridge connections to this article.\n";
		$prompt .= "2. Cover subtopics, FAQs, or implementation details that are currently missing.\n";
		$prompt .= "3. Strengthen topical authority.\n\n";
		$prompt .= 'Format your response as a valid JSON array of objects with keys: "title", "rationale", "brief". Do not include any other commentary or markdown formatting.';

		return $prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$payload    = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_topic_payload() : array();
		$post_title = isset($payload['post_title']) ? (string) $payload['post_title'] : '';
		$prompt     = $this->build_prompt($context, $session);

		$response = $this->ai_service->generate_text($prompt, array(
			'temperature' => 0.7,
			'max_tokens'  => 1200,
		));

		if (is_wp_error($response)) {
			return $response;
		}

		$raw = trim((string) $response);
		$raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
		$raw = preg_replace('/\s*```$/i', '', $raw);

		$parsed = json_decode((string) $raw, true);
		if (!is_array($parsed) || empty($parsed)) {
			$parsed = array(
				array(
					'title'     => sprintf(__('Complete Guide to %s: Best Practices & Strategies', 'ai-post-scheduler'), $post_title),
					'rationale' => __('Comprehensive foundational coverage that bridges technical concepts.', 'ai-post-scheduler'),
					'brief'     => __('Explore key principles, architectural patterns, and actionable takeaways.', 'ai-post-scheduler'),
				),
				array(
					'title'     => sprintf(__('Common Pitfalls When Implementing %s', 'ai-post-scheduler'), $post_title),
					'rationale' => __('Targets high-intent troubleshooting queries and provides natural internal link opportunities.', 'ai-post-scheduler'),
					'brief'     => __('Detail failure modes, prevention checklists, and resolution workflows.', 'ai-post-scheduler'),
				),
				array(
					'title'     => sprintf(__('Advanced Applications & Case Studies of %s', 'ai-post-scheduler'), $post_title),
					'rationale' => __('Deepens topical depth for advanced readers and builds authority.', 'ai-post-scheduler'),
					'brief'     => __('Real-world case studies, benchmark data, and specialized implementations.', 'ai-post-scheduler'),
				),
			);
		}

		return $parsed;
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
		return is_array($value) ? $value : array();
	}
}
