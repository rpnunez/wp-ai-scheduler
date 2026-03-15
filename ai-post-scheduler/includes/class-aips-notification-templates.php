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
	 * Registered templates keyed by type slug.
	 *
	 * @var AIPS_Notification_Template[]
	 */
	private $templates = array();

	/**
	 * Constructor – registers built-in templates then fires the filter.
	 */
	public function __construct() {
		$this->register_defaults();

		/**
		 * Filter: aips_notification_templates
		 *
		 * Allows third-party code to add, replace, or remove templates.
		 * The filter receives the registry instance and must return it.
		 *
		 * @since 1.9.0
		 * @param AIPS_Notification_Templates $registry This registry instance.
		 */
		apply_filters('aips_notification_templates', $this);
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

		$body = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; margin: 0; padding: 0; }
		.email-container { max-width: 640px; margin: 20px auto; background: #ffffff; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
		.email-header { background: #b32d2e; color: #ffffff; padding: 20px; text-align: center; }
		.email-header h1 { margin: 0; font-size: 24px; }
		.email-body { padding: 30px; }
		.alert-box { background: #fcf0f1; border-left: 4px solid #b32d2e; padding: 15px; margin: 20px 0; }
		.component-list { margin: 16px 0 24px; padding-left: 18px; }
		.button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 3px; font-weight: bold; margin-right: 12px; }
		.button-secondary { background: #50575e; }
		.email-footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #646970; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>' . esc_html__('Partial Generation Detected', 'ai-post-scheduler') . '</h1>
		</div>
		<div class="email-body">
			<p>' . esc_html__('An AI-generated post was created, but one or more requested components failed to generate.', 'ai-post-scheduler') . '</p>
			<div class="alert-box">
				<strong>' . esc_html__('Post:', 'ai-post-scheduler') . '</strong> {{post_title}}<br>
				<strong>' . esc_html__('Source:', 'ai-post-scheduler') . '</strong> {{source_label}}{{history_id_row}}
			</div>
			<p><strong>' . esc_html__('Missing Components:', 'ai-post-scheduler') . '</strong></p>
			{{missing_components}}
			<p>
				<a href="{{edit_url}}" class="button">' . esc_html__('Edit Post', 'ai-post-scheduler') . '</a>
				<a href="{{partial_url}}" class="button button-secondary">' . esc_html__('Open Partial Generations', 'ai-post-scheduler') . '</a>
			</p>
		</div>
		<div class="email-footer">
			<p>' . esc_html__('This email was sent by AI Post Scheduler on', 'ai-post-scheduler') . ' {{site_name}}</p>
		</div>
	</div>
</body>
</html>';

		return new AIPS_Notification_Template(
			'partial_generation',
			$subject,
			$body,
			__('Partial Generation Detected', 'ai-post-scheduler'),
			'#b32d2e'
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

		$body = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; margin: 0; padding: 0; }
		.email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
		.email-header { background: #2271b1; color: #ffffff; padding: 20px; text-align: center; }
		.email-header h1 { margin: 0; font-size: 24px; }
		.email-body { padding: 30px; }
		.email-body p { margin: 0 0 15px; }
		.stats-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; font-size: 18px; font-weight: bold; color: #2271b1; }
		.post-list { list-style: none; padding: 0; margin: 20px 0; }
		.post-item { padding: 12px; margin-bottom: 10px; background: #f9f9f9; border-left: 3px solid #2271b1; border-radius: 3px; }
		.post-title { font-weight: bold; color: #1d2327; margin-bottom: 5px; }
		.post-meta { font-size: 13px; color: #646970; }
		.button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 3px; font-weight: bold; margin: 20px 0; }
		.email-footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #646970; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>' . esc_html__('Posts Awaiting Review', 'ai-post-scheduler') . '</h1>
		</div>
		<div class="email-body">
			<p>' . esc_html__('Hello,', 'ai-post-scheduler') . '</p>
			<p>' . esc_html__('You have AI-generated posts waiting for review before publication.', 'ai-post-scheduler') . '</p>
			<div class="stats-box">{{stats_label}}</div>
			{{post_list}}
			{{more_posts}}
			<p style="text-align: center;">
				<a href="{{review_url}}" class="button">' . esc_html__('Review Posts', 'ai-post-scheduler') . '</a>
			</p>
			<p>' . esc_html__('Click the button above to review and publish your posts.', 'ai-post-scheduler') . '</p>
		</div>
		<div class="email-footer">
			<p>' . esc_html__('This email was sent by AI Post Scheduler on', 'ai-post-scheduler') . ' {{site_name}}</p>
			<p>' . esc_html__('To disable these notifications, visit the plugin settings page.', 'ai-post-scheduler') . '</p>
		</div>
	</div>
</body>
</html>';

		return new AIPS_Notification_Template(
			'posts_awaiting_review',
			$subject,
			$body,
			__('Posts Awaiting Review', 'ai-post-scheduler'),
			'#2271b1'
		);
	}
}
