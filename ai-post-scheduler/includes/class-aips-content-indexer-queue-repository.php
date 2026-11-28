<?php
/**
 * Content Indexer Queue Repository
 *
 * Encapsulates persistence, atomic concurrency, token-owned claims, and
 * background-process session accounting for the debounced Content Indexer Queue.
 *
 * Reuses `aips_background_processes` (via AIPS_Background_Process_Repository)
 * for session history and keeps legacy queue options (`aips_pending_index_queue`
 * and `aips_pending_topic_index_queue`) synchronized for backward compatibility.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Indexer_Queue_Repository {

	/**
	 * Process key stored in `aips_background_processes`.
	 */
	const PROCESS_KEY = 'content_indexer_queue';

	/**
	 * Option holding the structured queue items and versions.
	 */
	const STATE_OPTION = 'aips_indexer_queue_state';

	/**
	 * Legacy post queue option (flat int[] of pending and in-flight post IDs).
	 */
	const LEGACY_POST_OPTION = 'aips_pending_index_queue';

	/**
	 * Legacy topic queue option (flat int[] of pending and in-flight topic IDs).
	 */
	const LEGACY_TOPIC_OPTION = 'aips_pending_topic_index_queue';

	/**
	 * Option flag set while an administrator has paused the background queue.
	 */
	const PAUSED_OPTION = 'aips_indexer_queue_paused';

	/**
	 * Atomic lock key protecting queue state mutations.
	 */
	const MUTATION_LOCK = 'indexer_queue_mutation';

	/**
	 * Seconds an in-flight claim may be held before it is considered expired and retryable.
	 */
	const CLAIM_TTL = 300;

	/**
	 * Item outcomes passed to acknowledge_item().
	 */
	const OUTCOME_SUCCESS   = 'success';
	const OUTCOME_FAILED    = 'failed';
	const OUTCOME_SKIPPED   = 'skipped';
	const OUTCOME_RETRYABLE = 'retryable';

	/**
	 * @var AIPS_Background_Process_Repository
	 */
	private $run_repository;

	/**
	 * @var int Maximum lock acquisition attempts for mutating callbacks.
	 */
	private $lock_max_attempts = 260;

	/**
	 * @var int Microseconds to sleep between lock acquisition retries.
	 */
	private $lock_retry_sleep_us = 20000;

	/**
	 * @param AIPS_Background_Process_Repository|null $run_repository Background process repository.
	 */
	public function __construct(?AIPS_Background_Process_Repository $run_repository = null) {
		$this->run_repository = $run_repository ?: new AIPS_Background_Process_Repository();
	}

	/**
	 * Configure lock retry limits (primarily for deterministic contention tests).
	 *
	 * @param int $max_attempts   Maximum retry attempts before invoking fallback.
	 * @param int $retry_sleep_us Microseconds between attempts.
	 * @return void
	 */
	public function set_lock_retry_policy(int $max_attempts, int $retry_sleep_us): void {
		$this->lock_max_attempts   = max(1, $max_attempts);
		$this->lock_retry_sleep_us = max(0, $retry_sleep_us);
	}

	/**
	 * @return AIPS_Background_Process_Repository
	 */
	public function get_run_repository(): AIPS_Background_Process_Repository {
		return $this->run_repository;
	}

	/**
	 * Enqueue one or more items of a given entity type ('post' or 'topic').
	 *
	 * Starts a new session when the queue has no open session, or increases the
	 * open session's total when distinct new work (or a later edit to an already
	 * processed or in-flight item) arrives. Repeated enqueueing of an already
	 * pending item does not increase the session total.
	 *
	 * @param string $type 'post' or 'topic'.
	 * @param int[]  $ids  Entity IDs.
	 * @return array{added_to_total:int, run_id:int, pending_count:int}
	 */
	public function enqueue_items(string $type, array $ids): array {
		$type = ($type === 'topic') ? 'topic' : 'post';

		$clean_ids = array();
		foreach ($ids as $id) {
			$id = absint($id);
			if ($id > 0) {
				$clean_ids[] = $id;
			}
		}
		$clean_ids = array_values(array_unique($clean_ids));

		if (empty($clean_ids)) {
			$status = $this->get_status(false);
			return array(
				'added_to_total' => 0,
				'run_id'         => $status['run_id'],
				'pending_count'  => $status['pending_count'],
			);
		}

		return $this->with_lock(function () use ($type, $clean_ids) {
			$now   = time();
			$state = $this->load_and_reconcile_state($now, true);
			$run   = $this->get_open_session_from_state($state);

			$added_to_total = 0;

			foreach ($clean_ids as $id) {
				$key = $type . ':' . $id;

				if (!isset($state['items'][$key])) {
					$state['items'][$key] = array(
						'type'             => $type,
						'id'               => $id,
						'state'            => 'pending',
						'version'          => 1,
						'claimed_version'  => 0,
						'resolved_version' => 0,
						'pending_counted'  => true,
						'claim_token'      => '',
						'claimed_at'       => 0,
						'updated_at'       => $now,
					);
					$added_to_total++;
					continue;
				}

				$item = $state['items'][$key];

				if ($item['state'] === 'pending') {
					// Already pending: duplicate enqueue does not increase total.
					if (empty($item['pending_counted'])) {
						$item['pending_counted'] = true;
						$added_to_total++;
					}
					$state['items'][$key] = $item;
					continue;
				}

				if ($item['state'] === 'in_flight') {
					// Edit arriving while the item is in flight: mark a newer version so it
					// remains queued for another pass after the in-flight pass acknowledges.
					if (empty($item['pending_counted'])) {
						$item['version']         = max((int) $item['version'], (int) $item['claimed_version']) + 1;
						$item['pending_counted'] = true;
						$item['updated_at']      = $now;
						$added_to_total++;
					}
					$state['items'][$key] = $item;
					continue;
				}

				// Previously resolved item edited again in the same open session: new work.
				$item['state']           = 'pending';
				$item['version']         = max((int) $item['version'], (int) $item['resolved_version']) + 1;
				$item['claimed_version'] = 0;
				$item['pending_counted'] = true;
				$item['claim_token']     = '';
				$item['claimed_at']      = 0;
				$item['updated_at']      = $now;
				$state['items'][$key]    = $item;
				$added_to_total++;
			}

			if (!$run && $added_to_total > 0) {
				$run = $this->create_session_for_state($state, $added_to_total);
			} elseif ($run && $added_to_total > 0) {
				$options                      = is_array($run->options) ? $run->options : array();
				$options['total_increased']   = true;
				$options['last_increase_cnt'] = $added_to_total;
				$this->run_repository->update_options($run->id, $options);
				$this->run_repository->add_total(
					$run->id,
					$added_to_total,
					__('New items were added to the queue; total and percentage updated.', 'ai-post-scheduler')
				);
				$run = $this->run_repository->get($run->id);
			}

			$this->save_state($state);

			$counts = $this->count_items($state);

			return array(
				'added_to_total' => $added_to_total,
				'run_id'         => $run ? (int) $run->id : 0,
				'pending_count'  => $counts['pending_count'],
			);
		}, function () {
			$state  = $this->load_and_reconcile_state(time(), false);
			$counts = $this->count_items($state);
			$open   = $this->run_repository->get_open(self::PROCESS_KEY);
			return array(
				'added_to_total' => 0,
				'run_id'         => $open ? (int) $open->id : 0,
				'pending_count'  => $counts['pending_count'],
			);
		});
	}

	/**
	 * Adopt pre-existing legacy queue items into an open session when needed.
	 *
	 * Called on explicit control actions or worker startup so pre-existing items
	 * stored in `aips_pending_index_queue` or `aips_pending_topic_index_queue`
	 * are tracked under a session without inventing historical completed counts.
	 *
	 * @return object|null Open session row, or null when no work is queued.
	 */
	public function adopt_or_get_open_session(): ?object {
		return $this->with_lock(function () {
			$now   = time();
			$dirty = false;
			$state = $this->load_and_reconcile_state($now, true, $dirty);
			if ($dirty) {
				$this->save_state($state);
			}

			return $this->get_open_session_from_state($state);
		}, function () {
			return $this->run_repository->get_open(self::PROCESS_KEY);
		});
	}

	/**
	 * Claim up to $limit pending items for processing by a worker.
	 *
	 * Posts are claimed first, followed by topics up to the remaining capacity.
	 * Claims are atomic and token-owned; no database lock is held while the worker
	 * makes AI requests.
	 *
	 * @param int         $limit        Maximum items to claim in this slice.
	 * @param string|null $worker_token Optional caller token; generated when omitted.
	 * @return array{token:string, run_id:int, items:array<int,array{key:string,type:string,id:int,claimed_version:int}>}
	 */
	public function claim_slice(int $limit, ?string $worker_token = null): array {
		$limit = max(1, $limit);
		$token = ($worker_token !== null && $worker_token !== '')
			? $worker_token
			: 'w_' . wp_generate_password(16, false, false) . '_' . time();

		return $this->with_lock(function () use ($limit, $token) {
			$now   = time();
			$state = $this->load_and_reconcile_state($now, true);
			$run   = $this->get_open_session_from_state($state);

			$claimed = array();

			foreach (array('post', 'topic') as $target_type) {
				foreach ($state['items'] as $key => $item) {
					if (count($claimed) >= $limit) {
						break 2;
					}
					if ($item['type'] !== $target_type || $item['state'] !== 'pending') {
						continue;
					}

					$claimed_version               = max(1, (int) $item['version']);
					$item['state']                 = 'in_flight';
					$item['claimed_version']       = $claimed_version;
					$item['pending_counted']       = false;
					$item['claim_token']           = $token;
					$item['claimed_at']            = $now;
					$item['updated_at']            = $now;
					$state['items'][$key]          = $item;

					$claimed[] = array(
						'key'             => $key,
						'type'            => $item['type'],
						'id'              => (int) $item['id'],
						'claimed_version' => $claimed_version,
					);
				}
			}

			$counts = $this->count_items($state);
			if (empty($claimed) && $counts['outstanding_count'] === 0 && $run) {
				$terminal = (int) $run->processed > 0
					? AIPS_Background_Process_Repository::STATUS_COMPLETED
					: AIPS_Background_Process_Repository::STATUS_CANCELLED;
				$this->run_repository->transition(
					$run->id,
					$terminal,
					AIPS_Background_Process_Repository::open_statuses(),
					(int) $run->processed > 0 ? __('Finished.', 'ai-post-scheduler') : __('Queue cleared.', 'ai-post-scheduler')
				);
				$state['items']      = array();
				$state['session_id'] = 0;
				$run                 = null;
			} elseif (!empty($claimed) && $run && $run->status !== AIPS_Background_Process_Repository::STATUS_RUNNING && !$this->is_paused()) {
				$this->run_repository->transition(
					$run->id,
					AIPS_Background_Process_Repository::STATUS_RUNNING,
					AIPS_Background_Process_Repository::active_statuses(),
					null,
					$now
				);
			}

			$this->save_state($state);

			return array(
				'token'  => $token,
				'run_id' => $run ? (int) $run->id : 0,
				'items'  => $claimed,
			);
		}, function () use ($token) {
			return array(
				'token'  => $token,
				'run_id' => 0,
				'items'  => array(),
			);
		});
	}

	/**
	 * Check whether a worker still owns an active in-flight claim on an open session.
	 *
	 * Returns false if an administrator stopped/cleared the queue (or another worker
	 * reclaimed an expired claim) while the current slice was running.
	 *
	 * @param string $token           Worker claim token.
	 * @param string $type            'post' or 'topic'.
	 * @param int    $id              Entity ID.
	 * @param int    $claimed_version Claimed version.
	 * @return bool
	 */
	public function has_active_claim(string $token, string $type, int $id, int $claimed_version): bool {
		$this->refresh_option_cache(array(self::STATE_OPTION));
		$raw = get_option(self::STATE_OPTION, null);
		if (!is_array($raw) || empty($raw['items']) || !is_array($raw['items'])) {
			return false;
		}

		$key  = (($type === 'topic') ? 'topic' : 'post') . ':' . absint($id);
		$item = isset($raw['items'][$key]) && is_array($raw['items'][$key]) ? $raw['items'][$key] : null;

		return $item !== null
			&& ($item['state'] ?? '') === 'in_flight'
			&& ($item['claim_token'] ?? '') === $token
			&& (int) ($item['claimed_version'] ?? 0) === $claimed_version;
	}

	/**
	 * Acknowledge the outcome of a claimed item and commit session progress atomically.
	 *
	 * Duplicate acknowledgements for the same claim token/version are harmless.
	 * If an edit arrived while the item was in flight (`version > claimed_version`),
	 * the completed pass is recorded on the session while the item returns to
	 * `pending` for its next pass.
	 *
	 * @param string      $token           Worker token that claimed the item.
	 * @param string      $type            'post' or 'topic'.
	 * @param int         $id              Entity ID.
	 * @param int         $claimed_version Version claimed by claim_slice().
	 * @param string      $outcome         One of OUTCOME_SUCCESS, OUTCOME_FAILED, OUTCOME_SKIPPED, OUTCOME_RETRYABLE.
	 * @param int         $ai_calls_delta  Provider AI calls made for this item attempt.
	 * @param string|null $message         Optional status message.
	 * @return array{acknowledged:bool, completed_session:bool, remaining:int}
	 */
	public function acknowledge_item(
		string $token,
		string $type,
		int $id,
		int $claimed_version,
		string $outcome,
		int $ai_calls_delta = 0,
		?string $message = null
	): array {
		$type = ($type === 'topic') ? 'topic' : 'post';
		$id   = absint($id);
		$key  = $type . ':' . $id;

		return $this->with_lock(function () use ($token, $key, $claimed_version, $outcome, $ai_calls_delta, $message) {
			$now   = time();
			$state = $this->load_and_reconcile_state($now, false);
			$run   = $this->get_open_session_from_state($state);

			if (!isset($state['items'][$key])) {
				$counts = $this->count_items($state);
				return array(
					'acknowledged'      => false,
					'completed_session' => false,
					'remaining'         => $counts['outstanding_count'],
				);
			}

			$item = $state['items'][$key];

			// Verify token and claimed version ownership; ignore duplicate or stale acknowledgements.
			if (
				$item['state'] !== 'in_flight'
				|| $item['claim_token'] !== $token
				|| (int) $item['claimed_version'] !== $claimed_version
			) {
				$counts = $this->count_items($state);
				return array(
					'acknowledged'      => false,
					'completed_session' => false,
					'remaining'         => $counts['outstanding_count'],
				);
			}

			$processed_delta = 0;
			$failed_delta    = 0;

			if ($outcome === self::OUTCOME_RETRYABLE) {
				// Return item to pending without counting it as resolved.
				// If an edit arrived while in-flight, collapse pending_counted back into the
				// single pending item and adjust total so the item is not counted twice.
				$extra_edit_counted      = !empty($item['pending_counted']) && (int) $item['version'] > $claimed_version;
				$item['state']           = 'pending';
				$item['claimed_version'] = 0;
				$item['pending_counted'] = true;
				$item['claim_token']     = '';
				$item['claimed_at']      = 0;
				$item['updated_at']      = $now;
				$state['items'][$key]    = $item;

				if ($run) {
					if ($extra_edit_counted) {
						// Collapse the extra total increment since the first pass was not resolved.
						$this->run_repository->add_total($run->id, -1);
					}
					if ($ai_calls_delta > 0 || $message !== null) {
						$this->run_repository->add_progress(
							$run->id,
							0,
							0,
							0,
							max(0, $ai_calls_delta),
							$message !== null ? $message : (string) $run->message
						);
					}
				}
			} else {
				// Resolved: success, permanently failed, or deliberately skipped.
				$processed_delta = 1;
				$failed_delta    = ($outcome === self::OUTCOME_FAILED) ? 1 : 0;

				$item['resolved_version'] = $claimed_version;
				$item['claim_token']      = '';
				$item['claimed_at']       = 0;
				$item['updated_at']       = $now;

				if ((int) $item['version'] > $claimed_version) {
					// Edited while in flight: remain queued for another pass!
					$item['state']           = 'pending';
					$item['claimed_version'] = 0;
					$item['pending_counted'] = true;
				} else {
					$item['state']           = 'resolved';
					$item['claimed_version'] = 0;
					$item['pending_counted'] = false;
				}

				$state['items'][$key] = $item;

				if ($run) {
					$counts_after = $this->count_items($state);
					$progress_msg = $message;
					if ($progress_msg === null) {
						$progress_msg = $this->build_session_progress_message($run, $counts_after['outstanding_count']);
					}

					$this->run_repository->add_progress(
						$run->id,
						$processed_delta,
						$failed_delta,
						0,
						max(0, $ai_calls_delta),
						$progress_msg
					);
				}
			}

			$counts            = $this->count_items($state);
			$completed_session = false;

			// Complete the session only when pending and in-flight work are both empty.
			if ($counts['pending_count'] === 0 && $counts['in_flight_count'] === 0) {
				if ($run) {
					$this->run_repository->transition(
						$run->id,
						AIPS_Background_Process_Repository::STATUS_COMPLETED,
						AIPS_Background_Process_Repository::open_statuses(),
						__('Finished.', 'ai-post-scheduler')
					);
					$completed_session = true;
				}
				// Clear resolved items from active state map once the session closes.
				$state['items']      = array();
				$state['session_id'] = 0;
				delete_option(self::PAUSED_OPTION);
			}

			$this->save_state($state);

			return array(
				'acknowledged'      => true,
				'completed_session' => $completed_session,
				'remaining'         => $counts['outstanding_count'],
			);
		}, function () {
			$state  = $this->load_and_reconcile_state(time(), false);
			$counts = $this->count_items($state);
			return array(
				'acknowledged'      => false,
				'completed_session' => false,
				'remaining'         => $counts['outstanding_count'],
			);
		});
	}

	/**
	 * Release a list of claimed items back to pending (for example when a slice aborts early).
	 *
	 * @param string $token Worker claim token.
	 * @param array  $items Claimed item descriptors from claim_slice().
	 * @return void
	 */
	public function release_claims(string $token, array $items): void {
		if (empty($items)) {
			return;
		}

		foreach ($items as $claimed) {
			if (empty($claimed['type']) || empty($claimed['id']) || empty($claimed['claimed_version'])) {
				continue;
			}
			$this->acknowledge_item(
				$token,
				(string) $claimed['type'],
				(int) $claimed['id'],
				(int) $claimed['claimed_version'],
				self::OUTCOME_RETRYABLE,
				0
			);
		}
	}

	/**
	 * Pause or unpause the queue while preserving the open session and its counters.
	 *
	 * @param bool $paused True to pause, false to resume.
	 * @return bool True when state changed.
	 */
	public function set_paused(bool $paused): bool {
		return $this->with_lock(function () use ($paused) {
			$now   = time();
			$state = $this->load_and_reconcile_state($now, true);
			$run   = $this->get_open_session_from_state($state);

			if ($paused) {
				update_option(self::PAUSED_OPTION, 1, false);
				if ($run) {
					$this->run_repository->transition(
						$run->id,
						AIPS_Background_Process_Repository::STATUS_PAUSED,
						AIPS_Background_Process_Repository::active_statuses(),
						__('Paused by an administrator.', 'ai-post-scheduler')
					);
				}
			} else {
				delete_option(self::PAUSED_OPTION);
				if ($run && $run->status === AIPS_Background_Process_Repository::STATUS_PAUSED) {
					$counts = $this->count_items($state);
					$this->run_repository->transition(
						$run->id,
						AIPS_Background_Process_Repository::STATUS_RUNNING,
						array(AIPS_Background_Process_Repository::STATUS_PAUSED),
						$this->build_session_progress_message($run, $counts['outstanding_count']),
						$now
					);
				}
			}

			$this->save_state($state);

			return true;
		}, function () {
			return false;
		});
	}

	/**
	 * Whether an administrator has paused the background queue.
	 *
	 * @return bool
	 */
	public function is_paused(): bool {
		$this->refresh_option_cache(array(self::PAUSED_OPTION));
		return (bool) get_option(self::PAUSED_OPTION, false);
	}

	/**
	 * Record a waiting state (cooldown or quota) on the open session row.
	 *
	 * @param string $status   STATUS_COOLDOWN or STATUS_WAITING_QUOTA.
	 * @param int    $retry_at Unix timestamp when the worker will retry.
	 * @param string $message  Explanation message.
	 * @return void
	 */
	public function mark_waiting(string $status, int $retry_at, string $message): void {
		$this->with_lock(function () use ($status, $retry_at, $message) {
			$now   = time();
			$state = $this->load_and_reconcile_state($now, true);
			$run   = $this->get_open_session_from_state($state);
			$this->save_state($state);

			if ($run && !$this->is_paused()) {
				$this->run_repository->transition(
					$run->id,
					$status,
					AIPS_Background_Process_Repository::active_statuses(),
					$message,
					$retry_at
				);
			}
		}, function () {
			return null;
		});
	}

	/**
	 * Cancel the current session and clear pending work (or only one object type).
	 *
	 * Retains completed work and recorded session counts in `aips_background_processes`
	 * so the finished session stays visible until another session starts.
	 *
	 * @param string $object_type Optional filter: '' (all), 'post', or 'topic'.
	 * @param bool   $cancel_run  Whether to transition an open session to STATUS_CANCELLED.
	 * @return void
	 */
	public function clear(string $object_type = '', bool $cancel_run = true): void {
		$this->with_lock(function () use ($object_type, $cancel_run) {
			$now   = time();
			$state = $this->load_and_reconcile_state($now, false);
			$run   = $this->get_open_session_from_state($state);

			if ($object_type === 'post' || $object_type === 'topic') {
				foreach ($state['items'] as $key => $item) {
					if ($item['type'] === $object_type) {
						unset($state['items'][$key]);
					}
				}
				$counts = $this->count_items($state);
				if ($counts['pending_count'] === 0 && $counts['in_flight_count'] === 0) {
					if ($run) {
						$terminal = (!$cancel_run && (int) $run->processed > 0)
							? AIPS_Background_Process_Repository::STATUS_COMPLETED
							: AIPS_Background_Process_Repository::STATUS_CANCELLED;
						$this->run_repository->transition(
							$run->id,
							$terminal,
							AIPS_Background_Process_Repository::open_statuses(),
							$cancel_run ? __('Stopped by an administrator.', 'ai-post-scheduler') : __('Queue cleared.', 'ai-post-scheduler')
						);
					}
					$state['items']      = array();
					$state['session_id'] = 0;
				}
				$this->save_state($state);
				if ($object_type === 'post') {
					delete_option(self::LEGACY_POST_OPTION);
				} else {
					delete_option(self::LEGACY_TOPIC_OPTION);
				}
				return;
			}

			if ($run) {
				$terminal = (!$cancel_run && (int) $run->processed > 0)
					? AIPS_Background_Process_Repository::STATUS_COMPLETED
					: AIPS_Background_Process_Repository::STATUS_CANCELLED;
				$this->run_repository->transition(
					$run->id,
					$terminal,
					AIPS_Background_Process_Repository::open_statuses(),
					$cancel_run ? __('Stopped by an administrator.', 'ai-post-scheduler') : __('Queue cleared.', 'ai-post-scheduler')
				);
			}

			delete_option(self::STATE_OPTION);
			delete_option(self::LEGACY_POST_OPTION);
			delete_option(self::LEGACY_TOPIC_OPTION);
			delete_option(self::PAUSED_OPTION);
		}, function () {
			return null;
		});
	}

	/**
	 * Read current queue and session status.
	 *
	 * If legacy pending items exist without an open session and $adopt is true,
	 * adopts them into a session automatically.
	 *
	 * @param bool $adopt Whether to adopt unowned pending items into a session.
	 * @return array{
	 *     pending_count:int,
	 *     pending_posts:int,
	 *     pending_topics:int,
	 *     in_flight_count:int,
	 *     in_flight_posts:int,
	 *     in_flight_topics:int,
	 *     outstanding_count:int,
	 *     run_id:int,
	 *     open_run:object|null,
	 *     latest_run:object|null,
	 *     total_increased:bool
	 * }
	 */
	public function get_status(bool $adopt = true): array {
		return $this->with_lock(function () use ($adopt) {
			$now   = time();
			$dirty = false;
			$state = $this->load_and_reconcile_state($now, $adopt, $dirty);
			if ($adopt && $dirty) {
				$this->save_state($state);
			}

			return $this->build_status_payload($state);
		}, function () {
			// Under lock contention, return a read-only snapshot without mutating options unlocked.
			$state = $this->load_and_reconcile_state(time(), false);
			return $this->build_status_payload($state);
		}, min($this->lock_max_attempts, 15), 10000);
	}

	/**
	 * Build the status response array from a reconciled queue state.
	 *
	 * @param array $state Reconciled state.
	 * @return array
	 */
	private function build_status_payload(array $state): array {
		$counts     = $this->count_items($state);
		$open_run   = $this->get_open_session_from_state($state);
		$latest_run = $open_run ?: $this->run_repository->get_latest(self::PROCESS_KEY);

		$total_increased = false;
		if ($open_run && is_array($open_run->options) && !empty($open_run->options['total_increased'])) {
			$total_increased = true;
		}

		return array(
			'pending_count'     => $counts['pending_count'],
			'pending_posts'     => $counts['pending_posts'],
			'pending_topics'    => $counts['pending_topics'],
			'in_flight_count'   => $counts['in_flight_count'],
			'in_flight_posts'   => $counts['in_flight_posts'],
			'in_flight_topics'  => $counts['in_flight_topics'],
			'outstanding_count' => $counts['outstanding_count'],
			'run_id'            => $open_run ? (int) $open_run->id : 0,
			'open_run'          => $open_run,
			'latest_run'        => $latest_run,
			'total_increased'   => $total_increased,
		);
	}

	// -------------------------------------------------------------------------
	// Internal state management
	// -------------------------------------------------------------------------

	/**
	 * Load state from options, recover expired claims, reconcile with legacy
	 * queue options (`aips_pending_index_queue` and `aips_pending_topic_index_queue`),
	 * and optionally adopt unowned pending items into a session.
	 *
	 * @param int  $now   Current unix timestamp.
	 * @param bool $adopt Whether to create/update a session for adopted items.
	 * @param bool $dirty Output flag set to true when reconciled state differs from stored options.
	 * @return array{session_id:int, items:array<string,array>}
	 */
	private function load_and_reconcile_state(int $now, bool $adopt, bool &$dirty = false): array {
		$this->refresh_option_cache(array(
			self::STATE_OPTION,
			self::LEGACY_POST_OPTION,
			self::LEGACY_TOPIC_OPTION,
		));

		$raw               = get_option(self::STATE_OPTION, null);
		$has_state_option  = is_array($raw);

		$state = array(
			'session_id' => 0,
			'items'      => array(),
		);

		if ($has_state_option) {
			$state['session_id'] = isset($raw['session_id']) ? absint($raw['session_id']) : 0;
			if (isset($raw['items']) && is_array($raw['items'])) {
				$state['items'] = $raw['items'];
			}
		}

		// 1. Recover expired in-flight claims without counting completion twice.
		$has_in_flight = false;
		foreach ($state['items'] as $key => $item) {
			if (!is_array($item) || empty($item['type']) || empty($item['id'])) {
				unset($state['items'][$key]);
				$dirty = true;
				continue;
			}

			if ($item['state'] === 'in_flight') {
				$claimed_at = isset($item['claimed_at']) ? (int) $item['claimed_at'] : 0;
				if ($claimed_at <= 0 || ($now - $claimed_at) >= self::CLAIM_TTL) {
					$item['state']           = 'pending';
					$item['claimed_version'] = 0;
					$item['pending_counted'] = true;
					$item['claim_token']     = '';
					$item['claimed_at']      = 0;
					$item['updated_at']      = $now;
					$state['items'][$key]    = $item;
					$dirty                   = true;
				} else {
					$has_in_flight = true;
				}
			}
		}

		// 2. Reconcile with legacy options so pre-existing queue data (or direct option writes)
		// are preserved and adopted cleanly.
		$legacy_posts  = $this->read_legacy_ids(self::LEGACY_POST_OPTION);
		$legacy_topics = $this->read_legacy_ids(self::LEGACY_TOPIC_OPTION);

		$legacy_map = array();
		foreach ($legacy_posts as $pid) {
			$legacy_map['post:' . $pid] = array('type' => 'post', 'id' => $pid);
		}
		foreach ($legacy_topics as $tid) {
			$legacy_map['topic:' . $tid] = array('type' => 'topic', 'id' => $tid);
		}

		// When no items are currently in flight and the legacy options were replaced or
		// cleared externally (e.g. direct update_option('aips_pending_index_queue', ...)),
		// remove pending items that are no longer present in the legacy option.
		if ($has_state_option && !$has_in_flight) {
			foreach ($state['items'] as $key => $item) {
				if ($item['state'] === 'pending' && !isset($legacy_map[$key])) {
					unset($state['items'][$key]);
					$dirty = true;
				}
			}
		}

		$newly_adopted = 0;

		foreach ($legacy_map as $key => $desc) {
			if (!isset($state['items'][$key])) {
				$state['items'][$key] = array(
					'type'             => $desc['type'],
					'id'               => $desc['id'],
					'state'            => 'pending',
					'version'          => 1,
					'claimed_version'  => 0,
					'resolved_version' => 0,
					'pending_counted'  => true,
					'claim_token'      => '',
					'claimed_at'       => 0,
					'updated_at'       => $now,
				);
				$newly_adopted++;
				$dirty = true;
			} elseif ($state['items'][$key]['state'] === 'resolved') {
				$state['items'][$key]['state']           = 'pending';
				$state['items'][$key]['version']         = max(1, (int) $state['items'][$key]['version']) + 1;
				$state['items'][$key]['claimed_version'] = 0;
				$state['items'][$key]['pending_counted'] = true;
				$state['items'][$key]['updated_at']      = $now;
				$newly_adopted++;
				$dirty = true;
			}
		}

		// If all pending and in-flight items were removed externally, reset any orphaned open session.
		if ($has_state_option && !$has_in_flight && empty($legacy_map)) {
			$open_run = $this->get_open_session_from_state($state);
			if ($open_run) {
				$terminal = (int) $open_run->processed > 0
					? AIPS_Background_Process_Repository::STATUS_COMPLETED
					: AIPS_Background_Process_Repository::STATUS_CANCELLED;
				$this->run_repository->transition(
					$open_run->id,
					$terminal,
					AIPS_Background_Process_Repository::open_statuses(),
					__('Queue cleared.', 'ai-post-scheduler')
				);
			}
			$state['items']      = array();
			$state['session_id'] = 0;
			$dirty               = true;
		}

		// 3. Adopt into an open session when requested.
		if ($adopt) {
			$run    = $this->get_open_session_from_state($state);
			$counts = $this->count_items($state);

			if (!$run && $counts['outstanding_count'] > 0) {
				// Start a new session adopting all currently outstanding items; do not invent completed counts.
				$run   = $this->create_session_for_state($state, $counts['outstanding_count']);
				$dirty = true;
			} elseif ($run && !$has_state_option && (int) $run->processed === 0 && $counts['outstanding_count'] > 0) {
				// If a stale 0-progress session existed while STATE_OPTION was absent, reset its total
				// to match the adopted legacy items instead of adding on top of the stale total.
				$delta = $counts['outstanding_count'] - (int) $run->total;
				if ($delta !== 0) {
					$this->run_repository->add_total($run->id, $delta);
				}
				$dirty = true;
			} elseif ($run && $newly_adopted > 0) {
				$options                    = is_array($run->options) ? $run->options : array();
				$options['total_increased'] = true;
				$this->run_repository->update_options($run->id, $options);
				$this->run_repository->add_total(
					$run->id,
					$newly_adopted,
					__('New items were added to the queue; total and percentage updated.', 'ai-post-scheduler')
				);
				$dirty = true;
			}
		}

		return $state;
	}

	/**
	 * Persist the structured queue state and synchronize legacy flat options.
	 *
	 * @param array $state Queue state.
	 * @return void
	 */
	private function save_state(array $state): void {
		$post_ids  = array();
		$topic_ids = array();

		foreach ($state['items'] as $item) {
			if (!in_array($item['state'], array('pending', 'in_flight'), true)) {
				continue;
			}
			if ($item['type'] === 'post') {
				$post_ids[] = (int) $item['id'];
			} elseif ($item['type'] === 'topic') {
				$topic_ids[] = (int) $item['id'];
			}
		}

		$post_ids  = array_values(array_unique($post_ids));
		$topic_ids = array_values(array_unique($topic_ids));

		if (empty($state['items'])) {
			delete_option(self::STATE_OPTION);
		} else {
			update_option(self::STATE_OPTION, $state, false);
		}

		if (empty($post_ids)) {
			delete_option(self::LEGACY_POST_OPTION);
		} else {
			update_option(self::LEGACY_POST_OPTION, $post_ids, false);
		}

		if (empty($topic_ids)) {
			delete_option(self::LEGACY_TOPIC_OPTION);
		} else {
			update_option(self::LEGACY_TOPIC_OPTION, $topic_ids, false);
		}
	}

	/**
	 * Count pending, in-flight, and total outstanding items by entity type.
	 *
	 * @param array $state Queue state.
	 * @return array{
	 *     pending_count:int,
	 *     pending_posts:int,
	 *     pending_topics:int,
	 *     in_flight_count:int,
	 *     in_flight_posts:int,
	 *     in_flight_topics:int,
	 *     outstanding_count:int
	 * }
	 */
	private function count_items(array $state): array {
		$pending_posts    = 0;
		$pending_topics   = 0;
		$in_flight_posts  = 0;
		$in_flight_topics = 0;

		foreach ($state['items'] as $item) {
			if ($item['state'] === 'pending') {
				if ($item['type'] === 'topic') {
					$pending_topics++;
				} else {
					$pending_posts++;
				}
			} elseif ($item['state'] === 'in_flight') {
				if ($item['type'] === 'topic') {
					$in_flight_topics++;
					if (!empty($item['pending_counted']) && (int) $item['version'] > (int) $item['claimed_version']) {
						$pending_topics++;
					}
				} else {
					$in_flight_posts++;
					if (!empty($item['pending_counted']) && (int) $item['version'] > (int) $item['claimed_version']) {
						$pending_posts++;
					}
				}
			}
		}

		$pending_count   = $pending_posts + $pending_topics;
		$in_flight_count = $in_flight_posts + $in_flight_topics;

		return array(
			'pending_count'     => $pending_count,
			'pending_posts'     => $pending_posts,
			'pending_topics'    => $pending_topics,
			'in_flight_count'   => $in_flight_count,
			'in_flight_posts'   => $in_flight_posts,
			'in_flight_topics'  => $in_flight_topics,
			'outstanding_count' => $pending_count + $in_flight_count,
		);
	}

	/**
	 * Resolve the open session row from `aips_background_processes` and bind it to `$state`.
	 *
	 * @param array $state Queue state (by reference).
	 * @return object|null
	 */
	private function get_open_session_from_state(array &$state): ?object {
		if (!empty($state['session_id'])) {
			$run = $this->run_repository->get((int) $state['session_id']);
			if ($run && in_array($run->status, AIPS_Background_Process_Repository::open_statuses(), true)) {
				return $run;
			}
		}

		$open = $this->run_repository->get_open(self::PROCESS_KEY);
		if ($open) {
			$state['session_id'] = (int) $open->id;
			return $open;
		}

		$state['session_id'] = 0;
		return null;
	}

	/**
	 * Create a new open session row in `aips_background_processes`.
	 *
	 * @param array $state        Queue state (by reference).
	 * @param int   $initial_total Initial total count.
	 * @return object|null
	 */
	private function create_session_for_state(array &$state, int $initial_total): ?object {
		$run_id = $this->run_repository->create(
			self::PROCESS_KEY,
			max(1, $initial_total),
			array(),
			0,
			function_exists('get_current_user_id') ? (int) get_current_user_id() : 0
		);

		if ($run_id <= 0) {
			return null;
		}

		$state['session_id'] = $run_id;

		if ($this->is_paused()) {
			$this->run_repository->transition(
				$run_id,
				AIPS_Background_Process_Repository::STATUS_PAUSED,
				AIPS_Background_Process_Repository::active_statuses(),
				__('Paused by an administrator.', 'ai-post-scheduler')
			);
		} else {
			$this->run_repository->add_progress(
				$run_id,
				0,
				0,
				0,
				0,
				sprintf(
					/* translators: %s: number of queued items. */
					_n('%s item queued.', '%s items queued.', $initial_total, 'ai-post-scheduler'),
					number_format_i18n($initial_total)
				)
			);
		}

		return $this->run_repository->get($run_id);
	}

	/**
	 * Build a localized status message for an open session.
	 *
	 * @param object $run         Open session row.
	 * @param int    $outstanding Remaining (pending + in-flight) items.
	 * @return string
	 */
	private function build_session_progress_message(object $run, int $outstanding): string {
		if ($outstanding <= 0) {
			return __('Finished.', 'ai-post-scheduler');
		}

		$base = sprintf(
			/* translators: %s: number of queued items remaining. */
			_n('%s item queued.', '%s items queued.', $outstanding, 'ai-post-scheduler'),
			number_format_i18n($outstanding)
		);

		if (is_array($run->options) && !empty($run->options['total_increased'])) {
			return $base . ' ' . __('New items were added to this session.', 'ai-post-scheduler');
		}

		return $base;
	}

	/**
	 * Read positive integer IDs from a legacy queue option.
	 *
	 * @param string $option_name Option key.
	 * @return int[]
	 */
	private function read_legacy_ids(string $option_name): array {
		$raw = get_option($option_name, array());
		if (!is_array($raw)) {
			return array();
		}

		$clean = array();
		foreach ($raw as $id) {
			$id = absint($id);
			if ($id > 0) {
				$clean[] = $id;
			}
		}

		return array_values(array_unique($clean));
	}

	/**
	 * Flush local object-cache entries (including `notoptions`) for queue options
	 * so concurrent worker and Heartbeat/AJAX processes observe fresh database values.
	 *
	 * @param string[] $option_names Option names to refresh.
	 * @return void
	 */
	private function refresh_option_cache(array $option_names): void {
		$notoptions = wp_cache_get('notoptions', 'options');
		$changed    = false;

		foreach ($option_names as $name) {
			wp_cache_delete($name, 'options');
			if (is_array($notoptions) && isset($notoptions[$name])) {
				unset($notoptions[$name]);
				$changed = true;
			}
		}

		if ($changed) {
			wp_cache_set('notoptions', $notoptions, 'options');
		}
	}

	/**
	 * Execute a short state-mutation callback under an atomic database lock.
	 *
	 * Never executes `$callback` unlocked: if lock acquisition fails after bounded
	 * retries, invokes `$on_timeout` (when provided) instead of clobbering state.
	 *
	 * @param callable      $callback     Callback to execute while holding the lock.
	 * @param callable|null $on_timeout   Fallback callback when the lock cannot be acquired.
	 * @param int|null      $max_attempts Optional override for retry attempts.
	 * @param int|null      $sleep_us     Optional override for retry sleep in microseconds.
	 * @return mixed
	 */
	private function with_lock(callable $callback, ?callable $on_timeout = null, ?int $max_attempts = null, ?int $sleep_us = null) {
		$token    = null;
		$attempts = 0;
		$limit    = $max_attempts !== null ? max(1, $max_attempts) : $this->lock_max_attempts;
		$delay    = $sleep_us !== null ? max(0, $sleep_us) : $this->lock_retry_sleep_us;

		while ($token === null && $attempts < $limit) {
			$token = $this->run_repository->acquire_lock(self::MUTATION_LOCK, 5);
			if ($token === null) {
				$attempts++;
				if ($attempts < $limit && $delay > 0) {
					usleep($delay);
				}
			}
		}

		if ($token === null) {
			return $on_timeout !== null ? call_user_func($on_timeout) : null;
		}

		try {
			return call_user_func($callback);
		} finally {
			$this->run_repository->release_lock(self::MUTATION_LOCK, $token);
		}
	}
}
