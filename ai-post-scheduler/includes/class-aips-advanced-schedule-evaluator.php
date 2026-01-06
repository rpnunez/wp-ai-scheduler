<?php
/**
 * Advanced Schedule Evaluator
 *
 * Provides rule-based scheduling with AND/OR logic.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Advanced_Schedule_Evaluator
 *
 * Evaluates advanced schedule rules and calculates next run times.
 */
class AIPS_Advanced_Schedule_Evaluator {
	/**
	 * Sanitize an incoming rules payload.
	 *
	 * @param mixed $raw_rules Raw rules JSON string or array.
	 * @return array Sanitized rules array.
	 */
	public function sanitize_rules($raw_rules) {
		if (empty($raw_rules)) {
			return array(
				'mode' => 'all',
				'conditions' => array(),
			);
		}

		if (is_string($raw_rules)) {
			$json_source = function_exists('wp_unslash') ? wp_unslash($raw_rules) : $raw_rules;
			$decoded = json_decode($json_source, true);
			$raw_rules = is_array($decoded) ? $decoded : array();
		}

		$mode = isset($raw_rules['mode']) && strtolower($raw_rules['mode']) === 'any' ? 'any' : 'all';
		$conditions = array();

		if (!empty($raw_rules['conditions']) && is_array($raw_rules['conditions'])) {
			foreach ($raw_rules['conditions'] as $condition) {
				if (!is_array($condition) || empty($condition['type'])) {
					continue;
				}

				$type = sanitize_text_field($condition['type']);
				$clean_condition = array('type' => $type);

				switch ($type) {
					case 'time_between':
						$clean_condition['start'] = $this->sanitize_time_value(isset($condition['start']) ? $condition['start'] : '');
						$clean_condition['end'] = $this->sanitize_time_value(isset($condition['end']) ? $condition['end'] : '');
						if (empty($clean_condition['start']) || empty($clean_condition['end'])) {
							continue 2;
						}
						break;

					case 'days_of_week':
						$clean_condition['days'] = array();
						if (!empty($condition['days']) && is_array($condition['days'])) {
							foreach ($condition['days'] as $day) {
								$day_clean = strtolower(sanitize_text_field($day));
								if (in_array($day_clean, $this->get_days_of_week(), true)) {
									$clean_condition['days'][] = $day_clean;
								}
							}
						}

						if (empty($clean_condition['days'])) {
							continue 2;
						}
						break;

					case 'exclude_month_days':
						$clean_condition['days'] = array();
						if (!empty($condition['days']) && is_array($condition['days'])) {
							foreach ($condition['days'] as $day) {
								$day_int = absint($day);
								if ($day_int >= 1 && $day_int <= 31) {
									$clean_condition['days'][] = $day_int;
								}
							}
						}

						if (empty($clean_condition['days'])) {
							continue 2;
						}
						break;
				}

				$conditions[] = $clean_condition;
			}
		}

		return array(
			'mode' => $mode,
			'conditions' => $conditions,
		);
	}

	/**
	 * Determine if a timestamp matches a set of rules.
	 *
	 * @param mixed $rules Rules payload.
	 * @param int|null $timestamp Optional timestamp to evaluate; defaults to current time.
	 * @return bool True when rules match.
	 */
	public function matches($rules, $timestamp = null) {
		$sanitized = $this->sanitize_rules($rules);
		$conditions = $sanitized['conditions'];
		$mode = $sanitized['mode'];

		if ($timestamp === null) {
			$timestamp = $this->get_current_timestamp();
		}

		if (empty($conditions)) {
			// No conditions means always eligible.
			return true;
		}

		$results = array();
		foreach ($conditions as $condition) {
			$results[] = $this->is_condition_met($condition, $timestamp);
		}

		return $mode === 'any' ? in_array(true, $results, true) : !in_array(false, $results, true);
	}

	/**
	 * Calculate the next run time that satisfies the rules.
	 *
	 * @param mixed $rules Rules payload.
	 * @param string|null $from_time Optional starting point.
	 * @return string MySQL datetime of next run.
	 */
	public function calculate_next_run($rules, $from_time = null) {
		$start = $from_time ? strtotime($from_time) : $this->get_current_timestamp();
		if (!$start) {
			$start = $this->get_current_timestamp();
		}

		// Search up to 60 days ahead in 1-minute increments.
		$max_steps = 60 * 24 * 60;
		for ($i = 0; $i <= $max_steps; $i++) {
			$candidate = $start + ($i * 60);
			if ($this->matches($rules, $candidate)) {
				return date('Y-m-d H:i:s', $candidate);
			}
		}

		// Fallback: run in 24 hours to avoid stalling schedules.
		return date('Y-m-d H:i:s', strtotime('+1 day', $start));
	}

	/**
	 * Evaluate a single condition against a timestamp.
	 *
	 * @param array $condition Condition array.
	 * @param int $timestamp Timestamp to check.
	 * @return bool True if condition passes.
	 */
	private function is_condition_met($condition, $timestamp) {
		switch ($condition['type']) {
			case 'time_between':
				return $this->is_time_between($condition, $timestamp);

			case 'days_of_week':
				return $this->is_day_allowed($condition, $timestamp);

			case 'exclude_month_days':
				return $this->is_month_day_allowed($condition, $timestamp);
		}

		// Unknown conditions are treated as pass to remain backward compatible.
		return true;
	}

	/**
	 * Check time window match with overnight handling.
	 *
	 * @param array $condition Time condition.
	 * @param int $timestamp Timestamp to evaluate.
	 * @return bool
	 */
	private function is_time_between($condition, $timestamp) {
		$start = isset($condition['start']) ? $condition['start'] : '';
		$end = isset($condition['end']) ? $condition['end'] : '';

		if (empty($start) || empty($end)) {
			// Incomplete time constraints should not block execution.
			return true;
		}

		$current = date('H:i', $timestamp);

		if ($start === $end) {
			return true;
		}

		if ($start < $end) {
			return ($current >= $start && $current <= $end);
		}

		// Overnight window (e.g., 22:00 - 02:00)
		return ($current >= $start || $current <= $end);
	}

	/**
	 * Check allowed days of week.
	 *
	 * @param array $condition Day condition.
	 * @param int $timestamp Timestamp to evaluate.
	 * @return bool
	 */
	private function is_day_allowed($condition, $timestamp) {
		if (empty($condition['days'])) {
			return true;
		}

		$current_day = strtolower(date('l', $timestamp));
		return in_array($current_day, $condition['days'], true);
	}

	/**
	 * Check allowed month days (exclude list).
	 *
	 * @param array $condition Exclude condition.
	 * @param int $timestamp Timestamp to evaluate.
	 * @return bool
	 */
	private function is_month_day_allowed($condition, $timestamp) {
		if (empty($condition['days'])) {
			return true;
		}

		$current_day = (int) date('j', $timestamp);
		return !in_array($current_day, $condition['days'], true);
	}

	/**
	 * Sanitize a time value.
	 *
	 * @param string $value Raw time value.
	 * @return string Sanitized HH:MM or empty string.
	 */
	private function sanitize_time_value($value) {
		$value = sanitize_text_field($value);
		if (preg_match('/^(2[0-3]|[01]?[0-9]):[0-5][0-9]$/', $value)) {
			return $value;
		}

		return '';
	}

	/**
	 * Get allowed week day values.
	 *
	 * @return array List of days.
	 */
	private function get_days_of_week() {
		return array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
	}

	/**
	 * Get the current timestamp using WordPress helper when available.
	 *
	 * @return int Timestamp.
	 */
	private function get_current_timestamp() {
		return function_exists('current_time') ? current_time('timestamp') : time();
	}
}
