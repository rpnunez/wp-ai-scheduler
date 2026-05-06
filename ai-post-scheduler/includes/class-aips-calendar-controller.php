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
	 * Get calendar events for a specific month.
	 *
	 * @param int $year  Year (e.g., 2026)
	 * @param int $month Month (1-12)
	 * @return array Array of calendar events
	 */
	public function get_month_events($year, $month) {
		// Calculate the start and end timestamps for the month.
		// gmmktime() ensures UTC boundaries regardless of the server timezone.
		$start_ts      = gmmktime( 0, 0, 0, $month, 1, $year );
		$days_in_month = (int) gmdate( 't', $start_ts );
		$end_ts        = gmmktime( 23, 59, 59, $month, $days_in_month, $year );

		// Use repository to get active schedules
		$schedules = $this->schedule_repo->get_all(true);
		
		$events = array();
		
		foreach ($schedules as $schedule) {
			// Calculate all occurrences of this schedule within the month
			$occurrences = $this->calculate_schedule_occurrences($schedule, $start_ts, $end_ts);
			
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
	 * Calculate schedule occurrences within a timestamp range.
	 *
	 * @param object $schedule  Schedule object
	 * @param int    $start_ts  Range start as UTC Unix timestamp
	 * @param int    $end_ts    Range end as UTC Unix timestamp
	 * @return array Array of datetime strings in site-local format (for calendar frontend)
	 */
	private function calculate_schedule_occurrences($schedule, $start_ts, $end_ts) {
		$occurrences = array();

		// next_run is stored as a bigint Unix timestamp.
		$current = (int) $schedule->next_run;

		// If next_run is before the range start, advance it to the first occurrence
		// inside the range using the efficient interval calculator method.
		if ($current < $start_ts) {
			$current = $this->interval_calculator->calculate_next_occurrence_after(
				$schedule->frequency,
				$current,
				$start_ts
			);
		}

		// Calculate max occurrences based on frequency to avoid truncation.
		// For hourly schedules in a 31-day month: 31 * 24 = 744 occurrences.
		$max_occurrences = 1000;
		$count = 0;
		
		while ($current && $current <= $end_ts && $count < $max_occurrences) {
			if ($current >= $start_ts) {
				// Format in the WordPress site timezone so the calendar UI displays
				// the correct local time rather than a UTC or server-tz shifted value.
				$occurrences[] = wp_date('Y-m-d H:i:s', $current);
			}
			
			// Advance to the next occurrence using the interval calculator.
			$next = $this->interval_calculator->calculate_next_run($schedule->frequency, $current);

			if (!$next || $next <= $current) {
				break;
			}

			$current = $next;
			$count++;
		}
		
		return $occurrences;
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
				if ($category && !is_wp_error($category) && isset($category->name)) {
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
				if ($user && isset($user->display_name)) {
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::error(__('Unauthorized access.', 'ai-post-scheduler'));
		}
		
		$year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
		$month = isset($_POST['month']) ? absint($_POST['month']) : date('n');
		
		// Validate month
		if ($month < 1 || $month > 12) {
			AIPS_Ajax_Response::error(__('Invalid month.', 'ai-post-scheduler'));
		}
		
		$events = $this->get_month_events($year, $month);
		
		AIPS_Ajax_Response::success(array(
			'events' => $events,
			'year' => $year,
			'month' => $month,
		));
	}
}
