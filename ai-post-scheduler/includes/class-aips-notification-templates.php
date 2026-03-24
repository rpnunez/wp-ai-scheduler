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
 *   $tpl = $registry->get('partial_generation');
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
		$this->register($this->build_partial_generation_template());
		$this->register($this->build_posts_awaiting_review_template());
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
	}

	/**
	 * Build the "partial generation detected" email template.
	 *
	 * Tokens consumed by this template:
	 *   {{site_name}}             – WordPress site name
	 *   {{post_title}}            – Title of the generated post
	 *   {{source_label}}          – Human-readable source (e.g. "Template: Blog Post")
	 *   {{history_id_row}}        – Optional "<br><strong>Session ID:</strong> N" HTML, or empty
	 *   {{missing_components}}    – Pre-rendered `<ul>` HTML list of missing component labels
	 *   {{edit_url}}              – WordPress edit-post URL
	 *   {{partial_url}}           – Admin URL for the Partial Generations tab
	 *
	 * @return AIPS_Notification_Template
	 */
	private function build_partial_generation_template() {
		$subject = '[{{site_name}}] ' . __('Partial AI Post Generation Detected', 'ai-post-scheduler');

		$body_content =
			'<p>' . esc_html__('An AI-generated post was created, but one or more requested components failed to generate.', 'ai-post-scheduler') . '</p>'
			. '<div class="alert-box">'
			. '<strong>' . esc_html__('Post:', 'ai-post-scheduler') . '</strong> {{post_title}}<br>'
			. '<strong>' . esc_html__('Source:', 'ai-post-scheduler') . '</strong> {{source_label}}{{history_id_row}}'
			. '</div>'
			. '<p><strong>' . esc_html__('Missing Components:', 'ai-post-scheduler') . '</strong></p>'
			. '{{missing_components}}'
			. '<p>'
			. '<a href="{{edit_url}}" class="button">' . esc_html__('Edit Post', 'ai-post-scheduler') . '</a>'
			. '<a href="{{partial_url}}" class="button button-secondary">' . esc_html__('Open Partial Generations', 'ai-post-scheduler') . '</a>'
			. '</p>';

		$header_title = __('Partial Generation Detected', 'ai-post-scheduler');
		$header_color = '#b32d2e';
		$body         = $this->render_layout($header_title, $header_color, $body_content);

		return new AIPS_Notification_Template(
			'partial_generation',
			$subject,
			$body,
			$header_title,
			$header_color
		);
	}

	/**
	 * Build the "posts awaiting review" email template.
	 *
	 * Tokens consumed by this template:
	 *   {{site_name}}       – WordPress site name
	 *   {{stats_label}}     – Localised singular/plural count label
	 *   {{post_list}}       – Pre-rendered `<ul>` HTML list of posts (may be empty string)
	 *   {{more_posts}}      – Optional "…and N more posts" paragraph, or empty string
	 *   {{review_url}}      – Admin URL for the Pending Review tab
	 *
	 * @return AIPS_Notification_Template
	 */
	private function build_posts_awaiting_review_template() {
		$subject = '[{{site_name}}] {{stats_label}}';

		$body_content =
			'<p>' . esc_html__('Hello,', 'ai-post-scheduler') . '</p>'
			. '<p>' . esc_html__('You have AI-generated posts waiting for review before publication.', 'ai-post-scheduler') . '</p>'
			. '<div class="stats-box">{{stats_label}}</div>'
			. '{{post_list}}'
			. '{{more_posts}}'
			. '<p class="button-center">'
			. '<a href="{{review_url}}" class="button">' . esc_html__('Review Posts', 'ai-post-scheduler') . '</a>'
			. '</p>'
			. '<p>' . esc_html__('Click the button above to review and publish your posts.', 'ai-post-scheduler') . '</p>'
			. '<p>' . esc_html__('To disable these notifications, visit the plugin settings page.', 'ai-post-scheduler') . '</p>';

		$header_title = __('Posts Awaiting Review', 'ai-post-scheduler');
		$header_color = '#2271b1';
		$body         = $this->render_layout($header_title, $header_color, $body_content);

		return new AIPS_Notification_Template(
			'posts_awaiting_review',
			$subject,
			$body,
			$header_title,
			$header_color
		);
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
