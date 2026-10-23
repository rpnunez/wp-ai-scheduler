<?php
/**
 * Presets Installer
 *
 * Handles installation, dependency linking, tracking, and clean removal
 * of starter blueprints for AI Post Scheduler.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Preset_Installer {

	const INSTALLED_PRESETS_OPTION = 'aips_installed_presets';
	const ENTITIES_OPTION_PREFIX   = 'aips_preset_entities_';

	/**
	 * @var AIPS_Voices_Repository
	 */
	private $voices_repo;

	/**
	 * @var AIPS_Prompt_Section_Repository
	 */
	private $section_repo;

	/**
	 * @var AIPS_Article_Structure_Repository
	 */
	private $structure_repo;

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repo;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $topics_repo;

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repo;

	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repo;

	/**
	 * Constructor.
	 */
	public function __construct(
		?AIPS_Voices_Repository $voices_repo = null,
		?AIPS_Prompt_Section_Repository $section_repo = null,
		?AIPS_Article_Structure_Repository $structure_repo = null,
		?AIPS_Authors_Repository $authors_repo = null,
		?AIPS_Author_Topics_Repository $topics_repo = null,
		?AIPS_Template_Repository $template_repo = null,
		?AIPS_Schedule_Repository $schedule_repo = null
	) {
		$this->voices_repo    = $voices_repo ?: AIPS_Voices_Repository::instance();
		$this->section_repo   = $section_repo ?: AIPS_Prompt_Section_Repository::instance();
		$this->structure_repo = $structure_repo ?: AIPS_Article_Structure_Repository::instance();
		$this->authors_repo   = $authors_repo ?: AIPS_Authors_Repository::instance();
		$this->topics_repo    = $topics_repo ?: AIPS_Author_Topics_Repository::instance();
		$this->template_repo  = $template_repo ?: AIPS_Template_Repository::instance();
		$this->schedule_repo  = $schedule_repo ?: AIPS_Schedule_Repository::instance();
	}

	/**
	 * Get list of currently installed preset IDs.
	 *
	 * @return array<string>
	 */
	public function get_installed_presets(): array {
		$installed = get_option(self::INSTALLED_PRESETS_OPTION, array());
		return is_array($installed) ? $installed : array();
	}

	/**
	 * Check if a preset is installed.
	 *
	 * @param string $preset_id Preset identifier.
	 * @return bool
	 */
	public function is_installed(string $preset_id): bool {
		return in_array($preset_id, $this->get_installed_presets(), true);
	}

	/**
	 * Get stored entity IDs created for a specific preset.
	 *
	 * @param string $preset_id Preset identifier.
	 * @return array
	 */
	public function get_preset_entities(string $preset_id): array {
		$entities = get_option(self::ENTITIES_OPTION_PREFIX . $preset_id, array());
		return is_array($entities) ? $entities : array();
	}

	/**
	 * Retrieve usage stats for an installed preset (posts generated, schedules executed).
	 *
	 * @param string $preset_id Preset identifier.
	 * @return array
	 */
	public function get_usage_stats(string $preset_id): array {
		global $wpdb;

		$entities = $this->get_preset_entities($preset_id);
		$stats    = array(
			'post_count'    => 0,
			'schedules_run' => 0,
			'entities'      => $entities,
		);

		if (empty($entities)) {
			return $stats;
		}

		$template_ids = !empty($entities['template_ids']) ? array_map('absint', $entities['template_ids']) : array();
		$author_ids   = !empty($entities['author_ids']) ? array_map('absint', $entities['author_ids']) : array();
		$schedule_ids = !empty($entities['schedule_ids']) ? array_map('absint', $entities['schedule_ids']) : array();

		// Check active run history or generated posts in aips_history.
		$history_table = $wpdb->prefix . 'aips_history';
		if ($wpdb->get_var("SHOW TABLES LIKE '$history_table'") === $history_table) {
			$where_clauses = array();
			if (!empty($template_ids)) {
				$in_templates    = implode(',', $template_ids);
				$where_clauses[] = "template_id IN ($in_templates)";
			}
			if (!empty($author_ids)) {
				$in_authors      = implode(',', $author_ids);
				$where_clauses[] = "author_id IN ($in_authors)";
			}

			if (!empty($where_clauses)) {
				$where = implode(' OR ', $where_clauses);
				$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $history_table WHERE ($where) AND post_id IS NOT NULL AND post_id > 0");
				$stats['post_count'] = $count;
			}
		}

		// Check if any schedule has run (last_run > 0).
		if (!empty($schedule_ids)) {
			$schedule_table = $wpdb->prefix . 'aips_schedule';
			if ($wpdb->get_var("SHOW TABLES LIKE '$schedule_table'") === $schedule_table) {
				$in_schedules = implode(',', $schedule_ids);
				$runs = (int) $wpdb->get_var("SELECT COUNT(*) FROM $schedule_table WHERE id IN ($in_schedules) AND last_run > 0");
				$stats['schedules_run'] = $runs;
			}
		}

		return $stats;
	}

	/**
	 * Install a preset by creating all linked entities and recording metadata.
	 *
	 * @param string $preset_id    Preset identifier.
	 * @param bool   $set_strategy Whether to populate site strategy options if empty.
	 * @return array|WP_Error Array of created entities on success or WP_Error.
	 */
	public function install(string $preset_id, bool $set_strategy = true) {
		$preset = AIPS_Preset_Registry::get_preset($preset_id);
		if (!$preset) {
			return new WP_Error('invalid_preset', sprintf(__('Preset "%s" was not found.', 'ai-post-scheduler'), $preset_id));
		}

		// Prevent duplicate installation of the same preset without removal first.
		if ($this->is_installed($preset_id)) {
			return new WP_Error('already_installed', sprintf(__('Preset "%s" is already installed.', 'ai-post-scheduler'), $preset['name']));
		}

		$created = array(
			'preset_id'    => $preset_id,
			'installed_at' => AIPS_DateTime::now()->timestamp(),
			'voice_ids'    => array(),
			'section_ids'  => array(),
			'section_keys' => array(),
			'structure_ids'=> array(),
			'author_ids'   => array(),
			'topic_ids'    => array(),
			'template_ids' => array(),
			'schedule_ids' => array(),
		);

		// 1. Create Voice.
		$voice_data = array(
			'name'                 => $preset['voice']['name'],
			'title_prompt'         => $preset['voice']['title_prompt'],
			'content_instructions' => $preset['voice']['content_instructions'],
			'excerpt_instructions' => $preset['voice']['excerpt_instructions'],
			'is_active'            => 1,
		);
		$voice_id = $this->voices_repo->create($voice_data);
		if ($voice_id) {
			$created['voice_ids'][] = (int) $voice_id;
		}

		// 2. Create Prompt Sections.
		foreach ($preset['sections'] as $sec) {
			$existing_sec = $this->section_repo->get_by_key($sec['section_key']);
			if ($existing_sec && !empty($existing_sec->id)) {
				$created['section_ids'][]  = (int) $existing_sec->id;
				$created['section_keys'][] = $sec['section_key'];
			} else {
				$sec_id = $this->section_repo->create(array(
					'name'        => $sec['name'],
					'description' => $sec['description'],
					'section_key' => $sec['section_key'],
					'content'     => $sec['content'],
					'is_active'   => 1,
				));
				if ($sec_id) {
					$created['section_ids'][]  = (int) $sec_id;
					$created['section_keys'][] = $sec['section_key'];
				}
			}
		}

		// 3. Create Article Structure.
		$structure_data = array(
			'name'           => $preset['structure']['name'],
			'description'    => $preset['structure']['description'],
			'structure_data' => wp_json_encode(array(
				'sections'        => $preset['structure']['section_keys'],
				'prompt_template' => $preset['structure']['prompt_template'],
			)),
			'is_active'      => 1,
		);
		$structure_id = $this->structure_repo->create($structure_data);
		if ($structure_id) {
			$created['structure_ids'][] = (int) $structure_id;
		}

		// 4. Create Author.
		$author_data = array(
			'name'                       => $preset['author']['name'],
			'field_niche'                => $preset['author']['field_niche'],
			'description'                => $preset['author']['description'],
			'keywords'                   => $preset['author']['keywords'],
			'voice_tone'                 => $preset['author']['voice_tone'],
			'writing_style'              => $preset['author']['writing_style'],
			'target_audience'            => $preset['author']['target_audience'],
			'expertise_level'            => $preset['author']['expertise_level'],
			'article_structure_id'       => $structure_id ?: null,
			'topic_generation_frequency' => $preset['author']['topic_generation_frequency'],
			'topic_generation_quantity'  => $preset['author']['topic_generation_quantity'],
			'post_generation_frequency'  => $preset['author']['post_generation_frequency'],
			'post_generation_is_active'  => 0,
			'topic_generation_is_active' => 0,
			'is_active'                  => 1,
		);
		$author_id = $this->authors_repo->create($author_data);
		if ($author_id) {
			$created['author_ids'][] = (int) $author_id;
		}

		// 5. Create Starter Author Topics.
		if ($author_id && !empty($preset['author']['starter_topics'])) {
			foreach ($preset['author']['starter_topics'] as $topic_title) {
				$topic_id = $this->topics_repo->create(array(
					'author_id'   => $author_id,
					'topic_title' => $topic_title,
					'topic_prompt'=> '',
					'status'      => 'approved',
				));
				if ($topic_id) {
					$created['topic_ids'][] = (int) $topic_id;
				}
			}
		}

		// 6. Create Template.
		$template_data = array(
			'name'            => $preset['template']['name'],
			'prompt_template' => $preset['template']['prompt_template'],
			'title_prompt'    => $preset['template']['title_prompt'],
			'voice_id'        => $voice_id ?: null,
			'post_status'     => $preset['template']['post_status'],
			'post_type'       => $preset['template']['post_type'],
			'post_author'     => $author_id ?: null,
			'is_active'       => 1,
		);
		$template_id = $this->template_repo->create($template_data);
		if ($template_id) {
			$created['template_ids'][] = (int) $template_id;
		}

		// 7. Create Schedule.
		$schedule_data = array(
			'title'                => $preset['schedule']['title'],
			'template_id'          => $template_id ?: 0,
			'author_id'            => $author_id ?: null,
			'article_structure_id' => $structure_id ?: null,
			'frequency'            => $preset['schedule']['frequency'],
			'schedule_type'        => $preset['schedule']['schedule_type'],
			'is_active'            => $preset['schedule']['is_active'],
			'status'               => 'active',
			'next_run'             => AIPS_DateTime::now()->timestamp() + 86400,
		);
		$schedule_id = $this->schedule_repo->create($schedule_data);
		if ($schedule_id) {
			$created['schedule_ids'][] = (int) $schedule_id;
		}

		// 8. Optionally set strategy defaults if empty or requested.
		if ($set_strategy && !empty($preset['strategy'])) {
			$aips_config = AIPS_Config::get_instance();
			if (empty($aips_config->get_option('aips_site_niche'))) {
				update_option('aips_site_niche', sanitize_text_field($preset['strategy']['niche']));
			}
			if (empty($aips_config->get_option('aips_site_target_audience'))) {
				update_option('aips_site_target_audience', sanitize_text_field($preset['strategy']['target_audience']));
			}
			if (empty($aips_config->get_option('aips_site_content_goals'))) {
				update_option('aips_site_content_goals', sanitize_textarea_field($preset['strategy']['content_goals']));
			}
		}

		// 9. Save tracking option.
		update_option(self::ENTITIES_OPTION_PREFIX . $preset_id, $created);

		// 10. Update installed presets list.
		$installed = $this->get_installed_presets();
		if (!in_array($preset_id, $installed, true)) {
			$installed[] = $preset_id;
			update_option(self::INSTALLED_PRESETS_OPTION, $installed);
		}

		return $created;
	}

	/**
	 * Remove an installed preset with strict integrity check.
	 *
	 * @param string $preset_id Preset identifier.
	 * @param bool   $force     Whether to bypass protection if content exists.
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public function remove(string $preset_id, bool $force = false) {
		$preset = AIPS_Preset_Registry::get_preset($preset_id);
		$name   = $preset ? $preset['name'] : $preset_id;

		if (!$this->is_installed($preset_id)) {
			return new WP_Error('not_installed', sprintf(__('Preset "%s" is not installed.', 'ai-post-scheduler'), $name));
		}

		// Usage check: protect generated posts & executed runs.
		$usage = $this->get_usage_stats($preset_id);
		if (($usage['post_count'] > 0 || $usage['schedules_run'] > 0) && !$force) {
			return new WP_Error(
				'preset_in_use',
				sprintf(
					__('Cannot remove preset "%s": %d generated posts and %d executed schedule runs exist. Confirm force removal to proceed.', 'ai-post-scheduler'),
					$name,
					$usage['post_count'],
					$usage['schedules_run']
				),
				array(
					'post_count'    => $usage['post_count'],
					'schedules_run' => $usage['schedules_run'],
				)
			);
		}

		$entities = $this->get_preset_entities($preset_id);

		// Delete schedules.
		if (!empty($entities['schedule_ids'])) {
			foreach ($entities['schedule_ids'] as $id) {
				$this->schedule_repo->delete(absint($id));
			}
		}

		// Delete templates.
		if (!empty($entities['template_ids'])) {
			foreach ($entities['template_ids'] as $id) {
				$this->template_repo->delete(absint($id));
			}
		}

		// Delete topics.
		if (!empty($entities['topic_ids'])) {
			foreach ($entities['topic_ids'] as $id) {
				$this->topics_repo->delete(absint($id));
			}
		}

		// Delete authors.
		if (!empty($entities['author_ids'])) {
			foreach ($entities['author_ids'] as $id) {
				$this->authors_repo->delete(absint($id));
			}
		}

		// Delete structures.
		if (!empty($entities['structure_ids'])) {
			foreach ($entities['structure_ids'] as $id) {
				$this->structure_repo->delete(absint($id));
			}
		}

		// Delete prompt sections (only those specifically created for this preset).
		if (!empty($entities['section_ids'])) {
			foreach ($entities['section_ids'] as $id) {
				$this->section_repo->delete(absint($id));
			}
		}

		// Delete voices.
		if (!empty($entities['voice_ids'])) {
			foreach ($entities['voice_ids'] as $id) {
				$this->voices_repo->delete(absint($id));
			}
		}

		// Cleanup options.
		delete_option(self::ENTITIES_OPTION_PREFIX . $preset_id);

		$installed = $this->get_installed_presets();
		$installed = array_values(array_diff($installed, array($preset_id)));
		update_option(self::INSTALLED_PRESETS_OPTION, $installed);

		return true;
	}
}
