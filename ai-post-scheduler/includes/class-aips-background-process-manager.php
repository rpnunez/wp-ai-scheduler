<?php
/**
 * Background Process Manager
 *
 * Registry and control surface for every background process: resolves
 * adapters by key, applies start/pause/resume/stop, routes cron ticks, and
 * builds the snapshots shown in the Diagnostics tab, the admin bar and the
 * Heartbeat updates.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Background_Process_Manager {

	const ACTION_START  = 'start';
	const ACTION_PAUSE  = 'pause';
	const ACTION_RESUME = 'resume';
	const ACTION_CANCEL = 'cancel';

	/**
	 * Transient holding the cached snapshot list used for page-load rendering.
	 */
	const SUMMARY_TRANSIENT = 'aips_bg_summary';

	/**
	 * Seconds the page-load summary may be stale. Live pages refresh through Heartbeat.
	 */
	const SUMMARY_TTL = 15;

	/**
	 * Adapters built so far, keyed by process key.
	 *
	 * @var array<string,AIPS_Background_Process_Interface>
	 */
	private $processes = array();

	/**
	 * Process keys mapped to their adapter classes.
	 *
	 * @return array<string,string>
	 */
	private function get_process_classes(): array {
		$classes = array(
			AIPS_Internal_Links_Indexing_Process::KEY => 'AIPS_Internal_Links_Indexing_Process',
			AIPS_Content_Indexer_Queue_Process::KEY   => 'AIPS_Content_Indexer_Queue_Process',
			AIPS_Link_Index_Scan_Process::KEY         => 'AIPS_Link_Index_Scan_Process',
			AIPS_Relationships_Recompute_Process::KEY => 'AIPS_Relationships_Recompute_Process',
			AIPS_Author_Embeddings_Process::KEY       => 'AIPS_Author_Embeddings_Process',
			AIPS_Bulk_Generation_Process::KEY         => 'AIPS_Bulk_Generation_Process',
		);

		/**
		 * Filters the registered background processes.
		 *
		 * @param array<string,string> $classes Process key => class implementing AIPS_Background_Process_Interface.
		 */
		return (array) apply_filters('aips_background_processes', $classes);
	}

	/**
	 * All registered processes, keyed by process key.
	 *
	 * @return array<string,AIPS_Background_Process_Interface>
	 */
	public function all(): array {
		$all = array();

		foreach (array_keys($this->get_process_classes()) as $key) {
			$process = $this->get((string) $key);
			if ($process) {
				$all[$process->get_key()] = $process;
			}
		}

		return $all;
	}

	/**
	 * Resolve one process, building only that adapter.
	 *
	 * @param string $key Process key.
	 * @return AIPS_Background_Process_Interface|null
	 */
	public function get(string $key): ?AIPS_Background_Process_Interface {
		$key = sanitize_key($key);

		if (isset($this->processes[$key])) {
			return $this->processes[$key];
		}

		$classes = $this->get_process_classes();
		if (!isset($classes[$key]) || !is_string($classes[$key]) || !class_exists($classes[$key])) {
			return null;
		}

		$class   = $classes[$key];
		$process = new $class();
		if (!$process instanceof AIPS_Background_Process_Interface) {
			return null;
		}

		$this->processes[$key] = $process;

		return $process;
	}

	/**
	 * Apply a control action to one process.
	 *
	 * @param string $key     Process key.
	 * @param string $action  One of the ACTION_* constants.
	 * @param array  $options Options for ACTION_START.
	 * @return array|WP_Error The process snapshot after the action.
	 */
	public function control(string $key, string $action, array $options = array()) {
		$process = $this->get($key);
		if (!$process) {
			return new WP_Error('aips_bg_unknown_process', __('Unknown background process.', 'ai-post-scheduler'));
		}

		switch ($action) {
			case self::ACTION_START:
				$result = $process->start($options);
				if (is_wp_error($result)) {
					return $result;
				}
				break;

			case self::ACTION_PAUSE:
				if (!$process->pause()) {
					return new WP_Error('aips_bg_cannot_pause', __('This process is not running, so it cannot be paused.', 'ai-post-scheduler'));
				}
				break;

			case self::ACTION_RESUME:
				if (!$process->resume()) {
					return new WP_Error('aips_bg_cannot_resume', __('This process is not paused, so it cannot be resumed.', 'ai-post-scheduler'));
				}
				break;

			case self::ACTION_CANCEL:
				if (!$process->cancel()) {
					return new WP_Error('aips_bg_cannot_cancel', __('This process is not running or paused, so there is nothing to stop.', 'ai-post-scheduler'));
				}
				break;

			default:
				return new WP_Error('aips_bg_unknown_action', __('Unknown action.', 'ai-post-scheduler'));
		}

		$this->flush_summary();

		return $process->get_snapshot();
	}

	/**
	 * Pause every process that is currently running.
	 *
	 * @return string[] Keys of the processes that were paused.
	 */
	public function pause_all(): array {
		$paused = array();

		foreach ($this->all() as $key => $process) {
			$snapshot = $process->get_snapshot();
			if (!empty($snapshot['can_pause']) && $process->pause()) {
				$paused[] = $key;
			}
		}

		$this->flush_summary();

		return $paused;
	}

	/**
	 * Cron entry point: run one slice of a managed process.
	 *
	 * @param string $key Process key.
	 * @return void
	 */
	public function tick(string $key): void {
		$process = $this->get($key);

		if ($process instanceof AIPS_Managed_Background_Process) {
			$process->tick();
			$this->flush_summary();
		}
	}

	/**
	 * Fresh snapshots of every process.
	 *
	 * @return array[]
	 */
	public function get_snapshots(): array {
		$snapshots = array();

		foreach ($this->all() as $process) {
			$snapshots[] = $process->get_snapshot();
		}

		return $snapshots;
	}

	/**
	 * Snapshots briefly cached for page-load rendering (admin bar, tab shells).
	 *
	 * @return array[]
	 */
	public function get_summary(): array {
		$cached = get_transient(self::SUMMARY_TRANSIENT);
		if (is_array($cached)) {
			return $cached;
		}

		$snapshots = $this->get_snapshots();
		set_transient(self::SUMMARY_TRANSIENT, $snapshots, self::SUMMARY_TTL);

		return $snapshots;
	}

	/**
	 * Forget the cached page-load summary.
	 *
	 * @return void
	 */
	public function flush_summary(): void {
		delete_transient(self::SUMMARY_TRANSIENT);
	}

	/**
	 * Payload sent to the browser on every Heartbeat tick.
	 *
	 * Also re-queues any running managed process whose cron event went missing,
	 * so an open admin page keeps stalled work moving.
	 *
	 * @return array{processes:array[], active_count:int, server_time:int}
	 */
	public function get_heartbeat_payload(): array {
		foreach ($this->all() as $process) {
			if (method_exists($process, 'ensure_scheduled')) {
				$process->ensure_scheduled();
			}
		}

		$snapshots = $this->get_snapshots();
		set_transient(self::SUMMARY_TRANSIENT, $snapshots, self::SUMMARY_TTL);

		return array(
			'processes'    => $snapshots,
			'active_count' => count(self::filter_active($snapshots)),
			'server_time'  => time(),
		);
	}

	/**
	 * Only the snapshots that are running or waiting to retry.
	 *
	 * @param array[] $snapshots Snapshots.
	 * @return array[]
	 */
	public static function filter_active(array $snapshots): array {
		return array_values(array_filter($snapshots, function ($snapshot) {
			return !empty($snapshot['is_active']);
		}));
	}

	/**
	 * Heartbeat filter: answer the browser's request for process state.
	 *
	 * @param array $response Heartbeat response.
	 * @param array $data     Data sent by the browser.
	 * @return array
	 */
	public function on_heartbeat_received($response, $data) {
		if (empty($data['aips_bg']) || !current_user_can('manage_options')) {
			return $response;
		}

		$response['aips_bg'] = $this->get_heartbeat_payload();

		return $response;
	}
}
