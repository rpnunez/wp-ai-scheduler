<?php
/**
 * Post Audit & Refresher Service
 *
 * Scans older published WordPress posts for staleness, identifies candidate posts,
 * and generates updated staging revisions for editorial review.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Audit_Service {

	/**
	 * @var AIPS_Generation_Pipeline
	 */
	private $pipeline;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Pipeline|null $pipeline Pipeline orchestrator.
	 * @param AIPS_Logger_Interface|null    $logger   Logger.
	 */
	public function __construct(?AIPS_Generation_Pipeline $pipeline = null, ?AIPS_Logger_Interface $logger = null) {
		$container = AIPS_Container::get_instance();
		$this->pipeline = $pipeline ?: new AIPS_Generation_Pipeline();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
	}

	/**
	 * Find candidate posts that have not been updated for a given number of days.
	 *
	 * @param int $days_old Minimum days since last update. Default 180.
	 * @param int $limit    Max posts to return. Default 10.
	 * @return array<WP_Post>
	 */
	public function find_stale_posts(int $days_old = 180, int $limit = 10): array {
		$cutoff_date = gmdate('Y-m-d H:i:s', time() - ($days_old * DAY_IN_SECONDS));

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $cutoff_date,
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_aips_has_pending_revision',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_aips_has_pending_revision',
					'value'   => '0',
				),
			),
		);

		return get_posts($args);
	}

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

		$current_content = $post->post_content;
		$current_title   = $post->post_title;

		// Build topic context from existing post.
		$context = new AIPS_Topic_Context(array(
			'topic'           => $current_title,
			'content_prompt'  => "Review and refresh the following published article. Update outdated references, improve clarity, and preserve core structure:\n\n" . wp_strip_all_tags($current_content),
			'post_status'     => 'draft',
			'post_category'   => wp_get_post_categories($post_id),
		));

		// Execute generation pipeline.
		$payload = $this->pipeline->execute($context);

		if ($payload->has_errors()) {
			return new WP_Error('pipeline_failed', implode(', ', array_map(function ($e) {
				return is_wp_error($e) ? $e->get_error_message() : (string) $e;
			}, $payload->errors)));
		}

		$new_content = $payload->formatted_content ?: $payload->raw_content;

		// Store staging draft revision post.
		$revision_post_id = wp_insert_post(array(
			'post_title'   => $payload->title ?: $current_title,
			'post_content' => $new_content,
			'post_excerpt' => $payload->excerpt ?: $post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => $post->post_author,
		));

		if (is_wp_error($revision_post_id) || !$revision_post_id) {
			return is_wp_error($revision_post_id) ? $revision_post_id : new WP_Error('insert_failed', __('Failed to create staging post.', 'ai-post-scheduler'));
		}

		// Attach revision metadata links.
		update_post_meta($revision_post_id, '_aips_is_staging_revision', '1');
		update_post_meta($revision_post_id, '_aips_target_post_id', (string) $post_id);
		update_post_meta($revision_post_id, '_aips_revision_proposed_at', current_time('mysql'));
		update_post_meta($post_id, '_aips_has_pending_revision', '1');
		update_post_meta($post_id, '_aips_pending_revision_id', (string) $revision_post_id);

		$this->logger->info("Created staging revision #{$revision_post_id} for published post #{$post_id}");

		return $revision_post_id;
	}

	/**
	 * Approve and apply a staging revision to the live post.
	 *
	 * @param int $revision_post_id Staging revision post ID.
	 * @return bool|WP_Error
	 */
	public function approve_revision(int $revision_post_id) {
		$revision = get_post($revision_post_id);
		if (!$revision) {
			return new WP_Error('not_found', __('Staging revision not found.', 'ai-post-scheduler'));
		}

		$target_id = (int) get_post_meta($revision_post_id, '_aips_target_post_id', true);
		$target_post = get_post($target_id);

		if (!$target_post) {
			return new WP_Error('target_not_found', __('Target published post not found.', 'ai-post-scheduler'));
		}

		// Update target post with revision contents.
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
		update_post_meta($target_id, '_aips_has_pending_revision', '0');
		delete_post_meta($target_id, '_aips_pending_revision_id');
		update_post_meta($target_id, '_aips_last_refreshed_at', current_time('mysql'));

		// Delete or mark staging draft revision completed.
		wp_delete_post($revision_post_id, true);

		$this->logger->info("Approved and merged staging revision for post #{$target_id}");

		return true;
	}

	/**
	 * Reject / dismiss a staging revision.
	 *
	 * @param int $revision_post_id Staging revision post ID.
	 * @return bool
	 */
	public function reject_revision(int $revision_post_id): bool {
		$target_id = (int) get_post_meta($revision_post_id, '_aips_target_post_id', true);
		if ($target_id > 0) {
			update_post_meta($target_id, '_aips_has_pending_revision', '0');
			delete_post_meta($target_id, '_aips_pending_revision_id');
		}

		wp_delete_post($revision_post_id, true);
		return true;
	}
}
