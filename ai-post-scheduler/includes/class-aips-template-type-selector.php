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
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * Request-scoped cache.
	 *
	 * @var AIPS_Cache
	 */
	private $cache;
	
	/**
	 * Initialize the selector.
	 */
	public function __construct() {
		$this->structure_repository = new AIPS_Article_Structure_Repository();
		$this->schedule_repository = new AIPS_Schedule_Repository();
		$this->history_repository = new AIPS_History_Repository();
		$this->cache = AIPS_Config::get_instance()->get_runtime_cache('aips_template_type_selector', 'array');
	}
	
	/**
	 * Get the effective article structure ID based on the 5-tier inheritance hierarchy:
	 * Tier 1: Schedule override
	 * Tier 2: Author override
	 * Tier 3: Campaign override
	 * Tier 4: Template override
	 * Tier 5: Site default
	 *
	 * @param object|array|null $template Template entity or null.
	 * @param object|array|null $schedule Schedule entity or null.
	 * @param object|array|null $author   Author entity or null.
	 * @param object|array|null $campaign Campaign entity or null.
	 * @return int|null Structure ID or null if none resolved.
	 */
	public function get_effective_article_structure($template = null, $schedule = null, $author = null, $campaign = null) {
		// Tier 1: Schedule override
		if (!empty($schedule)) {
			$sched_id = is_object($schedule) ? ($schedule->article_structure_id ?? null) : (is_array($schedule) ? ($schedule['article_structure_id'] ?? null) : null);
			if (!empty($sched_id)) {
				return (int) $sched_id;
			}
		}

		// Tier 2: Author override
		if (!empty($author)) {
			$author_id = is_object($author) ? ($author->article_structure_id ?? null) : (is_array($author) ? ($author['article_structure_id'] ?? null) : null);
			if (!empty($author_id)) {
				return (int) $author_id;
			}
		}

		// Tier 3: Campaign override
		if (!empty($campaign)) {
			$camp_id = is_object($campaign) ? ($campaign->article_structure_id ?? null) : (is_array($campaign) ? ($campaign['article_structure_id'] ?? null) : null);
			if (!empty($camp_id)) {
				return (int) $camp_id;
			}
		}

		// Tier 4: Template override
		if (!empty($template)) {
			$tmpl_id = is_object($template) ? ($template->article_structure_id ?? null) : (is_array($template) ? ($template['article_structure_id'] ?? null) : null);
			if (!empty($tmpl_id)) {
				return (int) $tmpl_id;
			}
		}

		// Tier 5: Site default
		$site_default = $this->structure_repository->get_default();
		if ($site_default && !empty($site_default->id)) {
			return (int) $site_default->id;
		}

		$option_default = (int) AIPS_Config::get_instance()->get_option('aips_default_article_structure_id');
		if ($option_default > 0) {
			return $option_default;
		}

		return null;
	}

	/**
	 * Select article structure for a schedule execution.
	 *
	 * @param object $schedule Schedule object.
	 * @return int|null Structure ID or null if none selected.
	 */
	public function select_structure($schedule) {
		// 1. Schedule / Topic structure override
		if (!empty($schedule->article_structure_id)) {
			$structure = $this->structure_repository->get_by_id($schedule->article_structure_id);
			if ($this->is_structure_active($structure)) {
				return (int) $structure->id;
			}
		}

		// If rotation pattern is set, use pattern-based selection
		if (!empty($schedule->rotation_pattern)) {
			$pattern_res = $this->select_by_pattern($schedule);
			if ($pattern_res) {
				return (int) $pattern_res;
			}
		}

		// 2. Author structure override
		if (!empty($schedule->author_id)) {
			$author_repo = new AIPS_Authors_Repository();
			$author = $author_repo->get_by_id($schedule->author_id);
			if ($author && !empty($author->article_structure_id)) {
				$structure = $this->structure_repository->get_by_id($author->article_structure_id);
				if ($this->is_structure_active($structure)) {
					return (int) $structure->id;
				}
			}
		}

		// 3. Campaign structure override
		$campaign_id = !empty($schedule->campaign_id) ? (int) $schedule->campaign_id : 0;
		if (!$campaign_id && !empty($schedule->template_id)) {
			$template_repo = new AIPS_Template_Repository();
			$tmpl = $template_repo->get_by_id($schedule->template_id);
			if ($tmpl && !empty($tmpl->campaign_id)) {
				$campaign_id = (int) $tmpl->campaign_id;
			}
		}
		if ($campaign_id > 0) {
			$campaign_repo = new AIPS_Campaigns_Repository();
			$campaign = $campaign_repo->get_by_id($campaign_id);
			if ($campaign && !empty($campaign->article_structure_id)) {
				$structure = $this->structure_repository->get_by_id($campaign->article_structure_id);
				if ($this->is_structure_active($structure)) {
					return (int) $structure->id;
				}
			}
		}

		// 4. Template structure
		if (!empty($schedule->template_id)) {
			$template_repo = isset($template_repo) ? $template_repo : new AIPS_Template_Repository();
			$tmpl = $template_repo->get_by_id($schedule->template_id);
			if ($tmpl && !empty($tmpl->article_structure_id)) {
				$structure = $this->structure_repository->get_by_id($tmpl->article_structure_id);
				if ($this->is_structure_active($structure)) {
					return (int) $structure->id;
				}
			}
		}
		
		// 5. Global site default structure
		$default = $this->structure_repository->get_default();
		if ($default) {
			return (int) $default->id;
		}

		// Final fallback: use the first active structure when no default is configured
		$active_structures = $this->structure_repository->get_all(true);
		if (!empty($active_structures)) {
			return (int) $active_structures[0]->id;
		}

		return null;
	}
	
	/**
	 * Select structure based on rotation pattern.
	 *
	 * @param object $schedule Schedule object.
	 * @return int|null Structure ID or null.
	 */
	private function select_by_pattern($schedule) {
		$active_structures = $this->get_active_structures();
		
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
		$default_structure = $this->structure_repository->get_default();
		$default_structure_id = $default_structure ? (int) $default_structure->id : 0;
		
		foreach ($active_structures as $structure) {
			$weight = ((int) $structure->id === $default_structure_id) ? 2 : 1;
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
		return $this->history_repository->count_completed_for_schedule($schedule);
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
		$this->history_repository->invalidate_schedule_completed_count_cache($schedule_id);
	}
	
	/**
	 * Get next structure for a rotation pattern preview.
	 *
	 * @param string $pattern          Rotation pattern.
	 * @param int    $execution_count  Current execution count.
	 * @return array|null Structure info or null.
	 */
	public function preview_next_structure($pattern, $execution_count = 0) {
		$active_structures = $this->get_active_structures();
		
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
				$default_structure = $this->structure_repository->get_default();
				if ($default_structure && $this->is_structure_active($default_structure)) {
					$structure_id = $default_structure->id;
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

	/**
	 * Get the cached list of active structures for the current request.
	 *
	 * @return array
	 */
	private function get_active_structures() {
		$cache_key = 'active_structures';

		if (!$this->cache->has($cache_key)) {
			$this->cache->set($cache_key, $this->structure_repository->get_all(true));
		}

		return $this->cache->get($cache_key);
	}

	/**
	 * Check whether a structure row is active.
	 *
	 * @param object|null $structure Structure row.
	 * @return bool
	 */
	private function is_structure_active($structure) {
		return is_object($structure) && isset($structure->is_active) && (int) $structure->is_active === 1;
	}
}
