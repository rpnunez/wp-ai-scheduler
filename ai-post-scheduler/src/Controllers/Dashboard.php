<?php
namespace AIPS\Controllers;

if (!defined('ABSPATH')) {
	exit;
}

class Dashboard {
	public function render_page() {
		$history_repo = new \AIPS\Repository\History();
		$schedule_repo = new \AIPS\Repository\Schedule();
		$template_repo = new \AIPS\Repository\Template();

		$history_stats = $history_repo->get_stats();
		$schedule_counts = $schedule_repo->count_by_status();
		$template_counts = $template_repo->count_by_status();

		$total_generated = $history_stats['completed'];
		$pending_scheduled = $schedule_counts['active'];
		$total_templates = $template_counts['active'];
		$failed_count = $history_stats['failed'];

		$recent_posts_data = $history_repo->get_history(array('per_page' => 5));
		$recent_posts = $recent_posts_data['items'];

		$upcoming = $schedule_repo->get_upcoming(5);

		include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
