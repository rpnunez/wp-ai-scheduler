<?php
/**
 * Notification Email Preview
 *
 * Builds representative dispatch contexts so a notification email can be rendered
 * or sent on demand, without waiting for a real event to fire.  Used by the
 * Settings → Notifications "send a test email" control.
 *
 * Contexts go through the same AIPS_Email_View_Factory as live notifications, so a
 * test email exercises the real composition path rather than a parallel mock.
 * Where a type's design depends on a real post (metadata cells, featured image,
 * content preview), the most recent generated post is used when one exists and a
 * synthetic payload stands in when the site has no posts yet.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Email_Preview
 *
 * Usage:
 *
 *   $preview = new AIPS_Email_Preview();
 *   $context = $preview->context_for('post_generated');
 *   $view    = AIPS_Notifications::instance()->build_email_view('post_generated', $context);
 */
class AIPS_Email_Preview {

	/**
	 * Notification types offered in the test-send picker.
	 *
	 * Every registered type can be previewed; this list fixes the order so the
	 * picker leads with the types whose design differs most.
	 *
	 * @return array<string, string> Type slug => human label.
	 */
	public static function selectable_types() {
		$registry = AIPS_Notification_Registry::get_type_registry();

		$preferred = array(
			'post_generated',
			'post_ready_for_review',
			'generation_failed',
			'quota_alert',
			'daily_digest',
			'weekly_summary',
			'summarized_digest',
		);

		$types = array();

		foreach ($preferred as $type) {
			if (isset($registry[$type])) {
				$types[$type] = (string) $registry[$type]['label'];
			}
		}

		foreach ($registry as $type => $config) {
			if (!isset($types[$type]) && isset($config['label'])) {
				$types[$type] = (string) $config['label'];
			}
		}

		return $types;
	}

	/**
	 * Build a representative dispatch context for a notification type.
	 *
	 * @param string $type Notification type slug.
	 * @return array Dispatch context: title, message, url, level, meta, vars.
	 */
	public function context_for($type) {
		$type = (string) $type;

		switch ($type) {
			case 'post_generated':
			case 'post_ready_for_review':
			case 'manual_generation_completed':
			case 'partial_generation_completed':
			case 'post_rejected':
			case 'template_generated':
				return $this->post_event_context($type);

			case 'quota_alert':
				return $this->quota_alert_context();

			case 'generation_failed':
				return $this->generation_failed_context();

			case 'scheduler_error':
				return $this->scheduler_error_context();

			case 'daily_digest':
			case 'weekly_summary':
			case 'monthly_report':
				return $this->rollup_context($type);

			case 'summarized_digest':
				return $this->summarized_digest_context();
		}

		return $this->generic_context($type);
	}

	// -----------------------------------------------------------------------
	// Per-family sample contexts
	// -----------------------------------------------------------------------

	/**
	 * Context for a per-post event, preferring a real post so the metadata cells,
	 * featured image, and content preview reflect this site's actual content.
	 *
	 * @param string $type Notification type slug.
	 * @return array
	 */
	private function post_event_context($type) {
		$post = $this->find_sample_post();

		if ($post) {
			$status = (string) $post->post_status;

			$payload = array(
				'post_id'           => (int) $post->ID,
				'post_status'       => $status,
				'post_status_label' => $this->status_label($status),
				'creation_method'   => 'scheduled',
				'source_label'      => __('Template: Sample Template', 'ai-post-scheduler'),
				'template_name'     => __('Sample Template', 'ai-post-scheduler'),
			);

			$url = (string) get_edit_post_link($post->ID, 'raw');

			return array(
				'title'   => (string) $post->post_title,
				'message' => sprintf(
					/* translators: %s: post title */
					__('Generated post "%s" is awaiting review.', 'ai-post-scheduler'),
					(string) $post->post_title
				),
				'url'     => $url,
				'level'   => 'info',
				'meta'    => $payload,
				'vars'    => array(),
			);
		}

		// No posts on this site yet: stand in with the design's sample copy so the
		// layout can still be reviewed.
		$title = __('Continuous Pentesting and Agentic Remediation in Production', 'ai-post-scheduler');

		return array(
			'title'   => $title,
			'message' => __('A generated post is awaiting review.', 'ai-post-scheduler'),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
			'level'   => 'info',
			'meta'    => array(
				'post_status'       => 'draft',
				'post_status_label' => __('Draft', 'ai-post-scheduler'),
				'creation_method'   => 'scheduled',
				'template_name'     => __('Sample Template', 'ai-post-scheduler'),
			),
			'vars'    => array(),
		);
	}

	/**
	 * Context for the quota alert (design mockup 3f).
	 *
	 * @return array
	 */
	private function quota_alert_context() {
		return array(
			'title'   => __('Quota alert: embedding', 'ai-post-scheduler'),
			'message' => __('AI requests are being blocked for embedding operations.', 'ai-post-scheduler'),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('settings', array('tab' => 'ai')),
			'level'   => 'error',
			'meta'    => array(
				'request_type'  => __('Embedding', 'ai-post-scheduler'),
				'error_message' => __('Monthly rate limit reached (500/500)', 'ai-post-scheduler'),
				'error_detail'  => __('Vector embeddings monthly rate limit reached. Requests resume when the limit resets.', 'ai-post-scheduler'),
				'error_code'    => 'rate_limit_exceeded',
				'model'         => 'gemini-embedding-2',
				'usage'         => __('500 / 500 monthly', 'ai-post-scheduler'),
			),
			'vars'    => array(
				'{{action_label}}' => __('Review AI settings', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Context for a generation failure (design mockup 2c).
	 *
	 * @return array
	 */
	private function generation_failed_context() {
		return array(
			'title'   => __('Generation failed: Daily Security', 'ai-post-scheduler'),
			'message' => __('Generation failed for Daily Security.', 'ai-post-scheduler'),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('history'),
			'level'   => 'error',
			'meta'    => array(
				'resource_label' => __('Daily Security', 'ai-post-scheduler'),
				'schedule_name'  => __('Daily Security', 'ai-post-scheduler'),
				'topic'          => __('Zero-trust for CI pipelines', 'ai-post-scheduler'),
				'error_message'  => __('Provider returned an error', 'ai-post-scheduler'),
				'error_detail'   => __('Rate limit exceeded.', 'ai-post-scheduler'),
				'attempts'       => __('2 of 3', 'ai-post-scheduler'),
				'next_retry'     => AIPS_DateTime::now()->timestamp() + (30 * MINUTE_IN_SECONDS),
			),
			'vars'    => array(
				'{{action_label}}' => __('View history entry', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Context for a scheduler error.
	 *
	 * @return array
	 */
	private function scheduler_error_context() {
		return array(
			'title'   => __('Scheduler error: Daily Security', 'ai-post-scheduler'),
			'message' => __('The scheduler could not complete "Daily Security".', 'ai-post-scheduler'),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('history'),
			'level'   => 'error',
			'meta'    => array(
				'schedule_name' => __('Daily Security', 'ai-post-scheduler'),
				'error_message' => __('Could not obtain the execution lock', 'ai-post-scheduler'),
				'attempts'      => __('1 of 3', 'ai-post-scheduler'),
			),
			'vars'    => array(
				'{{action_label}}' => __('View history entry', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Context for a rollup digest or summary (design mockups 2b and 3e).
	 *
	 * @param string $type Notification type slug.
	 * @return array
	 */
	private function rollup_context($type) {
		$now = AIPS_DateTime::now()->timestamp();

		$window = DAY_IN_SECONDS;
		if ('weekly_summary' === $type) {
			$window = 7 * DAY_IN_SECONDS;
		} elseif ('monthly_report' === $type) {
			$window = 30 * DAY_IN_SECONDS;
		}

		$items = array();

		foreach ($this->find_sample_posts(3) as $index => $post) {
			$items[] = array(
				'title'      => (string) $post->post_title,
				'url'        => (string) get_edit_post_link($post->ID, 'raw'),
				'created_at' => $now - (($index + 1) * HOUR_IN_SECONDS),
			);
		}

		if (empty($items)) {
			$items = array(
				array(
					'title'      => __('Continuous Pentesting and Agentic Remediation in Production', 'ai-post-scheduler'),
					'url'        => '',
					'created_at' => $now - HOUR_IN_SECONDS,
				),
				array(
					'title'      => __('Pre-Deployment Validation Checklist for Autonomous Agents', 'ai-post-scheduler'),
					'url'        => '',
					'created_at' => $now - (6 * HOUR_IN_SECONDS),
				),
			);
		}

		return array(
			'title'   => __('Daily generation digest', 'ai-post-scheduler'),
			'message' => __('2 posts generated, 7 review-ready, 0 errors.', 'ai-post-scheduler'),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('history'),
			'level'   => 'info',
			'meta'    => array(
				'generated'    => count($items),
				'review_ready' => 7,
				'errors'       => 0,
				'window'       => $window,
				'items'        => $items,
			),
			'vars'    => array(
				'{{action_label}}' => __('Open history', 'ai-post-scheduler'),
			),
		);
	}

	/**
	 * Context for the batched summarized digest.
	 *
	 * @return array
	 */
	private function summarized_digest_context() {
		$items = array();

		foreach ($this->find_sample_posts(3) as $post) {
			$items[] = array(
				'type'  => 'post_generated',
				'title' => (string) $post->post_title,
				'url'   => (string) get_edit_post_link($post->ID, 'raw'),
				'meta'  => array(
					'post_status_label' => $this->status_label((string) $post->post_status),
				),
			);
		}

		if (empty($items)) {
			$items = array(
				array(
					'type'  => 'post_generated',
					'title' => __('Current Directory Resolution Across Developer Tooling', 'ai-post-scheduler'),
					'url'   => '',
					'meta'  => array('post_status_label' => __('Draft', 'ai-post-scheduler')),
				),
				array(
					'type'  => 'post_ready_for_review',
					'title' => __('Why Strict OpenAPI Contracts Still Crash', 'ai-post-scheduler'),
					'url'   => '',
					'meta'  => array('post_status_label' => __('Pending Review', 'ai-post-scheduler')),
				),
			);
		}

		return array(
			'title'   => sprintf(
				/* translators: %d: number of batched updates */
				_n('Notification Digest: %d update', 'Notification Digest: %d updates', count($items), 'ai-post-scheduler'),
				count($items)
			),
			'message' => __('AI Post Scheduler has batched updates for your review.', 'ai-post-scheduler'),
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
			'level'   => 'info',
			'meta'    => array(
				'count' => count($items),
				'items' => $items,
			),
			'vars'    => array(),
		);
	}

	/**
	 * Context for any other registered type.
	 *
	 * @param string $type Notification type slug.
	 * @return array
	 */
	private function generic_context($type) {
		$registry = AIPS_Notification_Registry::get_type_registry();
		$label    = isset($registry[$type]['label']) ? (string) $registry[$type]['label'] : ucwords(str_replace('_', ' ', $type));
		$message  = isset($registry[$type]['description']) ? (string) $registry[$type]['description'] : $label;
		$level    = isset($registry[$type]['level']) ? (string) $registry[$type]['level'] : 'info';

		return array(
			'title'   => $label,
			'message' => $message,
			'url'     => AIPS_Admin_Menu_Helper::get_page_url('history'),
			'level'   => $level,
			'meta'    => array(),
			'vars'    => array(),
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Most recent post on the site, for a realistic preview.
	 *
	 * @return WP_Post|null
	 */
	private function find_sample_post() {
		$posts = $this->find_sample_posts(1);

		return empty($posts) ? null : $posts[0];
	}

	/**
	 * Most recent posts on the site.
	 *
	 * @param int $limit How many posts to fetch.
	 * @return array<int, WP_Post>
	 */
	private function find_sample_posts($limit) {
		$posts = get_posts(array(
			'numberposts'      => max(1, absint($limit)),
			'post_type'        => 'post',
			'post_status'      => array('publish', 'draft', 'pending', 'future'),
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		));

		return is_array($posts) ? $posts : array();
	}

	/**
	 * Human-readable label for a post status.
	 *
	 * @param string $status Post status slug.
	 * @return string
	 */
	private function status_label($status) {
		$object = get_post_status_object($status);

		if ($object && !empty($object->label)) {
			return (string) $object->label;
		}

		return ucfirst(str_replace('_', ' ', (string) $status));
	}
}
