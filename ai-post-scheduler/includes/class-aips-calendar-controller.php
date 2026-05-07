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
		$month_range = $this->get_month_range($year, $month);
		
		// Use repository to get active schedules
		$schedules = $this->schedule_repo->get_all(true);
		
		$events = array();
		
		foreach ($schedules as $schedule) {
			// Calculate all occurrences of this schedule within the month
			$occurrences = $this->calculate_schedule_occurrences($schedule, $month_range['start'], $month_range['end']);
			
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
	private function calculate_schedule_occurrences($schedule, $start_timestamp, $end_timestamp) {
		$occurrences = array();
		
		$current = $this->normalize_datetime_input($schedule->next_run);
		$start = (int) $start_timestamp;
		$end = (int) $end_timestamp;

		if ($current <= 0) {
			return $occurrences;
		}
		
		// If next_run is before the start date, we need to calculate forward
		if ($current < $start) {
			$current = $this->calculate_next_occurrence($schedule, $start);
		}
		
		// Calculate max occurrences based on frequency to avoid truncation
		// For hourly schedules in a 31-day month: 31 * 24 = 744 occurrences
		$max_occurrences = 1000; // Increased from 100 to handle hourly schedules
		$count = 0;
		
		while ($current && $current <= $end && $count < $max_occurrences) {
			if ($current >= $start) {
				$occurrences[] = AIPS_DateTime::fromTimestamp($current)->toMysql();
			}
			
			// Calculate next occurrence using interval calculator
			$next_run = $this->interval_calculator->calculate_next_occurrence_after(
				$schedule->frequency,
				$current,
				$current + 1
			);
			
			if (empty($next_run) || !is_numeric($next_run) || (int) $next_run <= $current) {
				break;
			}
			
			$current = (int) $next_run;
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
	private function calculate_next_occurrence($schedule, $start_timestamp) {
		// Use the new efficient method in Interval Calculator
		// This avoids the 1000 iteration limit and handles large time gaps
		$base_timestamp = $this->normalize_datetime_input($schedule->next_run);

		if ($base_timestamp <= 0) {
			return (int) $start_timestamp;
		}

		$next_occurrence = $this->interval_calculator->calculate_next_occurrence_after(
			$schedule->frequency,
			$base_timestamp,
			(int) $start_timestamp
		);
		
		if (!$next_occurrence) {
			return (int) $start_timestamp;
		}
		
		return (int) $next_occurrence;
	}

	/**
	 * Get the UTC timestamp range for a calendar month.
	 *
	 * @param int $year  Year number.
	 * @param int $month Month number.
	 * @return array{start:int,end:int}
	 */
	private function get_month_range($year, $month) {
		$start = AIPS_DateTime::fromMysql(sprintf('%04d-%02d-01 00:00:00', $year, $month));

		return array(
			'start' => $start->timestamp(),
			'end' => $start->advance('+1 month')->addSeconds(-1)->timestamp(),
		);
	}

	/**
	 * Normalize stored schedule times to UTC timestamps.
	 *
	 * @param mixed $value Stored schedule datetime value.
	 * @return int
	 */
	private function normalize_datetime_input($value) {
		if ($value instanceof AIPS_DateTime) {
			return $value->timestamp();
		}

		if (is_numeric($value)) {
			return max(0, (int) $value);
		}

		if (is_string($value)) {
			$parsed = AIPS_DateTime::fromMysqlOrNull($value);
			if ($parsed !== null) {
				return $parsed->timestamp();
			}
		}

		return 0;
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
		$now = AIPS_DateTime::now();

		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::error(__('Unauthorized access.', 'ai-post-scheduler'));
		}
		
		$year = isset($_POST['year']) ? absint($_POST['year']) : (int) $now->toDisplay('Y');
		$month = isset($_POST['month']) ? absint($_POST['month']) : (int) $now->toDisplay('n');
		
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
