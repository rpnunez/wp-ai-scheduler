<?php
/**
 * Taxonomy Post Component
 *
 * Manages WordPress post category (`post_category`) and tag (`post_tag`)
 * assignment and status reconciliation within the post generation pipeline.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Taxonomy_Post_Component
 */
class AIPS_Taxonomy_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_taxonomy';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Taxonomies', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_PERSISTENCE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		$categories = $context->get_post_category();
		$tags       = trim((string) $context->get_post_tags());

		return !empty($categories) || $tags !== '';
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
		return $this->sanitize_value(array(
			'post_category' => $context->get_post_category(),
			'post_tag'      => $context->get_post_tags(),
		));
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
		$applied   = true;

		if (!empty($sanitized['post_category'])) {
			$res = wp_set_post_categories($post_id, $sanitized['post_category'], false);
			if (is_wp_error($res)) {
				$applied = false;
			}
		}

		if (!empty($sanitized['post_tag'])) {
			$res = wp_set_post_tags($post_id, $sanitized['post_tag'], false);
			if (is_wp_error($res)) {
				$applied = false;
			}
		}

		return $applied;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$post_id = absint($target_id);
		if ($post_id <= 0) {
			return false;
		}

		$categories = wp_get_post_categories($post_id);
		$tags       = wp_get_post_tags($post_id);

		return !empty($categories) || !empty($tags);
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		if (!is_array($value)) {
			return array(
				'post_category' => array(),
				'post_tag'      => array(),
			);
		}

		$raw_categories = isset($value['post_category']) ? $value['post_category'] : array();
		if (is_string($raw_categories)) {
			$raw_categories = explode(',', $raw_categories);
		} elseif (!is_array($raw_categories)) {
			$raw_categories = array($raw_categories);
		}
		$categories = array_values(array_filter(array_map('absint', $raw_categories)));

		$raw_tags = isset($value['post_tag']) ? $value['post_tag'] : array();
		if (is_string($raw_tags)) {
			$raw_tags = explode(',', $raw_tags);
		} elseif (!is_array($raw_tags)) {
			$raw_tags = array();
		}
		$tags = array_values(array_filter(array_map(static function ($tag) {
			return sanitize_text_field(trim((string) $tag));
		}, $raw_tags)));

		return array(
			'post_category' => $categories,
			'post_tag'      => $tags,
		);
	}
}
