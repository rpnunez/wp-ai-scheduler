<?php
/**
 * Link Report Job Handler
 *
 * Handles AJAX long-running background job and autolink operations for Link Report.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Report_Job_Handler
 */
class AIPS_Link_Report_Job_Handler {

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $service;

	/**
	 * @var AIPS_Link_Report_Query_Handler
	 */
	private $query_handler;

	public function __construct(AIPS_Link_Index_Service $service, AIPS_Link_Report_Query_Handler $query_handler) {
		$this->service = $service;
		$this->query_handler = $query_handler;
	}

	public function start_backfill() {
		AIPS_Ajax_Guard::check_admin();

		if (!$this->service->is_enabled()) {
			AIPS_Ajax_Response::error(__('The link index is disabled in Settings.', 'ai-post-scheduler'), 'disabled');
		}

		$mode   = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : AIPS_Link_Index_Service::MODE_MISSING;
		$days   = isset($_POST['days']) ? absint($_POST['days']) : 30;
		$result = $this->service->start_backfill($mode, $days);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::LINK_INDEX_REBUILT,
			true,
			sprintf(__('Link index backfill started for %d posts', 'ai-post-scheduler'), (int) $result['total']),
			'link_index_run',
			array('event' => __('Link index backfill', 'ai-post-scheduler'), 'detail' => sprintf(__('Mode "%s"', 'ai-post-scheduler'), $mode)),
			'manual',
			array('mode' => $mode, 'days' => $days, 'total' => (int) $result['total'])
		);

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(
				_n('Scanning %d post for links in the background.', 'Scanning %d posts for links in the background.', (int) $result['total'], 'ai-post-scheduler'),
				(int) $result['total']
			),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	public function pause_backfill() {
		AIPS_Ajax_Guard::check_admin();

		if (!$this->service->pause_backfill()) {
			AIPS_Ajax_Response::error(__('There is no running link scan to pause.', 'ai-post-scheduler'), 'not_running');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => __('Link scan paused. Resume it any time to continue where it stopped.', 'ai-post-scheduler'),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	public function resume_backfill() {
		AIPS_Ajax_Guard::check_admin();

		if (!$this->service->resume_backfill()) {
			AIPS_Ajax_Response::error(__('There is no paused link scan to resume.', 'ai-post-scheduler'), 'not_paused');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => __('Link scan resumed.', 'ai-post-scheduler'),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	public function cancel_backfill() {
		AIPS_Ajax_Guard::check_admin();

		if (!$this->service->cancel_backfill()) {
			AIPS_Ajax_Response::error(__('There is no link scan to cancel.', 'ai-post-scheduler'), 'not_running');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => __('Link scan cancelled. Posts scanned so far stay in the index.', 'ai-post-scheduler'),
			'backfill' => $this->service->get_backfill_status(),
			'summary'  => $this->query_handler->get_totals(),
		));
	}

	public function suggest() {
		AIPS_Ajax_Guard::check_admin();

		$target_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$result    = $this->query_handler->get_inbound()->generate_for_target($target_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'post_id'     => $target_id,
			'title'       => get_the_title($target_id),
			'suggestions' => $result['suggestions'],
			'phrases'     => $result['phrases'],
		));
	}

	public function autolink_start() {
		AIPS_Ajax_Guard::check_admin();

		$scope   = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : AIPS_Autolink_Run_Service::SCOPE_ORPHANS;
		$dry_run = isset($_POST['dry_run']) && filter_var(wp_unslash($_POST['dry_run']), FILTER_VALIDATE_BOOLEAN);
		$result  = $this->query_handler->get_runs()->start($scope, $dry_run);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(
				_n('Auto-linking %d post in the background.', 'Auto-linking %d posts in the background.', (int) $result['total'], 'ai-post-scheduler'),
				(int) $result['total']
			),
			'autolink' => $this->query_handler->get_autolink_payload(),
		));
	}

	public function autolink_pause() {
		AIPS_Ajax_Guard::check_admin();
		$this->respond_with_run($this->query_handler->get_runs()->pause(), __('Auto-link run paused.', 'ai-post-scheduler'), __('There is no running auto-link run to pause.', 'ai-post-scheduler'));
	}

	public function autolink_resume() {
		AIPS_Ajax_Guard::check_admin();
		$this->respond_with_run($this->query_handler->get_runs()->resume(), __('Auto-link run resumed.', 'ai-post-scheduler'), __('There is no paused auto-link run to resume.', 'ai-post-scheduler'));
	}

	public function autolink_cancel() {
		AIPS_Ajax_Guard::check_admin();
		$this->respond_with_run($this->query_handler->get_runs()->cancel(), __('Auto-link run cancelled. Links already inserted can be undone from the run history.', 'ai-post-scheduler'), __('There is no auto-link run to cancel.', 'ai-post-scheduler'));
	}

	private function respond_with_run(bool $ok, string $success, string $failure) {
		if (!$ok) {
			AIPS_Ajax_Response::error($failure, 'invalid_state');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => $success,
			'autolink' => $this->query_handler->get_autolink_payload(),
		));
	}
}
