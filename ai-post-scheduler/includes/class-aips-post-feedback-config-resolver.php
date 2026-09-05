<?php
if (!defined('ABSPATH')) { exit; }

/** Resolves global -> Author -> Template feedback settings exactly once. */
class AIPS_Post_Feedback_Config_Resolver {
	private $config;
	private static $defaults = array(
		'like_weight' => 1.0, 'dislike_weight' => 1.25, 'similarity_weight' => 1.0,
		'recency_weight' => .35, 'author_match_weight' => 1.25, 'template_match_weight' => 1.5,
		'global_pool_weight' => .5, 'max_examples' => 6, 'min_similarity' => .7,
		'min_samples' => 1, 'prompt_budget_chars' => 4000, 'edited_content_weight' => .35,
	);

	public function __construct($config = null) { $this->config = $config ?: AIPS_Config::get_instance(); }

	public function resolve($context) {
		if (!$this->as_bool($this->config->get_option('aips_post_feedback_enabled'))) {
			return AIPS_Post_Feedback_Policy::disabled();
		}

		$values = array();
		foreach (self::$defaults as $key => $default) {
			$value = $this->config->get_option('aips_post_feedback_' . $key);
			$values[$key] = null === $value ? $default : $value;
		}

		$scope = array('author_id' => null, 'template_id' => null);
		$enabled = true;
		$author = method_exists($context, 'get_author') ? $context->get_author() : null;
		$template = method_exists($context, 'get_template') ? $context->get_template() : null;
		if ($author) {
			$scope['author_id'] = isset($author->id) ? absint($author->id) : null;
			$enabled = $this->apply_scope($author, $enabled, $values);
		}
		if ($template) {
			$scope['template_id'] = isset($template->id) ? absint($template->id) : null;
			$enabled = $this->apply_scope($template, $enabled, $values);
		}

		return new AIPS_Post_Feedback_Policy($enabled, $this->normalize($values), $scope);
	}

	private function apply_scope($entity, $enabled, array &$values) {
		if (property_exists($entity, 'feedback_enabled') && null !== $entity->feedback_enabled) {
			$enabled = $this->as_bool($entity->feedback_enabled);
		}
		$overrides = isset($entity->feedback_config) ? $entity->feedback_config : array();
		if (is_string($overrides)) { $overrides = json_decode($overrides, true); }
		if (is_array($overrides)) {
			foreach ($overrides as $key => $value) {
				if (array_key_exists($key, self::$defaults)) { $values[$key] = $value; }
			}
		}
		return $enabled;
	}

	private function normalize(array $values) {
		foreach ($values as $key => $value) { $values[$key] = is_numeric($value) ? (float) $value : self::$defaults[$key]; }
		foreach (array('like_weight','dislike_weight','similarity_weight','recency_weight','author_match_weight','template_match_weight','global_pool_weight','edited_content_weight') as $key) {
			$values[$key] = max(0.0, min(10.0, $values[$key]));
		}
		$values['max_examples'] = max(1, min(20, (int) $values['max_examples']));
		$values['min_samples'] = max(1, min(1000, (int) $values['min_samples']));
		$values['prompt_budget_chars'] = max(300, min(20000, (int) $values['prompt_budget_chars']));
		$values['min_similarity'] = max(0.0, min(1.0, $values['min_similarity']));
		return $values;
	}

	private function as_bool($value) { return true === $value || 1 === $value || '1' === $value || 'yes' === $value || 'on' === $value; }
}
