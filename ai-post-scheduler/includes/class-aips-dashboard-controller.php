<?php
/**
 * Dashboard Controller
 *
 * Handles the Dashboard admin page.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Dashboard_Controller
 *
 * Manages the Dashboard admin interface.
 */
class AIPS_Dashboard_Controller {

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repo;

	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repo;

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repo;

	/**
	 * Initialize the controller
	 */
	public function __construct() {
		$this->history_repo = new AIPS_History_Repository();
		$this->schedule_repo = new AIPS_Schedule_Repository();
		$this->template_repo = new AIPS_Template_Repository();
	}

	/**
	 * Render the Dashboard admin page
	 */
	public function render_page() {
		// Get stats
		$history_stats = $this->history_repo->get_stats();
		$schedule_counts = $this->schedule_repo->count_by_status();
		$template_counts = $this->template_repo->count_by_status();

		$total_generated = $history_stats['completed'];
		$pending_scheduled = $schedule_counts['active'];
		$total_templates = $template_counts['active'];
		$failed_count = $history_stats['failed'];

		// Get recent history
		$recent_posts_data = $this->history_repo->get_history(array('per_page' => 5));
		$recent_posts = $recent_posts_data['items'];

		// Get upcoming schedules (Bolt optimization: using limit in query)
		$upcoming = $this->schedule_repo->get_upcoming(5);

		include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
