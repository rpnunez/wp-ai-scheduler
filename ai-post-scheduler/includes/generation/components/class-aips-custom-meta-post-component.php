<?php
/**
 * Custom Meta Post Component
 *
 * Manages post custom metadata persistence (such as source snapshots used and
 * structured post meta key/value pairs) during the integrations stage of the
 * post generation pipeline.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Custom_Meta_Post_Component
 */
class AIPS_Custom_Meta_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_meta';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Post Metadata', 'ai-post-scheduler');
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
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$meta = array();
		if (!empty($options['source_snapshots']) && is_array($options['source_snapshots'])) {
			$meta['aips_source_snapshots_used'] = $options['source_snapshots'];
		}

		if (!empty($options['custom_meta']) && is_array($options['custom_meta'])) {
			foreach ($options['custom_meta'] as $key => $val) {
				$meta[$key] = $val;
			}
		}

		return $this->sanitize_value($meta);
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
		if ($post_id <= 0 || !is_array($value)) {
			return false;
		}

		$sanitized = $this->sanitize_value($value);
		foreach ($sanitized as $meta_key => $meta_value) {
			update_post_meta($post_id, $meta_key, $meta_value);
		}

		return true;
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
		if (!is_array($value)) {
			return array();
		}

		$clean = array();
		foreach ($value as $key => $val) {
			$sanitized_key = sanitize_key((string) $key);
			if ($sanitized_key === '') {
				continue;
			}
			$clean[$sanitized_key] = is_string($val) ? sanitize_text_field($val) : $val;
		}

		return $clean;
	}
}
