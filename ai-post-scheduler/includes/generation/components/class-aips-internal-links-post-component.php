<?php
/**
 * Internal Links Post Component
 *
 * Owns candidate published post discovery, contextual internal link weaving
 * prompt construction, JSON parsing, HTML sanitization, and post application
 * within the Post Generation & Universal AI Edit framework.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Post_Component
 */
class AIPS_Internal_Links_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null            $generator  Optional generator instance.
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service instance.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_AI_Service_Interface $ai_service = null
	) {
		parent::__construct($generator);
		$this->ai_service = $ai_service;
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
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_internal_links';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Internal Links', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_INTEGRATIONS;
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
	 * Fetch candidate published posts for contextual internal link weaving.
	 *
	 * @param int $current_post_id Post ID being edited.
	 * @param int $limit           Maximum candidates to retrieve.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidate_posts(int $current_post_id, int $limit = 10): array {
		$query_args = array(
			'post_type'              => array('post'),
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => array($current_post_id),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$candidates = array();
		$query      = new WP_Query($query_args);

		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$permalink = get_permalink($post->ID);
				if ($permalink) {
					$candidates[] = array(
						'id'    => $post->ID,
						'title' => $post->post_title,
						'url'   => $permalink,
					);
				}
			}
		}

		return $candidates;
	}

	/**
	 * Build the internal link weaving prompt for content and candidate posts.
	 *
	 * @param string $content    Original HTML/text content.
	 * @param array  $candidates Candidate published post records.
	 * @param string $directive  Optional custom AI Edit directive.
	 * @return string
	 */
	public function build_weave_prompt(string $content, array $candidates, string $directive = ''): string {
		$candidate_list = '';
		foreach ($candidates as $candidate) {
			$candidate_list .= '- Title: "' . esc_attr((string) $candidate['title']) . '" | URL: ' . esc_url((string) $candidate['url']) . "\n";
		}

		$prompt  = "You are an expert WordPress editor specializing in organic internal linking for SEO.\n\n";
		$prompt .= "Task:\n";
		$prompt .= "Weave 1 or 2 relevant internal hyperlinks into the text below using the candidate site articles provided.\n\n";
		$prompt .= "Rules:\n";
		$prompt .= "1. Only link when genuinely relevant to the context.\n";
		$prompt .= "2. Use descriptive, organic anchor text (do not use generic phrases like 'click here' or 'read more').\n";
		$prompt .= "3. Keep the original text and tone as preserved as possible, only adjusting phrasing where needed to fit the anchor naturally.\n";
		$prompt .= "4. Output MUST be valid JSON only with exactly two keys: 'modified_html' (string containing HTML <a href=\"...\">...</a> tags) and 'inserted_links' (array of objects with 'url', 'title', and 'anchor').\n\n";

		$directive = trim($directive);
		if ($directive !== '') {
			$prompt .= "Additional Directive:\n" . $directive . "\n\n";
		}

		$prompt .= "Candidate Articles Available to Link to:\n" . $candidate_list . "\n\n";
		$prompt .= "Original Content:\n\"\"\"\n" . $content . "\n\"\"\"\n\n";
		$prompt .= "Example JSON output:\n";
		$prompt .= "{\n  \"modified_html\": \"Here is text with <a href=\\\"https://example.com/post\\\">descriptive anchor text</a> woven in.\",\n  \"inserted_links\": [\n    {\n      \"url\": \"https://example.com/post\",\n      \"title\": \"Post Title\",\n      \"anchor\": \"descriptive anchor text\"\n    }\n  ]\n}";

		return $prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$post_id = isset($options['post_id']) ? absint($options['post_id']) : 0;
		$content = isset($options['content']) ? (string) $options['content'] : '';
		$directive = isset($options['custom_directive']) ? (string) $options['custom_directive'] : '';

		return $this->weave_links($post_id, $content, $directive, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$post_id = absint($target_id);
		$content = isset($options['current_content']) ? (string) $options['current_content'] : ($post_id > 0 ? (string) get_post_field('post_content', $post_id) : '');

		return $this->weave_links($post_id, $content, $directive, $options);
	}

	/**
	 * Weave internal links naturally into content using candidate posts.
	 *
	 * @param int    $post_id   Post ID being edited.
	 * @param string $content   Content snippet or post body.
	 * @param string $directive Optional custom directive.
	 * @param array  $options   Optional overrides ('ai_service', 'candidates', 'limit').
	 * @return array|WP_Error Array with 'modified_html' and 'inserted_links', or WP_Error.
	 */
	public function weave_links(int $post_id, string $content, string $directive = '', array $options = array()) {
		$ai_service = (isset($options['ai_service']) && $options['ai_service'] instanceof AIPS_AI_Service_Interface)
			? $options['ai_service']
			: $this->get_ai_service();

		if (!$ai_service || !$ai_service->is_available()) {
			return new WP_Error('ai_service_unavailable', __('AI service is currently unavailable. Please check your AI Engine configuration.', 'ai-post-scheduler'));
		}

		$content = trim($content);
		if ('' === $content) {
			return new WP_Error('empty_content', __('No content provided for link weaving.', 'ai-post-scheduler'));
		}

		$limit      = isset($options['limit']) ? max(1, absint($options['limit'])) : 8;
		$candidates = isset($options['candidates']) && is_array($options['candidates'])
			? $options['candidates']
			: $this->get_candidate_posts($post_id, $limit);

		if (empty($candidates)) {
			return new WP_Error('no_candidates', __('No other published posts found on this site to link to.', 'ai-post-scheduler'));
		}

		$prompt   = $this->build_weave_prompt($content, $candidates, $directive);
		$response = $ai_service->generate_text($prompt);

		if (is_wp_error($response)) {
			return $response;
		}

		$raw = trim((string) $response);
		if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
			$decoded = json_decode($matches[0], true);
			if (is_array($decoded) && isset($decoded['modified_html'])) {
				return array(
					'modified_html'  => wp_kses_post($decoded['modified_html']),
					'inserted_links' => isset($decoded['inserted_links']) && is_array($decoded['inserted_links']) ? $decoded['inserted_links'] : array(),
				);
			}
		}

		return array(
			'modified_html'  => wp_kses_post($content),
			'inserted_links' => array(),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0) {
			return false;
		}

		$html = is_array($value) && isset($value['modified_html'])
			? (string) $value['modified_html']
			: (is_string($value) ? $value : '');

		$sanitized = wp_kses_post($html);
		if ('' === trim($sanitized)) {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => wp_slash($sanitized),
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
		return strpos($content, '<a ') !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		if (is_array($value)) {
			return array(
				'modified_html'  => isset($value['modified_html']) ? wp_kses_post((string) $value['modified_html']) : '',
				'inserted_links' => isset($value['inserted_links']) && is_array($value['inserted_links']) ? $value['inserted_links'] : array(),
			);
		}

		return is_string($value) ? wp_kses_post($value) : '';
	}
}
