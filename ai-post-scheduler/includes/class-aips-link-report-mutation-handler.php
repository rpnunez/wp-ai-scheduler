<?php
/**
 * Link Report Mutation Handler
 *
 * Handles AJAX mutation endpoints for Link Report.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Report_Mutation_Handler
 */
class AIPS_Link_Report_Mutation_Handler {

	/**
	 * @var AIPS_Link_Report_Query_Handler
	 */
	private $query_handler;

	public function __construct(AIPS_Link_Report_Query_Handler $query_handler) {
		$this->query_handler = $query_handler;
	}

	public function apply_suggestion() {
		AIPS_Ajax_Guard::check_admin();
		$this->respond_with_suggestions($this->query_handler->get_inbound()->apply($this->suggestion_id()), __('Link inserted.', 'ai-post-scheduler'));
	}

	public function revert_suggestion() {
		AIPS_Ajax_Guard::check_admin();
		$this->respond_with_suggestions($this->query_handler->get_inbound()->revert($this->suggestion_id()), __('Link removed and original text restored.', 'ai-post-scheduler'));
	}

	public function dismiss_suggestion() {
		AIPS_Ajax_Guard::check_admin();

		$result = $this->query_handler->get_inbound()->dismiss($this->suggestion_id())
			? true
			: new WP_Error('aips_inbound_not_pending', __('This suggestion is no longer pending.', 'ai-post-scheduler'));

		$this->respond_with_suggestions($result, __('Suggestion dismissed.', 'ai-post-scheduler'));
	}

	public function autolink_undo_run() {
		AIPS_Ajax_Guard::check_admin();

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
		$result = $this->query_handler->get_runs()->undo_run($job_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$message = $result['conflicts'] > 0
			? sprintf(
				__('Removed %1$d links. %2$d could not be undone because their posts were edited afterwards; remove those in the editor.', 'ai-post-scheduler'),
				$result['reverted'],
				$result['conflicts']
			)
			: sprintf(
				_n('Removed %d link and restored the original text.', 'Removed %d links and restored the original text.', $result['reverted'], 'ai-post-scheduler'),
				$result['reverted']
			);

		AIPS_Ajax_Response::success(array(
			'message'  => $message,
			'autolink' => $this->query_handler->get_autolink_payload(),
			'summary'  => $this->query_handler->get_totals(),
		));
	}

	private function respond_with_suggestions($result, string $message) {
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$target_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		AIPS_Ajax_Response::success(array(
			'message'     => $message,
			'post_id'     => $target_id,
			'suggestions' => $target_id ? $this->query_handler->get_inbound()->get_suggestions($target_id) : array(),
			'summary'     => $this->query_handler->get_totals(),
		));
	}

	private function suggestion_id(): int {
		return isset($_POST['suggestion_id']) ? absint($_POST['suggestion_id']) : 0;
	}
}
