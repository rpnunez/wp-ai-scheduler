<?php
/**
 * Notification Senders
 *
 * Holds the 21 named convenience sender methods that build structured
 * payloads and dispatch them via injected callables.  This keeps message
 * assembly isolated from the core dispatch/channel logic in AIPS_Notifications.
 *
 * Constructor callables
 * ---------------------
 *   $dispatcher   — called as ($type, $options_array); maps to
 *                   AIPS_Notifications::dispatch_notification().
 *   $vars_builder — called as ($title, $message, $details, $url, $label);
 *                   maps to AIPS_Notifications::build_standard_notification_vars().
 *
 * @package AI_Post_Scheduler
 * @since 1.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notification_Senders
 */
class AIPS_Notification_Senders {

	// -----------------------------------------------------------------------
	// Dependencies (injected callables)
	// -----------------------------------------------------------------------

	/**
	 * Callable that dispatches a notification.
	 * Signature: ( string $type, array $options ) : bool
	 *
	 * @var callable
	 */
	private $dispatcher;

	/**
	 * Callable that builds standard template variable arrays.
	 * Signature: ( string $title, string $message, array $details, string $action_url, string $action_label ) : array
	 *
	 * @var callable
	 */
	private $vars_builder;

	// -----------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param callable $dispatcher   Dispatch callable (wraps dispatch_notification).
	 * @param callable $vars_builder Vars-builder callable (wraps build_standard_notification_vars).
	 */
	public function __construct( callable $dispatcher, callable $vars_builder ) {
		$this->dispatcher   = $dispatcher;
		$this->vars_builder = $vars_builder;
	}

	// -----------------------------------------------------------------------
	// Named sender methods
	// -----------------------------------------------------------------------

	/**
	 * Create a DB notification when topics have been generated for an author.
	 *
	 * @param string $author_name Author display name.
	 * @param int    $topic_count Number of topics generated.
	 * @param int    $author_id   Author ID (used to build the action link URL).
	 * @return void
	 */
	public function author_topics_generated( $author_name, $topic_count, $author_id ) {
		$url = AIPS_Admin_Menu_Helper::get_page_url(
			'author_topics',
			array(
				'author_id' => absint($author_id),
				'status'    => 'pending',
			)
		);

		/* translators: 1: author name, 2: number of topics */
		$message = sprintf(
			__('Author (%1$s) generated %2$d pending topic(s) for review', 'ai-post-scheduler'),
			$author_name,
			(int) $topic_count
		);

		call_user_func(
			$this->dispatcher,
			'author_topics_generated',
			array(
				'channels' => array(AIPS_Notifications::CHANNEL_DB),
				'url'      => $url,
				'message'  => $message,
			)
		);
	}

	/**
	 * Send a high-priority generation failure notification.
	 *
	 * @param array $payload Failure payload.
	 * @return void
	 */
	public function generation_failed( array $payload ) {
		$resource_label = !empty($payload['resource_label']) ? $payload['resource_label'] : __('AI generation request', 'ai-post-scheduler');
		$error_message  = !empty($payload['error_message'])  ? $payload['error_message']  : __('Unknown error', 'ai-post-scheduler');
		$title          = sprintf(__('Generation failed: %s', 'ai-post-scheduler'), $resource_label);
		$message        = sprintf(__('Generation failed for %1$s. Error: %2$s', 'ai-post-scheduler'), $resource_label, $error_message);

		call_user_func(
			$this->dispatcher,
			'generation_failed',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => !empty($payload['url']) ? $payload['url'] : '',
				'level'         => 'error',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Open generation history', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a high-priority quota alert notification.
	 *
	 * @param array $payload Alert payload.
	 * @return void
	 */
	public function quota_alert( array $payload ) {
		$request_type  = !empty($payload['request_type'])  ? $payload['request_type']  : __('request', 'ai-post-scheduler');
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Quota threshold reached.', 'ai-post-scheduler');
		$title         = sprintf(__('Quota alert: %s', 'ai-post-scheduler'), $request_type);
		$message       = sprintf(__('AI requests are being blocked for %1$s operations. Error: %2$s', 'ai-post-scheduler'), $request_type, $error_message);

		call_user_func(
			$this->dispatcher,
			'quota_alert',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => !empty($payload['url']) ? $payload['url'] : '',
				'level'         => 'error',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Review AI settings', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a high-priority integration error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function integration_error( array $payload ) {
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('AI integration unavailable.', 'ai-post-scheduler');
		$title         = __('AI integration error', 'ai-post-scheduler');
		$message       = sprintf(__('The AI integration is unavailable. Error: %s', 'ai-post-scheduler'), $error_message);

		call_user_func(
			$this->dispatcher,
			'integration_error',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => !empty($payload['url']) ? $payload['url'] : '',
				'level'         => 'error',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Check integration status', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a scheduler error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function scheduler_error( array $payload ) {
		$schedule_name = !empty($payload['schedule_name']) ? $payload['schedule_name'] : __('Scheduled run', 'ai-post-scheduler');
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Unknown scheduler error', 'ai-post-scheduler');
		$title         = sprintf(__('Scheduler error: %s', 'ai-post-scheduler'), $schedule_name);
		$message       = sprintf(__('The scheduler could not complete "%1$s". Error: %2$s', 'ai-post-scheduler'), $schedule_name, $error_message);

		call_user_func(
			$this->dispatcher,
			'scheduler_error',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('schedule'),
				'level'         => 'error',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('schedule'), __('Open schedules', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a system error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function system_error( array $payload ) {
		$error_message = !empty($payload['error_message']) ? $payload['error_message'] : __('Unknown system error', 'ai-post-scheduler');
		$title         = !empty($payload['title'])         ? $payload['title']         : __('System error', 'ai-post-scheduler');
		$message       = sprintf(__('A system-level plugin error occurred. Error: %s', 'ai-post-scheduler'), $error_message);

		call_user_func(
			$this->dispatcher,
			'system_error',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => !empty($payload['url']) ? $payload['url'] : '',
				'level'         => 'error',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 0,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, !empty($payload['url']) ? $payload['url'] : '', __('Review details', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a template-generated notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function template_generated( array $payload ) {
		$post_ids      = isset($payload['post_ids']) && is_array($payload['post_ids']) ? array_values(array_filter(array_map('absint', $payload['post_ids']))) : array();
		$post_count    = count($post_ids);
		$template_name = !empty($payload['template_name']) ? $payload['template_name'] : __('Template', 'ai-post-scheduler');

		$title = sprintf(
			_n('%1$d post generated from "%2$s"', '%1$d posts generated from "%2$s"', $post_count, 'ai-post-scheduler'),
			$post_count,
			$template_name
		);

		$message = sprintf(
			_n('Scheduled run generated %1$d post for template "%2$s".', 'Scheduled run generated %1$d posts for template "%2$s".', $post_count, 'ai-post-scheduler'),
			$post_count,
			$template_name
		);

		$url = !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		call_user_func(
			$this->dispatcher,
			'template_generated',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'info',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Review generated posts', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a manual generation completed notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function manual_generation_completed( array $payload ) {
		$post_id    = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post       = $post_id ? get_post($post_id) : null;
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');

		$title   = sprintf(__('Manual generation completed: %s', 'ai-post-scheduler'), $post_title);
		$message = sprintf(__('Manual generation created post "%s".', 'ai-post-scheduler'), $post_title);
		$url     = $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		call_user_func(
			$this->dispatcher,
			'manual_generation_completed',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'info',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Edit generated post', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a post-ready-for-review notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_ready_for_review( array $payload ) {
		$post_id    = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post       = $post_id ? get_post($post_id) : null;
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');

		$title   = sprintf(__('Post ready for review: %s', 'ai-post-scheduler'), $post_title);
		$message = sprintf(__('Generated post "%s" is awaiting review.', 'ai-post-scheduler'), $post_title);
		$url     = $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		call_user_func(
			$this->dispatcher,
			'post_ready_for_review',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'info',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Open review queue', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a post-rejected notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_rejected( array $payload ) {
		$post_id    = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post_label = !empty($payload['post_title']) ? $payload['post_title'] : sprintf(__('Post #%d', 'ai-post-scheduler'), $post_id);

		$title   = sprintf(__('Post rejected: %s', 'ai-post-scheduler'), $post_label);
		$message = sprintf(__('Generated draft "%s" was removed from the review queue.', 'ai-post-scheduler'), $post_label);
		$url     = !empty($payload['url']) ? $payload['url'] : AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		call_user_func(
			$this->dispatcher,
			'post_rejected',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'warning',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 120,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Open generated posts', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a partial-generation-completed notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function partial_generation_completed( array $payload ) {
		$post_id    = !empty($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post       = $post_id ? get_post($post_id) : null;
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');

		$title   = sprintf(__('Partial generation completed: %s', 'ai-post-scheduler'), $post_title);
		$message = sprintf(__('Post "%s" was saved with missing components and requires review.', 'ai-post-scheduler'), $post_title);
		$url     = !empty($payload['url']) ? $payload['url'] : admin_url('admin.php?page=aips-generated-posts#aips-partial-generations');

		call_user_func(
			$this->dispatcher,
			'partial_generation_completed',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'warning',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : '',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 60,
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Open partial generations', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a daily digest summary notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function daily_digest( array $payload ) {
		$title   = __('Daily generation digest', 'ai-post-scheduler');
		$message = sprintf(
			__('Today: %1$d posts generated, %2$d review-ready, %3$d errors.', 'ai-post-scheduler'),
			isset($payload['generated'])    ? (int) $payload['generated']    : 0,
			isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0,
			isset($payload['errors'])       ? (int) $payload['errors']       : 0
		);

		$url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');

		call_user_func(
			$this->dispatcher,
			'daily_digest',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'info',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
				'dedupe_window' => 3600,
				'channels'      => array(AIPS_Notifications::CHANNEL_EMAIL),
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Open generated posts', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a weekly summary notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function weekly_summary( array $payload ) {
		$title   = __('Weekly generation summary', 'ai-post-scheduler');
		$message = sprintf(
			__('This week: %1$d posts generated, %2$d review-ready, %3$d errors.', 'ai-post-scheduler'),
			isset($payload['generated'])    ? (int) $payload['generated']    : 0,
			isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0,
			isset($payload['errors'])       ? (int) $payload['errors']       : 0
		);

		$url = AIPS_Admin_Menu_Helper::get_page_url('history');

		call_user_func(
			$this->dispatcher,
			'weekly_summary',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'info',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
				'dedupe_window' => 3600,
				'channels'      => array(AIPS_Notifications::CHANNEL_EMAIL),
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Open history', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a monthly report notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function monthly_report( array $payload ) {
		$title   = __('Monthly generation report', 'ai-post-scheduler');
		$message = sprintf(
			__('This month: %1$d posts generated, %2$d review-ready, %3$d errors.', 'ai-post-scheduler'),
			isset($payload['generated'])    ? (int) $payload['generated']    : 0,
			isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0,
			isset($payload['errors'])       ? (int) $payload['errors']       : 0
		);

		$url = AIPS_Admin_Menu_Helper::get_page_url('system_status');

		call_user_func(
			$this->dispatcher,
			'monthly_report',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $url,
				'level'         => 'info',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key']) ? $payload['dedupe_key'] : '',
				'dedupe_window' => 3600,
				'channels'      => array(AIPS_Notifications::CHANNEL_EMAIL),
				'vars'          => call_user_func($this->vars_builder, $title, $message, $payload, $url, __('Open system status', 'ai-post-scheduler')),
			)
		);
	}

	/**
	 * Send a cleanup-completed notification.
	 *
	 * @param array $payload Cleanup payload.
	 * @return void
	 */
	public function history_cleanup( array $payload ) {
		$deleted = isset($payload['deleted']) ? (int) $payload['deleted'] : 0;
		$errors  = isset($payload['errors'])  ? (int) $payload['errors']  : 0;

		$title   = __('Cleanup completed', 'ai-post-scheduler');
		$message = sprintf(__('Cleanup finished. Deleted: %1$d. Errors: %2$d.', 'ai-post-scheduler'), $deleted, $errors);

		call_user_func(
			$this->dispatcher,
			'history_cleanup',
			array(
				'title'   => $title,
				'message' => $message,
				'url'     => AIPS_Admin_Menu_Helper::get_page_url('system_status'),
				'level'   => $errors > 0 ? 'warning' : 'info',
				'meta'    => $payload,
			)
		);
	}

	/**
	 * Send a seeder-completed notification.
	 *
	 * @param array $payload Seeder payload.
	 * @return void
	 */
	public function seeder_complete( array $payload ) {
		$type        = !empty($payload['type'])    ? sanitize_text_field($payload['type'])    : __('unknown', 'ai-post-scheduler');
		$message_raw = !empty($payload['message']) ? sanitize_text_field($payload['message']) : __('Seeder operation completed.', 'ai-post-scheduler');

		call_user_func(
			$this->dispatcher,
			'seeder_complete',
			array(
				'title'   => sprintf(__('Seeder completed: %s', 'ai-post-scheduler'), $type),
				'message' => $message_raw,
				'url'     => AIPS_Admin_Menu_Helper::get_page_url('seeder'),
				'level'   => 'info',
				'meta'    => $payload,
			)
		);
	}

	/**
	 * Send a template-change notification.
	 *
	 * @param array $payload Template payload.
	 * @return void
	 */
	public function template_change( array $payload ) {
		$action        = !empty($payload['action'])        ? sanitize_key($payload['action'])              : 'updated';
		$template_name = !empty($payload['template_name']) ? sanitize_text_field($payload['template_name']) : __('Template', 'ai-post-scheduler');

		call_user_func(
			$this->dispatcher,
			'template_change',
			array(
				'title'   => sprintf(__('Template %1$s: %2$s', 'ai-post-scheduler'), $action, $template_name),
				'message' => sprintf(__('Template "%1$s" was %2$s.', 'ai-post-scheduler'), $template_name, $action),
				'url'     => AIPS_Admin_Menu_Helper::get_page_url('templates'),
				'level'   => 'info',
				'meta'    => $payload,
			)
		);
	}

	/**
	 * Send an author-suggestions-ready notification.
	 *
	 * @param array $payload Suggestions payload.
	 * @return void
	 */
	public function author_suggestions( array $payload ) {
		$count = isset($payload['count'])       ? (int) $payload['count']                        : 0;
		$niche = !empty($payload['site_niche']) ? sanitize_text_field($payload['site_niche']) : __('N/A', 'ai-post-scheduler');

		call_user_func(
			$this->dispatcher,
			'author_suggestions',
			array(
				'title'   => sprintf(__('Author suggestions ready (%d)', 'ai-post-scheduler'), $count),
				'message' => sprintf(__('Generated %1$d author suggestion(s) for niche "%2$s".', 'ai-post-scheduler'), $count, $niche),
				'url'     => AIPS_Admin_Menu_Helper::get_page_url('authors'),
				'level'   => 'info',
				'meta'    => $payload,
			)
		);
	}

	/**
	 * Send a circuit-breaker-opened notification.
	 *
	 * @param array $payload Event payload from the resilience service.
	 * @return void
	 */
	public function circuit_breaker_opened( array $payload ) {
		$error_code  = !empty($payload['error_code'])  ? $payload['error_code']  : '';
		$failures    = isset($payload['failures'])     ? (int) $payload['failures']  : 0;
		$threshold   = isset($payload['threshold'])    ? (int) $payload['threshold'] : 0;
		$reason_code = !empty($payload['reason_code']) ? $payload['reason_code'] : 'threshold_reached';

		if ('immediate_open' === $reason_code) {
			/* translators: %s: provider error code slug (e.g. "insufficient_quota") */
			$reason = sprintf(__('immediate circuit open triggered by error code "%s"', 'ai-post-scheduler'), $error_code);
		} elseif ($threshold > 0) {
			/* translators: 1: number of failures recorded 2: failure threshold */
			$reason = sprintf(__('failure threshold reached (%1$d/%2$d)', 'ai-post-scheduler'), $failures, $threshold);
		} else {
			$reason = __('failure threshold reached', 'ai-post-scheduler');
		}

		$title   = __('Circuit breaker opened', 'ai-post-scheduler');
		$message = sprintf(
			/* translators: 1: failure count 2: reason string */
			__('The AI circuit breaker tripped after %1$d failure(s) (%2$s). All AI requests are temporarily blocked. Use the System Status page to reset it after resolving the underlying issue.', 'ai-post-scheduler'),
			$failures,
			$reason
		);

		$status_url = AIPS_Admin_Menu_Helper::get_page_url('system_status');

		call_user_func(
			$this->dispatcher,
			'circuit_breaker_opened',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $status_url,
				'level'         => 'error',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : 'circuit_breaker_opened',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 1800,
				'vars'          => call_user_func(
					$this->vars_builder,
					$title,
					$message,
					array(
						__('Error code', 'ai-post-scheduler') => $error_code ?: __('N/A', 'ai-post-scheduler'),
						__('Failures',   'ai-post-scheduler') => (string) $failures,
						__('Reason',     'ai-post-scheduler') => $reason,
					),
					$status_url,
					__('Open System Status', 'ai-post-scheduler')
				),
			)
		);
	}

	/**
	 * Send a rate-limit-reached notification.
	 *
	 * @param array $payload Event payload from the resilience service.
	 * @return void
	 */
	public function rate_limit_reached( array $payload ) {
		$current = isset($payload['current_requests']) ? (int) $payload['current_requests'] : 0;
		$max     = isset($payload['max_requests'])     ? (int) $payload['max_requests']     : 0;
		$period  = isset($payload['period_seconds'])   ? (int) $payload['period_seconds']   : 0;

		$title   = __('AI rate limit reached', 'ai-post-scheduler');
		$message = sprintf(
			/* translators: 1: request count 2: max requests 3: period seconds */
			__('The internal AI rate limiter has been hit: %1$d/%2$d requests in %3$d seconds. Requests will resume automatically when the window resets.', 'ai-post-scheduler'),
			$current,
			$max,
			$period
		);

		$status_url = AIPS_Admin_Menu_Helper::get_page_url('system_status');

		call_user_func(
			$this->dispatcher,
			'rate_limit_reached',
			array(
				'title'         => $title,
				'message'       => $message,
				'url'           => $status_url,
				'level'         => 'warning',
				'meta'          => $payload,
				'dedupe_key'    => !empty($payload['dedupe_key'])    ? $payload['dedupe_key']          : 'rate_limit_reached',
				'dedupe_window' => !empty($payload['dedupe_window']) ? (int) $payload['dedupe_window'] : 900,
				'vars'          => call_user_func(
					$this->vars_builder,
					$title,
					$message,
					array(
						__('Requests',      'ai-post-scheduler') => "{$current}/{$max}",
						__('Window (secs)', 'ai-post-scheduler') => (string) $period,
					),
					$status_url,
					__('Open System Status', 'ai-post-scheduler')
				),
			)
		);
	}

	/**
	 * Send a research-topics-ready notification.
	 *
	 * @param array $payload Research payload.
	 * @return void
	 */
	public function research_topics_ready( array $payload ) {
		$count = isset($payload['count']) ? (int) $payload['count'] : 0;
		$niche = !empty($payload['niche']) ? sanitize_text_field($payload['niche']) : __('N/A', 'ai-post-scheduler');

		call_user_func(
			$this->dispatcher,
			'research_topics_ready',
			array(
				'title'         => sprintf(__('Research topics ready (%d)', 'ai-post-scheduler'), $count),
				'message'       => sprintf(__('Scheduled research found %1$d new topic(s) for niche "%2$s".', 'ai-post-scheduler'), $count, $niche),
				'url'           => AIPS_Admin_Menu_Helper::get_page_url('research'),
				'level'         => 'info',
				'meta'          => array(
					'niche' => $niche,
					'count' => $count,
				),
				'dedupe_key'    => 'research_topics_ready_' . sanitize_key($niche) . '_' . gmdate('YmdH'),
				'dedupe_window' => 300,
			)
		);
	}
}
