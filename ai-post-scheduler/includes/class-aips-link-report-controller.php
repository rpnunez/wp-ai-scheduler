<?php
/**
 * Link Report Controller
 *
 * View data and AJAX endpoints for the Link Report (Content hub): per-post
 * inbound / outbound / external / broken link counts, true orphans, a
 * per-post drill-down, and the link index backfill job.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Report_Controller
 */
class AIPS_Link_Report_Controller {

	/**
	 * Rows per report page.
	 */
	const PER_PAGE = 20;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $service;

	/**
	 * @var AIPS_Link_Index_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Inbound_Links_Service|null
	 */
	private $inbound;

	/**
	 * @param AIPS_Link_Index_Service|null    $service Link index service.
	 * @param AIPS_Inbound_Links_Service|null $inbound Inbound suggestions service (lazy by default).
	 */
	public function __construct(?AIPS_Link_Index_Service $service = null, ?AIPS_Inbound_Links_Service $inbound = null) {
		$this->inbound = $inbound;

		$container        = AIPS_Container::get_instance();
		$this->service    = $service ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->repository = $this->service->get_repository();

		add_action('wp_ajax_aips_link_report_get', array($this, 'ajax_get_report'));
		add_action('wp_ajax_aips_link_report_get_post_links', array($this, 'ajax_get_post_links'));
		add_action('wp_ajax_aips_link_report_start_backfill', array($this, 'ajax_start_backfill'));
		add_action('wp_ajax_aips_link_report_backfill_status', array($this, 'ajax_backfill_status'));
		add_action('wp_ajax_aips_link_report_pause_backfill', array($this, 'ajax_pause_backfill'));
		add_action('wp_ajax_aips_link_report_resume_backfill', array($this, 'ajax_resume_backfill'));
		add_action('wp_ajax_aips_link_report_cancel_backfill', array($this, 'ajax_cancel_backfill'));
		add_action('wp_ajax_aips_link_report_suggest', array($this, 'ajax_suggest'));
		add_action('wp_ajax_aips_link_report_get_suggestions', array($this, 'ajax_get_suggestions'));
		add_action('wp_ajax_aips_link_report_apply_suggestion', array($this, 'ajax_apply_suggestion'));
		add_action('wp_ajax_aips_link_report_revert_suggestion', array($this, 'ajax_revert_suggestion'));
		add_action('wp_ajax_aips_link_report_dismiss_suggestion', array($this, 'ajax_dismiss_suggestion'));
		add_action('wp_ajax_aips_autolink_start', array($this, 'ajax_autolink_start'));
		add_action('wp_ajax_aips_autolink_status', array($this, 'ajax_autolink_status'));
		add_action('wp_ajax_aips_autolink_pause', array($this, 'ajax_autolink_pause'));
		add_action('wp_ajax_aips_autolink_resume', array($this, 'ajax_autolink_resume'));
		add_action('wp_ajax_aips_autolink_cancel', array($this, 'ajax_autolink_cancel'));
		add_action('wp_ajax_aips_autolink_undo_run', array($this, 'ajax_autolink_undo_run'));
	}

	/**
	 * Data for the initial server render of templates/admin/link-report.php.
	 *
	 * @return array{summary:array, orphan_count:int, total_posts:int, post_types:array<string,string>, enabled:bool, backfill:array|null}
	 */
	public function get_view_data(): array {
		$post_types = array();
		foreach ($this->service->get_post_types() as $type) {
			$object             = get_post_type_object($type);
			$post_types[$type] = $object ? $object->labels->name : $type;
		}

		$scope = array('post_types' => array_keys($post_types));

		return array(
			'summary'      => $this->repository->get_summary(),
			'orphan_count' => $this->repository->get_report_count($scope + array('orphans_only' => true)),
			'total_posts'  => $this->repository->get_report_count($scope),
			'post_types'   => $post_types,
			'enabled'      => $this->service->is_enabled(),
			'backfill'     => $this->service->get_backfill_status(),
			'autolink'     => $this->get_autolink_payload(),
		);
	}

	/**
	 * Site totals plus orphan and in-scope post counts for the stat tiles.
	 *
	 * @return array
	 */
	private function get_totals(): array {
		$scope = array('post_types' => $this->service->get_post_types());

		return array_merge(
			$this->repository->get_summary(),
			array(
				'orphans' => $this->repository->get_report_count($scope + array('orphans_only' => true)),
				'posts'   => $this->repository->get_report_count($scope),
			)
		);
	}

	/**
	 * AJAX: one page of the Link Report.
	 *
	 * @return void
	 */
	public function ajax_get_report() {
		$this->verify_request();

		$allowed_types = $this->service->get_post_types();
		$post_type     = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
		$post_types    = in_array($post_type, $allowed_types, true) ? array($post_type) : $allowed_types;

		$args = array(
			'post_types'   => $post_types,
			'orphans_only' => isset($_POST['orphans_only']) && filter_var(wp_unslash($_POST['orphans_only']), FILTER_VALIDATE_BOOLEAN),
			'search'       => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
			'orderby'      => isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : 'inbound',
			'order'        => isset($_POST['order']) ? sanitize_key(wp_unslash($_POST['order'])) : 'asc',
			'per_page'     => self::PER_PAGE,
			'page'         => isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1,
		);

		$total   = $this->repository->get_report_count($args);
		$rows    = array();
		$page    = $this->repository->get_report_page($args);
		$pending = (new AIPS_Internal_Links_Repository())->count_pending_by_targets(wp_list_pluck($page, 'ID'), AIPS_Inbound_Links_Service::ORIGIN);

		foreach ($page as $row) {
			$type_object = get_post_type_object($row->post_type);

			$rows[] = array(
				'id'         => (int) $row->ID,
				'title'      => $row->post_title !== '' ? $row->post_title : __('(no title)', 'ai-post-scheduler'),
				'post_type'  => $type_object ? $type_object->labels->singular_name : $row->post_type,
				'inbound'    => (int) $row->inbound,
				'outbound'   => (int) $row->outbound,
				'external'   => (int) $row->external,
				'broken'     => (int) $row->broken,
				'is_orphan'  => ((int) $row->inbound === 0),
				'suggestions' => isset($pending[(int) $row->ID]) ? $pending[(int) $row->ID] : 0,
				'can_suggest' => AIPS_Inbound_Links_Service::should_suggest((int) $row->inbound),
				'edit_url'   => (string) get_edit_post_link((int) $row->ID, 'raw'),
				'view_url'   => (string) get_permalink((int) $row->ID),
			);
		}

		AIPS_Ajax_Response::success(array(
			'rows'        => $rows,
			'total'       => $total,
			'page'        => $args['page'],
			'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
			'summary'     => $this->get_totals(),
		));
	}

	/**
	 * AJAX: the links in a post (outbound) and the posts linking to it (inbound).
	 *
	 * @return void
	 */
	public function ajax_get_post_links() {
		$this->verify_request();

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$post    = $post_id ? get_post($post_id) : null;

		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'), 'not_found');
		}

		$outbound = array();
		foreach ($this->repository->get_outbound($post_id) as $link) {
			$target_id  = (int) $link->target_post_id;
			$outbound[] = array(
				'anchor'       => (string) $link->anchor_text,
				'url'          => (string) $link->target_url,
				'type'         => (string) $link->link_type,
				'is_broken'    => ($link->link_type === AIPS_Link_Index_Repository::TYPE_INTERNAL && $target_id === 0),
				'is_nofollow'  => !empty($link->is_nofollow),
				'target_title' => $target_id ? get_the_title($target_id) : '',
				'target_edit'  => $target_id ? (string) get_edit_post_link($target_id, 'raw') : '',
			);
		}

		$inbound = array();
		foreach ($this->repository->get_inbound($post_id) as $link) {
			$source_id = (int) $link->source_post_id;
			$inbound[] = array(
				'anchor'       => (string) $link->anchor_text,
				'source_title' => get_the_title($source_id),
				'source_edit'  => (string) get_edit_post_link($source_id, 'raw'),
			);
		}

		AIPS_Ajax_Response::success(array(
			'post_id'  => $post_id,
			'title'    => get_the_title($post_id),
			'outbound' => $outbound,
			'inbound'  => $inbound,
		));
	}

	/**
	 * AJAX: queue a full link index rebuild.
	 *
	 * @return void
	 */
	public function ajax_start_backfill() {
		$this->verify_request();

		if (!$this->service->is_enabled()) {
			AIPS_Ajax_Response::error(__('The link index is disabled in Settings.', 'ai-post-scheduler'), 'disabled');
		}

		$mode   = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : AIPS_Link_Index_Service::MODE_MISSING;
		$days   = isset($_POST['days']) ? absint($_POST['days']) : 30;
		$result = $this->service->start_backfill($mode, $days);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(
				/* translators: %d: number of posts queued */
				_n('Scanning %d post for links in the background.', 'Scanning %d posts for links in the background.', (int) $result['total'], 'ai-post-scheduler'),
				(int) $result['total']
			),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	/**
	 * AJAX: progress of the most recent backfill.
	 *
	 * @return void
	 */
	public function ajax_backfill_status() {
		$this->verify_request();

		$this->service->ensure_scan_scheduled();

		AIPS_Ajax_Response::success(array(
			'backfill' => $this->service->get_backfill_status(),
			'summary'  => $this->get_totals(),
		));
	}

	/**
	 * AJAX: pause the running scan after its current batch.
	 *
	 * @return void
	 */
	public function ajax_pause_backfill() {
		$this->verify_request();

		if (!$this->service->pause_backfill()) {
			AIPS_Ajax_Response::error(__('There is no running link scan to pause.', 'ai-post-scheduler'), 'not_running');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => __('Link scan paused. Resume it any time to continue where it stopped.', 'ai-post-scheduler'),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	/**
	 * AJAX: resume a paused scan.
	 *
	 * @return void
	 */
	public function ajax_resume_backfill() {
		$this->verify_request();

		if (!$this->service->resume_backfill()) {
			AIPS_Ajax_Response::error(__('There is no paused link scan to resume.', 'ai-post-scheduler'), 'not_paused');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => __('Link scan resumed.', 'ai-post-scheduler'),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	/**
	 * AJAX: cancel the running or paused scan.
	 *
	 * @return void
	 */
	public function ajax_cancel_backfill() {
		$this->verify_request();

		if (!$this->service->cancel_backfill()) {
			AIPS_Ajax_Response::error(__('There is no link scan to cancel.', 'ai-post-scheduler'), 'not_running');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => __('Link scan cancelled. Posts scanned so far stay in the index.', 'ai-post-scheduler'),
			'backfill' => $this->service->get_backfill_status(),
			'summary'  => $this->get_totals(),
		));
	}

	/**
	 * AJAX: generate inbound link suggestions for a post.
	 *
	 * @return void
	 */
	public function ajax_suggest() {
		$this->verify_request();

		$target_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$result    = $this->get_inbound()->generate_for_target($target_id);

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

	/**
	 * AJAX: existing inbound suggestions (pending and inserted) for a post.
	 *
	 * @return void
	 */
	public function ajax_get_suggestions() {
		$this->verify_request();

		$target_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$target_id || !get_post($target_id)) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'), 'not_found');
		}

		AIPS_Ajax_Response::success(array(
			'post_id'     => $target_id,
			'title'       => get_the_title($target_id),
			'suggestions' => $this->get_inbound()->get_suggestions($target_id),
		));
	}

	/**
	 * AJAX: insert one suggested link.
	 *
	 * @return void
	 */
	public function ajax_apply_suggestion() {
		$this->verify_request();
		$this->respond_with_suggestions($this->get_inbound()->apply($this->suggestion_id()), __('Link inserted.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: undo an inserted link.
	 *
	 * @return void
	 */
	public function ajax_revert_suggestion() {
		$this->verify_request();
		$this->respond_with_suggestions($this->get_inbound()->revert($this->suggestion_id()), __('Link removed and original text restored.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: dismiss a pending suggestion.
	 *
	 * @return void
	 */
	public function ajax_dismiss_suggestion() {
		$this->verify_request();

		$result = $this->get_inbound()->dismiss($this->suggestion_id())
			? true
			: new WP_Error('aips_inbound_not_pending', __('This suggestion is no longer pending.', 'ai-post-scheduler'));

		$this->respond_with_suggestions($result, __('Suggestion dismissed.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: start a bulk auto-link run.
	 *
	 * @return void
	 */
	public function ajax_autolink_start() {
		$this->verify_request();

		$scope   = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : AIPS_Autolink_Run_Service::SCOPE_ORPHANS;
		$dry_run = isset($_POST['dry_run']) && filter_var(wp_unslash($_POST['dry_run']), FILTER_VALIDATE_BOOLEAN);
		$result  = $this->get_runs()->start($scope, $dry_run);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(
				/* translators: %d: number of posts */
				_n('Auto-linking %d post in the background.', 'Auto-linking %d posts in the background.', (int) $result['total'], 'ai-post-scheduler'),
				(int) $result['total']
			),
			'autolink' => $this->get_autolink_payload(),
		));
	}

	/**
	 * AJAX: auto-link run progress and history.
	 *
	 * @return void
	 */
	public function ajax_autolink_status() {
		$this->verify_request();
		$this->get_runs()->ensure_scheduled();

		AIPS_Ajax_Response::success(array(
			'autolink' => $this->get_autolink_payload(),
			'summary'  => $this->get_totals(),
		));
	}

	/**
	 * AJAX: pause the current run.
	 *
	 * @return void
	 */
	public function ajax_autolink_pause() {
		$this->verify_request();
		$this->respond_with_run($this->get_runs()->pause(), __('Auto-link run paused.', 'ai-post-scheduler'), __('There is no running auto-link run to pause.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: resume the paused run.
	 *
	 * @return void
	 */
	public function ajax_autolink_resume() {
		$this->verify_request();
		$this->respond_with_run($this->get_runs()->resume(), __('Auto-link run resumed.', 'ai-post-scheduler'), __('There is no paused auto-link run to resume.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: cancel the current run.
	 *
	 * @return void
	 */
	public function ajax_autolink_cancel() {
		$this->verify_request();
		$this->respond_with_run($this->get_runs()->cancel(), __('Auto-link run cancelled. Links already inserted can be undone from the run history.', 'ai-post-scheduler'), __('There is no auto-link run to cancel.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: undo every link a run inserted.
	 *
	 * @return void
	 */
	public function ajax_autolink_undo_run() {
		$this->verify_request();

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
		$result = $this->get_runs()->undo_run($job_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$message = $result['conflicts'] > 0
			? sprintf(
				/* translators: 1: links removed, 2: links that could not be removed */
				__('Removed %1$d links. %2$d could not be undone because their posts were edited afterwards; remove those in the editor.', 'ai-post-scheduler'),
				$result['reverted'],
				$result['conflicts']
			)
			: sprintf(
				/* translators: %d: links removed */
				_n('Removed %d link and restored the original text.', 'Removed %d links and restored the original text.', $result['reverted'], 'ai-post-scheduler'),
				$result['reverted']
			);

		AIPS_Ajax_Response::success(array(
			'message'  => $message,
			'autolink' => $this->get_autolink_payload(),
			'summary'  => $this->get_totals(),
		));
	}

	/**
	 * Respond to a run pause/resume/cancel request.
	 *
	 * @param bool   $ok      Whether the transition happened.
	 * @param string $success Success message.
	 * @param string $failure Failure message.
	 * @return void
	 */
	private function respond_with_run(bool $ok, string $success, string $failure) {
		if (!$ok) {
			AIPS_Ajax_Response::error($failure, 'invalid_state');
		}

		AIPS_Ajax_Response::success(array(
			'message'  => $success,
			'autolink' => $this->get_autolink_payload(),
		));
	}

	/**
	 * Auto-link state for the Link Report.
	 *
	 * @return array{enabled:bool, current:array|null, history:array[], pending_review:int, review_url:string}
	 */
	private function get_autolink_payload(): array {
		$runs = $this->get_runs();

		return array(
			'enabled'        => (new AIPS_Autolink_Policy())->is_enabled(),
			'current'        => $runs->get_current(),
			'history'        => $runs->get_history(),
			'pending_review' => (new AIPS_Internal_Links_Repository())->count_pending_inbound(),
			'review_url'     => admin_url('admin.php?page=aips-automations&tab=internal-links&origin=inbound&status=pending'),
		);
	}

	/**
	 * @return AIPS_Autolink_Run_Service
	 */
	private function get_runs(): AIPS_Autolink_Run_Service {
		$container = AIPS_Container::get_instance();
		return $container->has(AIPS_Autolink_Run_Service::class)
			? $container->make(AIPS_Autolink_Run_Service::class)
			: new AIPS_Autolink_Run_Service($this->get_inbound(), $this->service);
	}

	/**
	 * Send a suggestion action result with the target's refreshed suggestions.
	 *
	 * @param mixed  $result  Service result (WP_Error on failure).
	 * @param string $message Success message.
	 * @return void
	 */
	private function respond_with_suggestions($result, string $message) {
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$target_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		AIPS_Ajax_Response::success(array(
			'message'     => $message,
			'post_id'     => $target_id,
			'suggestions' => $target_id ? $this->get_inbound()->get_suggestions($target_id) : array(),
			'summary'     => $this->get_totals(),
		));
	}

	/**
	 * Suggestion ID from the request.
	 *
	 * @return int
	 */
	private function suggestion_id(): int {
		return isset($_POST['suggestion_id']) ? absint($_POST['suggestion_id']) : 0;
	}

	/**
	 * @return AIPS_Inbound_Links_Service
	 */
	private function get_inbound(): AIPS_Inbound_Links_Service {
		if ($this->inbound === null) {
			$container     = AIPS_Container::get_instance();
			$this->inbound = $container->has(AIPS_Inbound_Links_Service::class)
				? $container->make(AIPS_Inbound_Links_Service::class)
				: new AIPS_Inbound_Links_Service(null, $this->service);
		}
		return $this->inbound;
	}

	/**
	 * Nonce and capability gate shared by every endpoint.
	 *
	 * @return void
	 */
	private function verify_request() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed. Please refresh the page.', 'ai-post-scheduler'), 'invalid_nonce');
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
