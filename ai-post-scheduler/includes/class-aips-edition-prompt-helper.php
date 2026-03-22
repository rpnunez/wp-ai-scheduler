<?php
/**
 * Edition Prompt Helper
 *
 * Injects edition-aware template variables for coordinated editorial packages.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Edition_Prompt_Helper {

	/**
	 * @var array
	 */
	private static $current_context = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter('aips_template_variables', array($this, 'register_template_variables'), 10, 2);
	}

	/**
	 * Set the current edition context for a generation run.
	 *
	 * @param array $context Edition context.
	 * @return void
	 */
	public static function set_current_context($context) {
		self::$current_context = is_array($context) ? $context : array();
	}

	/**
	 * Clear the current edition context.
	 *
	 * @return void
	 */
	public static function clear_current_context() {
		self::$current_context = array();
	}

	/**
	 * Add edition variables to the template processor.
	 *
	 * @param array       $variables Existing variables.
	 * @param string|null $topic     Current topic.
	 * @return array
	 */
	public function register_template_variables($variables, $topic = null) {
		$context = self::$current_context;
		$related_items = '';

		if (!empty($context['edition_related_items']) && is_array($context['edition_related_items'])) {
			$related_items = implode('; ', array_map('sanitize_text_field', $context['edition_related_items']));
		}

		$variables['{{edition_name}}'] = isset($context['edition_name']) ? sanitize_text_field($context['edition_name']) : '';
		$variables['{{edition_theme}}'] = isset($context['edition_theme']) ? sanitize_text_field($context['edition_theme']) : '';
		$variables['{{edition_cadence}}'] = isset($context['edition_cadence']) ? sanitize_text_field($context['edition_cadence']) : '';
		$variables['{{edition_target_publish_date}}'] = isset($context['edition_target_publish_date']) ? sanitize_text_field($context['edition_target_publish_date']) : '';
		$variables['{{edition_required_slots}}'] = isset($context['edition_required_slots']) ? (string) absint($context['edition_required_slots']) : '';
		$variables['{{edition_owner}}'] = isset($context['edition_owner']) ? sanitize_text_field($context['edition_owner']) : '';
		$variables['{{edition_channel_type}}'] = isset($context['edition_channel_type']) ? sanitize_text_field($context['edition_channel_type']) : '';
		$variables['{{edition_slot_name}}'] = isset($context['edition_slot_name']) ? sanitize_text_field($context['edition_slot_name']) : '';
		$variables['{{edition_related_items}}'] = $related_items;

		return $variables;
	}
}
