<?php
/**
 * Notification Templates Registry
 *
 * Holds all registered AIPS_Notification_Template instances.  Built-in plugin
 * templates are added by register_defaults(); third-party code can add or
 * replace templates via the `aips_notification_templates` filter.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notification_Templates
 *
 * Registry of every notification template known to the plugin.  Usage:
 *
 *   $registry = new AIPS_Notification_Templates();
 *
 *   // Retrieve a template:
 *   $tpl = $registry->get('generation_failed');
 *
 *   // Register a custom template at run-time:
 *   $registry->register( new AIPS_Notification_Template( 'my_event', ... ) );
 *
 * Developers may also hook into `aips_notification_templates` (filter) to
 * add or override templates before the registry is used for the first time.
 */
class AIPS_Notification_Templates {

	/**
	 * Path to the shared email layout template, relative to the plugin root.
	 *
	 * @var string
	 */
	const EMAIL_LAYOUT_PATH = 'templates/email/email_layout.php';

	/**
	 * Registered templates keyed by type slug.
	 *
	 * @var AIPS_Notification_Template[]
	 */
	private $templates = array();

	/**
	 * Constructor – registers built-in templates then fires the action hook.
	 */
	public function __construct() {
		$this->register_defaults();

		/**
		 * Action: aips_notification_templates
		 *
		 * Fires after built-in templates are registered, allowing third-party
		 * code to add, replace, or remove templates via register().
		 *
		 * @since 1.9.0
		 * @param AIPS_Notification_Templates $registry This registry instance.
		 */
		do_action('aips_notification_templates', $this);
	}

	/**
	 * Register a template.  If a template with the same type already exists it
	 * will be replaced.
	 *
	 * @param AIPS_Notification_Template $template Template to register.
	 * @return void
	 */
	public function register(AIPS_Notification_Template $template) {
		$this->templates[$template->get_type()] = $template;
	}

	/**
	 * Retrieve a template by type slug.
	 *
	 * @param string $type Template type slug.
	 * @return AIPS_Notification_Template|null Null when the type is not registered.
	 */
	public function get($type) {
		return isset($this->templates[$type]) ? $this->templates[$type] : null;
	}

	/**
	 * Return all registered templates.
	 *
	 * @return AIPS_Notification_Template[]
	 */
	public function all() {
		return $this->templates;
	}

	// -----------------------------------------------------------------------
	// Built-in templates
	// -----------------------------------------------------------------------

	/**
	 * Register the plugin's built-in email templates.
	 *
	 * @return void
	 */
	private function register_defaults() {
		$this->register($this->build_standard_alert_template('generation_failed', __('Generation Failed', 'ai-post-scheduler'), '#b32d2e'));
		$this->register($this->build_standard_alert_template('quota_alert', __('Quota Alert', 'ai-post-scheduler'), '#b32d2e'));
		$this->register($this->build_standard_alert_template('integration_error', __('Integration Error', 'ai-post-scheduler'), '#b32d2e'));
		$this->register($this->build_standard_alert_template('scheduler_error', __('Scheduler Error', 'ai-post-scheduler'), '#b32d2e'));
		$this->register($this->build_standard_alert_template('system_error', __('System Error', 'ai-post-scheduler'), '#b32d2e'));
		$this->register($this->build_standard_event_template('template_generated', __('Template Generation Completed', 'ai-post-scheduler'), '#2271b1'));
		$this->register($this->build_standard_event_template('manual_generation_completed', __('Manual Generation Completed', 'ai-post-scheduler'), '#2271b1'));
		$this->register($this->build_standard_event_template('post_ready_for_review', __('Post Ready For Review', 'ai-post-scheduler'), '#2271b1'));
		$this->register($this->build_standard_event_template('post_rejected', __('Post Rejected', 'ai-post-scheduler'), '#dba617'));
		$this->register($this->build_standard_event_template('partial_generation_completed', __('Partial Generation Completed', 'ai-post-scheduler'), '#dba617'));
		$this->register($this->build_post_generated_template());
		$this->register($this->build_standard_event_template('daily_digest', __('Daily Digest', 'ai-post-scheduler'), '#2271b1'));
		$this->register($this->build_standard_event_template('weekly_summary', __('Weekly Summary', 'ai-post-scheduler'), '#2271b1'));
		$this->register($this->build_standard_event_template('monthly_report', __('Monthly Report', 'ai-post-scheduler'), '#2271b1'));
	}

	/**
	 * Build a standard alert-style email template.
	 *
	 * @param string $type         Notification type.
	 * @param string $header_title Email header title.
	 * @param string $header_color Email header color.
	 * @return AIPS_Notification_Template
	 */
	private function build_standard_alert_template($type, $header_title, $header_color) {
		$subject = '[{{site_name}}] {{notification_title}}';

		$body_content =
			'<p>' . esc_html__('A high-priority notification was triggered by AI Post Scheduler.', 'ai-post-scheduler') . '</p>'
			. '<div class="alert-box">'
			. '<strong>' . esc_html__('Alert:', 'ai-post-scheduler') . '</strong> {{notification_title}}<br>'
			. '<strong>' . esc_html__('Summary:', 'ai-post-scheduler') . '</strong> {{notification_message}}'
			. '</div>'
			. '{{details_html}}'
			. '<p class="button-center">'
			. '<a href="{{action_url}}" class="button">{{action_label}}</a>'
			. '</p>';

		$body = $this->render_layout($header_title, $header_color, $body_content);

		return new AIPS_Notification_Template(
			$type,
			$subject,
			$body,
			$header_title,
			$header_color
		);
	}

	/**
	 * Build a standard non-error event email template.
	 *
	 * @param string $type         Notification type.
	 * @param string $header_title Email header title.
	 * @param string $header_color Email header color.
	 * @return AIPS_Notification_Template
	 */
	private function build_standard_event_template($type, $header_title, $header_color) {
		$subject = '[{{site_name}}] {{notification_title}}';

		$body_content =
			'<p>' . esc_html__('AI Post Scheduler has a new notification for your review.', 'ai-post-scheduler') . '</p>'
			. '<div class="alert-box">'
			. '<strong>' . esc_html__('Update:', 'ai-post-scheduler') . '</strong> {{notification_title}}<br>'
			. '<strong>' . esc_html__('Summary:', 'ai-post-scheduler') . '</strong> {{notification_message}}'
			. '</div>'
			. '{{details_html}}'
			. '<p class="button-center">'
			. '<a href="{{action_url}}" class="button">{{action_label}}</a>'
			. '</p>';

		$body = $this->render_layout($header_title, $header_color, $body_content);

		return new AIPS_Notification_Template(
			$type,
			$subject,
			$body,
			$header_title,
			$header_color
		);
	}

	/**
	 * Build the "Post Generated" email template.
	 *
	 * Uses a bespoke body (not build_standard_event_template()'s generic alert
	 * box) so it can present the full generated post as an email-safe HTML
	 * table: title, featured image (if any), excerpt, content, source/status,
	 * and edit/view links.
	 *
	 * @return AIPS_Notification_Template
	 */
	private function build_post_generated_template() {
		$header_title = __('Post Generated', 'ai-post-scheduler');
		$header_color = '#2271b1';
		$subject      = '[{{site_name}}] ' . __('Post Generated', 'ai-post-scheduler') . ' — {{source_label}} — {{post_status_label}}';

		$body_content =
			'<p>' . esc_html__('AI Post Scheduler generated a new post:', 'ai-post-scheduler') . '</p>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ddd;border-radius:4px;overflow:hidden;margin:20px 0;border-collapse:collapse;">'
			. '<tr><td style="background:#2271b1;color:#ffffff;padding:14px 18px;font-size:18px;font-weight:bold;">{{post_title}}</td></tr>'
			. '{{featured_image_row}}'
			. '<tr><td style="padding:14px 18px;border-top:1px solid #eee;">'
			. '<strong style="display:block;margin-bottom:6px;color:#1d2327;">' . esc_html__('Excerpt', 'ai-post-scheduler') . '</strong>'
			. '<div style="color:#3c434a;">{{post_excerpt}}</div></td></tr>'
			. '<tr><td style="padding:14px 18px;border-top:1px solid #eee;">'
			. '<strong style="display:block;margin-bottom:6px;color:#1d2327;">' . esc_html__('Content', 'ai-post-scheduler') . '</strong>'
			. '<div style="color:#3c434a;line-height:1.6;">{{post_content}}</div></td></tr>'
			. '<tr><td style="padding:12px 18px;border-top:1px solid #eee;background:#f9f9f9;font-size:13px;color:#646970;">'
			. '<strong>{{source_label}}</strong> &middot; <strong>{{post_status_label}}</strong></td></tr>'
			. '</table>'
			. '<p class="button-center">'
			. '<a href="{{edit_url}}" class="button">' . esc_html__('Edit Post', 'ai-post-scheduler') . '</a>'
			. '<a href="{{view_url}}" class="button button-secondary">' . esc_html__('View Post', 'ai-post-scheduler') . '</a>'
			. '</p>';

		$body = $this->render_layout($header_title, $header_color, $body_content);

		return new AIPS_Notification_Template(
			'post_generated',
			$subject,
			$body,
			$header_title,
			$header_color
		);
	}

	// -----------------------------------------------------------------------
	// Shared layout helper
	// -----------------------------------------------------------------------

	/**
	 * Render the shared email layout template with the given content.
	 *
	 * Loads `templates/email/email_layout.php`, injects the required variables,
	 * and returns the full HTML string.  All shared chrome (DOCTYPE, CSS, header
	 * banner, footer) lives in the layout template; only the body content fragment
	 * is passed in here.
	 *
	 * @param string $header_title  Text for the coloured header banner.
	 * @param string $header_color  CSS colour for the header banner.
	 * @param string $body_content  HTML fragment for the email body section.  May contain `{{token}}` placeholders.
	 * @return string Full HTML email document (with any remaining `{{token}}` placeholders intact).
	 */
	private function render_layout($header_title, $header_color, $body_content) {
		$layout_path = AIPS_PLUGIN_DIR . self::EMAIL_LAYOUT_PATH;

		if (!file_exists($layout_path)) {
			// Graceful fallback: wrap the body content in a minimal shell.
			return '<!DOCTYPE html><html><body>' . $body_content . '</body></html>';
		}

		// Expose local variables to the template scope.
		$site_name = get_bloginfo('name');

		ob_start();
		include $layout_path;
		return ob_get_clean();
	}
}
