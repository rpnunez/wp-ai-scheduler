<?php
/**
 * Review policy decisions for generated posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Review_Policy
 */
class AIPS_Review_Policy {

	/**
	 * Resolve the configured review policy mode.
	 *
	 * @return string
	 */
	public function get_mode() {
		$config = AIPS_Config::get_instance();
		$allowed_modes = $config->get_review_policy_modes();
		$default_mode = $config->get_default_review_policy_mode();

		$mode = (string) $config->get_option('aips_review_policy_mode', $default_mode);

		if (!in_array($mode, $allowed_modes, true)) {
			return $default_mode;
		}

		return $mode;
	}

	/**
	 * Get the configured quality threshold.
	 *
	 * @return int
	 */
	public function get_quality_threshold() {
		$config = AIPS_Config::get_instance();
		$defaults = $config->get_default_options();
		$default_threshold = isset($defaults['aips_review_quality_threshold']) ? absint($defaults['aips_review_quality_threshold']) : 80;
		$threshold = absint($config->get_option('aips_review_quality_threshold', $default_threshold));

		return $threshold > 0 ? $threshold : $default_threshold;
	}

	/**
	 * Whether partial generations must be reviewed.
	 *
	 * @return bool
	 */
	public function requires_review_for_partial_generations() {
		return !empty(AIPS_Config::get_instance()->get_option('aips_review_require_partial_generations'));
	}

	/**
	 * Whether publish requests should be intercepted before generation completes.
	 *
	 * @param string $requested_status Requested status.
	 * @return bool
	 */
	public function should_intercept_requested_publish($requested_status) {
		$modes = AIPS_Config::get_instance()->get_review_policy_publish_intercept_modes();

		return ('publish' === $requested_status && in_array($this->get_mode(), $modes, true));
	}

	/**
	 * Evaluate whether a generated post requires review.
	 *
	 * @param array $audit Audit result payload.
	 * @return array
	 */
	public function evaluate($audit) {
		$mode = $this->get_mode();
		$score = isset($audit['score']) ? (int) $audit['score'] : 0;
		$critical_flags = isset($audit['critical_flags']) && is_array($audit['critical_flags']) ? $audit['critical_flags'] : array();
		$has_critical_flags = !empty($audit['has_critical_flags']);

		$decision = array(
			'mode' => $mode,
			'requires_review' => false,
			'reason' => '',
		);

		if ('disabled' === $mode) {
			return $decision;
		}

		if ('always' === $mode) {
			$decision['requires_review'] = true;
			$decision['reason'] = __('Manual review is required by policy.', 'ai-post-scheduler');
			return $decision;
		}

		if ($this->requires_review_for_partial_generations() && in_array('partial_generation', $critical_flags, true)) {
			$decision['requires_review'] = true;
			$decision['reason'] = __('Generation completed with missing or failed components.', 'ai-post-scheduler');
			return $decision;
		}

		if ($has_critical_flags) {
			$decision['requires_review'] = true;
			$decision['reason'] = __('Critical quality issues were detected.', 'ai-post-scheduler');
			return $decision;
		}

		if ($score < $this->get_quality_threshold()) {
			$decision['requires_review'] = true;
			$decision['reason'] = __('Quality score is below the configured threshold.', 'ai-post-scheduler');
		}

		return $decision;
	}
}
