<?php
/**
 * Quality gate coordinator for generated posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Quality_Gate
 */
class AIPS_Post_Quality_Gate {

	/**
	 * @var AIPS_Post_Quality_Auditor
	 */
	private $auditor;

	/**
	 * @var AIPS_Review_Policy
	 */
	private $policy;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Post_Quality_Auditor|null $auditor Auditor dependency.
	 * @param AIPS_Review_Policy|null        $policy  Policy dependency.
	 */
	public function __construct($auditor = null, $policy = null) {
		$this->auditor = $auditor ?: new AIPS_Post_Quality_Auditor();
		$this->policy = $policy ?: new AIPS_Review_Policy();
	}

	/**
	 * Audit a post, persist quality metadata, and reconcile publish state.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Audit input data.
	 * @return array
	 */
	public function apply_to_post($post_id, $data) {
		$audit = $this->auditor->audit($data);
		$decision = $this->policy->evaluate($audit);
		$requested_status = $this->get_requested_post_status($post_id);
		$review_required = !empty($decision['requires_review']);
		$review_state = $review_required ? 'needs_review' : 'not_required';

		update_post_meta($post_id, 'aips_quality_score', (int) $audit['score']);
		update_post_meta($post_id, 'aips_quality_flags', wp_json_encode($audit['flags']));
		update_post_meta($post_id, 'aips_review_required', $review_required ? 'true' : 'false');
		update_post_meta($post_id, 'aips_review_required_reason', (string) $decision['reason']);
		update_post_meta($post_id, 'aips_review_state', $review_state);

		$final_post_status = $requested_status;

		if ($review_required) {
			$final_post_status = 'draft';
			wp_update_post(array(
				'ID' => $post_id,
				'post_status' => 'draft',
			));
		} elseif ('publish' === $requested_status && $this->policy->should_intercept_requested_publish($requested_status)) {
			$final_post_status = 'publish';
			wp_update_post(array(
				'ID' => $post_id,
				'post_status' => 'publish',
			));
		}

		return array_merge($audit, $decision, array(
			'requested_post_status' => $requested_status,
			'review_state' => $review_state,
			'final_post_status' => $final_post_status,
		));
	}

	/**
	 * Resolve the originally requested status for a generated post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_requested_post_status($post_id) {
		$requested_status = (string) get_post_meta($post_id, 'aips_requested_post_status', true);

		if ($requested_status !== '') {
			return $requested_status;
		}

		$post = get_post($post_id);
		if ($post && !empty($post->post_status)) {
			return (string) $post->post_status;
		}

		return 'draft';
	}
}
