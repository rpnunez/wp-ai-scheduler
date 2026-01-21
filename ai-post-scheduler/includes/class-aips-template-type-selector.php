<?php
/**
 * Template Type Selector
 *
 * Service class for selecting article structures based on rotation patterns.
 * Handles automated assignment of structures for batch schedules.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Template_Type_Selector
 *
 * Selects article structures based on rotation patterns and schedule configuration.
 */
class AIPS_Template_Type_Selector {
	
	/**
	 * @var AIPS_Article_Structure_Repository
	 */
	private $structure_repository;
	
	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Cache active structures to prevent N+1 queries.
	 *
	 * @var array|null
	 */
	private $active_structures_cache = null;
	
	/**
	 * Initialize the selector.
	 */
	public function __construct() {
		$this->structure_repository = new AIPS_Article_Structure_Repository();
		$this->schedule_repository = new AIPS_Schedule_Repository();
	}
	
	/**
	 * Select article structure for a schedule execution.
	 *
	 * @param object $schedule Schedule object.
	 * @return int|null Structure ID or null if none selected.
	 */
	public function select_structure($schedule) {
		// If specific structure is set, use it
		if (!empty($schedule->article_structure_id)) {
			$structure = $this->structure_repository->get_by_id($schedule->article_structure_id);
			if ($structure && !empty($structure->is_active)) {
				return $structure->id;
			}
		}
		
		// If rotation pattern is set, use pattern-based selection
		if (!empty($schedule->rotation_pattern)) {
			return $this->select_by_pattern($schedule);
		}
		
		// Fall back to default structure
		$default = $this->structure_repository->get_default();
		return $default ? $default->id : null;
	}
	
	/**
	 * Select structure based on rotation pattern.
	 *
	 * @param object $schedule Schedule object.
	 * @return int|null Structure ID or null.
	 */
	private function select_by_pattern($schedule) {
		// Use cached structures if available, otherwise fetch and cache
		if ($this->active_structures_cache === null) {
			$this->active_structures_cache = $this->structure_repository->get_all(true);
		}

		$active_structures = $this->active_structures_cache;
		
		if (empty($active_structures)) {
			return null;
		}
		
		switch ($schedule->rotation_pattern) {
			case 'sequential':
				return $this->select_sequential($schedule, $active_structures);
			
			case 'random':
				return $this->select_random($active_structures);
			
			case 'weighted':
				return $this->select_weighted($active_structures);
			
			case 'alternating':
				return $this->select_alternating($schedule, $active_structures);
			
			default:
				return $active_structures[0]->id;
		}
	}
	
	/**
	 * Select structure sequentially (cycle through all structures in order).
	 *
	 * @param object $schedule          Schedule object.
	 * @param array  $active_structures Array of active structures.
	 * @return int Structure ID.
	 */
	private function select_sequential($schedule, $active_structures) {
		// Get count of posts generated for this schedule
		$count = $this->get_schedule_execution_count($schedule);
		
		// Select structure based on count
		$index = $count % count($active_structures);
		
		return $active_structures[$index]->id;
	}
	
	/**
	 * Select structure randomly.
	 *
	 * @param array $active_structures Array of active structures.
	 * @return int Structure ID.
	 */
	private function select_random($active_structures) {
		$index = array_rand($active_structures);
		return $active_structures[$index]->id;
	}
	
	/**
	 * Select structure with weighted probability.
	 *
	 * Default structures get higher weight.
	 *
	 * @param array $active_structures Array of active structures.
	 * @return int Structure ID.
	 */
	private function select_weighted($active_structures) {
		// Build weighted array (default structures have 2x weight)
		$weighted = array();
		
		foreach ($active_structures as $structure) {
			$weight = !empty($structure->is_default) ? 2 : 1;
			for ($i = 0; $i < $weight; $i++) {
				$weighted[] = $structure->id;
			}
		}
		
		if (empty($weighted)) {
			return $active_structures[0]->id;
		}
		
		$index = array_rand($weighted);
		return $weighted[$index];
	}
	
	/**
	 * Select structure alternating between two most used structures.
	 *
	 * @param object $schedule          Schedule object.
	 * @param array  $active_structures Array of active structures.
	 * @return int Structure ID.
	 */
	private function select_alternating($schedule, $active_structures) {
		// Get top 2 structures
		$structures = array_slice($active_structures, 0, 2);
		
		if (count($structures) < 2) {
			return $structures[0]->id;
		}
		
		// Get count of posts generated for this schedule
		$count = $this->get_schedule_execution_count($schedule);
		
		// Alternate between first and second
		$index = $count % 2;
		
		return $structures[$index]->id;
	}
	
	/**
	 * Get execution count for a schedule.
	 *
	 * Uses the history table to count successful generations.
	 *
	 * @param int|object $schedule Schedule ID or object.
	 * @return int Execution count.
	 */
	private function get_schedule_execution_count($schedule) {
		global $wpdb;
		$table_history = $wpdb->prefix . 'aips_history';
		$table_schedule = $wpdb->prefix . 'aips_schedule';
		
		// Handle ID or Object input to allow avoiding N+1 queries
		if (is_numeric($schedule)) {
			$schedule_id = (int) $schedule;
			$schedule = $this->schedule_repository->get_by_id($schedule_id);
		} else {
			$schedule_id = $schedule->id;
		}
		
		if (!$schedule) {
			return 0;
		}
		
		// Check transient cache to prevent N+1 queries during batch processing
		// Bolt Optimization (Cache for 24 hours, invalidated on run)
		$cache_key = 'aips_sched_cnt_' . $schedule_id;
		$cached_count = get_transient($cache_key);

		if ($cached_count !== false) {
			return (int) $cached_count;
		}

		// Count completed generations for this template
		// Note: We use template_id since history doesn't directly link to schedule
		// Optimization (Bolt): Use created_at from object if available to avoid subquery
		$query = '';
		if (isset($schedule->created_at)) {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM $table_history
				WHERE template_id = %d
				AND status = 'completed'
				AND created_at >= %s",
				$schedule->template_id,
				$schedule->created_at
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM $table_history
				WHERE template_id = %d
				AND status = 'completed'
				AND created_at >= (SELECT created_at FROM $table_schedule WHERE id = %d)",
				$schedule->template_id,
				$schedule_id
			);
		}

		$count = $wpdb->get_var($query);
		
		$count = (int) $count;

		// Cache the result
		set_transient($cache_key, $count, DAY_IN_SECONDS);

		return $count;
	}

	/**
	 * Invalidate the execution count cache for a schedule.
	 *
	 * Should be called after a new post is generated.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function invalidate_count_cache($schedule_id) {
		delete_transient('aips_sched_cnt_' . $schedule_id);
	}
	
	/**
	 * Get next structure for a rotation pattern preview.
	 *
	 * @param string $pattern          Rotation pattern.
	 * @param int    $execution_count  Current execution count.
	 * @return array|null Structure info or null.
	 */
	public function preview_next_structure($pattern, $execution_count = 0) {
		$active_structures = $this->structure_repository->get_all(true);
		
		if (empty($active_structures)) {
			return null;
		}
		
		$structure_id = null;
		
		switch ($pattern) {
			case 'sequential':
			case 'alternating':
				$index = $execution_count % count($active_structures);
				$structure_id = $active_structures[$index]->id;
				break;
			
			case 'random':
				$structure_id = $active_structures[array_rand($active_structures)]->id;
				break;
			
			case 'weighted':
				// For preview, just show the default or first
				foreach ($active_structures as $structure) {
					if (!empty($structure->is_default)) {
						$structure_id = $structure->id;
						break;
					}
				}
				if (!$structure_id) {
					$structure_id = $active_structures[0]->id;
				}
				break;
			
			default:
				$structure_id = $active_structures[0]->id;
		}
		
		if (!$structure_id) {
			return null;
		}
		
		$structure = $this->structure_repository->get_by_id($structure_id);
		
		return array(
			'id' => $structure->id,
			'name' => $structure->name,
			'description' => $structure->description,
		);
	}
	
	/**
	 * Get available rotation patterns.
	 *
	 * @return array Array of pattern_key => pattern_name.
	 */
	public function get_rotation_patterns() {
		return array(
			'sequential' => __('Sequential (Cycle Through All)', 'ai-post-scheduler'),
			'random' => __('Random', 'ai-post-scheduler'),
			'weighted' => __('Weighted (Favor Default)', 'ai-post-scheduler'),
			'alternating' => __('Alternating (Between Two)', 'ai-post-scheduler'),
		);
	}
}
