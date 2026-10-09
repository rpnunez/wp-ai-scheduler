<?php
/**
 * Background Process Base
 *
 * Shared snapshot shape and status vocabulary for background process adapters.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

abstract class AIPS_Background_Process_Base implements AIPS_Background_Process_Interface {

	/**
	 * Status reported when a process has no open run.
	 */
	const STATUS_IDLE = 'idle';

	/**
	 * Localized labels for every status a snapshot can report.
	 *
	 * @return array<string,string>
	 */
	public static function get_status_labels(): array {
		return array(
			'idle'          => __('Idle', 'ai-post-scheduler'),
			'running'       => __('Running', 'ai-post-scheduler'),
			'waiting_quota' => __('Waiting for quota', 'ai-post-scheduler'),
			'cooldown'      => __('Cooldown', 'ai-post-scheduler'),
			'paused'        => __('Paused', 'ai-post-scheduler'),
			'completed'     => __('Completed', 'ai-post-scheduler'),
			'failed'        => __('Failed', 'ai-post-scheduler'),
			'cancelled'     => __('Stopped', 'ai-post-scheduler'),
		);
	}

	/**
	 * Whether a status means work is in flight (running or waiting to retry).
	 *
	 * @param string $status Snapshot status.
	 * @return bool
	 */
	public static function is_active_status(string $status): bool {
		return in_array($status, array('running', 'waiting_quota', 'cooldown'), true);
	}

	/**
	 * Build a normalized snapshot, deriving the control flags from the status.
	 *
	 * Keys: key, label, description, uses_ai, status, status_label, is_active,
	 * can_start, can_pause, can_resume, can_cancel, processed, total, failed,
	 * percent, ai_calls_used, ai_calls_budget, message, next_run_at, started_at,
	 * updated_at, run_id, last_status, last_finished_at.
	 *
	 * @param array $data Raw values; unknown keys are ignored, missing keys default.
	 * @return array
	 */
	protected function make_snapshot(array $data): array {
		$status = isset($data['status']) ? (string) $data['status'] : self::STATUS_IDLE;
		$labels = self::get_status_labels();

		$processed = isset($data['processed']) ? max(0, (int) $data['processed']) : 0;
		$total     = isset($data['total']) ? max(0, (int) $data['total']) : 0;
		$percent   = $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : 0;

		if ($status === 'completed') {
			$percent = 100;
		}

		$is_active = self::is_active_status($status);
		$is_open   = $is_active || $status === 'paused';

		return array(
			'key'              => $this->get_key(),
			'label'            => $this->get_label(),
			'description'      => $this->get_description(),
			'uses_ai'          => $this->uses_ai(),
			'status'           => $status,
			'status_label'     => isset($labels[$status]) ? $labels[$status] : $status,
			'is_active'        => $is_active,
			'can_start'        => !$is_open,
			'can_pause'        => $is_active,
			'can_resume'       => $status === 'paused',
			'can_cancel'       => $is_open,
			'processed'        => $processed,
			'total'            => $total,
			'failed'           => isset($data['failed']) ? max(0, (int) $data['failed']) : 0,
			'percent'          => $percent,
			'ai_calls_used'    => isset($data['ai_calls_used']) ? max(0, (int) $data['ai_calls_used']) : 0,
			'ai_calls_budget'  => isset($data['ai_calls_budget']) ? max(0, (int) $data['ai_calls_budget']) : 0,
			'message'          => isset($data['message']) ? (string) $data['message'] : '',
			'next_run_at'      => isset($data['next_run_at']) ? max(0, (int) $data['next_run_at']) : 0,
			'started_at'       => isset($data['started_at']) ? max(0, (int) $data['started_at']) : 0,
			'updated_at'       => isset($data['updated_at']) ? max(0, (int) $data['updated_at']) : 0,
			'run_id'           => isset($data['run_id']) ? (int) $data['run_id'] : 0,
			'last_status'      => isset($data['last_status']) ? (string) $data['last_status'] : '',
			'last_finished_at' => isset($data['last_finished_at']) ? max(0, (int) $data['last_finished_at']) : 0,
		);
	}

	/**
	 * Default: no estimate.
	 *
	 * @return array
	 */
	public function get_estimate(): array {
		return array();
	}

	/**
	 * Default: the process does not use AI.
	 *
	 * @return bool
	 */
	public function uses_ai(): bool {
		return false;
	}
}
