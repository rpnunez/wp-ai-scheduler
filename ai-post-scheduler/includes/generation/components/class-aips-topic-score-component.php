<?php
/**
 * Topic Score Component
 *
 * Represents the viability/relevance score (0-100) associated with an Author
 * Topic within AIPS_Topic_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Score_Component
 */
class AIPS_Topic_Score_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * @var AIPS_Author_Topics_Repository|null
	 */
	private $topics_repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null     $ai_service        Optional AI service.
	 * @param AIPS_Author_Topics_Repository|null $topics_repository Optional topics repository.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Author_Topics_Repository $topics_repository = null
	) {
		$this->ai_service        = $ai_service;
		$this->topics_repository = $topics_repository;
	}

	/**
	 * Resolve AI service lazily.
	 *
	 * @return AIPS_AI_Service_Interface
	 */
	private function get_ai_service(): AIPS_AI_Service_Interface {
		if ($this->ai_service === null) {
			$container        = AIPS_Container::get_instance();
			$this->ai_service = $container->has(AIPS_AI_Service_Interface::class)
				? $container->make(AIPS_AI_Service_Interface::class)
				: new AIPS_AI_Service();
		}

		return $this->ai_service;
	}

	/**
	 * Resolve topics repository lazily.
	 *
	 * @return AIPS_Author_Topics_Repository
	 */
	private function get_topics_repository(): AIPS_Author_Topics_Repository {
		if ($this->topics_repository === null) {
			$this->topics_repository = new AIPS_Author_Topics_Repository();
		}

		return $this->topics_repository;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'topic_score';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Topic Score', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'author_topics';
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
		return false;
	}

	/**
	 * Return the JSON schema fragment contributed by this component.
	 *
	 * @return array
	 */
	public function get_schema_fragment(): array {
		return array(
			'score' => array(
				'type' => 'integer',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$niche = (string) $context->get_topic();
		return sprintf(
			'Evaluate the topic relevance and editorial potential in the "%s" niche on an integer scale of 0 to 100. Return ONLY the integer score.',
			$niche !== '' ? $niche : 'General'
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (isset($options['raw_item']) && is_array($options['raw_item'])) {
			$raw = isset($options['raw_item']['score']) ? $options['raw_item']['score'] : 50;
			return $this->sanitize_value($raw);
		}

		$prompt = $this->build_prompt($context, $session);
		if (!empty($options['topic_title'])) {
			$prompt .= "\n\n" . sprintf('Topic title: %s', (string) $options['topic_title']);
		}

		$result = $this->get_ai_service()->generate_text($prompt, array('temperature' => 0.3));
		if (is_wp_error($result)) {
			return $result;
		}

		return $this->sanitize_value($result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$topic_id = absint($target_id);
		$prompt   = $this->build_prompt($context, $session);

		if ($topic_id > 0) {
			$existing = $this->get_topics_repository()->get_by_id($topic_id);
			if ($existing && !empty($existing->topic_title)) {
				$prompt .= "\n\n" . sprintf('Topic title: %s', (string) $existing->topic_title);
			}
		}

		$directive = trim($directive);
		if ($directive !== '') {
			$prompt .= "\n\n" . sprintf(__('Follow this directive: %s', 'ai-post-scheduler'), $directive);
		}

		$result = $this->get_ai_service()->generate_text($prompt, array('temperature' => 0.3));
		if (is_wp_error($result)) {
			return $result;
		}

		return $this->sanitize_value($result);
	}

	/**
	 * {@inheritdoc}
	 *
	 * A score is a single derived number and is not AI-editable, so it offers no variants.
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		$topic_id = is_object($target) && isset($target->id) ? absint($target->id) : absint($target);
		if ($topic_id <= 0) {
			return false;
		}

		$score = $this->sanitize_value($value);
		return (bool) $this->get_topics_repository()->update($topic_id, array('score' => $score));
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$topic_id = absint($target_id);
		if ($topic_id <= 0) {
			return false;
		}

		$topic = $this->get_topics_repository()->get_by_id($topic_id);
		return $topic && isset($topic->score) && (int) $topic->score > 0;
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
		if (is_numeric($value)) {
			return max(0, min(100, (int) round((float) $value)));
		}

		if (is_string($value) && preg_match('/\b(100|[1-9]?[0-9])\b/', $value, $matches)) {
			return max(0, min(100, (int) $matches[1]));
		}

		return 50;
	}
}
