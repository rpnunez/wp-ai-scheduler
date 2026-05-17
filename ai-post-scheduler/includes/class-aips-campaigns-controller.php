<?php
/**
 * Campaigns Controller
 *
 * Handles AJAX operations for the Campaigns admin page.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Campaigns_Controller {

	/**
	 * @var AIPS_Campaigns_Repository
	 */
	private $campaigns_repository;

	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->campaigns_repository = AIPS_Campaigns_Repository::instance();
		$this->schedule_repository = AIPS_Schedule_Repository::instance();

		add_action('wp_ajax_aips_get_campaigns', array($this, 'ajax_get_campaigns'));
		add_action('wp_ajax_aips_get_campaign_metrics', array($this, 'ajax_get_campaign_metrics'));
		add_action('wp_ajax_aips_toggle_campaign', array($this, 'ajax_toggle_campaign'));
		add_action('wp_ajax_aips_duplicate_campaign', array($this, 'ajax_duplicate_campaign'));
		add_action('wp_ajax_aips_archive_campaign', array($this, 'ajax_archive_campaign'));
	}

	/**
	 * Render the campaigns page.
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		include AIPS_PLUGIN_DIR . 'templates/admin/campaigns.php';
	}

	/**
	 * AJAX: Get all campaigns.
	 */
	public function ajax_get_campaigns() {
		$this->ajax_guard();

		$active_only = isset($_POST['active_only']) && $_POST['active_only'];
		$campaigns = $this->campaigns_repository->get_all_campaigns($active_only);
		$stats = $this->campaigns_repository->get_summary_stats();

		AIPS_Ajax_Response::success(array(
			'campaigns' => $campaigns,
			'stats'     => $stats,
		));
	}

	/**
	 * AJAX: Get campaign metrics.
	 */
	public function ajax_get_campaign_metrics() {
		$this->ajax_guard();

		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

		if (!$schedule_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		$metrics = $this->campaigns_repository->get_campaign_metrics($schedule_id);

		AIPS_Ajax_Response::success(array('metrics' => $metrics));
	}

	/**
	 * AJAX: Toggle campaign active status.
	 */
	public function ajax_toggle_campaign() {
		$this->ajax_guard();

		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
		$is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

		if (!$schedule_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		$result = $this->schedule_repository->set_active($schedule_id, $is_active);

		if ($result) {
			$message = $is_active ? __('Campaign activated.', 'ai-post-scheduler') : __('Campaign paused.', 'ai-post-scheduler');
			AIPS_Ajax_Response::success(array('is_active' => $is_active), $message);
		} else {
			AIPS_Ajax_Response::error(__('Failed to update campaign status.', 'ai-post-scheduler'), 'update_failed', 500);
		}
	}

	/**
	 * AJAX: Duplicate campaign.
	 */
	public function ajax_duplicate_campaign() {
		$this->ajax_guard();

		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

		if (!$schedule_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		$new_id = $this->campaigns_repository->duplicate_campaign($schedule_id);

		if ($new_id) {
			$campaign = $this->campaigns_repository->get_campaign_by_id($new_id);
			AIPS_Ajax_Response::success(array('campaign' => $campaign), __('Campaign duplicated successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to duplicate campaign.', 'ai-post-scheduler'), 'duplicate_failed', 500);
		}
	}

	/**
	 * AJAX: Archive campaign.
	 */
	public function ajax_archive_campaign() {
		$this->ajax_guard();

		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

		if (!$schedule_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		$result = $this->campaigns_repository->archive_campaign($schedule_id);

		if ($result) {
			AIPS_Ajax_Response::success(array(), __('Campaign archived successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to archive campaign.', 'ai-post-scheduler'), 'archive_failed', 500);
		}
	}

	/**
	 * Guard AJAX requests.
	 */
	private function ajax_guard() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
