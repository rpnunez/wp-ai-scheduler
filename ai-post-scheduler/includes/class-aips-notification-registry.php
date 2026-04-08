<?php
/**
 * Notification Type Registry
 *
 * Centralises all static registry data for AIPS notification types:
 * the full type registry, the high-priority subset, and the channel-mode
 * options used on the Settings page.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notification_Registry
 */
class AIPS_Notification_Registry {

	// -----------------------------------------------------------------------
	// Channel-mode constants (mirrors AIPS_Notifications for single source of
	// truth; AIPS_Notifications keeps its own copies for backward compat).
	// -----------------------------------------------------------------------

	/** Disable all delivery for a notification type. */
	const MODE_OFF = 'off';

	/** Persist only in the DB. */
	const MODE_DB_ONLY = 'db';

	/** Send only email notifications. */
	const MODE_EMAIL_ONLY = 'email';

	/** Send to both DB and email. */
	const MODE_BOTH = 'both';

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Return the full notification type registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_type_registry() {
		return array(
			'author_topics_generated' => array(
				'label'        => __('Author Topics Generated', 'ai-post-scheduler'),
				'description'  => __('New author topics are available for review in the admin area.', 'ai-post-scheduler'),
				'default_mode' => self::MODE_DB_ONLY,
				'level'        => 'info',
			),
			'generation_failed' => array(
				'label'         => __('Generation Failed', 'ai-post-scheduler'),
				'description'   => __('A manual or direct post generation request failed.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 900,
			),
			'quota_alert' => array(
				'label'         => __('Quota Alert', 'ai-post-scheduler'),
				'description'   => __('The AI provider is rejecting requests because usage limits or circuit protection were reached.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
			'integration_error' => array(
				'label'         => __('Integration Error', 'ai-post-scheduler'),
				'description'   => __('The AI Engine dependency is unavailable or misconfigured.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
			'scheduler_error' => array(
				'label'         => __('Scheduler Error', 'ai-post-scheduler'),
				'description'   => __('A scheduled automation run failed or could not obtain its execution lock.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 900,
			),
			'system_error' => array(
				'label'         => __('System Error', 'ai-post-scheduler'),
				'description'   => __('A plugin-level operational error occurred during activation, upgrades, or cron execution.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
			'template_generated' => array(
				'label'         => __('Template Generated', 'ai-post-scheduler'),
				'description'   => __('A scheduled template run generated one or more posts.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 60,
			),
			'manual_generation_completed' => array(
				'label'         => __('Manual Generation Completed', 'ai-post-scheduler'),
				'description'   => __('A manually triggered generation request completed successfully.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 60,
			),
			'post_ready_for_review' => array(
				'label'         => __('Post Ready For Review', 'ai-post-scheduler'),
				'description'   => __('A generated post is waiting for editorial review.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 60,
			),
			'post_rejected' => array(
				'label'         => __('Post Rejected', 'ai-post-scheduler'),
				'description'   => __('A generated draft was removed from the review queue.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'warning',
				'dedupe_window' => 120,
			),
			'partial_generation_completed' => array(
				'label'         => __('Partial Generation Completed', 'ai-post-scheduler'),
				'description'   => __('A post was saved with missing generated components and needs follow-up.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'warning',
				'dedupe_window' => 60,
			),
			'daily_digest' => array(
				'label'         => __('Daily Digest', 'ai-post-scheduler'),
				'description'   => __('Daily summary of generation and review activity.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_EMAIL_ONLY,
				'level'         => 'info',
				'dedupe_window' => 3600,
			),
			'weekly_summary' => array(
				'label'         => __('Weekly Summary', 'ai-post-scheduler'),
				'description'   => __('Weekly summary of generation performance and workflow activity.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_EMAIL_ONLY,
				'level'         => 'info',
				'dedupe_window' => 3600,
			),
			'monthly_report' => array(
				'label'         => __('Monthly Report', 'ai-post-scheduler'),
				'description'   => __('Monthly generation and operational report.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_EMAIL_ONLY,
				'level'         => 'info',
				'dedupe_window' => 3600,
			),
			'history_cleanup' => array(
				'label'         => __('History Cleanup', 'ai-post-scheduler'),
				'description'   => __('Operational cleanup completed.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
			),
			'seeder_complete' => array(
				'label'         => __('Seeder Completed', 'ai-post-scheduler'),
				'description'   => __('Seeder operation finished successfully.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
			),
			'template_change' => array(
				'label'         => __('Template Changed', 'ai-post-scheduler'),
				'description'   => __('A template was created, updated, cloned, or deleted.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 180,
			),
			'author_suggestions' => array(
				'label'         => __('Author Suggestions Ready', 'ai-post-scheduler'),
				'description'   => __('AI-generated author profile suggestions are available.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
			),
			'circuit_breaker_opened' => array(
				'label'         => __('Circuit Breaker Opened', 'ai-post-scheduler'),
				'description'   => __('The circuit breaker has tripped — AI requests are temporarily blocked after repeated failures.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'error',
				'dedupe_window' => 1800,
			),
			'rate_limit_reached' => array(
				'label'         => __('Rate Limit Reached', 'ai-post-scheduler'),
				'description'   => __('The internal AI request rate limit has been reached. Requests are being paused.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_BOTH,
				'level'         => 'warning',
				'dedupe_window' => 900,
			),
			'research_topics_ready' => array(
				'label'         => __('Research Topics Ready', 'ai-post-scheduler'),
				'description'   => __('Scheduled research completed and new trending topics are available.', 'ai-post-scheduler'),
				'default_mode'  => self::MODE_DB_ONLY,
				'level'         => 'info',
				'dedupe_window' => 300,
			),
		);
	}

	/**
	 * Return only the high-priority notification types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_high_priority_types() {
		$registry = self::get_type_registry();

		return array_intersect_key(
			$registry,
			array_flip(array(
				'generation_failed',
				'quota_alert',
				'integration_error',
				'scheduler_error',
				'system_error',
				'template_generated',
				'manual_generation_completed',
				'post_ready_for_review',
				'post_rejected',
				'partial_generation_completed',
				'circuit_breaker_opened',
				'rate_limit_reached',
			))
		);
	}

	/**
	 * Return the available channel modes for settings UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_channel_mode_options() {
		return array(
			self::MODE_OFF        => __('Off', 'ai-post-scheduler'),
			self::MODE_DB_ONLY    => __('DB only', 'ai-post-scheduler'),
			self::MODE_EMAIL_ONLY => __('Email only', 'ai-post-scheduler'),
			self::MODE_BOTH       => __('DB + Email', 'ai-post-scheduler'),
		);
	}
}
