<?php
/**
 * WP Admin Dashboard Widgets
 *
 * Registers WordPress admin dashboard widgets for the AI Post Scheduler plugin.
 * To add a new widget, create a new add_*_widget() method and call it from register().
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_WP_Widgets
 */
class AIPS_WP_Widgets {

	/**
	 * @var AIPS_Post_Review_Repository
	 */
	private $repository;

	/**
	 * @param AIPS_Post_Review_Repository|null $repository
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: new AIPS_Post_Review_Repository();
	}

	/**
	 * Register all dashboard widgets on `wp_dashboard_setup`.
	 *
	 * @return void
	 */
	public function register() {
		add_action('wp_dashboard_setup', array($this, 'add_pending_review_widget'));
	}

	/**
	 * Add the Pending Review widget via wp_add_dashboard_widget.
	 *
	 * @return void
	 */
	public function add_pending_review_widget() {
		if (!current_user_can('manage_options')) {
			return;
		}

		wp_add_dashboard_widget(
			'aips_review_queue_widget',
			__('AI Post Scheduler — Review Queue', 'ai-post-scheduler'),
			array($this, 'render_pending_review')
		);
	}

	/**
	 * Render the Pending Review widget content.
	 *
	 * @return void
	 */
	public function render_pending_review() {
		$count     = $this->repository->get_draft_count();
		$queue_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts') . '#aips-pending-review';
		include plugin_dir_path(__FILE__) . '../templates/widgets/pending-review.php';
	}
}
