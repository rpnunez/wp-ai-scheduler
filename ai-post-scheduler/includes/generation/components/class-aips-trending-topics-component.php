<?php
/**
 * Trending Topics Research Component
 *
 * Owns niche trending topic discovery and source-grounded topic research,
 * including prompt construction, structured JSON schema, validation,
 * normalization, and score-based ranking.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Trending_Topics_Component
 */
class AIPS_Trending_Topics_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service.
	 * @param AIPS_Logger_Interface|null     $logger     Optional logger.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$this->ai_service = $ai_service;
		$this->logger     = $logger;
	}

	/**
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
	 * @return AIPS_Logger_Interface
	 */
	private function get_logger(): AIPS_Logger_Interface {
		if ($this->logger === null) {
			$container    = AIPS_Container::get_instance();
			$this->logger = $container->has(AIPS_Logger_Interface::class)
				? $container->make(AIPS_Logger_Interface::class)
				: new AIPS_Logger();
		}

		return $this->logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'trending_topics';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Trending Topics', 'ai-post-scheduler');
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
			return in_array($context->get_mode(), array(
				AIPS_Research_Generation_Context::MODE_TRENDING,
				AIPS_Research_Generation_Context::MODE_SOURCES,
			), true);
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * JSON schema for the researched topic array returned by the AI.
	 *
	 * @return array<string, mixed>
	 */
	public function get_topic_json_schema(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'topic'    => array('type' => 'string'),
					'score'    => array('type' => 'integer'),
					'reason'   => array('type' => 'string'),
					'keywords' => array(
						'type'  => 'array',
						'items' => array('type' => 'string'),
					),
				),
				'required' => array('topic', 'score', 'reason', 'keywords'),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$niche    = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_niche() : (string) $context->get_topic();
		$count    = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_count() : 10;
		$keywords = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_keywords() : array();

		return $this->build_trending_prompt($niche, $count, $keywords);
	}

	/**
	 * Build the AI prompt for trending topics research.
	 *
	 * @param string $niche    Niche to research.
	 * @param int    $count    Number of topics to generate.
	 * @param array  $keywords Optional focus keywords.
	 * @return string
	 */
	public function build_trending_prompt(string $niche, int $count, array $keywords = array()): string {
		$now          = AIPS_DateTime::now();
		$current_date = $now->toDisplay('F j, Y');
		$current_year = $now->toDisplay('Y');

		$prompt  = "You are a content research expert analyzing trending topics for '{$niche}' as of {$current_date}.\n\n";
		$prompt .= "Your task: Identify the top {$count} most trending, relevant, and engaging topics in this niche right now.\n\n";

		if (!empty($keywords)) {
			$keyword_list = implode(', ', $keywords);
			$prompt      .= "Focus areas: {$keyword_list}\n\n";
		}

		$prompt .= "Consider:\n";
		$prompt .= "1. Current events and news in {$current_year}\n";
		$prompt .= '2. Seasonal relevance for ' . $now->toDisplay('F') . "\n";
		$prompt .= "3. Search trends and user interest\n";
		$prompt .= "4. Evergreen value combined with timeliness\n";
		$prompt .= "5. Content gap opportunities\n\n";
		$prompt .= 'Return a JSON array where each item has: "topic" (string), "score" (integer 1-100), "reason" (string, max 100 chars), "keywords" (array of 3-5 strings).';

		return $prompt;
	}

	/**
	 * Build the AI prompt for source-grounded research.
	 *
	 * @param string $niche          Niche context.
	 * @param int    $count          Number of topics to produce.
	 * @param array  $keywords       Optional focus keywords.
	 * @param string $source_context Scraped source content block.
	 * @return string
	 */
	public function build_source_research_prompt(string $niche, int $count, array $keywords, string $source_context): string {
		$current_date = AIPS_DateTime::now()->toDisplay('F j, Y');

		$prompt  = 'You are a content research expert. Using the source material below as your primary reference, ';
		$prompt .= "identify {$count} specific, high-value blog post topics for the '{$niche}' niche as of {$current_date}.\n\n";

		if (!empty($keywords)) {
			$keyword_list = implode(', ', $keywords);
			$prompt      .= "Additional focus keywords: {$keyword_list}\n\n";
		}

		$prompt .= "SOURCE MATERIAL:\n";
		$prompt .= $source_context . "\n";
		$prompt .= "Instructions:\n";
		$prompt .= "- Ground your topic suggestions in the specific facts, trends, and insights from the sources above.\n";
		$prompt .= "- Prefer specific, actionable topics over generic ones.\n";
		$prompt .= "- Consider gaps or follow-up angles suggested by the source content.\n\n";
		$prompt .= 'Return a JSON array where each item has: "topic" (string), "score" (integer 1-100), "reason" (string, max 100 chars), "keywords" (array of 3-5 strings).';

		return $prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if ($context instanceof AIPS_Research_Generation_Context && $context->get_mode() === AIPS_Research_Generation_Context::MODE_SOURCES) {
			return $this->generate_from_sources($context);
		}

		$niche    = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_niche() : (string) $context->get_topic();
		$count    = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_count() : 10;
		$keywords = ($context instanceof AIPS_Research_Generation_Context) ? $context->get_keywords() : array();

		if (empty($niche)) {
			return new WP_Error('missing_niche', __('Niche parameter is required for research.', 'ai-post-scheduler'));
		}

		if (!$this->get_ai_service()->is_available()) {
			return new WP_Error('ai_unavailable', __('AI Engine is not available for research.', 'ai-post-scheduler'));
		}

		$prompt = $this->build_trending_prompt($niche, $count, $keywords);

		$this->get_logger()->log("Starting trending topics research for niche: {$niche}", 'info', array(
			'niche'    => $niche,
			'count'    => $count,
			'keywords' => $keywords,
		));

		$result = $this->request_topics_payload($prompt);

		if (is_wp_error($result)) {
			$this->get_logger()->log('Research failed: ' . $result->get_error_message(), 'error');
			return $result;
		}

		$topics = $this->validate_and_normalize_topics($result, $count);
		if (is_wp_error($topics)) {
			return $topics;
		}

		$this->get_logger()->log('Research completed successfully', 'info', array(
			'topics_found' => count($topics),
		));

		return $topics;
	}

	/**
	 * Request structured topic JSON from the AI service, falling back to
	 * generate_text() + JSON extraction when structured JSON is unpopulated.
	 *
	 * @param string $prompt Research prompt.
	 * @return array|WP_Error
	 */
	private function request_topics_payload(string $prompt) {
		$ai_service = $this->get_ai_service();
		$result     = $ai_service->generate_json($prompt, array(
			'temperature' => 0.7,
			'json_schema' => $this->get_topic_json_schema(),
		));

		if (is_wp_error($result) || is_array($result)) {
			return $result;
		}

		$text_response = $ai_service->generate_text($prompt, array(
			'temperature' => 0.7,
		));

		if (is_wp_error($text_response)) {
			return $text_response;
		}

		if (!is_string($text_response) || '' === trim($text_response)) {
			return new WP_Error('invalid_format', __('AI response is not in expected array format.', 'ai-post-scheduler'));
		}

		$decoded = AIPS_JSON_Extractor::decode_json_response($text_response);
		if (!is_wp_error($decoded) && is_array($decoded)) {
			return $decoded;
		}

		$fallback_topics = $this->parse_plain_text_topics($text_response);
		if (!empty($fallback_topics)) {
			return $fallback_topics;
		}

		return new WP_Error('invalid_format', __('AI response is not in expected array format.', 'ai-post-scheduler'));
	}

	/**
	 * Parse numbered or bulleted plain-text lines into topic payload entries.
	 *
	 * @param string $text Raw plain-text AI response.
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_plain_text_topics(string $text): array {
		$lines  = preg_split('/\r\n|\r|\n/', $text);
		$topics = array();
		$index  = 0;

		foreach ((array) $lines as $line) {
			$line = trim((string) $line);
			if ('' === $line) {
				continue;
			}
			if (preg_match('/^(?:\d+[\.\)\-:]|[-*•])\s+(.+)$/u', $line, $matches)) {
				$title = trim($matches[1]);
				if ('' !== $title) {
					$topics[] = array(
						'topic'    => $title,
						'score'    => max(50, 90 - ($index * 5)),
						'reason'   => '',
						'keywords' => array(),
					);
					$index++;
				}
			}
		}

		return $topics;
	}

	/**
	 * Execute source-grounded topic research.
	 *
	 * @param AIPS_Research_Generation_Context $context Research context.
	 * @return array|WP_Error
	 */
	private function generate_from_sources(AIPS_Research_Generation_Context $context) {
		$term_ids = $context->get_term_ids();
		$niche    = $context->get_niche();
		$count    = $context->get_count();
		$keywords = $context->get_keywords();

		if (empty($term_ids)) {
			return new WP_Error('no_term_ids', __('At least one source group is required.', 'ai-post-scheduler'));
		}

		if (empty($niche)) {
			return new WP_Error('missing_niche', __('Niche parameter is required for source-based research.', 'ai-post-scheduler'));
		}

		if (!$this->get_ai_service()->is_available()) {
			return new WP_Error('ai_unavailable', __('AI Engine is not available for research.', 'ai-post-scheduler'));
		}

		$sources_repo = new AIPS_Sources_Repository();
		$data_repo    = new AIPS_Sources_Data_Repository();

		$source_rows = $sources_repo->get_by_group_term_ids($term_ids, true);
		if (empty($source_rows)) {
			return new WP_Error('no_sources', __('No active sources found for the selected groups.', 'ai-post-scheduler'));
		}

		$source_ids  = array_map(static function ($s) {
			return (int) $s->id;
		}, $source_rows);
		$content_map = $data_repo->get_extracted_texts_by_source_ids($source_ids);

		$source_context = '';
		foreach ($source_rows as $source) {
			$sid             = (int) $source->id;
			$label           = !empty($source->label) ? $source->label : $source->url;
			$source_context .= sprintf("--- Source: %s (%s) ---\n", $label, $source->url);
			if (isset($content_map[$sid])) {
				$snippet = $content_map[$sid]->extracted_text;
				if (mb_strlen($snippet) > 1500) {
					$snippet = mb_substr($snippet, 0, 1500) . '…';
				}
				$source_context .= $snippet . "\n";
			} else {
				$source_context .= "[No fetched content available]\n";
			}
			$source_context .= "\n";
		}

		$prompt = $this->build_source_research_prompt($niche, $count, $keywords, $source_context);

		$this->get_logger()->log(
			sprintf('AIPS_Research_Service: source-based research for niche "%s" with %d source(s).', $niche, count($source_rows)),
			'info'
		);

		$result = $this->request_topics_payload($prompt);

		if (is_wp_error($result)) {
			$this->get_logger()->log('Source-based research failed: ' . $result->get_error_message(), 'error');
			return $result;
		}

		return $this->validate_and_normalize_topics($result, $count);
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
		if (!is_array($value)) {
			return array();
		}

		$normalized = $this->validate_and_normalize_topics($value, count($value));
		return is_wp_error($normalized) ? array() : $normalized;
	}

	/**
	 * Validate, normalize, sort by score descending, and slice topics.
	 *
	 * @param mixed $topics Raw AI JSON response.
	 * @param int   $count  Maximum topics to return.
	 * @return array|WP_Error
	 */
	public function validate_and_normalize_topics($topics, int $count) {
		if (!is_array($topics)) {
			return new WP_Error('invalid_format', __('AI response is not in expected array format.', 'ai-post-scheduler'));
		}

		$validated_topics = array();
		foreach ($topics as $topic) {
			if (is_array($topic) && isset($topic['topic']) && isset($topic['score']) && !empty($topic['topic'])) {
				$validated_topics[] = array(
					'topic'         => sanitize_text_field($topic['topic']),
					'score'         => absint($topic['score']),
					'reason'        => isset($topic['reason']) ? sanitize_text_field($topic['reason']) : '',
					'keywords'      => isset($topic['keywords']) && is_array($topic['keywords'])
						? AIPS_Utilities::sanitize_string_array($topic['keywords'])
						: array(),
					'researched_at' => AIPS_DateTime::now()->timestamp(),
				);
			}
		}

		if (empty($validated_topics)) {
			return new WP_Error('no_valid_topics', __('No valid topics found in AI response.', 'ai-post-scheduler'));
		}

		usort($validated_topics, static function ($a, $b) {
			return $b['score'] - $a['score'];
		});

		return array_slice($validated_topics, 0, $count);
	}
}
