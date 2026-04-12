<?php
/**
 * Schedule Entry DTO
 *
 * Immutable value object that wraps a row from the `aips_schedule` DB table.
 * Provides typed, IDE-completable access to all schedule fields instead of
 * ad-hoc `$row->next_run` / `$row['next_run']` property lookups scattered
 * throughout the codebase.
 *
 * Usage:
 *   $entry = AIPS_Schedule_Entry::from_row( $wpdb_row_object );
 *   echo $entry->next_run;          // '2025-01-15 08:00:00'
 *   echo $entry->frequency;         // 'daily'
 *   echo $entry->template_name;     // populated when row comes from a JOIN query
 *
 * `AIPS_Bulk_Generation_Result` is the existing public-readonly precedent.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Schedule_Entry
 *
 * Immutable value object representing one row from `aips_schedule`.
 * All properties are public readonly.
 */
class AIPS_Schedule_Entry {

	// -----------------------------------------------------------------------
	// Core schedule fields
	// -----------------------------------------------------------------------

	/**
	 * Primary key.
	 *
	 * @var int
	 */
	public readonly int $id;

	/**
	 * Foreign key to `aips_templates`.
	 *
	 * @var int
	 */
	public readonly int $template_id;

	/**
	 * Optional human-readable label for this schedule entry.
	 *
	 * @var string|null
	 */
	public readonly ?string $title;

	/**
	 * Optional FK to `aips_article_structures`.
	 *
	 * @var int|null
	 */
	public readonly ?int $article_structure_id;

	/**
	 * Rotation pattern for multi-topic schedules (e.g. 'sequential', 'random').
	 *
	 * @var string|null
	 */
	public readonly ?string $rotation_pattern;

	/**
	 * Frequency identifier (e.g. 'daily', 'weekly', 'hourly').
	 *
	 * @var string
	 */
	public readonly string $frequency;

	/**
	 * Optional topic text to inject into generation prompts.
	 *
	 * @var string|null
	 */
	public readonly ?string $topic;

	/**
	 * Datetime of the next scheduled run (MySQL format).
	 *
	 * @var string
	 */
	public readonly string $next_run;

	/**
	 * Datetime of the last completed run, or null if never run (MySQL format).
	 *
	 * @var string|null
	 */
	public readonly ?string $last_run;

	/**
	 * Whether this schedule is active.
	 *
	 * @var bool
	 */
	public readonly bool $is_active;

	/**
	 * Operational status string (e.g. 'active', 'paused').
	 *
	 * @var string
	 */
	public readonly string $status;

	/**
	 * Optional FK to the history record for the most recent run.
	 *
	 * @var int|null
	 */
	public readonly ?int $schedule_history_id;

	/**
	 * Schedule type discriminator (e.g. 'post_generation', 'topic_generation').
	 *
	 * @var string
	 */
	public readonly string $schedule_type;

	/**
	 * Circuit-breaker state ('closed', 'open', 'half_open').
	 *
	 * @var string
	 */
	public readonly string $circuit_state;

	/**
	 * Serialised run-state blob (JSON string or null).
	 *
	 * @var string|null
	 */
	public readonly ?string $run_state;

	/**
	 * Serialised batch-progress blob (JSON string or null).
	 *
	 * @var string|null
	 */
	public readonly ?string $batch_progress;

	/**
	 * Row creation datetime (MySQL format).
	 *
	 * @var string
	 */
	public readonly string $created_at;

	// -----------------------------------------------------------------------
	// Joined / virtual fields
	// -----------------------------------------------------------------------

	/**
	 * Template name populated by JOIN queries (e.g. from get_all() / get_upcoming()).
	 *
	 * Null when the row was fetched without a JOIN.
	 *
	 * @var string|null
	 */
	public readonly ?string $template_name;

	// -----------------------------------------------------------------------
	// Constructor (private — use from_row())
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param int         $id                   Schedule primary key.
	 * @param int         $template_id          FK to templates table.
	 * @param string|null $title                Optional label.
	 * @param int|null    $article_structure_id FK to article structures table.
	 * @param string|null $rotation_pattern     Multi-topic rotation strategy.
	 * @param string      $frequency            Frequency identifier.
	 * @param string|null $topic                Optional topic text.
	 * @param string      $next_run             Next run datetime (MySQL).
	 * @param string|null $last_run             Last run datetime (MySQL) or null.
	 * @param bool        $is_active            Active flag.
	 * @param string      $status               Operational status.
	 * @param int|null    $schedule_history_id  FK to history for last run.
	 * @param string      $schedule_type        Type discriminator.
	 * @param string      $circuit_state        Circuit-breaker state.
	 * @param string|null $run_state            Serialised run-state.
	 * @param string|null $batch_progress       Serialised batch-progress.
	 * @param string      $created_at           Row creation datetime (MySQL).
	 * @param string|null $template_name        Joined template name, if available.
	 */
	private function __construct(
		int $id,
		int $template_id,
		?string $title,
		?int $article_structure_id,
		?string $rotation_pattern,
		string $frequency,
		?string $topic,
		string $next_run,
		?string $last_run,
		bool $is_active,
		string $status,
		?int $schedule_history_id,
		string $schedule_type,
		string $circuit_state,
		?string $run_state,
		?string $batch_progress,
		string $created_at,
		?string $template_name
	) {
		$this->id                   = $id;
		$this->template_id          = $template_id;
		$this->title                = $title;
		$this->article_structure_id = $article_structure_id;
		$this->rotation_pattern     = $rotation_pattern;
		$this->frequency            = $frequency;
		$this->topic                = $topic;
		$this->next_run             = $next_run;
		$this->last_run             = $last_run;
		$this->is_active            = $is_active;
		$this->status               = $status;
		$this->schedule_history_id  = $schedule_history_id;
		$this->schedule_type        = $schedule_type;
		$this->circuit_state        = $circuit_state;
		$this->run_state            = $run_state;
		$this->batch_progress       = $batch_progress;
		$this->created_at           = $created_at;
		$this->template_name        = $template_name;
	}

	// -----------------------------------------------------------------------
	// Factory
	// -----------------------------------------------------------------------

	/**
	 * Build an instance from a DB row object returned by wpdb.
	 *
	 * Handles the loose typing produced by wpdb (all values arrive as strings
	 * or null) and coerces them to the correct PHP types.
	 *
	 * @param object $row A stdClass row from aips_schedule (optionally with template_name from JOIN).
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->template_id,
			isset( $row->title ) && $row->title !== '' ? (string) $row->title : null,
			isset( $row->article_structure_id ) && $row->article_structure_id !== null ? (int) $row->article_structure_id : null,
			isset( $row->rotation_pattern ) && $row->rotation_pattern !== '' ? (string) $row->rotation_pattern : null,
			(string) ( $row->frequency ?? 'daily' ),
			isset( $row->topic ) && $row->topic !== '' ? (string) $row->topic : null,
			(string) $row->next_run,
			isset( $row->last_run ) && $row->last_run !== '' ? (string) $row->last_run : null,
			isset( $row->is_active ) && $row->is_active !== null ? 1 === (int) $row->is_active : true,
			(string) ( $row->status ?? 'active' ),
			isset( $row->schedule_history_id ) && $row->schedule_history_id !== null ? (int) $row->schedule_history_id : null,
			(string) ( $row->schedule_type ?? 'post_generation' ),
			(string) ( $row->circuit_state ?? 'closed' ),
			isset( $row->run_state ) && $row->run_state !== '' ? (string) $row->run_state : null,
			isset( $row->batch_progress ) && $row->batch_progress !== '' ? (string) $row->batch_progress : null,
			(string) ( $row->created_at ?? '' ),
			isset( $row->template_name ) && $row->template_name !== '' ? (string) $row->template_name : null
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Whether this schedule is overdue (next_run is in the past).
	 *
	 * @param string|null $current_time Optional. MySQL-format current time. Defaults to now.
	 * @return bool
	 */
	public function is_due( ?string $current_time = null ): bool {
		if ( $current_time === null ) {
			$current_time = current_time( 'mysql' );
		}
		return $this->next_run <= $current_time;
	}

	/**
	 * Whether the circuit breaker is open (schedule is currently suspended).
	 *
	 * @return bool
	 */
	public function is_circuit_open(): bool {
		return $this->circuit_state === 'open';
	}
}
