<?php
/**
 * Post Audit & Refresher Service
 *
 * Scans older published WordPress posts for staleness, identifies candidate posts,
 * and generates updated staging revisions for editorial review.
 *
 * All thresholds (staleness window, batch size, post types, auto-approval) are
 * driven by the Settings page via AIPS_Config::get_post_refresher_config().
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Audit_Service {

	/**
	 * WP-Cron hook that runs the autonomous refresher scan.
	 */
	const CRON_HOOK = 'aips_post_refresher_scan';

	/**
	 * Post meta: post is excluded from all refresher activity.
	 */
	const META_IMMUTABLE = '_aips_refresh_immutable';

	/**
	 * Post meta: append-only JSON log of every refresher check.
	 */
	const META_AUDIT_LOG = '_aips_audit_log';

	/**
	 * Post meta: timestamp of the most recent refresher check.
	 */
	const META_LAST_AUDITED_AT = '_aips_last_audited_at';

	/**
	 * Post meta: total number of refresher checks performed on this post.
	 */
	const META_AUDIT_COUNT = '_aips_audit_check_count';

	/**
	 * Post meta: marks a draft as a staging revision produced by the refresher.
	 */
	const META_IS_STAGING = '_aips_is_staging_revision';

	/**
	 * Post meta (on the staging draft): the live post this revision targets.
	 */
	const META_TARGET_POST_ID = '_aips_target_post_id';

	/**
	 * Post meta (on the staging draft): when the revision was proposed.
	 */
	const META_PROPOSED_AT = '_aips_revision_proposed_at';

	/**
	 * Post meta (on the live post): '1' while a revision awaits review.
	 */
	const META_HAS_PENDING = '_aips_has_pending_revision';

	/**
	 * Post meta (on the live post): ID of the pending staging draft.
	 */
	const META_PENDING_ID = '_aips_pending_revision_id';

	/**
	 * Post meta (on the live post): when a revision was last merged in.
	 */
	const META_LAST_REFRESHED_AT = '_aips_last_refreshed_at';

	/**
	 * Audit log result codes.
	 */
	const RESULT_SKIPPED_IMMUTABLE = 'skipped_immutable';
	const RESULT_SKIPPED_PENDING   = 'skipped_pending';
	const RESULT_REVISION_CREATED  = 'revision_created';
	const RESULT_FAILED            = 'failed';
	const RESULT_APPROVED          = 'approved';
	const RESULT_REJECTED          = 'rejected';

	/**
	 * @var AIPS_Generation_Pipeline
	 */
	private $pipeline;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Pipeline|null $pipeline Pipeline orchestrator.
	 * @param AIPS_Logger_Interface|null    $logger   Logger.
	 * @param AIPS_Config|null              $config   Config accessor.
	 */
	public function __construct(
		?AIPS_Generation_Pipeline $pipeline = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Config $config = null
	) {
		$container = AIPS_Container::get_instance();
		$this->pipeline = $pipeline ?: new AIPS_Generation_Pipeline();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->config = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Get the effective refresher configuration.
	 *
	 * @return array See AIPS_Config::get_post_refresher_config().
	 */
	public function get_config(): array {
		return $this->config->get_post_refresher_config();
	}

	// ---------------------------------------------------------------------
	// Immutability
	// ---------------------------------------------------------------------

	/**
	 * Whether a post is marked immutable (never refreshed).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_immutable(int $post_id): bool {
		$stored = get_post_meta($post_id, self::META_IMMUTABLE, true);

		if ($stored === '') {
			$immutable = $this->get_config()['default_immutable'];
		} else {
			$immutable = ($stored === '1' || $stored === 1 || $stored === true);
		}

		/**
		 * Filter whether a post is immutable to the Autonomous Post Refresher.
		 *
		 * @param bool $immutable Whether the post is protected.
		 * @param int  $post_id   Post ID.
		 */
		return (bool) apply_filters('aips_post_refresh_is_immutable', $immutable, $post_id);
	}

	/**
	 * Mark a post immutable or mutable.
	 *
	 * @param int  $post_id   Post ID.
	 * @param bool $immutable Whether the post should be protected.
	 * @return bool True when the meta value was written.
	 */
	public function set_immutable(int $post_id, bool $immutable): bool {
		if (!get_post($post_id)) {
			return false;
		}

		update_post_meta($post_id, self::META_IMMUTABLE, $immutable ? '1' : '0');

		return true;
	}

	// ---------------------------------------------------------------------
	// Audit log
	// ---------------------------------------------------------------------

	/**
	 * Read the audit log for a post, newest entry last.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_audit_log(int $post_id): array {
		$raw = get_post_meta($post_id, self::META_AUDIT_LOG, true);

		if (is_array($raw)) {
			return array_values($raw);
		}

		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				return array_values($decoded);
			}
		}

		return array();
	}

	/**
	 * Append an entry to a post's audit log.
	 *
	 * Called every time the refresher inspects a post — including when the post
	 * is skipped — so the log is a complete history of refresher activity. The
	 * log is trimmed to the configured entry limit (oldest entries dropped).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $result  One of the self::RESULT_* codes.
	 * @param array  $extra   Optional extra fields (message, revision_id, ...).
	 * @return array<int, array<string, mixed>> The updated log.
	 */
	public function record_audit_check(int $post_id, string $result, array $extra = array()): array {
		$entry = array_merge(
			array(
				'checked_at' => current_time('mysql'),
				'result'     => sanitize_key($result),
				'message'    => '',
			),
			$extra
		);

		$entry['message'] = sanitize_text_field((string) $entry['message']);

		if (isset($entry['revision_id'])) {
			$entry['revision_id'] = (int) $entry['revision_id'];
		}

		$log   = $this->get_audit_log($post_id);
		$log[] = $entry;

		$limit = $this->get_config()['audit_log_limit'];
		if (count($log) > $limit) {
			$log = array_slice($log, -$limit);
		}

		update_post_meta($post_id, self::META_AUDIT_LOG, wp_json_encode(array_values($log)));
		update_post_meta($post_id, self::META_LAST_AUDITED_AT, $entry['checked_at']);
		update_post_meta($post_id, self::META_AUDIT_COUNT, (int) get_post_meta($post_id, self::META_AUDIT_COUNT, true) + 1);

		/**
		 * Fires after a refresher check has been recorded on a post.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $entry   The recorded log entry.
		 */
		do_action('aips_post_refresh_check_recorded', $post_id, $entry);

		return $log;
	}

	// ---------------------------------------------------------------------
	// Scanning
	// ---------------------------------------------------------------------

	/**
	 * Find candidate posts that have not been updated for a given number of days.
	 *
	 * Immutable posts and posts that already have a pending staging revision are
	 * excluded at query level.
	 *
	 * @param int|null $days_old Minimum days since last update. Null = configured value.
	 * @param int|null $limit    Max posts to return. Null = configured batch limit.
	 * @return array<WP_Post>
	 */
	public function find_stale_posts(?int $days_old = null, ?int $limit = null): array {
		$config = $this->get_config();

		$days_old = ($days_old === null) ? $config['stale_days'] : max(1, min(3650, $days_old));
		$limit    = ($limit === null) ? $config['batch_limit'] : max(1, min($config['max_batch_limit'], $limit));

		$cutoff_date = gmdate('Y-m-d H:i:s', time() - ($days_old * DAY_IN_SECONDS));

		$meta_query = array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => self::META_HAS_PENDING,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_HAS_PENDING,
					'value'   => '1',
					'compare' => '!=',
				),
			),
			// Never touch staging drafts produced by the refresher itself.
			array(
				'key'     => self::META_IS_STAGING,
				'compare' => 'NOT EXISTS',
			),
		);

		// Immutability is opt-out by default, opt-in when the site default flips.
		if ($config['default_immutable']) {
			$meta_query[] = array(
				'key'     => self::META_IMMUTABLE,
				'value'   => '0',
				'compare' => '=',
			);
		} else {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_IMMUTABLE,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_IMMUTABLE,
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		$args = array(
			'post_type'           => $config['post_types'],
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'orderby'             => 'modified',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'date_query'          => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $cutoff_date,
				),
			),
			'meta_query'          => $meta_query,
		);

		/**
		 * Filter the WP_Query args used to find stale posts.
		 *
		 * @param array $args     Query args.
		 * @param int   $days_old Staleness threshold in days.
		 * @param int   $limit    Batch limit.
		 */
		$args = apply_filters('aips_post_refresh_stale_query_args', $args, $days_old, $limit);

		$posts = get_posts($args);

		// Defence in depth: the filter above may reintroduce protected posts.
		return array_values(array_filter($posts, function (WP_Post $post) {
			return !$this->is_immutable((int) $post->ID);
		}));
	}

	/**
	 * Run one refresher pass: find stale posts and stage a revision for each.
	 *
	 * @param int|null $days_old Staleness threshold. Null = configured value.
	 * @param int|null $limit    Batch limit. Null = configured value.
	 * @return array{scanned:int, created:int, skipped:int, revision_ids:int[], errors:string[]}
	 */
	public function run_scan(?int $days_old = null, ?int $limit = null): array {
		$stale_posts  = $this->find_stale_posts($days_old, $limit);
		$auto_approve = $this->get_config()['auto_approve'];

		$result = array(
			'scanned'      => count($stale_posts),
			'created'      => 0,
			'skipped'      => 0,
			'revision_ids' => array(),
			'errors'       => array(),
		);

		foreach ($stale_posts as $post) {
			$revision_id = $this->create_staging_revision((int) $post->ID);

			if (is_wp_error($revision_id)) {
				if (in_array($revision_id->get_error_code(), array('immutable_post', 'revision_pending'), true)) {
					$result['skipped']++;
				} else {
					/* translators: 1: post ID, 2: error message. */
					$result['errors'][] = sprintf(__('Post #%1$d: %2$s', 'ai-post-scheduler'), $post->ID, $revision_id->get_error_message());
				}
				continue;
			}

			$result['created']++;
			$result['revision_ids'][] = (int) $revision_id;

			if ($auto_approve) {
				$approved = $this->approve_revision((int) $revision_id);
				if (is_wp_error($approved)) {
					/* translators: 1: post ID, 2: error message. */
					$result['errors'][] = sprintf(__('Post #%1$d: %2$s', 'ai-post-scheduler'), $post->ID, $approved->get_error_message());
				}
			}
		}

		return $result;
	}

	/**
	 * Cron entry point. Runs a scan only when the refresher is enabled.
	 *
	 * @return array|null Scan result, or null when disabled.
	 */
	public function run_scheduled_scan(): ?array {
		if (!$this->get_config()['enabled']) {
			return null;
		}

		$result = $this->run_scan();

		$this->logger->log(sprintf(
			'Post Refresher scan complete: %d scanned, %d staged, %d skipped, %d error(s).',
			$result['scanned'],
			$result['created'],
			$result['skipped'],
			count($result['errors'])
		), 'info');

		return $result;
	}

	// ---------------------------------------------------------------------
	// Staging revisions
	// ---------------------------------------------------------------------

	/**
	 * Create a staging revision for a stale post.
	 *
	 * @param int $post_id Target published post ID.
	 * @return int|WP_Error Staging revision post ID or error.
	 */
	public function create_staging_revision(int $post_id) {
		$post = get_post($post_id);

		if (!$post || $post->post_status !== 'publish') {
			return new WP_Error('invalid_post', __('Target post not found or not published.', 'ai-post-scheduler'));
		}

		if ($this->is_immutable($post_id)) {
			$this->record_audit_check($post_id, self::RESULT_SKIPPED_IMMUTABLE, array(
				'message' => __('Post is marked immutable; refresher skipped it.', 'ai-post-scheduler'),
			));

			return new WP_Error('immutable_post', __('Post is marked immutable and cannot be refreshed.', 'ai-post-scheduler'));
		}

		$pending_id = (int) get_post_meta($post_id, self::META_PENDING_ID, true);
		if ($pending_id > 0 && get_post($pending_id)) {
			$this->record_audit_check($post_id, self::RESULT_SKIPPED_PENDING, array(
				'message'     => __('A staging revision is already awaiting review.', 'ai-post-scheduler'),
				'revision_id' => $pending_id,
			));

			return new WP_Error('revision_pending', __('A staging revision is already pending for this post.', 'ai-post-scheduler'));
		}

		$context = new AIPS_Post_Refresh_Context($post);
		$payload = $this->pipeline->execute($context);

		if ($payload->has_errors()) {
			$message = implode(', ', array_map(function ($e) {
				return is_wp_error($e) ? $e->get_error_message() : (string) $e;
			}, $payload->errors));

			// The pipeline may have persisted a partial draft before failing.
			$this->discard_orphan_draft($payload->post_id);

			$this->record_audit_check($post_id, self::RESULT_FAILED, array('message' => $message));

			return new WP_Error('pipeline_failed', $message);
		}

		$new_content = $payload->formatted_content ?: $payload->raw_content;

		if ($new_content === '') {
			$this->discard_orphan_draft($payload->post_id);
			$this->record_audit_check($post_id, self::RESULT_FAILED, array(
				'message' => __('Pipeline returned empty content.', 'ai-post-scheduler'),
			));

			return new WP_Error('empty_content', __('Pipeline returned empty content.', 'ai-post-scheduler'));
		}

		// The review-preparation stage already persists the draft; reuse it
		// rather than inserting a second post.
		$revision_post_id = (int) $payload->post_id;

		if ($revision_post_id > 0 && get_post($revision_post_id)) {
			$updated = wp_update_post(array(
				'ID'           => $revision_post_id,
				'post_title'   => $payload->title ?: $post->post_title,
				'post_excerpt' => $payload->excerpt ?: $post->post_excerpt,
				'post_status'  => 'draft',
			), true);

			if (is_wp_error($updated)) {
				$this->record_audit_check($post_id, self::RESULT_FAILED, array('message' => $updated->get_error_message()));
				return $updated;
			}
		} else {
			$revision_post_id = wp_insert_post(array(
				'post_title'   => $payload->title ?: $post->post_title,
				'post_content' => $new_content,
				'post_excerpt' => $payload->excerpt ?: $post->post_excerpt,
				'post_status'  => 'draft',
				'post_type'    => $post->post_type,
				'post_author'  => $post->post_author,
			), true);

			if (is_wp_error($revision_post_id) || !$revision_post_id) {
				$error = is_wp_error($revision_post_id)
					? $revision_post_id
					: new WP_Error('insert_failed', __('Failed to create staging post.', 'ai-post-scheduler'));

				$this->record_audit_check($post_id, self::RESULT_FAILED, array('message' => $error->get_error_message()));

				return $error;
			}

			$revision_post_id = (int) $revision_post_id;
		}

		// Attach revision metadata links.
		update_post_meta($revision_post_id, self::META_IS_STAGING, '1');
		update_post_meta($revision_post_id, self::META_TARGET_POST_ID, (string) $post_id);
		update_post_meta($revision_post_id, self::META_PROPOSED_AT, current_time('mysql'));
		update_post_meta($post_id, self::META_HAS_PENDING, '1');
		update_post_meta($post_id, self::META_PENDING_ID, (string) $revision_post_id);

		$this->record_audit_check($post_id, self::RESULT_REVISION_CREATED, array(
			'message'     => __('Staging revision created and awaiting review.', 'ai-post-scheduler'),
			'revision_id' => $revision_post_id,
		));

		$this->logger->log("Created staging revision #{$revision_post_id} for published post #{$post_id}", 'info');

		return $revision_post_id;
	}

	/**
	 * Approve and apply a staging revision to the live post.
	 *
	 * @param int $revision_post_id Staging revision post ID.
	 * @return bool|WP_Error
	 */
	public function approve_revision(int $revision_post_id) {
		$revision = $this->get_staging_revision($revision_post_id);

		if (is_wp_error($revision)) {
			return $revision;
		}

		$target_id   = (int) get_post_meta($revision_post_id, self::META_TARGET_POST_ID, true);
		$target_post = get_post($target_id);

		if (!$target_post) {
			return new WP_Error('target_not_found', __('Target published post not found.', 'ai-post-scheduler'));
		}

		if ($this->is_immutable($target_id)) {
			return new WP_Error('immutable_post', __('Target post is marked immutable and cannot be updated.', 'ai-post-scheduler'));
		}

		$update_result = wp_update_post(array(
			'ID'           => $target_id,
			'post_title'   => $revision->post_title,
			'post_content' => $revision->post_content,
			'post_excerpt' => $revision->post_excerpt,
		), true);

		if (is_wp_error($update_result)) {
			return $update_result;
		}

		// Mark target post updated and clear pending flags.
		update_post_meta($target_id, self::META_HAS_PENDING, '0');
		delete_post_meta($target_id, self::META_PENDING_ID);
		update_post_meta($target_id, self::META_LAST_REFRESHED_AT, current_time('mysql'));

		$this->record_audit_check($target_id, self::RESULT_APPROVED, array(
			'message'     => __('Staging revision approved and merged into the live post.', 'ai-post-scheduler'),
			'revision_id' => $revision_post_id,
		));

		wp_delete_post($revision_post_id, true);

		$this->logger->log("Approved and merged staging revision for post #{$target_id}", 'info');

		/**
		 * Fires after a staging revision has been merged into its live post.
		 *
		 * @param int $target_id        Live post ID.
		 * @param int $revision_post_id Staging revision ID (now deleted).
		 */
		do_action('aips_post_refresh_revision_approved', $target_id, $revision_post_id);

		return true;
	}

	/**
	 * Reject / dismiss a staging revision.
	 *
	 * @param int $revision_post_id Staging revision post ID.
	 * @return bool|WP_Error
	 */
	public function reject_revision(int $revision_post_id) {
		$revision = $this->get_staging_revision($revision_post_id);

		if (is_wp_error($revision)) {
			return $revision;
		}

		$target_id = (int) get_post_meta($revision_post_id, self::META_TARGET_POST_ID, true);

		if ($target_id > 0 && get_post($target_id)) {
			update_post_meta($target_id, self::META_HAS_PENDING, '0');
			delete_post_meta($target_id, self::META_PENDING_ID);

			$this->record_audit_check($target_id, self::RESULT_REJECTED, array(
				'message'     => __('Staging revision dismissed by an editor.', 'ai-post-scheduler'),
				'revision_id' => $revision_post_id,
			));
		}

		wp_delete_post($revision_post_id, true);

		return true;
	}

	/**
	 * Resolve a post ID to a genuine staging revision.
	 *
	 * Guards the destructive approve/reject paths so that an arbitrary post ID
	 * can never be merged into another post or force-deleted.
	 *
	 * @param int $revision_post_id Candidate revision ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_staging_revision(int $revision_post_id) {
		$revision = get_post($revision_post_id);

		if (!$revision) {
			return new WP_Error('not_found', __('Staging revision not found.', 'ai-post-scheduler'));
		}

		if (get_post_meta($revision_post_id, self::META_IS_STAGING, true) !== '1') {
			return new WP_Error('not_a_revision', __('That post is not a refresher staging revision.', 'ai-post-scheduler'));
		}

		return $revision;
	}

	/**
	 * Delete a draft the pipeline persisted before failing.
	 *
	 * @param int|null $draft_id Draft post ID, if any.
	 * @return void
	 */
	private function discard_orphan_draft($draft_id): void {
		$draft_id = (int) $draft_id;

		if ($draft_id > 0 && get_post($draft_id)) {
			wp_delete_post($draft_id, true);
		}
	}

	// ---------------------------------------------------------------------
	// Scheduling
	// ---------------------------------------------------------------------

	/**
	 * Reconcile the WP-Cron event with the configured frequency.
	 *
	 * Called on activation and whenever the refresher frequency setting changes.
	 * The event is always scheduled (the handler no-ops when the feature is
	 * disabled) so that toggling the feature on does not require a re-activation.
	 *
	 * @return void
	 */
	/**
	 * Resolve the configured scan frequency to a valid WP-Cron schedule slug.
	 *
	 * Falls back to 'daily' when the stored value is not a registered schedule
	 * (e.g. a plugin that provided a custom interval was deactivated).
	 *
	 * @return string
	 */
	public static function get_schedule_slug(): string {
		$frequency = AIPS_Config::get_instance()->get_post_refresher_config()['frequency'];
		$schedules = wp_get_schedules();

		return isset($schedules[$frequency]) ? $frequency : 'daily';
	}

	public static function sync_schedule(): void {
		$frequency = self::get_schedule_slug();
		$next      = wp_next_scheduled(self::CRON_HOOK);

		if ($next) {
			$event = wp_get_scheduled_event(self::CRON_HOOK);
			if ($event && isset($event->schedule) && $event->schedule === $frequency) {
				return;
			}

			wp_clear_scheduled_hook(self::CRON_HOOK);
		}

		wp_schedule_event(time() + MINUTE_IN_SECONDS, $frequency, self::CRON_HOOK);
	}
}
