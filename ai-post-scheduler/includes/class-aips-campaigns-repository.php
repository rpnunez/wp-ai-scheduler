<?php
/**
 * Campaigns Repository
 *
 * Canonical parent-model repository for campaigns and their owned child rows.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Campaigns_Repository {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $campaigns_table;

	/**
	 * @var string
	 */
	private $templates_table;

	/**
	 * @var string
	 */
	private $schedule_table;

	/**
	 * @var string
	 */
	private $history_table;

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repository;

	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->campaigns_table = $wpdb->prefix . 'aips_campaigns';
		$this->templates_table = $wpdb->prefix . 'aips_templates';
		$this->schedule_table = $wpdb->prefix . 'aips_schedule';
		$this->history_table = $wpdb->prefix . 'aips_history';
		$this->template_repository = AIPS_Template_Repository::instance();
		$this->schedule_repository = AIPS_Schedule_Repository::instance();
	}

	/**
	 * Fetch campaigns with ownership and generation metrics.
	 *
	 * @param bool|null $archived Optional archive filter.
	 * @return array
	 */
	public function get_all_campaigns($archived = null) {
		$where_sql = '';
		$where_args = array();

		if ($archived !== null) {
			$where_sql = 'WHERE c.is_archived = %d';
			$where_args[] = $archived ? 1 : 0;
		}

		$sql = "
			SELECT
				c.*,
				(SELECT COUNT(*) FROM {$this->templates_table} t WHERE t.campaign_id = c.id) AS linked_template_count,
				(SELECT COUNT(*) FROM {$this->schedule_table} s WHERE s.campaign_id = c.id) AS linked_schedule_count,
				(SELECT COUNT(*) FROM {$this->history_table} h WHERE h.campaign_id = c.id AND h.status = 'completed') AS generated_posts_count,
				(SELECT MAX(s.last_run) FROM {$this->schedule_table} s WHERE s.campaign_id = c.id) AS last_run,
				(SELECT MIN(s.next_run) FROM {$this->schedule_table} s WHERE s.campaign_id = c.id AND s.is_active = 1 AND s.next_run > 0) AS next_run,
				(SELECT MIN(t.id) FROM {$this->templates_table} t WHERE t.campaign_id = c.id) AS primary_template_id,
				(SELECT MIN(s.id) FROM {$this->schedule_table} s WHERE s.campaign_id = c.id) AS primary_schedule_id,
				(SELECT COUNT(*) FROM {$this->schedule_table} s WHERE s.campaign_id = c.id AND s.is_active = 1) AS active_schedule_count
			FROM {$this->campaigns_table} c
			{$where_sql}
			ORDER BY c.is_archived ASC, c.created_at DESC
		";

		$rows = empty($where_args)
			? $this->wpdb->get_results($sql)
			: $this->wpdb->get_results($this->wpdb->prepare($sql, $where_args));

		if (empty($rows)) {
			return array();
		}

		foreach ($rows as $row) {
			$row->generated_posts_count = (int) $row->generated_posts_count;
			$row->linked_template_count = (int) $row->linked_template_count;
			$row->linked_schedule_count = (int) $row->linked_schedule_count;
			$row->active_schedule_count = (int) $row->active_schedule_count;
			$row->can_delete = $row->generated_posts_count === 0;
		}

		return $rows;
	}

	/**
	 * Fetch one campaign row.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return object|null
	 */
	public function get_campaign_by_id($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return null;
		}

		$campaigns = $this->get_all_campaigns(null);
		foreach ($campaigns as $campaign) {
			if ((int) $campaign->id === $campaign_id) {
				return $campaign;
			}
		}

		return null;
	}

	/**
	 * Get summary stats for the campaigns page.
	 *
	 * @return array
	 */
	public function get_summary_stats() {
		$stats = $this->wpdb->get_row("
			SELECT
				COUNT(*) AS total_campaigns,
				SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) AS active_campaigns,
				SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) AS archived_campaigns
			FROM {$this->campaigns_table}
		");

		return array(
			'total'    => isset($stats->total_campaigns) ? (int) $stats->total_campaigns : 0,
			'active'   => isset($stats->active_campaigns) ? (int) $stats->active_campaigns : 0,
			'archived' => isset($stats->archived_campaigns) ? (int) $stats->archived_campaigns : 0,
		);
	}

	/**
	 * Get campaign metrics payload.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public function get_campaign_metrics($campaign_id) {
		$campaign = $this->get_campaign_by_id($campaign_id);

		if (!$campaign) {
			return array(
				'posts_generated' => 0,
				'last_run' => null,
				'next_run' => null,
				'template_count' => 0,
				'schedule_count' => 0,
			);
		}

		return array(
			'posts_generated' => (int) $campaign->generated_posts_count,
			'last_run' => !empty($campaign->last_run) ? (int) $campaign->last_run : null,
			'next_run' => !empty($campaign->next_run) ? (int) $campaign->next_run : null,
			'template_count' => (int) $campaign->linked_template_count,
			'schedule_count' => (int) $campaign->linked_schedule_count,
		);
	}

	/**
	 * Get campaigns for content filter dropdowns.
	 *
	 * @return array
	 */
	public function get_campaign_filter_options() {
		return $this->wpdb->get_results("
			SELECT id, name, is_archived
			FROM {$this->campaigns_table}
			ORDER BY is_archived ASC, name ASC
		");
	}

	/**
	 * Create and finalize a new campaign with owned child records.
	 *
	 * @param array $payload Wizard payload.
	 * @return array{campaign_id:int,template_id:int,schedule_id:int}
	 */
	public function create_campaign_bundle($payload) {
		$started_transaction = $this->start_transaction();

		try {
			$campaign_id = $this->create_campaign(array(
				'name'          => $payload['campaign_name'],
				'content_goal'  => $payload['content_goal'],
				'campaign_mode' => $payload['campaign_mode'],
				'is_active'     => $payload['is_active'],
				'is_archived'   => 0,
			));

			if (!$campaign_id) {
				throw new RuntimeException(__('Campaign could not be saved.', 'ai-post-scheduler'));
			}

			$template_id = $this->create_campaign_template($campaign_id, $payload);
			if (!$template_id) {
				throw new RuntimeException(__('Template could not be saved.', 'ai-post-scheduler'));
			}

			$scheduler = new AIPS_Scheduler();
			$schedule_id = $scheduler->save_schedule(array(
				'template_id'           => $template_id,
				'campaign_id'           => $campaign_id,
				'title'                 => $payload['campaign_name'],
				'frequency'             => $payload['frequency'],
				'start_time'            => $payload['start_time'],
				'is_active'             => $payload['is_active'],
				'topic'                 => $payload['content_goal'],
				'article_structure_id'  => $payload['article_structure_id'],
				'rotation_pattern'      => $payload['rotation_pattern'],
				'author_id'             => $payload['author_id'],
				'campaign_mode'         => $payload['campaign_mode'],
				'post_type_rules'       => $payload['post_type_rules'],
				'blackout_dates'        => $payload['blackout_dates'],
				'time_window_start'     => $payload['time_window_start'],
				'time_window_end'       => $payload['time_window_end'],
				'day_preferences'       => $payload['day_preferences'],
				'season_end_date'       => $payload['season_end_date'],
			));

			if (!$schedule_id) {
				throw new RuntimeException(__('Schedule could not be created.', 'ai-post-scheduler'));
			}

			if ($started_transaction) {
				$this->wpdb->query('COMMIT');
			}

			return array(
				'campaign_id' => (int) $campaign_id,
				'template_id' => (int) $template_id,
				'schedule_id' => (int) $schedule_id,
			);
		} catch (Throwable $e) {
			if ($started_transaction) {
				$this->wpdb->query('ROLLBACK');
			}

			throw $e;
		}
	}

	/**
	 * Duplicate a campaign as a new parent with new child rows.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|false
	 */
	public function duplicate_campaign($campaign_id) {
		$campaign = $this->get_campaign_by_id($campaign_id);
		if (!$campaign) {
			return false;
		}

		$templates = $this->get_templates_by_campaign($campaign_id);
		$schedules = $this->get_schedules_by_campaign($campaign_id);
		$started_transaction = $this->start_transaction();

		try {
			$new_campaign_id = $this->create_campaign(array(
				'name'          => $campaign->name . ' ' . __('(Copy)', 'ai-post-scheduler'),
				'content_goal'  => $campaign->content_goal,
				'campaign_mode' => $campaign->campaign_mode,
				'is_active'     => 0,
				'is_archived'   => 0,
			));

			if (!$new_campaign_id) {
				throw new RuntimeException(__('Campaign could not be duplicated.', 'ai-post-scheduler'));
			}

			$template_map = array();
			foreach ($templates as $template) {
				$template_data = get_object_vars($template);
				unset($template_data['id'], $template_data['created_at'], $template_data['updated_at']);
				$template_data['name'] = $template->name . ' ' . __('(Copy)', 'ai-post-scheduler');
				$template_data['campaign_id'] = $new_campaign_id;
				$new_template_id = $this->template_repository->create($template_data);

				if (!$new_template_id) {
					throw new RuntimeException(__('Campaign template could not be duplicated.', 'ai-post-scheduler'));
				}

				$template_map[(int) $template->id] = (int) $new_template_id;
			}

			foreach ($schedules as $schedule) {
				$schedule_data = get_object_vars($schedule);
				unset($schedule_data['id'], $schedule_data['schedule_history_id'], $schedule_data['last_run'], $schedule_data['created_at']);
				$schedule_data['template_id'] = isset($template_map[(int) $schedule->template_id]) ? $template_map[(int) $schedule->template_id] : (int) $schedule->template_id;
				$schedule_data['campaign_id'] = $new_campaign_id;
				$schedule_data['title'] = $schedule->title . ' ' . __('(Copy)', 'ai-post-scheduler');
				$schedule_data['is_active'] = 0;
				$schedule_data['next_run'] = AIPS_DateTime::now()->timestamp();

				$new_schedule_id = (new AIPS_Scheduler())->save_schedule($schedule_data);
				if (!$new_schedule_id) {
					throw new RuntimeException(__('Campaign schedule could not be duplicated.', 'ai-post-scheduler'));
				}
			}

			if ($started_transaction) {
				$this->wpdb->query('COMMIT');
			}

			return $new_campaign_id;
		} catch (Throwable $e) {
			if ($started_transaction) {
				$this->wpdb->query('ROLLBACK');
			}

			return false;
		}
	}

	/**
	 * Set campaign operational active state.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $is_active Active flag.
	 * @return bool
	 */
	public function set_active($campaign_id, $is_active) {
		$campaign_id = absint($campaign_id);
		$is_active = $is_active ? 1 : 0;

		$result = $this->update_campaign($campaign_id, array('is_active' => $is_active));
		if (!$result) {
			return false;
		}

		$schedules = $this->get_schedules_by_campaign($campaign_id);
		foreach ($schedules as $schedule) {
			$this->schedule_repository->set_active((int) $schedule->id, $is_active);
		}

		return true;
	}

	/**
	 * Archive a campaign and pause owned schedules.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function archive_campaign($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return false;
		}

		$result = $this->update_campaign($campaign_id, array(
			'is_active' => 0,
			'is_archived' => 1,
		));

		if (!$result) {
			return false;
		}

		$schedules = $this->get_schedules_by_campaign($campaign_id);
		foreach ($schedules as $schedule) {
			$this->schedule_repository->set_active((int) $schedule->id, 0);
		}

		return true;
	}

	/**
	 * Restore archived campaign visibility without reactivating schedules.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function restore_campaign($campaign_id) {
		return $this->update_campaign($campaign_id, array(
			'is_archived' => 0,
			'is_active' => 0,
		));
	}

	/**
	 * Delete a campaign when no generated posts are attached.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function delete_campaign($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id || !$this->can_delete_campaign($campaign_id)) {
			return false;
		}

		$started_transaction = $this->start_transaction();

		try {
			foreach ($this->get_schedules_by_campaign($campaign_id) as $schedule) {
				$this->schedule_repository->delete((int) $schedule->id);
			}

			foreach ($this->get_templates_by_campaign($campaign_id) as $template) {
				$this->template_repository->delete((int) $template->id);
			}

			$deleted = $this->wpdb->delete(
				$this->campaigns_table,
				array('id' => $campaign_id),
				array('%d')
			);

			if ($deleted === false) {
				throw new RuntimeException(__('Campaign could not be deleted.', 'ai-post-scheduler'));
			}

			if ($started_transaction) {
				$this->wpdb->query('COMMIT');
			}

			return true;
		} catch (Throwable $e) {
			if ($started_transaction) {
				$this->wpdb->query('ROLLBACK');
			}

			return false;
		}
	}

	/**
	 * Check whether a campaign can be deleted.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function can_delete_campaign($campaign_id) {
		return $this->get_generated_post_count($campaign_id) === 0;
	}

	/**
	 * Count generated posts attached to a campaign historically.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	public function get_generated_post_count($campaign_id) {
		return (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table} WHERE campaign_id = %d AND status = 'completed'",
			absint($campaign_id)
		));
	}

	/**
	 * Create a campaign parent row.
	 *
	 * @param array $data Campaign data.
	 * @return int|false
	 */
	public function create_campaign($data) {
		$now = AIPS_DateTime::now()->timestamp();
		$result = $this->wpdb->insert(
			$this->campaigns_table,
			array(
				'name'          => sanitize_text_field($data['name']),
				'content_goal'  => isset($data['content_goal']) ? sanitize_textarea_field($data['content_goal']) : '',
				'campaign_mode' => isset($data['campaign_mode']) ? sanitize_key($data['campaign_mode']) : 'template',
				'is_active'     => isset($data['is_active']) ? absint($data['is_active']) : 1,
				'is_archived'   => isset($data['is_archived']) ? absint($data['is_archived']) : 0,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array('%s', '%s', '%s', '%d', '%d', '%d', '%d')
		);

		return $result ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update a campaign parent row.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public function update_campaign($campaign_id, $data) {
		$update_data = array();
		$format = array();

		if (isset($data['name'])) {
			$update_data['name'] = sanitize_text_field($data['name']);
			$format[] = '%s';
		}

		if (isset($data['content_goal'])) {
			$update_data['content_goal'] = sanitize_textarea_field($data['content_goal']);
			$format[] = '%s';
		}

		if (isset($data['campaign_mode'])) {
			$update_data['campaign_mode'] = sanitize_key($data['campaign_mode']);
			$format[] = '%s';
		}

		if (array_key_exists('is_active', $data)) {
			$update_data['is_active'] = $data['is_active'] ? 1 : 0;
			$format[] = '%d';
		}

		if (array_key_exists('is_archived', $data)) {
			$update_data['is_archived'] = $data['is_archived'] ? 1 : 0;
			$format[] = '%d';
		}

		if (empty($update_data)) {
			return false;
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$format[] = '%d';

		return $this->wpdb->update(
			$this->campaigns_table,
			$update_data,
			array('id' => absint($campaign_id)),
			$format,
			array('%d')
		) !== false;
	}

	/**
	 * Get child templates by campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public function get_templates_by_campaign($campaign_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->templates_table} WHERE campaign_id = %d ORDER BY id ASC",
			absint($campaign_id)
		));
	}

	/**
	 * Get child schedules by campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public function get_schedules_by_campaign($campaign_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->schedule_table} WHERE campaign_id = %d ORDER BY id ASC",
			absint($campaign_id)
		));
	}

	/**
	 * Create a campaign-owned template.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $payload Wizard payload.
	 * @return int|false
	 */
	private function create_campaign_template($campaign_id, $payload) {
		$template_data = array(
			'name'                 => $payload['campaign_name'],
			'prompt_template'      => $this->resolve_prompt_template($payload),
			'title_prompt'         => $payload['title_prompt'],
			'voice_id'             => $payload['voice_id'],
			'post_quantity'        => 1,
			'post_status'          => $payload['post_status'],
			'post_type'            => $payload['post_type'],
			'post_category'        => $payload['post_category'],
			'post_tags'            => $payload['post_tags'],
			'post_author'          => $payload['post_author'],
			'campaign_id'          => $campaign_id,
			'is_active'            => 1,
		);

		if ('existing' === $payload['template_mode']) {
			$existing_template = $this->template_repository->get_by_id($payload['template_id']);
			if ($existing_template) {
				$template_data = array_merge(get_object_vars($existing_template), $template_data);
				unset($template_data['id'], $template_data['created_at'], $template_data['updated_at']);
			}
		}

		return $this->template_repository->create($template_data);
	}

	/**
	 * Resolve prompt template content from wizard state.
	 *
	 * @param array $payload Wizard payload.
	 * @return string
	 */
	private function resolve_prompt_template($payload) {
		if ('existing' === $payload['template_mode']) {
			$template = $this->template_repository->get_by_id($payload['template_id']);
			if ($template && !empty($payload['prompt_template'])) {
				return $payload['prompt_template'];
			}

			return $template ? $template->prompt_template : '';
		}

		return $payload['prompt_template'];
	}

	/**
	 * Start a DB transaction when supported.
	 *
	 * @return bool
	 */
	private function start_transaction() {
		return $this->wpdb->query('START TRANSACTION') !== false;
	}
}
