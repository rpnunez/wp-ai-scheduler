<?php
/**
 * Central Notifications Service
 *
 * AIPS_Notifications is the single entry-point for sending any notification
 * within the plugin, regardless of channel (DB admin-bar notification, email,
 * or both simultaneously).
 *
 * Built-in notification types
 * ---------------------------
 *  - author_topics_generated  — DB notification shown in the admin bar.
 *  - partial_generation       — Email alert when a post is saved with missing components.
 *  - posts_awaiting_review    — Daily email digest of draft posts waiting for review.
 *
 * Usage examples
 * --------------
 *
 *   // 1. Convenience methods (recommended for built-in notifications)
 *   $notifs = new AIPS_Notifications();
 *   $notifs->author_topics_generated( 'Jane Doe', 10, 42 );
 *   $notifs->partial_generation( $post_id, $component_statuses, $context, $history_id );
 *   $notifs->posts_awaiting_review( $draft_posts, $draft_count );
 *
 *   // 2. Low-level send() for custom or third-party notification types
 *   $notifs->send(
 *       'my_type',
 *       array( '{{site_name}}' => get_bloginfo('name'), '{{user}}' => 'Jane' ),
 *       array( AIPS_Notifications::CHANNEL_DB, AIPS_Notifications::CHANNEL_EMAIL ),
 *       'admin@example.com',   // $to_email  (required for CHANNEL_EMAIL)
 *       'https://...',         // $db_url    (optional, for the DB notification link)
 *       'Human-readable msg'   // $db_message (optional, DB notification body)
 *   );
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notifications
 */
class AIPS_Notifications {

	// -----------------------------------------------------------------------
	// Channel constants
	// -----------------------------------------------------------------------

	/** Persist the notification in the DB (visible in the admin-bar dropdown). */
	const CHANNEL_DB = 'db';

	/** Send the notification via email using wp_mail(). */
	const CHANNEL_EMAIL = 'email';

	// -----------------------------------------------------------------------
	// Dependencies
	// -----------------------------------------------------------------------

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Notification_Templates
	 */
	private $templates;

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	// -----------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * All dependencies are optional and default to their concrete implementations,
	 * making the class easy to unit-test by passing mocks.
	 *
	 * @param AIPS_Notifications_Repository|null $repository      DB notifications repository.
	 * @param AIPS_Notification_Templates|null   $templates       Email template registry.
	 * @param AIPS_History_Service|null          $history_service History/audit service.
	 */
	public function __construct(
		$repository = null,
		$templates = null,
		$history_service = null
	) {
		$this->repository      = $repository      instanceof AIPS_Notifications_Repository ? $repository      : new AIPS_Notifications_Repository();
		$this->templates       = $templates       instanceof AIPS_Notification_Templates   ? $templates       : new AIPS_Notification_Templates();
		$this->history_service = $history_service instanceof AIPS_History_Service          ? $history_service : new AIPS_History_Service();

		$this->register_hooks();
	}

	// -----------------------------------------------------------------------
	// Hook registration
	// -----------------------------------------------------------------------

	/**
	 * Register WordPress action hooks for the built-in scheduled notifications.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Daily cron: send draft-posts-review digest email.
		add_action('aips_send_review_notifications', array($this, 'handle_review_notifications_cron'));

		// Immediate: email alert when a post is saved with missing generated components.
		add_action('aips_post_generation_incomplete', array($this, 'handle_partial_generation'), 10, 4);
	}

	// -----------------------------------------------------------------------
	// Core send method
	// -----------------------------------------------------------------------

	/**
	 * Send a notification through one or more channels.
	 *
	 * @param string $type       Notification type slug (must match a registered template for CHANNEL_EMAIL).
	 * @param array  $vars       Token-replacement map: `'{{token}}' => 'value'` (or just `'token' => 'value'`).
	 * @param array  $channels   Channels to use.  Defaults to `[CHANNEL_DB]`.  Pass `[CHANNEL_DB, CHANNEL_EMAIL]` for both.
	 * @param string $to_email   Target email address.  Required when CHANNEL_EMAIL is included.
	 * @param string $db_url     Optional URL attached to the DB notification (action link in the admin bar).
	 * @param string $db_message Human-readable message for the DB notification.  Required when CHANNEL_DB is included.
	 * @return void
	 */
	public function send(
		$type,
		array $vars = array(),
		array $channels = array(self::CHANNEL_DB),
		$to_email = '',
		$db_url = '',
		$db_message = ''
	) {
		foreach ($channels as $channel) {
			if ($channel === self::CHANNEL_DB) {
				$this->send_db_notification($type, $db_message, $db_url);
			} elseif ($channel === self::CHANNEL_EMAIL) {
				$template = $this->templates->get($type);
				if ($template && is_email($to_email)) {
					$this->send_email_notification($to_email, $template, $vars);
				}
			}
		}
	}

	// -----------------------------------------------------------------------
	// Named convenience methods
	// -----------------------------------------------------------------------

	/**
	 * Create a DB notification when topics have been generated for an author.
	 *
	 * @param string $author_name  Author display name.
	 * @param int    $topic_count  Number of topics generated.
	 * @param int    $author_id    Author ID (used to build the action link URL).
	 * @return void
	 */
	public function author_topics_generated($author_name, $topic_count, $author_id) {
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

		$this->send(
			'author_topics_generated',
			array(),
			array(self::CHANNEL_DB),
			'',
			$url,
			$message
		);
	}

	/**
	 * Send an email notification when a post is saved with missing generated components.
	 *
	 * @param int                     $post_id             WordPress post ID.
	 * @param array                   $component_statuses  Per-component boolean status map.
	 * @param AIPS_Generation_Context $context             Generation context.
	 * @param int                     $history_id          Related history session ID.
	 * @return void
	 */
	public function partial_generation($post_id, array $component_statuses, $context, $history_id = 0) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$to_email = get_option('aips_review_notifications_email', get_option('admin_email'));
		if (!is_email($to_email)) {
			return;
		}

		$missing_components = $this->get_missing_components($component_statuses);
		if (empty($missing_components)) {
			return;
		}

		$post       = get_post($post_id);
		$post_title = ($post && !empty($post->post_title)) ? $post->post_title : __('Untitled', 'ai-post-scheduler');
		$edit_url   = get_edit_post_link($post_id);
		$partial_url = admin_url('admin.php?page=aips-generated-posts#aips-partial-generations');

		// Build the `<ul>` list for missing components.
		$components_html = '<ul class="component-list">';
		foreach ($missing_components as $label) {
			$components_html .= '<li>' . esc_html($label) . '</li>';
		}
		$components_html .= '</ul>';

		// Optional session ID row.
		$history_id_row = '';
		if (!empty($history_id)) {
			$history_id_row = '<br><strong>' . esc_html__('Session ID:', 'ai-post-scheduler') . '</strong> ' . esc_html($history_id);
		}

		$vars = array(
			'{{site_name}}'          => esc_html(get_bloginfo('name')),
			'{{post_title}}'         => esc_html($post_title),
			'{{source_label}}'       => esc_html($this->get_source_label($context)),
			'{{history_id_row}}'     => $history_id_row,
			'{{missing_components}}' => $components_html,
			'{{edit_url}}'           => esc_url($edit_url),
			'{{partial_url}}'        => esc_url($partial_url),
		);

		$this->send(
			'partial_generation',
			$vars,
			array(self::CHANNEL_EMAIL),
			$to_email
		);
	}

	/**
	 * Send a daily digest email listing the draft posts that await review.
	 *
	 * @param array $draft_posts Repository result set (array with 'items' key).
	 * @param int   $total_count Total number of draft posts.
	 * @return void
	 */
	public function posts_awaiting_review(array $draft_posts, $total_count) {
		$to_email = get_option('aips_review_notifications_email', get_option('admin_email'));
		if (!is_email($to_email)) {
			return;
		}

		$review_url  = AIPS_Admin_Menu_Helper::get_page_url('generated_posts') . '#aips-pending-review';
		$stats_label = sprintf(
			_n('%d Post Awaiting Review', '%d Posts Awaiting Review', $total_count, 'ai-post-scheduler'),
			$total_count
		);

		// Build post list HTML.
		$post_list_html = '';
		if (!empty($draft_posts['items'])) {
			$post_list_html .= '<p><strong>' . esc_html__('Recent Draft Posts:', 'ai-post-scheduler') . '</strong></p>';
			$post_list_html .= '<ul class="post-list">';
			foreach ($draft_posts['items'] as $post) {
				$title = $post->post_title ?: ($post->generated_title ?? __('Untitled', 'ai-post-scheduler'));
				$meta  = sprintf(
					__('Template: %s | Created: %s', 'ai-post-scheduler'),
					$post->template_name ?: __('None', 'ai-post-scheduler'),
					date_i18n(get_option('date_format'), strtotime($post->created_at))
				);
				$post_list_html .= '<li class="post-item">';
				$post_list_html .= '<div class="post-title">' . esc_html($title) . '</div>';
				$post_list_html .= '<div class="post-meta">' . esc_html($meta) . '</div>';
				$post_list_html .= '</li>';
			}
			$post_list_html .= '</ul>';
		}

		// "…and N more" note.
		$more_posts_html = '';
		if ($total_count > 10) {
			$more_posts_html = '<p><em>' . esc_html(sprintf(
				__('...and %d more posts', 'ai-post-scheduler'),
				$total_count - 10
			)) . '</em></p>';
		}

		$vars = array(
			'{{site_name}}'   => esc_html(get_bloginfo('name')),
			'{{stats_label}}' => esc_html($stats_label),
			'{{post_list}}'   => $post_list_html,
			'{{more_posts}}'  => $more_posts_html,
			'{{review_url}}'  => esc_url($review_url),
		);

		$this->send(
			'posts_awaiting_review',
			$vars,
			array(self::CHANNEL_EMAIL),
			$to_email
		);
	}

	// -----------------------------------------------------------------------
	// Cron / action hook handlers
	// -----------------------------------------------------------------------

	/**
	 * Hook handler: `aips_send_review_notifications` (daily cron).
	 *
	 * Checks plugin settings, fetches draft posts, and delegates to
	 * posts_awaiting_review() which handles the actual sending.
	 *
	 * @return void
	 */
	public function handle_review_notifications_cron() {
		if (!get_option('aips_review_notifications_enabled', 0)) {
			return;
		}

		$repository  = new AIPS_Post_Review_Repository();
		$draft_count = $repository->get_draft_count();

		if ($draft_count === 0) {
			return;
		}

		$draft_posts = $repository->get_draft_posts(array(
			'per_page' => 10,
			'page'     => 1,
		));

		$this->posts_awaiting_review($draft_posts, $draft_count);
	}

	/**
	 * Hook handler: `aips_post_generation_incomplete` (fired by the generator).
	 *
	 * @param int                     $post_id            WordPress post ID.
	 * @param array                   $component_statuses Per-component boolean status map.
	 * @param AIPS_Generation_Context $context            Generation context.
	 * @param int                     $history_id         Related history session ID.
	 * @return void
	 */
	public function handle_partial_generation($post_id, $component_statuses, $context, $history_id = 0) {
		$this->partial_generation($post_id, $component_statuses, $context, $history_id);
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Persist a DB notification via the repository.
	 *
	 * @param string $type    Notification type slug.
	 * @param string $message Human-readable message body.
	 * @param string $url     Optional action-link URL.
	 * @return void
	 */
	private function send_db_notification($type, $message, $url = '') {
		if (empty($message)) {
			return;
		}
		$this->repository->create($type, $message, $url);
	}

	/**
	 * Render and dispatch an email notification.
	 *
	 * Logs the outcome via AIPS_History_Service.
	 *
	 * @param string                     $to_email Target email address.
	 * @param AIPS_Notification_Template $template Template to render.
	 * @param array                      $vars     Token-replacement map.
	 * @return void
	 */
	private function send_email_notification($to_email, AIPS_Notification_Template $template, array $vars) {
		$subject = $template->render_subject($vars);
		$body    = $template->render_body($vars);
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		$sent = wp_mail($to_email, $subject, $body, $headers);

		if ($sent) {
			$history = $this->history_service->create('notification_sent', array());
			$history->record(
				'activity',
				/* translators: %s: recipient email address */
				sprintf(__('Notification email sent to %s', 'ai-post-scheduler'), $to_email),
				array(
					'event_type'   => 'notification_email_sent',
					'event_status' => 'success',
				),
				null,
				array(
					'notification_type' => $template->get_type(),
					'to_email'          => $to_email,
				)
			);
		}
	}

	/**
	 * Derive a human-readable source label from a generation context object.
	 *
	 * @param AIPS_Generation_Context|mixed $context Generation context.
	 * @return string
	 */
	private function get_source_label($context) {
		if (!is_object($context) || !method_exists($context, 'get_type')) {
			return __('Unknown', 'ai-post-scheduler');
		}

		if ($context instanceof AIPS_Template_Context) {
			$template = $context->get_template();
			if ($template && !empty($template->name)) {
				return sprintf(__('Template: %s', 'ai-post-scheduler'), $template->name);
			}
			return __('Template', 'ai-post-scheduler');
		}

		if ($context instanceof AIPS_Topic_Context) {
			$topic = $context->get_topic();
			if (!empty($topic)) {
				return sprintf(__('Author Topic: %s', 'ai-post-scheduler'), $topic);
			}
			return __('Author Topic', 'ai-post-scheduler');
		}

		return __('Unknown', 'ai-post-scheduler');
	}

	/**
	 * Return the list of missing component labels from a status map.
	 *
	 * @param array $component_statuses Per-component boolean status map.
	 * @return array Array of translated label strings.
	 */
	private function get_missing_components(array $component_statuses) {
		$labels = array(
			'post_title'     => __('Title', 'ai-post-scheduler'),
			'post_excerpt'   => __('Excerpt', 'ai-post-scheduler'),
			'post_content'   => __('Content', 'ai-post-scheduler'),
			'featured_image' => __('Featured Image', 'ai-post-scheduler'),
		);

		$missing = array();
		foreach ($labels as $key => $label) {
			if (array_key_exists($key, $component_statuses) && !$component_statuses[$key]) {
				$missing[] = $label;
			}
		}

		return $missing;
	}
}
