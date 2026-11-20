<?php
/**
 * Affiliate Links Post Component
 *
 * Owns AI-assisted contextual affiliate link sentence matching and
 * `[[anchor]]` replacement during the STAGE_INTEGRATIONS phase.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Affiliate_Links_Post_Component
 */
class AIPS_Affiliate_Links_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null            $generator  Optional generator instance.
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service.
	 * @param AIPS_Logger_Interface|null     $logger     Optional logger.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null
	) {
		parent::__construct($generator);
		$this->ai_service = $ai_service;
		$this->logger     = $logger;
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
	 * Resolve logger lazily.
	 *
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
		return 'post_affiliate_links';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Affiliate Links', 'ai-post-scheduler');
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
		return (bool) $context->get_affiliate_links_enabled();
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
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return '';
	}

	/**
	 * Inject a contextual affiliate link into content using AI sentence matching.
	 *
	 * @param string $content       Post content.
	 * @param string $affiliate_url Affiliate URL.
	 * @param string $tag           Tag name for topical context.
	 * @return string Updated content (or original content unchanged on failure/no match).
	 */
	public function inject_affiliate_link(string $content, string $affiliate_url, string $tag): string {
		$prompt = sprintf(
			"You are editing a blog post. Find ONE sentence in the post content below that naturally relates to the topic \"%s\" and would benefit from an affiliate link. " .
			"Return ONLY a JSON object with two keys:\n" .
			"  \"match\": the exact sentence from the content (verbatim, no changes)\n" .
			"  \"replacement\": the same sentence with the most relevant 2-5 words wrapped in [[double square brackets]] to become the hyperlink anchor\n\n" .
			"Rules:\n" .
			"- The [[bracketed]] text must appear verbatim inside \"match\"\n" .
			"- If no suitable sentence exists, return {\"match\":\"\",\"replacement\":\"\"}\n\n" .
			"Post content:\n%s",
			esc_html($tag),
			wp_strip_all_tags($content)
		);

		$response = $this->get_ai_service()->generate_json($prompt, array('maxTokens' => 500));

		if (is_wp_error($response) || !is_array($response)) {
			$this->get_logger()->log(
				'Affiliate AI injection failed: ' . (is_wp_error($response) ? $response->get_error_message() : 'invalid response'),
				'warning'
			);
			return $content;
		}

		$match       = isset($response['match']) ? (string) $response['match'] : '';
		$replacement = isset($response['replacement']) ? (string) $response['replacement'] : '';

		if ($match === '' || $replacement === '') {
			return $content;
		}

		if (strpos($content, $match) === false) {
			$this->get_logger()->log('Affiliate AI injection: match sentence not found in content.', 'warning');
			return $content;
		}

		$linked = preg_replace_callback(
			'/\[\[(.*?)\]\]/s',
			static function ($m) use ($affiliate_url) {
				return '<a href="' . esc_url($affiliate_url) . '" rel="nofollow sponsored" target="_blank">' . esc_html($m[1]) . '</a>';
			},
			$replacement
		);

		return str_replace($match, (string) $linked, $content);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$content       = isset($options['content']) ? (string) $options['content'] : '';
		$affiliate_url = isset($options['affiliate_url']) ? (string) $options['affiliate_url'] : '';
		$tag           = isset($options['tag']) ? (string) $options['tag'] : '';

		if ($content === '' || $affiliate_url === '') {
			return $content;
		}

		return $this->inject_affiliate_link($content, $affiliate_url, $tag);
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
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0 || !is_string($value)) {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => wp_slash(wp_kses_post($value)),
		), true);

		return !is_wp_error($updated) && (int) $updated === $post_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return absint($target_id) > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return is_string($value) ? wp_kses_post($value) : '';
	}
}
