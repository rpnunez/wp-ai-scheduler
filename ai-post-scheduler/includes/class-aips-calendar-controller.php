<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Calendar_Controller
 *
 * Handles calendar view logic for scheduled posts.
 * Provides data and rendering for month/week/day calendar views
 * with color-coding by author, category, and template.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Calendar_Controller {
	
	/**
	 * @var AIPS_Schedule_Repository Schedule repository instance
	 */
	private $schedule_repo;
	
	/**
	 * @var AIPS_Template_Repository Template repository instance
	 */
	private $template_repo;
	
	/**
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->schedule_repo = new AIPS_Schedule_Repository();
		add_action('wp_ajax_aips_get_calendar_events', array($this, 'ajax_get_calendar_events'));
	}
	
	/**
	 * Render the calendar page.
	 *
	 * @return void
	 */
	public function render_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/calendar.php';
	}
	
	/**
	 * Get calendar events for a specific month.
	 *
	 * @param int $year  Year (e.g., 2026)
	 * @param int $month Month (1-12)
	 * @return array Array of calendar events
	 */
	public function get_month_events($year, $month) {
		global $wpdb;
		
		// Calculate the start and end dates for the month
		$start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
		$days_in_month = date('t', strtotime($start_date)); // Number of days in month
		$end_date = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $days_in_month);
		
		$schedule_table = $wpdb->prefix . 'aips_schedule';
		$templates_table = $wpdb->prefix . 'aips_templates';
		
		// Get all schedules that have runs in this month
		$schedules = $wpdb->get_results($wpdb->prepare("
			SELECT s.*, t.name as template_name, t.id as template_id
			FROM {$schedule_table} s
			LEFT JOIN {$templates_table} t ON s.template_id = t.id
			WHERE s.is_active = 1
			ORDER BY s.next_run ASC
		"));
		
		$events = array();
		
		foreach ($schedules as $schedule) {
			// Calculate all occurrences of this schedule within the month
			$occurrences = $this->calculate_schedule_occurrences($schedule, $start_date, $end_date);
			
			foreach ($occurrences as $occurrence) {
				$events[] = array(
					'id' => $schedule->id,
					'title' => $schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler'),
					'start' => $occurrence,
					'template_id' => $schedule->template_id,
					'template_name' => $schedule->template_name,
					'frequency' => $schedule->frequency,
					'topic' => $schedule->topic,
					'category' => $this->get_schedule_category($schedule),
					'author' => $this->get_schedule_author($schedule),
				);
			}
		}
		
		return $events;
	}
	
	/**
	 * Calculate schedule occurrences within a date range.
	 *
	 * @param object $schedule   Schedule object
	 * @param string $start_date Start date (MySQL format)
	 * @param string $end_date   End date (MySQL format)
	 * @return array Array of datetime strings in MySQL format
	 */
	private function calculate_schedule_occurrences($schedule, $start_date, $end_date) {
		$occurrences = array();
		
		$current = strtotime($schedule->next_run);
		$start = strtotime($start_date);
		$end = strtotime($end_date);
		
		// If next_run is before the start date, we need to calculate forward
		if ($current < $start) {
			$current = $this->calculate_next_occurrence($schedule, $start_date);
		}
		
		// Generate occurrences until we exceed the end date
		$max_occurrences = 100; // Safety limit
		$count = 0;
		
		while ($current && $current <= $end && $count < $max_occurrences) {
			if ($current >= $start) {
				$occurrences[] = date('Y-m-d H:i:s', $current);
			}
			
			// Calculate next occurrence based on frequency
			$current = $this->calculate_next_run_timestamp($current, $schedule->frequency);
			$count++;
		}
		
		return $occurrences;
	}
	
	/**
	 * Calculate the next occurrence timestamp based on frequency.
	 *
	 * @param int    $current_timestamp Current timestamp
	 * @param string $frequency         Frequency (daily, weekly, monthly, etc.)
	 * @return int|false Next timestamp or false if unable to calculate
	 */
	private function calculate_next_run_timestamp($current_timestamp, $frequency) {
		switch ($frequency) {
			case 'hourly':
				return strtotime('+1 hour', $current_timestamp);
			case 'twice_daily':
				return strtotime('+12 hours', $current_timestamp);
			case 'daily':
				return strtotime('+1 day', $current_timestamp);
			case 'twice_weekly':
				return strtotime('+3.5 days', $current_timestamp);
			case 'weekly':
				return strtotime('+1 week', $current_timestamp);
			case 'monthly':
				return strtotime('+1 month', $current_timestamp);
			default:
				return false;
		}
	}
	
	/**
	 * Calculate the next occurrence from a given start date.
	 *
	 * @param object $schedule   Schedule object
	 * @param string $start_date Start date
	 * @return int Timestamp of next occurrence
	 */
	private function calculate_next_occurrence($schedule, $start_date) {
		$next_run = strtotime($schedule->next_run);
		$target = strtotime($start_date);
		
		// Fast-forward to the target date
		while ($next_run < $target) {
			$next_run = $this->calculate_next_run_timestamp($next_run, $schedule->frequency);
			if (!$next_run) {
				return $target;
			}
		}
		
		return $next_run;
	}
	
	/**
	 * Get the category for a schedule.
	 *
	 * @param object $schedule Schedule object
	 * @return string Category name
	 */
	private function get_schedule_category($schedule) {
		// For now, return a default category
		// In the future, this could be extended to get actual category from template or schedule
		return __('General', 'ai-post-scheduler');
	}
	
	/**
	 * Get the author for a schedule.
	 *
	 * @param object $schedule Schedule object
	 * @return string Author name
	 */
	private function get_schedule_author($schedule) {
		// For now, return a default author
		// In the future, this could be extended to get actual author from schedule
		return __('AI Generated', 'ai-post-scheduler');
	}
	
	/**
	 * AJAX handler to get calendar events.
	 *
	 * @return void
	 */
	public function ajax_get_calendar_events() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
		}
		
		$year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
		$month = isset($_POST['month']) ? absint($_POST['month']) : date('n');
		
		// Validate month
		if ($month < 1 || $month > 12) {
			wp_send_json_error(array('message' => __('Invalid month.', 'ai-post-scheduler')));
		}
		
		$events = $this->get_month_events($year, $month);
		
		wp_send_json_success(array(
			'events' => $events,
			'year' => $year,
			'month' => $month,
		));
	}
}
