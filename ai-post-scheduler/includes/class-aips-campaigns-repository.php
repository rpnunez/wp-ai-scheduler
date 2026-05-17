<?php
/**
 * Campaigns Repository
 *
 * Repository for campaign-specific operations and metrics.
 * Campaigns are schedules created via the Campaign Wizard with enhanced metadata.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Campaigns_Repository {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
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
	 * @var string The schedule table name (with prefix)
	 */
	private $schedule_table;

	/**
	 * @var string The templates table name (with prefix)
	 */
	private $templates_table;

	/**
	 * @var string The history table name (with prefix)
	 */
	private $history_table;

	/**
	 * @var string The authors table name (with prefix)
	 */
	private $authors_table;

	/**
	 * @var wpdb WordPress database abstraction object
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->schedule_table = $wpdb->prefix . 'aips_schedule';
		$this->templates_table = $wpdb->prefix . 'aips_templates';
		$this->history_table = $wpdb->prefix . 'aips_history';
		$this->authors_table = $wpdb->prefix . 'aips_authors';
	}

	/**
	 * Get all campaigns with metrics.
	 *
	 * @param bool $active_only Optional. Return only active campaigns. Default false.
	 * @return array Array of campaign objects with metrics.
	 */
	public function get_all_campaigns($active_only = false) {
		$where = $active_only ? "WHERE s.is_active = 1" : "";

		return $this->wpdb->get_results("
			SELECT
				s.*,
				t.name as template_name,
				a.name as author_name,
				(SELECT COUNT(*) FROM {$this->history_table} h WHERE h.template_id = s.template_id AND h.status = 'completed') as posts_generated,
				(SELECT COUNT(*) FROM {$this->history_table} h WHERE h.template_id = s.template_id AND h.status IN ('failed', 'error')) as posts_failed
			FROM {$this->schedule_table} s
			LEFT JOIN {$this->templates_table} t ON s.template_id = t.id
			LEFT JOIN {$this->authors_table} a ON s.author_id = a.id
			{$where}
			ORDER BY s.created_at DESC
		");
	}

	/**
	 * Get campaign by schedule ID.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return object|null Campaign object with metrics or null.
	 */
	public function get_campaign_by_id($schedule_id) {
		return $this->wpdb->get_row($this->wpdb->prepare("
			SELECT
				s.*,
				t.name as template_name,
				a.name as author_name,
				(SELECT COUNT(*) FROM {$this->history_table} h WHERE h.template_id = s.template_id AND h.status = 'completed') as posts_generated,
				(SELECT COUNT(*) FROM {$this->history_table} h WHERE h.template_id = s.template_id AND h.status IN ('failed', 'error')) as posts_failed
			FROM {$this->schedule_table} s
			LEFT JOIN {$this->templates_table} t ON s.template_id = t.id
			LEFT JOIN {$this->authors_table} a ON s.author_id = a.id
			WHERE s.id = %d
		", $schedule_id));
	}

	/**
	 * Get campaign metrics.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return array Campaign metrics array.
	 */
	public function get_campaign_metrics($schedule_id) {
		$schedule = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT template_id, last_run, created_at FROM {$this->schedule_table} WHERE id = %d",
			$schedule_id
		));

		if (!$schedule) {
			return array(
				'posts_generated'  => 0,
				'posts_failed'     => 0,
				'success_rate'     => 0,
				'last_run'         => null,
				'total_runs'       => 0,
			);
		}

		$posts_generated = (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table} WHERE template_id = %d AND status = 'completed'",
			$schedule->template_id
		));

		$posts_failed = (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table} WHERE template_id = %d AND status IN ('failed', 'error')",
			$schedule->template_id
		));

		$total_posts = $posts_generated + $posts_failed;
		$success_rate = $total_posts > 0 ? round(($posts_generated / $total_posts) * 100, 2) : 0;

		return array(
			'posts_generated'  => $posts_generated,
			'posts_failed'     => $posts_failed,
			'success_rate'     => $success_rate,
			'last_run'         => $schedule->last_run ? $schedule->last_run : null,
			'total_runs'       => $total_posts,
		);
	}

	/**
	 * Duplicate a campaign.
	 *
	 * @param int $schedule_id Schedule ID to duplicate.
	 * @return int|false New schedule ID or false on failure.
	 */
	public function duplicate_campaign($schedule_id) {
		$schedule = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->schedule_table} WHERE id = %d",
			$schedule_id
		));

		if (!$schedule) {
			return false;
		}

		$title = $schedule->title . ' (Copy)';
		$next_run = AIPS_DateTime::now()->timestamp();

		$result = $this->wpdb->insert(
			$this->schedule_table,
			array(
				'template_id'              => $schedule->template_id,
				'title'                    => $title,
				'article_structure_id'     => $schedule->article_structure_id,
				'rotation_pattern'         => $schedule->rotation_pattern,
				'frequency'                => $schedule->frequency,
				'topic'                    => $schedule->topic,
				'next_run'                 => $next_run,
				'is_active'                => 0,
				'status'                   => $schedule->status,
				'schedule_type'            => $schedule->schedule_type,
				'author_id'                => $schedule->author_id,
				'campaign_mode'            => $schedule->campaign_mode,
				'post_type_rules'          => $schedule->post_type_rules,
				'blackout_dates'           => $schedule->blackout_dates,
				'time_window_start'        => $schedule->time_window_start,
				'time_window_end'          => $schedule->time_window_end,
				'day_preferences'          => $schedule->day_preferences,
				'season_end_date'          => $schedule->season_end_date,
				'dynamic_quantity_rules'   => $schedule->dynamic_quantity_rules,
				'campaign_metadata'        => $schedule->campaign_metadata,
				'created_at'               => AIPS_DateTime::now()->timestamp(),
			),
			array(
				'%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s',
				'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d'
			)
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Archive a campaign (soft delete via status).
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return bool True on success, false on failure.
	 */
	public function archive_campaign($schedule_id) {
		return $this->wpdb->update(
			$this->schedule_table,
			array('status' => 'archived', 'is_active' => 0),
			array('id' => $schedule_id),
			array('%s', '%d'),
			array('%d')
		) !== false;
	}

	/**
	 * Get campaign statistics summary.
	 *
	 * @return array Summary statistics.
	 */
	public function get_summary_stats() {
		$stats = $this->wpdb->get_row("
			SELECT
				COUNT(*) as total_campaigns,
				SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_campaigns,
				SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_campaigns
			FROM {$this->schedule_table}
		");

		return array(
			'total'    => isset($stats->total_campaigns) ? (int) $stats->total_campaigns : 0,
			'active'   => isset($stats->active_campaigns) ? (int) $stats->active_campaigns : 0,
			'archived' => isset($stats->archived_campaigns) ? (int) $stats->archived_campaigns : 0,
		);
	}
}
