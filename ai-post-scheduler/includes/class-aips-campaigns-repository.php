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
	 * @var string
	 */
	private $history_log_table;

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repository;

	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * @var AIPS_Cache|null
	 */
	private $cache;

	/**
	 * @var bool
	 */
	private $cache_initialized;

	/**
	 * @var int
	 */
	private const CAMPAIGN_CACHE_TTL = 43200;

	/**
	 * @var string
	 */
	private const CAMPAIGN_CACHE_GROUP = 'campaigns_repository';

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
	public function __construct($template_repository = null, $schedule_repository = null) {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->campaigns_table = $wpdb->prefix . 'aips_campaigns';
		$this->templates_table = $wpdb->prefix . 'aips_templates';
		$this->schedule_table = $wpdb->prefix . 'aips_schedule';
		$this->history_table = $wpdb->prefix . 'aips_history';
		$this->history_log_table = $wpdb->prefix . 'aips_history_log';
		$this->template_repository = $template_repository ?: AIPS_Template_Repository::instance();
		$this->schedule_repository = $schedule_repository ?: AIPS_Schedule_Repository::instance();
		$this->cache = null;
		$this->cache_initialized = false;
	}

	/**
	 * Fetch campaigns with ownership and generation metrics.
	 *
	 * @param bool|null $archived Optional archive filter.
	 * @param int|null  $campaign_id Optional campaign ID filter.
	 * @return array
	 */
	public function get_campaigns($archived = null, $campaign_id = null) {
		$where_clauses = array();
		$where_args = array();

		if ($archived !== null) {
			$where_clauses[] = 'c.is_archived = %d';
			$where_args[] = $archived ? 1 : 0;
		}

		if ($campaign_id !== null) {
			$campaign_id = absint($campaign_id);
			if (!$campaign_id) {
				return array();
			}

			$where_clauses[] = 'c.id = %d';
			$where_args[] = $campaign_id;
		}

		$where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);
		$order_sql = null === $campaign_id ? 'ORDER BY c.is_archived ASC, c.created_at DESC' : '';

		$sql = $this->build_campaign_metrics_query($where_sql, $order_sql);

		$rows = empty($where_args)
			? $this->wpdb->get_results($sql)
			: $this->wpdb->get_results($this->wpdb->prepare($sql, $where_args));

		if (empty($rows)) {
			return array();
		}

		foreach ($rows as $row) {
			$this->normalize_campaign_row($row);
			$this->set_cached_campaign_row($row);
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

		$cached_campaign = $this->get_cached_campaign_row($campaign_id);
		if ($cached_campaign !== null) {
			return $cached_campaign;
		}

		$sql = $this->build_campaign_metrics_query('WHERE c.id = %d');
		$rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $campaign_id));
		if (empty($rows)) {
			return null;
		}

		$row = $rows[0];
		$this->normalize_campaign_row($row);
		$this->set_cached_campaign_row($row);

		return $row;
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
	 * Get health counters and warning inputs for one campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public function get_campaign_health($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return array(
				'failed_generation_count' => 0,
				'pending_review_count' => 0,
				'inactive_schedule_count' => 0,
				'empty_template_prompt_count' => 0,
				'has_future_run' => false,
				'failed_last_run' => false,
			);
		}

		$posts_table = $this->wpdb->posts;
		$now = AIPS_DateTime::now()->timestamp();

		$history_stats = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT
				SUM(CASE WHEN h.status = 'failed' THEN 1 ELSE 0 END) AS failed_generation_count,
				COUNT(DISTINCT CASE WHEN h.status = 'completed' AND h.post_id IS NOT NULL AND p.post_status IN ('draft', 'pending') THEN h.post_id END) AS pending_review_count
			FROM {$this->history_table} h
			LEFT JOIN {$posts_table} p ON h.post_id = p.ID
			WHERE h.campaign_id = %d",
			$campaign_id
		));

		$schedule_stats = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT
				SUM(CASE WHEN is_active = 0 OR status != 'active' THEN 1 ELSE 0 END) AS inactive_schedule_count,
				SUM(CASE WHEN is_active = 1 AND next_run > %d THEN 1 ELSE 0 END) AS future_run_count
			FROM {$this->schedule_table}
			WHERE campaign_id = %d",
			$now,
			$campaign_id
		));

		$empty_prompt_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->templates_table} WHERE campaign_id = %d AND TRIM(COALESCE(prompt_template, '')) = ''",
			$campaign_id
		));

		$last_history_status = $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT status FROM {$this->history_table} WHERE campaign_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
			$campaign_id
		));

		return array(
			'failed_generation_count' => isset($history_stats->failed_generation_count) ? (int) $history_stats->failed_generation_count : 0,
			'pending_review_count' => isset($history_stats->pending_review_count) ? (int) $history_stats->pending_review_count : 0,
			'inactive_schedule_count' => isset($schedule_stats->inactive_schedule_count) ? (int) $schedule_stats->inactive_schedule_count : 0,
			'empty_template_prompt_count' => $empty_prompt_count,
			'has_future_run' => isset($schedule_stats->future_run_count) && (int) $schedule_stats->future_run_count > 0,
			'failed_last_run' => 'failed' === $last_history_status,
		);
	}

	/**
	 * Get recent campaign activity across history and history log rows.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $limit Number of activity rows.
	 * @return array
	 */
	public function get_recent_activity($campaign_id, $limit = 10) {
		$campaign_id = absint($campaign_id);
		$limit = max(1, min(50, absint($limit)));
		if (!$campaign_id) {
			return array();
		}

		$sql = "
			(SELECT
				'history' AS activity_source,
				h.id AS activity_id,
				h.id AS history_id,
				h.status AS activity_type,
				COALESCE(NULLIF(h.generated_title, ''), NULLIF(h.error_message, ''), h.creation_method, 'History entry') AS activity_details,
				h.created_at AS activity_timestamp,
				h.post_id AS post_id,
				h.template_id AS template_id
			FROM {$this->history_table} h
			WHERE h.campaign_id = %d)
			UNION ALL
			(SELECT
				'history_log' AS activity_source,
				l.id AS activity_id,
				h.id AS history_id,
				l.log_type AS activity_type,
				COALESCE(NULLIF(l.details, ''), 'Log entry') AS activity_details,
				l.timestamp AS activity_timestamp,
				h.post_id AS post_id,
				h.template_id AS template_id
			FROM {$this->history_log_table} l
			INNER JOIN {$this->history_table} h ON l.history_id = h.id
			WHERE h.campaign_id = %d)
			ORDER BY activity_timestamp DESC, activity_id DESC
			LIMIT %d
		";

		return $this->wpdb->get_results($this->wpdb->prepare($sql, $campaign_id, $campaign_id, $limit));
	}

	/**
	 * Get recent generated posts attributed to a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $limit Number of posts.
	 * @return array
	 */
	public function get_recent_generated_posts($campaign_id, $limit = 10) {
		$campaign_id = absint($campaign_id);
		$limit = max(1, min(50, absint($limit)));
		if (!$campaign_id) {
			return array();
		}

		$posts_table = $this->wpdb->posts;

		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT
				h.id AS history_id,
				h.post_id,
				h.generated_title,
				h.created_at,
				h.completed_at,
				p.post_title,
				p.post_status,
				p.post_date,
				p.post_modified,
				t.name AS template_name
			FROM {$this->history_table} h
			INNER JOIN (
				SELECT post_id, MAX(id) AS latest_history_id
				FROM {$this->history_table}
				WHERE campaign_id = %d
				AND status = 'completed'
				AND post_id IS NOT NULL
				GROUP BY post_id
			) latest ON latest.latest_history_id = h.id
			INNER JOIN {$posts_table} p ON h.post_id = p.ID
			LEFT JOIN {$this->templates_table} t ON h.template_id = t.id
			ORDER BY COALESCE(NULLIF(h.completed_at, 0), h.created_at) DESC, h.id DESC
			LIMIT %d",
			$campaign_id,
			$limit
		));
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
	 * @return array{campaign_id:int,template_id:int,schedule_id:int}|WP_Error
	 */
	public function create_campaign_bundle($payload) {
		$started_transaction = $this->start_transaction();
		$campaign_id = $this->create_campaign(array(
			'name'          => $payload['campaign_name'],
			'content_goal'  => $payload['content_goal'],
			'campaign_mode' => $payload['campaign_mode'],
			'is_active'     => $payload['is_active'],
			'is_archived'   => 0,
		));

		if (!$campaign_id) {
			return $this->rollback_with_error(
				$started_transaction,
				$this->build_campaign_error('campaign_create_failed', __('Campaign could not be saved.', 'ai-post-scheduler'))
			);
		}

		$template_id = $this->create_campaign_template($campaign_id, $payload);
		if (is_wp_error($template_id)) {
			return $this->rollback_with_error($started_transaction, $template_id);
		}

		if (!$template_id) {
			return $this->rollback_with_error(
				$started_transaction,
				$this->build_campaign_error('campaign_template_create_failed', __('Template could not be saved.', 'ai-post-scheduler'))
			);
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

		if (is_wp_error($schedule_id)) {
			return $this->rollback_with_error($started_transaction, $schedule_id);
		}

		if (!$schedule_id) {
			return $this->rollback_with_error(
				$started_transaction,
				$this->build_campaign_error('campaign_schedule_create_failed', __('Schedule could not be created.', 'ai-post-scheduler'))
			);
		}

		if ($started_transaction) {
			$this->wpdb->query('COMMIT');
		}

		$this->flush_campaign_cache($campaign_id);

		return array(
			'campaign_id' => (int) $campaign_id,
			'template_id' => (int) $template_id,
			'schedule_id' => (int) $schedule_id,
		);
	}

	/**
	 * Duplicate a campaign as a new parent with new child rows.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|WP_Error
	 */
	public function duplicate_campaign($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return $this->build_campaign_error('invalid_campaign_id', __('Invalid campaign ID.', 'ai-post-scheduler'));
		}

		$campaign = $this->get_campaign_by_id($campaign_id);
		if (!$campaign) {
			return $this->build_campaign_error('campaign_not_found', __('Campaign not found.', 'ai-post-scheduler'));
		}

		$templates = $this->get_templates_by_campaign($campaign_id);
		$schedules = $this->get_schedules_by_campaign($campaign_id);
		$started_transaction = $this->start_transaction();

		$new_campaign_id = $this->create_campaign(array(
			'name'          => $campaign->name . ' ' . __('(Copy)', 'ai-post-scheduler'),
			'content_goal'  => $campaign->content_goal,
			'campaign_mode' => $campaign->campaign_mode,
			'is_active'     => 0,
			'is_archived'   => 0,
		));

		if (!$new_campaign_id) {
			return $this->rollback_with_error(
				$started_transaction,
				$this->build_campaign_error('campaign_duplicate_failed', __('Campaign could not be duplicated.', 'ai-post-scheduler'))
			);
		}

		$template_map = array();
		foreach ($templates as $template) {
			$template_data = get_object_vars($template);
			unset($template_data['id'], $template_data['created_at'], $template_data['updated_at']);
			$template_data['name'] = $template->name . ' ' . __('(Copy)', 'ai-post-scheduler');
			$template_data['campaign_id'] = $new_campaign_id;
			$new_template_id = $this->template_repository->create($template_data);

			if (!$new_template_id) {
				return $this->rollback_with_error(
					$started_transaction,
					$this->build_campaign_error('campaign_template_duplicate_failed', __('Campaign template could not be duplicated.', 'ai-post-scheduler'))
				);
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
			if (is_wp_error($new_schedule_id)) {
				return $this->rollback_with_error($started_transaction, $new_schedule_id);
			}

			if (!$new_schedule_id) {
				return $this->rollback_with_error(
					$started_transaction,
					$this->build_campaign_error('campaign_schedule_duplicate_failed', __('Campaign schedule could not be duplicated.', 'ai-post-scheduler'))
				);
			}
		}

		if ($started_transaction) {
			$this->wpdb->query('COMMIT');
		}

		$this->flush_campaign_cache($new_campaign_id);

		return (int) $new_campaign_id;
	}

	/**
	 * Set campaign operational active state.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $is_active Active flag.
	 * @return bool|WP_Error
	 */
	public function set_active($campaign_id, $is_active) {
		$campaign_id = absint($campaign_id);
		$is_active = $is_active ? 1 : 0;
		if (!$campaign_id) {
			return $this->build_campaign_error('invalid_campaign_id', __('Invalid campaign ID.', 'ai-post-scheduler'));
		}

		$campaign = $this->get_campaign_by_id($campaign_id);
		if (!$campaign) {
			return $this->build_campaign_error('campaign_not_found', __('Campaign not found.', 'ai-post-scheduler'));
		}

		if ((int) $campaign->is_active === $is_active && (int) $campaign->is_archived === 0) {
			return true;
		}

		$result = $this->update_campaign($campaign_id, array('is_active' => $is_active));
		if (is_wp_error($result)) {
			return $result;
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
	 * @return bool|WP_Error
	 */
	public function archive_campaign($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return $this->build_campaign_error('invalid_campaign_id', __('Invalid campaign ID.', 'ai-post-scheduler'));
		}

		$campaign = $this->get_campaign_by_id($campaign_id);
		if (!$campaign) {
			return $this->build_campaign_error('campaign_not_found', __('Campaign not found.', 'ai-post-scheduler'));
		}

		if ((int) $campaign->is_archived === 1 && (int) $campaign->is_active === 0) {
			return true;
		}

		$result = $this->update_campaign($campaign_id, array(
			'is_active' => 0,
			'is_archived' => 1,
		));

		if (is_wp_error($result)) {
			return $result;
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
	 * @return bool|WP_Error
	 */
	public function restore_campaign($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return $this->build_campaign_error('invalid_campaign_id', __('Invalid campaign ID.', 'ai-post-scheduler'));
		}

		$campaign = $this->get_campaign_by_id($campaign_id);
		if (!$campaign) {
			return $this->build_campaign_error('campaign_not_found', __('Campaign not found.', 'ai-post-scheduler'));
		}

		if ((int) $campaign->is_archived === 0 && (int) $campaign->is_active === 0) {
			return true;
		}

		return $this->update_campaign($campaign_id, array(
			'is_archived' => 0,
			'is_active' => 0,
		));
	}

	/**
	 * Delete a campaign when no generated posts are attached.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool|WP_Error
	 */
	public function delete_campaign($campaign_id) {
		$campaign_id = absint($campaign_id);

		if (!$campaign_id) {
			return $this->build_campaign_error('invalid_campaign_id', __('Invalid campaign ID.', 'ai-post-scheduler'));
		}

		if (!$this->can_delete_campaign($campaign_id)) {
			return $this->build_campaign_error('delete_blocked', __('This campaign has generated posts and can only be archived.', 'ai-post-scheduler'));
		}

		$started_transaction = $this->start_transaction();

		foreach ($this->get_schedules_by_campaign($campaign_id) as $schedule) {
			if (!$this->schedule_repository->delete((int) $schedule->id)) {
				return $this->rollback_with_error(
					$started_transaction,
					$this->build_campaign_error('campaign_schedule_delete_failed', __('Failed to delete campaign schedule.', 'ai-post-scheduler'))
				);
			}
		}

		foreach ($this->get_templates_by_campaign($campaign_id) as $template) {
			if (!$this->template_repository->delete((int) $template->id)) {
				return $this->rollback_with_error(
					$started_transaction,
					$this->build_campaign_error('campaign_template_delete_failed', __('Failed to delete campaign template.', 'ai-post-scheduler'))
				);
			}
		}

		$deleted = $this->wpdb->delete(
			$this->campaigns_table,
			array('id' => $campaign_id),
			array('%d')
		);

		if ($deleted === false) {
			return $this->rollback_with_error(
				$started_transaction,
				$this->build_campaign_error('campaign_delete_failed', __('Campaign could not be deleted.', 'ai-post-scheduler'))
			);
		}

		if ($started_transaction) {
			$this->wpdb->query('COMMIT');
		}

		$this->flush_campaign_cache($campaign_id);

		return true;
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
	 * Count historical attribution rows attached to a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	public function get_generated_post_count($campaign_id) {
		return (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table} WHERE campaign_id = %d",
			absint($campaign_id)
		));
	}

	/**
	 * Invalidate one campaign cache entry.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	public function flush_campaign_cache($campaign_id) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return;
		}

		$cache = $this->get_cache();
		if (!$cache) {
			return;
		}

		try {
			$cache->delete(
				$this->get_campaign_cache_key($campaign_id),
				self::CAMPAIGN_CACHE_GROUP
			);
		} catch (Throwable $e) {
			return;
		}
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

		if (!$result) {
			return false;
		}

		$campaign_id = (int) $this->wpdb->insert_id;
		$this->flush_campaign_cache($campaign_id);

		return $campaign_id;
	}

	/**
	 * Update a campaign parent row.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $data Update data.
	 * @return bool|WP_Error
	 */
	public function update_campaign($campaign_id, $data) {
		$campaign_id = absint($campaign_id);
		if (!$campaign_id) {
			return $this->build_campaign_error('invalid_campaign_id', __('Invalid campaign ID.', 'ai-post-scheduler'));
		}

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
			return true;
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$format[] = '%d';

		$updated = $this->wpdb->update(
			$this->campaigns_table,
			$update_data,
			array('id' => $campaign_id),
			$format,
			array('%d')
		);

		if ($updated === false) {
			return $this->build_campaign_error('campaign_update_failed', __('Campaign could not be updated.', 'ai-post-scheduler'));
		}

		if ($updated !== false) {
			$this->flush_campaign_cache($campaign_id);
		}

		return true;
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
	 * @return int|WP_Error|false
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
	 * Build a user-safe campaign mutation error.
	 *
	 * @param string $code Error code.
	 * @param string $message Display message.
	 * @return WP_Error
	 */
	private function build_campaign_error($code, $message) {
		$error = new WP_Error($code, $message);

		if (!empty($this->wpdb->last_error)) {
			$error->add_data(array('db_error' => $this->wpdb->last_error));
		}

		return $error;
	}

	/**
	 * Roll back transaction when active and return error.
	 *
	 * @param bool     $started_transaction Whether transaction started here.
	 * @param WP_Error $error Error to return.
	 * @return WP_Error
	 */
	private function rollback_with_error($started_transaction, WP_Error $error) {
		if ($started_transaction) {
			$this->wpdb->query('ROLLBACK');
		}

		return $error;
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

	/**
	 * Normalize aggregate fields on a fetched campaign row.
	 *
	 * @param object $row Campaign row.
	 * @return void
	 */
	private function normalize_campaign_row($row) {
		$row->generated_posts_count = (int) $row->generated_posts_count;
		$row->total_history_count = (int) $row->total_history_count;
		$row->linked_template_count = (int) $row->linked_template_count;
		$row->linked_schedule_count = (int) $row->linked_schedule_count;
		$row->active_schedule_count = (int) $row->active_schedule_count;
		$row->primary_template_id = !empty($row->primary_template_id) ? (int) $row->primary_template_id : null;
		$row->primary_schedule_id = !empty($row->primary_schedule_id) ? (int) $row->primary_schedule_id : null;
		$row->can_delete = 0 === $row->total_history_count;
	}

	/**
	 * Build the aggregate campaign query.
	 *
	 * @param string $where_sql WHERE clause.
	 * @param string $order_sql ORDER clause.
	 * @return string
	 */
	private function build_campaign_metrics_query($where_sql = '', $order_sql = '') {
		return "
			SELECT
				c.*,
				COALESCE(t.linked_template_count, 0) AS linked_template_count,
				COALESCE(s.linked_schedule_count, 0) AS linked_schedule_count,
				COALESCE(h.generated_posts_count, 0) AS generated_posts_count,
				COALESCE(h.total_history_count, 0) AS total_history_count,
				s.last_run AS last_run,
				s.next_run AS next_run,
				COALESCE(s.active_schedule_count, 0) AS active_schedule_count,
				t.primary_template_id AS primary_template_id,
				s.primary_schedule_id AS primary_schedule_id
			FROM {$this->campaigns_table} c
			LEFT JOIN (
				SELECT campaign_id, COUNT(*) AS linked_template_count, MIN(id) AS primary_template_id
				FROM {$this->templates_table}
				WHERE campaign_id IS NOT NULL
				GROUP BY campaign_id
			) t ON t.campaign_id = c.id
			LEFT JOIN (
				SELECT
					campaign_id,
					COUNT(*) AS linked_schedule_count,
					SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_schedule_count,
					MAX(last_run) AS last_run,
					MIN(CASE WHEN is_active = 1 AND next_run > 0 THEN next_run END) AS next_run,
					MIN(id) AS primary_schedule_id
				FROM {$this->schedule_table}
				WHERE campaign_id IS NOT NULL
				GROUP BY campaign_id
			) s ON s.campaign_id = c.id
			LEFT JOIN (
				SELECT
					campaign_id,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS generated_posts_count,
					COUNT(*) AS total_history_count
				FROM {$this->history_table}
				WHERE campaign_id IS NOT NULL
				GROUP BY campaign_id
			) h ON h.campaign_id = c.id
			{$where_sql}
			{$order_sql}
		";
	}

	/**
	 * Build campaign cache key.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	private function get_campaign_cache_key($campaign_id) {
		return 'aips_campaign_' . absint($campaign_id) . '_data';
	}

	/**
	 * Get cache instance for campaign metrics.
	 *
	 * @return AIPS_Cache|null
	 */
	private function get_cache() {
		if ($this->cache_initialized) {
			return $this->cache;
		}

		try {
			$this->cache = AIPS_Cache_Factory::make('db');
		} catch (Throwable $e) {
			$this->cache = null;
			if (class_exists('AIPS_Logger')) {
				AIPS_Logger::instance()->warning('Failed to initialize DB cache for campaign metrics; continuing without cache.', array(
					'error' => $e->getMessage(),
					'exception_class' => get_class($e),
				));
			}
		}

		$this->cache_initialized = true;

		return $this->cache;
	}

	/**
	 * Read one cached campaign row.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return object|null
	 */
	private function get_cached_campaign_row($campaign_id) {
		$cache = $this->get_cache();
		if (!$cache) {
			return null;
		}

		try {
			$cached_row = $cache->get(
				$this->get_campaign_cache_key($campaign_id),
				self::CAMPAIGN_CACHE_GROUP
			);
		} catch (Throwable $e) {
			return null;
		}

		if (is_array($cached_row)) {
			$cached_row = (object) $cached_row;
		}

		if (!is_object($cached_row)) {
			return null;
		}

		$required_keys = array(
			'id',
			'generated_posts_count',
			'total_history_count',
			'linked_template_count',
			'linked_schedule_count',
			'active_schedule_count',
			'primary_template_id',
			'primary_schedule_id',
		);
		foreach ($required_keys as $required_key) {
			if (!property_exists($cached_row, $required_key)) {
				return null;
			}
		}

		$this->normalize_campaign_row($cached_row);

		return $cached_row;
	}

	/**
	 * Store one campaign row in cache.
	 *
	 * @param object $row Campaign row.
	 * @return void
	 */
	private function set_cached_campaign_row($row) {
		if (!isset($row->id)) {
			return;
		}

		$cache = $this->get_cache();
		if (!$cache) {
			return;
		}

		try {
			$cache->set(
				$this->get_campaign_cache_key((int) $row->id),
				$row,
				self::CAMPAIGN_CACHE_TTL,
				self::CAMPAIGN_CACHE_GROUP
			);
		} catch (Throwable $e) {
			return;
		}
	}
}
