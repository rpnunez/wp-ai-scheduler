<?php
/**
 * Abstract Post Generation Component
 *
 * Shared base implementation for WordPress post components implementing
 * AIPS_Post_Component_Interface.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Abstract_Post_Component
 */
abstract class AIPS_Abstract_Post_Component implements AIPS_Post_Component_Interface {

	/**
	 * @var AIPS_Generator|null Generator service used for AI execution.
	 */
	protected $generator;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null $generator Optional generator instance.
	 */
	public function __construct(?AIPS_Generator $generator = null) {
		$this->generator = $generator;
	}

	/**
	 * Resolve the generator instance, lazily creating one if needed.
	 *
	 * @return AIPS_Generator
	 */
	protected function get_generator(): AIPS_Generator {
		if ($this->generator === null) {
			$this->generator = new AIPS_Generator();
		}

		return $this->generator;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'posts';
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
	 * {@inheritdoc}
	 */
	public function participates_in_metadata_turn(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_metadata_turn_schema(): array {
		return array();
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
	public function apply($target, $value): bool {
		$post_id = 0;
		if ($target instanceof WP_Post) {
			$post_id = (int) $target->ID;
		} elseif (is_numeric($target)) {
			$post_id = absint($target);
		}

		if ($post_id <= 0) {
			return false;
		}

		return $this->apply_to_post($post_id, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		$count = max(1, min(10, $count));
		$variants = array();

		for ($i = 0; $i < $count; $i++) {
			$result = $this->regenerate($target_id, $context, null, '', $options);
			if (is_wp_error($result)) {
				if (empty($variants)) {
					return $result;
				}
				break;
			}
			$variants[] = $this->sanitize_value($result);
		}

		return $variants;
	}

	/**
	 * Parse a JSON array of variant strings from an AI text response.
	 *
	 * @param string $raw   Raw AI response text.
	 * @param int    $count Maximum variants to return.
	 * @return string[]|WP_Error
	 */
	protected function parse_json_variants_response(string $raw, int $count = 3) {
		$count   = max(1, $count);
		$decoded = AIPS_JSON_Extractor::decode_json_response($raw);
		if (is_wp_error($decoded)) {
			return $decoded;
		}

		if (!array_is_list($decoded)) {
			return new WP_Error(
				'invalid_variants_payload',
				__('AI response did not contain a JSON array of variant strings.', 'ai-post-scheduler')
			);
		}

		$variants = array();
		foreach ($decoded as $item) {
			if (!is_scalar($item)) {
				continue;
			}
			$cleaned = trim((string) $item);
			if ($cleaned !== '') {
				$variants[] = $cleaned;
			}
			if (count($variants) >= $count) {
				break;
			}
		}

		if (empty($variants)) {
			return new WP_Error(
				'invalid_variants_payload',
				__('AI response did not contain a JSON array of variant strings.', 'ai-post-scheduler')
			);
		}

		return $variants;
	}

	/**
	 * Resolve a post object and truncated plain-text content sample for variant prompts.
	 *
	 * @param int|string $target_id Post ID.
	 * @param array      $options   Optional overrides ('post').
	 * @return array{post:WP_Post,content_sample:string}|WP_Error
	 */
	protected function resolve_post_variant_source($target_id, array $options = array()) {
		$post = (isset($options['post']) && $options['post'] instanceof WP_Post)
			? $options['post']
			: get_post(absint($target_id));

		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		$content_sample = wp_strip_all_tags((string) $post->post_content);
		if (strlen($content_sample) > 2000) {
			$content_sample = substr($content_sample, 0, 2000) . '...';
		}

		return array(
			'post'           => $post,
			'content_sample' => $content_sample,
		);
	}

	/**
	 * Resolve an AI service instance from options or container.
	 *
	 * @param array $options Runtime options.
	 * @return AIPS_AI_Service_Interface
	 */
	protected function resolve_variant_ai_service(array $options = array()): AIPS_AI_Service_Interface {
		if (isset($options['ai_service']) && $options['ai_service'] instanceof AIPS_AI_Service_Interface) {
			return $options['ai_service'];
		}

		$container = AIPS_Container::get_instance();
		return $container->has(AIPS_AI_Service_Interface::class)
			? $container->make(AIPS_AI_Service_Interface::class)
			: new AIPS_AI_Service();
	}

	/**
	 * Merge a custom directive into an AI options array.
	 *
	 * @param array  $options   Base options array.
	 * @param string $directive Custom directive string.
	 * @return array
	 */
	protected function merge_directive_option(array $options, string $directive): array {
		$directive = trim($directive);
		if ($directive !== '') {
			$options['custom_directive'] = $directive;
		}

		return $options;
	}

	/**
	 * Extract clean AI provider options from a regeneration/pipeline options array.
	 *
	 * @param array $options Runtime options.
	 * @return array
	 */
	protected function extract_provider_options(array $options): array {
		$ai_options = array();

		if (isset($options['ai_options']) && is_array($options['ai_options'])) {
			$ai_options = $options['ai_options'];
		}

		if (!empty($options['custom_directive'])) {
			$ai_options['custom_directive'] = (string) $options['custom_directive'];
		}

		if (isset($options['temperature']) && is_numeric($options['temperature'])) {
			$ai_options['temperature'] = (float) $options['temperature'];
		}

		if (isset($options['max_tokens']) && is_numeric($options['max_tokens'])) {
			$ai_options['max_tokens'] = absint($options['max_tokens']);
		}

		if (!empty($options['model']) && is_string($options['model'])) {
			$ai_options['model'] = sanitize_text_field($options['model']);
		}

		if (!empty($options['context']) && is_string($options['context'])) {
			$ai_options['context'] = $options['context'];
		}

		return $ai_options;
	}
}
