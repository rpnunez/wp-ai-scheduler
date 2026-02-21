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
	 * @var AIPS_Interval_Calculator Interval calculator instance
	 */
	private $interval_calculator;
	
	/**
	 * @var AIPS_Template_Repository Template repository instance
	 */
	private $template_repo;
	
	/**
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->schedule_repo = new AIPS_Schedule_Repository();
		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->template_repo = new AIPS_Template_Repository();
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
		// Calculate the start and end dates for the month
		$start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
		$days_in_month = date('t', strtotime($start_date)); // Number of days in month
		$end_date = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $days_in_month);
		
		// Use repository to get active schedules
		$schedules = $this->schedule_repo->get_all(true);
		
		$events = array();
		
		foreach ($schedules as $schedule) {
			// Calculate all occurrences of this schedule within the month
			$occurrences = $this->calculate_schedule_occurrences($schedule, $start_date, $end_date);
			
			foreach ($occurrences as $occurrence) {
				$events[] = array(
					'id' => $schedule->id,
					'title' => $schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler'),
					'start' => $occurrence,
					'template_id' => property_exists($schedule, 'template_id') ? $schedule->template_id : null,
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
		
		// Calculate max occurrences based on frequency to avoid truncation
		// For hourly schedules in a 31-day month: 31 * 24 = 744 occurrences
		$max_occurrences = 1000; // Increased from 100 to handle hourly schedules
		$count = 0;
		
		while ($current && $current <= $end && $count < $max_occurrences) {
			if ($current >= $start) {
				$occurrences[] = date('Y-m-d H:i:s', $current);
			}
			
			// Calculate next occurrence using interval calculator
			$next_run_str = $this->interval_calculator->calculate_next_run(
				$schedule->frequency,
				date('Y-m-d H:i:s', $current)
			);
			
			if (!$next_run_str) {
				break;
			}
			
			$current = strtotime($next_run_str);
			$count++;
		}
		
		return $occurrences;
	}
	
	/**
	 * Calculate the next occurrence from a given start date.
	 *
	 * @param object $schedule   Schedule object
	 * @param string $start_date Start date
	 * @return int Timestamp of next occurrence
	 */
	private function calculate_next_occurrence($schedule, $start_date) {
		// Use the new efficient method in Interval Calculator
		// This avoids the 1000 iteration limit and handles large time gaps
		$next_occurrence = $this->interval_calculator->calculate_next_occurrence_after(
			$schedule->frequency,
			$schedule->next_run,
			$start_date
		);
		
		if (!$next_occurrence) {
			return strtotime($start_date); // Fallback
		}
		
		return strtotime($next_occurrence);
	}
	
	/**
	 * Get the category for a schedule.
	 *
	 * @param object $schedule Schedule object
	 * @return string Category name
	 */
	private function get_schedule_category($schedule) {
		// Get category from linked template if available
		if (!empty($schedule->template_id)) {
			$template = $this->template_repo->get_by_id($schedule->template_id);
			if ($template && !empty($template->post_category)) {
				$category = get_category($template->post_category);
				if ($category && !is_wp_error($category)) {
					return $category->name;
				}
			}
		}
		
		// Fallback to generic category
		return __('General', 'ai-post-scheduler');
	}
	
	/**
	 * Get the author for a schedule.
	 *
	 * @param object $schedule Schedule object
	 * @return string Author name
	 */
	private function get_schedule_author($schedule) {
		// Get author from linked template if available
		if (!empty($schedule->template_id)) {
			$template = $this->template_repo->get_by_id($schedule->template_id);
			if ($template && !empty($template->post_author)) {
				$user = get_userdata($template->post_author);
				if ($user) {
					return $user->display_name;
				}
			}
		}
		
		// Fallback to generic author
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
