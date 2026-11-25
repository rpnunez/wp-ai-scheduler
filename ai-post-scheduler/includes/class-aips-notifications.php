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
 *
 * Usage examples
 * --------------
 *
 *   // 1. Convenience methods (recommended for built-in notifications)
 *   $notifs = new AIPS_Notifications();
 *   $notifs->author_topics_generated( 'Jane Doe', 10, 42 );
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

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------------
	// Channel constants
	// -----------------------------------------------------------------------

	/** Persist the notification in the DB (visible in the admin-bar dropdown). */
	const CHANNEL_DB = 'db';

	/** Send the notification via email using wp_mail(). */
	const CHANNEL_EMAIL = 'email';

	/** Disable all delivery for a notification type. */
	const MODE_OFF = 'off';

	/** Persist only in the DB. */
	const MODE_DB_ONLY = 'db';

	/** Send only email notifications. */
	const MODE_EMAIL_ONLY = 'email';

	/** Send to both DB and email. */
	const MODE_BOTH = 'both';

	// -----------------------------------------------------------------------
	// Dependencies
	// -----------------------------------------------------------------------

	/**
	 * @var AIPS_Notifications_Repository_Interface
	 */
	private $repository;

	/**
	 * @var AIPS_Notification_Templates
	 */
	private $templates;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Notifications_Event_Handler
	 */
	private $event_handler;

	/**
	 * @var AIPS_Notification_Senders
	 */
	private $senders;

	/**
	 * Builds the structured view model behind the redesigned emails.
	 *
	 * @var AIPS_Email_View_Factory
	 */
	private $email_view_factory;

	/**
	 * Renders a view model into an HTML email document.
	 *
	 * @var AIPS_Email_Renderer
	 */
	private $email_renderer;

	/**
	 * Plain-text alternative for the email currently being handed to wp_mail().
	 *
	 * Held on the instance so the `phpmailer_init` callback can reach it; wp_mail()
	 * offers no other way to set a multipart alternative body.
	 *
	 * @var string
	 */
	private $pending_alt_body = '';

	// -----------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * All dependencies are optional and default to their concrete implementations,
	 * making the class easy to unit-test by passing mocks.
	 *
	 * @param AIPS_Notifications_Repository_Interface|null $repository      DB notifications repository.
	 * @param AIPS_Notification_Templates|null   $templates       Email template registry.
	 * @param AIPS_History_Service_Interface|null $history_service History/audit service.
	 */
	public function __construct(
		?AIPS_Notifications_Repository_Interface $repository = null,
		$templates = null,
		?AIPS_History_Service_Interface $history_service = null
	) {
		$container = AIPS_Container::get_instance();
		$this->repository      = $repository      ?: ($container->has(AIPS_Notifications_Repository_Interface::class) ? $container->make(AIPS_Notifications_Repository_Interface::class) : new AIPS_Notifications_Repository());
		$this->templates       = $templates       instanceof AIPS_Notification_Templates   ? $templates       : new AIPS_Notification_Templates();
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());

		$this->event_handler = new AIPS_Notifications_Event_Handler($this, $this->repository);

		$this->email_view_factory = new AIPS_Email_View_Factory();
		$this->email_renderer     = new AIPS_Email_Renderer();

		$this->senders = new AIPS_Notification_Senders(
			array( $this, 'dispatch_notification' ),
			array( $this, 'build_standard_notification_vars' )
		);
	}

	/**
	 * Return the event handler instance.
	 *
	 * @return AIPS_Notifications_Event_Handler
	 */
	public function get_event_handler() {
		return $this->event_handler;
	}

	/**
	 * Return the available channel modes for settings.
	 *
	 * @return array<string, string>
	 */
	public static function get_channel_mode_options() {
		return AIPS_Notification_Registry::get_channel_mode_options();
	}

	/**
	 * Return the notification type registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_notification_type_registry() {
		return AIPS_Notification_Registry::get_type_registry();
	}

	/**
	 * Return only the high-priority notification types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_high_priority_notification_types() {
		return AIPS_Notification_Registry::get_high_priority_types();
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
		$db_message = '',
		array $options = array()
	) {
		$options['vars'] = $vars;
		$options['channels'] = $channels;
		$options['to_email'] = $to_email;
		$options['url'] = $db_url;
		$options['message'] = $db_message;

		$this->dispatch_notification($type, $options);
	}

	// -----------------------------------------------------------------------
	// Named convenience methods (proxy to AIPS_Notification_Senders)
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
		$this->senders->author_topics_generated($author_name, $topic_count, $author_id);
	}

	/**
	 * Send a high-priority generation failure notification.
	 *
	 * @param array $payload Failure payload.
	 * @return void
	 */
	public function generation_failed(array $payload) {
		$this->senders->generation_failed($payload);
	}

	/**
	 * Send a high-priority quota alert notification.
	 *
	 * @param array $payload Alert payload.
	 * @return void
	 */
	public function quota_alert(array $payload) {
		$this->senders->quota_alert($payload);
	}

	/**
	 * Send a high-priority integration error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function integration_error(array $payload) {
		$this->senders->integration_error($payload);
	}

	/**
	 * Send a scheduler error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function scheduler_error(array $payload) {
		$this->senders->scheduler_error($payload);
	}

	/**
	 * Send a system error notification.
	 *
	 * @param array $payload Error payload.
	 * @return void
	 */
	public function system_error(array $payload) {
		$this->senders->system_error($payload);
	}

	/**
	 * Send a template-generated notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function template_generated(array $payload) {
		$this->senders->template_generated($payload);
	}

	/**
	 * Send a manual generation completed notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function manual_generation_completed(array $payload) {
		$this->senders->manual_generation_completed($payload);
	}

	/**
	 * Send a post-ready-for-review notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_ready_for_review(array $payload) {
		$this->senders->post_ready_for_review($payload);
	}

	/**
	 * Send a post-rejected notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_rejected(array $payload) {
		$this->senders->post_rejected($payload);
	}

	/**
	 * Send a partial-generation-completed notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function partial_generation_completed(array $payload) {
		$this->senders->partial_generation_completed($payload);
	}

	/**
	 * Send a post-generated notification.
	 *
	 * @param array $payload Notification payload.
	 * @return void
	 */
	public function post_generated(array $payload) {
		$this->senders->post_generated($payload);
	}

	/**
	 * Send a daily digest summary notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function daily_digest(array $payload) {
		$this->senders->daily_digest($payload);
	}

	/**
	 * Send a summarized activity digest notification.
	 *
	 * @param array $payload Summary payload containing 'items' and optional 'count'.
	 * @return void
	 */
	public function summarized_digest(array $payload) {
		$this->senders->summarized_digest($payload);
	}

	/**
	 * Send a weekly summary notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function weekly_summary(array $payload) {
		$this->senders->weekly_summary($payload);
	}

	/**
	 * Send a monthly report notification.
	 *
	 * @param array $payload Summary payload.
	 * @return void
	 */
	public function monthly_report(array $payload) {
		$this->senders->monthly_report($payload);
	}

	/**
	 * Send a cleanup-completed notification.
	 *
	 * @param array $payload Cleanup payload.
	 * @return void
	 */
	public function history_cleanup(array $payload) {
		$this->senders->history_cleanup($payload);
	}

	/**
	 * Send a seeder-completed notification.
	 *
	 * @param array $payload Seeder payload.
	 * @return void
	 */
	public function seeder_complete(array $payload) {
		$this->senders->seeder_complete($payload);
	}

	/**
	 * Send a template-change notification.
	 *
	 * @param array $payload Template payload.
	 * @return void
	 */
	public function template_change(array $payload) {
		$this->senders->template_change($payload);
	}

	/**
	 * Send an author-suggestions-ready notification.
	 *
	 * @param array $payload Suggestions payload.
	 * @return void
	 */
	public function author_suggestions(array $payload) {
		$this->senders->author_suggestions($payload);
	}

	/**
	 * Send a circuit-breaker-opened notification.
	 *
	 * @param array $payload Event payload from the resilience service.
	 * @return void
	 */
	public function circuit_breaker_opened(array $payload) {
		$this->senders->circuit_breaker_opened($payload);
	}

	/**
	 * Send a rate-limit-reached notification.
	 *
	 * @param array $payload Event payload from the resilience service.
	 * @return void
	 */
	public function rate_limit_reached(array $payload) {
		$this->senders->rate_limit_reached($payload);
	}

	/**
	 * Send a research-topics-ready notification.
	 *
	 * @param array $payload Research payload.
	 * @return void
	 */
	public function research_topics_ready(array $payload) {
		$this->senders->research_topics_ready($payload);
	}

	/**
	 * Send a posts-consolidated notification.
	 *
	 * @param array $payload Consolidation payload (see AIPS_Consolidation_Service).
	 * @return void
	 */
	public function post_consolidated(array $payload) {
		$this->senders->post_consolidated($payload);
	}

	/**
	 * Notify that a scheduled duplicate scan found new groups to review.
	 *
	 * @param array $payload Keys: new_groups, group_count, post_count.
	 * @return void
	 */
	public function duplicate_groups_found(array $payload) {
		$this->senders->duplicate_groups_found($payload);
	}


	/**
	 * Persist a DB notification via the repository.
	 *
	 * @param string $type    Notification type slug.
	 * @param string $message Human-readable message body.
	 * @param string $url     Optional action-link URL.
	 * @return void
	 */
	private function send_db_notification($type, $message, $url = '', array $options = array()) {
		if (empty($message)) {
			return;
		}

		$this->repository->create_notification(array(
			'type'       => $type,
			'title'      => isset($options['title']) ? $options['title'] : '',
			'message'    => $message,
			'url'        => $url,
			'level'      => isset($options['level']) ? $options['level'] : 'info',
			'meta'       => isset($options['meta']) ? $options['meta'] : null,
			'dedupe_key' => isset($options['dedupe_key']) ? $options['dedupe_key'] : '',
		));
	}

	/**
	 * Render and dispatch an email notification.
	 *
	 * Logs the outcome via AIPS_History_Service.
	 *
	 * @param string                     $to_email Target email address.
	 * @param AIPS_Notification_Template $template Template to render.
	 * @param array                      $vars     Token-replacement map.
	 * @return bool True when wp_mail reports success.
	 */
	private function send_email_notification($to_email, AIPS_Notification_Template $template, array $vars, ?AIPS_Email_View $view = null) {
		// The subject keeps coming from the template so per-type subject patterns
		// (and any inbox rule matching them) are unchanged by the redesign.
		$subject = $template->render_subject($vars);

		$body     = '';
		$alt_body = '';

		if ($view instanceof AIPS_Email_View) {
			$body = $this->email_renderer->render($view);

			if ('' !== $body) {
				$alt_body = $this->email_renderer->render_text($view);
			}
		}

		if ('' === $body) {
			// Fallback: the legacy token-replacement body. Reached when no view was
			// composed (a third-party template registered through the
			// `aips_notification_templates` hook) or when the layout file is
			// unavailable.
			$body = $template->render_body($vars);
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		$sent = $this->mail_with_alternative($to_email, $subject, $body, $headers, $alt_body);

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
			$history->complete_success();

			return true;
		}

		$history = $this->history_service->create('notification_sent', array());
		$history->record(
			'activity',
			/* translators: %s: recipient email address */
			sprintf(__('Notification email failed for %s', 'ai-post-scheduler'), $to_email),
			array(
				'event_type'   => 'notification_email_sent',
				'event_status' => 'failed',
			),
			null,
			array(
				'notification_type' => $template->get_type(),
				'to_email'          => $to_email,
			)
		);
		$history->complete_failure(sprintf(__('Notification email failed for %s', 'ai-post-scheduler'), $to_email));

		return false;
	}

	/**
	 * Compose the structured email view for a notification.
	 *
	 * Returns null when the factory cannot produce a view, in which case the email
	 * falls back to the legacy token-replacement body.
	 *
	 * @param string $type    Notification type slug.
	 * @param array  $context Dispatch context: title, message, url, level, meta, vars.
	 * @return AIPS_Email_View|null
	 */
	public function build_email_view($type, array $context = array()) {
		if (!class_exists('AIPS_Email_View_Factory')) {
			return null;
		}

		$view = $this->email_view_factory->build($type, $context);

		return ($view instanceof AIPS_Email_View) ? $view : null;
	}

	/**
	 * Render a notification view to HTML.
	 *
	 * Exposed so the Settings screen can send a test email (and so tests can
	 * assert on real rendered output) without duplicating the render pipeline.
	 *
	 * @param AIPS_Email_View $view View to render.
	 * @return string
	 */
	public function render_email_view(AIPS_Email_View $view) {
		return $this->email_renderer->render($view);
	}

	/**
	 * Send an HTML email with a plain-text alternative part.
	 *
	 * wp_mail() has no parameter for a multipart alternative body, so the text part
	 * is attached through `phpmailer_init`.  The hook is registered only for the
	 * duration of this call so nothing else in the request inherits it.
	 *
	 * @param string $to_email Recipient address.
	 * @param string $subject  Subject line.
	 * @param string $body     HTML body.
	 * @param array  $headers  Mail headers.
	 * @param string $alt_body Plain-text alternative, or '' to send HTML only.
	 * @return bool True when wp_mail() reports success.
	 */
	private function mail_with_alternative($to_email, $subject, $body, array $headers, $alt_body = '') {
		if ('' === (string) $alt_body) {
			return wp_mail($to_email, $subject, $body, $headers);
		}

		$this->pending_alt_body = (string) $alt_body;

		$attach_alt_body = function ($phpmailer) {
			if ('' !== $this->pending_alt_body) {
				$phpmailer->AltBody = $this->pending_alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar -- PHPMailer property.
			}
		};

		add_action('phpmailer_init', $attach_alt_body);

		try {
			$sent = wp_mail($to_email, $subject, $body, $headers);
		} finally {
			remove_action('phpmailer_init', $attach_alt_body);
			$this->pending_alt_body = '';
		}

		return $sent;
	}

	/**
	 * Dispatch a notification using the resolved channels.
	 *
	 * Public visibility is intentional: this method is part of the public API
	 * contract and is injected as a callable into AIPS_Notification_Senders via
	 * the constructor to support dependency injection without tight coupling.
	 *
	 * @param string $type    Notification type.
	 * @param array  $options Notification options.
	 * @return bool True when at least one channel was used.
	 */
	public function dispatch_notification($type, array $options = array()) {
		$config = $this->get_notification_type_config($type);
		$channels = $this->resolve_channels($type, isset($options['channels']) ? $options['channels'] : array(self::CHANNEL_DB));
		$vars = isset($options['vars']) && is_array($options['vars']) ? $options['vars'] : array();
		$vars['{{site_name}}'] = isset($vars['{{site_name}}']) ? $vars['{{site_name}}'] : esc_html(get_bloginfo('name'));

		$dedupe_key = !empty($options['dedupe_key']) ? sanitize_text_field($options['dedupe_key']) : '';
		$dedupe_window = isset($options['dedupe_window']) && absint($options['dedupe_window']) > 0 ? absint($options['dedupe_window']) : (isset($config['dedupe_window']) ? absint($config['dedupe_window']) : 0);

		if ($this->is_duplicate_notification($dedupe_key, $dedupe_window)) {
			return false;
		}

		$sent = false;
		$title = isset($options['title']) ? $options['title'] : '';
		$message = isset($options['message']) ? $options['message'] : '';
		$url = isset($options['url']) ? $options['url'] : '';
		$level = isset($options['level']) ? $options['level'] : (isset($config['level']) ? $config['level'] : 'info');
		$meta = isset($options['meta']) ? $options['meta'] : null;

		if (in_array(self::CHANNEL_DB, $channels, true)) {
			$this->send_db_notification($type, $message, $url, array(
				'title'      => $title,
				'level'      => $level,
				'meta'       => $meta,
				'dedupe_key' => $dedupe_key,
			));
			$sent = true;
		}

		if (in_array(self::CHANNEL_EMAIL, $channels, true)) {
			if ($this->should_intercept_for_digest($type)) {
				$this->enqueue_digest_notification($type, array(
					'title'      => $title,
					'message'    => $message,
					'url'        => $url,
					'level'      => $level,
					'meta'       => $meta,
					'vars'       => $vars,
					'to_email'   => isset($options['to_email']) ? $options['to_email'] : '',
					'dedupe_key' => $dedupe_key,
				));
				$sent = true;
			} else {
				$template = $this->templates->get($type);
				$recipients = $this->parse_notification_emails(isset($options['to_email']) ? $options['to_email'] : '');

				if ($template && !empty($recipients)) {
					// Compose the structured view once for all recipients — but only
					// for the plugin's own, unmodified templates. A type registered or
					// overridden through `aips_notification_templates` keeps rendering
					// its own body, so a custom message (and its custom tokens) is not
					// discarded in favour of the generic designed view.
					$view = $this->templates->uses_design_system($type)
						? $this->build_email_view($type, array(
							'title'   => $title,
							'message' => $message,
							'url'     => $url,
							'level'   => $level,
							'meta'    => is_array($meta) ? $meta : array(),
							'vars'    => $vars,
						))
						: null;

					$email_sent = false;
					foreach ($recipients as $recipient) {
						if ($this->send_email_notification($recipient, $template, $vars, $view)) {
							$email_sent = true;
						}
					}
					if ($email_sent) {
						$sent = true;
					}
				}
			}
		}

		if ($sent && '' !== $dedupe_key && $dedupe_window > 0) {
			set_transient($this->get_dedupe_transient_key($dedupe_key), 1, $dedupe_window);
		}

		return $sent;
	}

	/**
	 * Resolve effective channels for a notification type.
	 *
	 * @param string $type             Notification type.
	 * @param array  $fallback_channels Fallback channels.
	 * @return array<int, string>
	 */
	private function resolve_channels($type, array $fallback_channels) {
		$config = $this->get_notification_type_config($type);
		$mode = null;

		if (isset($config['default_mode'])) {
			$preferences = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
			$mode = isset($preferences[$type]) ? $preferences[$type] : (isset($config['default_mode']) ? $config['default_mode'] : self::MODE_BOTH);
		}

		if (null === $mode) {
			return $fallback_channels;
		}

		switch ($mode) {
			case self::MODE_OFF:
				return array();
			case self::MODE_DB_ONLY:
				return array(self::CHANNEL_DB);
			case self::MODE_EMAIL_ONLY:
				return array(self::CHANNEL_EMAIL);
			case self::MODE_BOTH:
			default:
				return array(self::CHANNEL_DB, self::CHANNEL_EMAIL);
		}
	}

	/**
	 * Return notification configuration for a type.
	 *
	 * @param string $type Notification type.
	 * @return array<string, mixed>
	 */
	private function get_notification_type_config($type) {
		$registry = AIPS_Notification_Registry::get_type_registry();

		return isset($registry[$type]) ? $registry[$type] : array();
	}

	/**
	 * Check whether notification recipients are configured.
	 *
	 * @return bool
	 */
	private function has_notification_recipients() {
		return !empty($this->parse_notification_emails(''));
	}

	/**
	 * Parse a notification email list.
	 *
	 * @param string|array $emails Raw email list.
	 * @return array<int, string>
	 */
	private function parse_notification_emails($emails) {
		if (empty($emails)) {
			$stored = AIPS_Config::get_instance()->get_option('aips_review_notifications_email');
			if (is_string($stored)) {
				$stored = trim($stored);
			}

			if (empty($stored)) {
				$emails = get_option('admin_email');
			} else {
				$emails = $stored;
			}
		}

		if (is_array($emails)) {
			$candidates = $emails;
		} else {
			$candidates = preg_split('/\s*,\s*/', (string) $emails);
		}

		$candidates = is_array($candidates) ? $candidates : array();
		$valid = array();

		foreach ($candidates as $candidate) {
			$candidate = sanitize_email($candidate);
			if (!empty($candidate) && is_email($candidate)) {
				$valid[] = $candidate;
			}
		}

		return array_values(array_unique($valid));
	}

	/**
	 * Determine whether a notification is a duplicate inside the dedupe window.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @param int    $window     Dedupe window in seconds.
	 * @return bool
	 */
	private function is_duplicate_notification($dedupe_key, $window) {
		if ('' === $dedupe_key || $window < 1) {
			return false;
		}

		if (get_transient($this->get_dedupe_transient_key($dedupe_key))) {
			return true;
		}

		return $this->repository->was_recently_sent($dedupe_key, $window);
	}

	/**
	 * Return the transient key used for dedupe.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @return string
	 */
	private function get_dedupe_transient_key($dedupe_key) {
		return 'aips_notif_' . md5($dedupe_key);
	}

	/**
	 * Build standard notification template variables.
	 *
	 * Public visibility is intentional: this method is part of the public API
	 * contract and is injected as a callable into AIPS_Notification_Senders via
	 * the constructor to support dependency injection without tight coupling.
	 *
	 * @param string $title        Notification title.
	 * @param string $message      Notification message.
	 * @param array  $details      Additional detail payload.
	 * @param string $action_url   Optional action URL.
	 * @param string $action_label Optional action label.
	 * @return array<string, string>
	 */
	public function build_standard_notification_vars($title, $message, array $details, $action_url = '', $action_label = '') {
		return array(
			'{{site_name}}'          => esc_html(get_bloginfo('name')),
			'{{notification_title}}' => esc_html($title),
			'{{notification_message}}' => esc_html($message),
			'{{details_html}}'       => $this->build_details_html($details),
			'{{action_url}}'         => esc_url($action_url),
			'{{action_label}}'       => esc_html($action_label),
		);
	}

	/**
	 * Render detail rows for email templates.
	 *
	 * @param array $details Notification details.
	 * @return string
	 */
	private function build_details_html(array $details) {
		$items = array();

		foreach ($details as $key => $value) {
			if (is_array($value) || is_object($value) || '' === (string) $value) {
				continue;
			}

			$label = ucwords(str_replace(array('_', '-'), ' ', (string) $key));
			$items[] = '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html((string) $value) . '</li>';
		}

		if (empty($items)) {
			return '';
		}

		return '<ul class="notification-details">' . implode('', $items) . '</ul>';
	}

	// -----------------------------------------------------------------------
	// Summarized Notification Digest Queue & Dispatch API
	// -----------------------------------------------------------------------

	/**
	 * Check whether a notification type should be queued into the summarized digest.
	 *
	 * @param string $type Notification type slug.
	 * @return bool
	 */
	public function should_intercept_for_digest($type) {
		$config = AIPS_Config::get_instance();
		$digest_config = $config->get_notification_summarized_digest_config();

		if (!$digest_config['enabled']) {
			return false;
		}

		$eligible = AIPS_Notification_Registry::get_digest_eligible_types();
		if (!isset($eligible[$type])) {
			return false;
		}

		return in_array($type, $digest_config['types'], true);
	}

	/**
	 * Enqueue a notification into the pending summarized digest queue.
	 *
	 * If the threshold is reached or exceeded, flushes the queue immediately.
	 *
	 * @param string $type    Notification type.
	 * @param array  $payload Notification payload.
	 * @return bool
	 */
	public function enqueue_digest_notification($type, array $payload = array()) {
		$queue = $this->get_digest_queue();
		$queue[] = array(
			'type'        => $type,
			'title'       => isset($payload['title']) ? $payload['title'] : '',
			'message'     => isset($payload['message']) ? $payload['message'] : '',
			'url'         => isset($payload['url']) ? $payload['url'] : '',
			'level'       => isset($payload['level']) ? $payload['level'] : 'info',
			'meta'        => isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : array(),
			'vars'        => isset($payload['vars']) && is_array($payload['vars']) ? $payload['vars'] : array(),
			'to_email'    => isset($payload['to_email']) ? $payload['to_email'] : '',
			'enqueued_at' => AIPS_DateTime::now()->timestamp(),
		);

		update_option('aips_notif_digest_queue', $queue, false);

		$config = AIPS_Config::get_instance();
		$digest_config = $config->get_notification_summarized_digest_config();

		if (count($queue) >= $digest_config['threshold']) {
			$this->flush_digest_queue();
		}

		return true;
	}

	/**
	 * Get the pending digest queue.
	 *
	 * @return array
	 */
	public function get_digest_queue() {
		$queue = get_option('aips_notif_digest_queue', array());
		return is_array($queue) ? $queue : array();
	}

	/**
	 * Get count of pending items in the digest queue.
	 *
	 * @return int
	 */
	public function get_digest_queue_count() {
		return count($this->get_digest_queue());
	}

	/**
	 * Flush the pending digest queue and send the summarized email.
	 *
	 * @return int Number of flushed items, or 0 if nothing was queued.
	 */
	public function flush_digest_queue() {
		$queue = $this->get_digest_queue();
		if (empty($queue)) {
			return 0;
		}

		// Clear queue before sending to prevent concurrent loops.
		update_option('aips_notif_digest_queue', array(), false);

		$count = count($queue);
		$this->senders->summarized_digest(array(
			'items' => $queue,
			'count' => $count,
		));

		return $count;
	}

	/**
	 * Check whether the oldest queued item exceeds the max delay window.
	 *
	 * @return bool True if flushed, false otherwise.
	 */
	public function check_digest_timeout() {
		$queue = $this->get_digest_queue();
		if (empty($queue)) {
			return false;
		}

		$config = AIPS_Config::get_instance();
		$digest_config = $config->get_notification_summarized_digest_config();
		$max_delay = $digest_config['max_delay'] > 0 ? $digest_config['max_delay'] : 3600;

		$oldest_timestamp = isset($queue[0]['enqueued_at']) ? absint($queue[0]['enqueued_at']) : 0;
		$now = AIPS_DateTime::now()->timestamp();

		if (($now - $oldest_timestamp) >= $max_delay) {
			$this->flush_digest_queue();
			return true;
		}

		return false;
	}

}
