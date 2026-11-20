<?php
/**
 * Topic Title Component
 *
 * Represents the atomic title field of an Author Topic within
 * AIPS_Topic_Generation_Strategy. Supports structured schema contribution,
 * sanitization, persistence, reconciliation, and AI Edit regeneration.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Title_Component
 */
class AIPS_Topic_Title_Component implements AIPS_Generation_Component_Interface {

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
		return 'topic_title';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Topic Title', 'ai-post-scheduler');
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
		return true;
	}

	/**
	 * Return the JSON schema fragment contributed by this component.
	 *
	 * @return array
	 */
	public function get_schema_fragment(): array {
		return array(
			'title' => array('type' => 'string'),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$niche = (string) $context->get_topic();
		$name  = (string) $context->get_name();

		return sprintf(
			'Generate a single compelling, specific blog post topic title for author "%s" in the "%s" niche. Return only the title text without quotes.',
			$name !== '' ? $name : 'Author',
			$niche !== '' ? $niche : 'General'
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (isset($options['raw_item']) && is_array($options['raw_item'])) {
			$raw = isset($options['raw_item']['title']) ? $options['raw_item']['title'] : '';
			return $this->sanitize_value($raw);
		}

		$prompt = $this->build_prompt($context, $session);
		if (!empty($options['custom_directive'])) {
			$prompt .= "\n\n" . sprintf(__('Follow this directive: %s', 'ai-post-scheduler'), (string) $options['custom_directive']);
		}

		$result = $this->get_ai_service()->generate_text($prompt, array('temperature' => 0.7));
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
				$prompt .= "\n\n" . sprintf('Current topic title to improve: %s', (string) $existing->topic_title);
			}
		}

		$directive = trim($directive);
		if ($directive !== '') {
			$prompt .= "\n\n" . sprintf(__('Follow this directive: %s', 'ai-post-scheduler'), $directive);
		}

		$result = $this->get_ai_service()->generate_text($prompt, array('temperature' => 0.7));
		if (is_wp_error($result)) {
			return $result;
		}

		return $this->sanitize_value($result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		$count    = max(1, min(10, $count));
		$variants = array();

		for ($i = 0; $i < $count; $i++) {
			$val = $this->regenerate($target_id, $context, null, '', $options);
			if (is_wp_error($val)) {
				return empty($variants) ? $val : $variants;
			}
			$variants[] = $val;
		}

		return $variants;
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		$topic_id = is_object($target) && isset($target->id) ? absint($target->id) : absint($target);
		$title    = $this->sanitize_value($value);

		if ($topic_id <= 0 || $title === '') {
			return false;
		}

		return (bool) $this->get_topics_repository()->update($topic_id, array('topic_title' => $title));
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
		return $topic && '' !== trim((string) $topic->topic_title);
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		$clean = trim(sanitize_text_field((string) $value));
		return preg_replace('/^["\']|["\']$/', '', $clean);
	}
}
