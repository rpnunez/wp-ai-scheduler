<?php
/**
 * Link Report Controller
 *
 * View data and AJAX endpoints for the Link Report (Content hub).
 * Coordinates query, mutation, and job handlers.
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
	 * @var AIPS_Link_Report_Query_Handler
	 */
	private $query_handler;

	/**
	 * @var AIPS_Link_Report_Mutation_Handler
	 */
	private $mutation_handler;

	/**
	 * @var AIPS_Link_Report_Job_Handler
	 */
	private $job_handler;

	/**
	 * @param AIPS_Link_Index_Service|null    $service Link index service.
	 * @param AIPS_Inbound_Links_Service|null $inbound Inbound suggestions service (lazy by default).
	 */
	public function __construct(?AIPS_Link_Index_Service $service = null, ?AIPS_Inbound_Links_Service $inbound = null) {
		$this->inbound = $inbound;

		$container        = AIPS_Container::get_instance();
		$this->service    = $service ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->repository = $this->service->get_repository();

		$this->query_handler    = new AIPS_Link_Report_Query_Handler($this->service, $this->inbound);
		$this->mutation_handler = new AIPS_Link_Report_Mutation_Handler($this->query_handler);
		$this->job_handler      = new AIPS_Link_Report_Job_Handler($this->service, $this->query_handler);

		// Canonical hook registration
		AIPS_Ajax_Registry::register_controller_actions($this);
	}

	/**
	 * Data for the initial server render of templates/admin/link-report.php.
	 *
	 * @return array
	 */
	public function get_view_data(): array {
		return $this->query_handler->get_view_data();
	}

	/**
	 * Link data for one post: counts, sources linking to it and inbound suggestions.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public function get_post_panel_data(WP_Post $post): array {
		return $this->query_handler->get_post_panel_data($post);
	}

	// --- Query endpoints ---

	public function ajax_get_report() {
		return $this->query_handler->get_report();
	}

	public function ajax_get_post_links() {
		return $this->query_handler->get_post_links();
	}

	public function ajax_get_suggestions() {
		return $this->query_handler->get_suggestions();
	}

	public function ajax_get_post_panel() {
		return $this->query_handler->get_post_panel();
	}

	public function ajax_backfill_status() {
		return $this->query_handler->backfill_status();
	}

	public function ajax_autolink_status() {
		return $this->query_handler->autolink_status();
	}

	// --- Mutation endpoints ---

	public function ajax_apply_suggestion() {
		return $this->mutation_handler->apply_suggestion();
	}

	public function ajax_revert_suggestion() {
		return $this->mutation_handler->revert_suggestion();
	}

	public function ajax_dismiss_suggestion() {
		return $this->mutation_handler->dismiss_suggestion();
	}

	public function ajax_autolink_undo_run() {
		return $this->mutation_handler->autolink_undo_run();
	}

	// --- Job endpoints ---

	public function ajax_start_backfill() {
		return $this->job_handler->start_backfill();
	}

	public function ajax_pause_backfill() {
		return $this->job_handler->pause_backfill();
	}

	public function ajax_resume_backfill() {
		return $this->job_handler->resume_backfill();
	}

	public function ajax_cancel_backfill() {
		return $this->job_handler->cancel_backfill();
	}

	public function ajax_suggest() {
		return $this->job_handler->suggest();
	}

	public function ajax_autolink_start() {
		return $this->job_handler->autolink_start();
	}

	public function ajax_autolink_pause() {
		return $this->job_handler->autolink_pause();
	}

	public function ajax_autolink_resume() {
		return $this->job_handler->autolink_resume();
	}

	public function ajax_autolink_cancel() {
		return $this->job_handler->autolink_cancel();
	}
}
