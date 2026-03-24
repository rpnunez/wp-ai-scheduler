<?php
/**
 * Shared Email Layout Template
 *
 * Renders a full HTML email document with a consistent header, body, and footer
 * layout.  All notification-specific content is injected via variables.
 *
 * Expected variables (set by the caller before including this template):
 *
 *   @var string $header_title  Text shown inside the email banner.
 *   @var string $header_color  CSS background colour for the banner (e.g. '#2271b1').
 *   @var string $body_content  HTML fragment rendered inside the email body section.
 *   @var string $site_name     WordPress site name, used in the footer.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?><!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; margin: 0; padding: 0; }
		.email-container { max-width: 640px; margin: 20px auto; background: #ffffff; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
		.email-header { background: <?php echo esc_attr($header_color); ?>; color: #ffffff; padding: 20px; text-align: center; }
		.email-header h1 { margin: 0; font-size: 24px; }
		.email-body { padding: 30px; }
		.email-body p { margin: 0 0 15px; }
		.email-footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #646970; }
		/* Component-specific styles */
		.alert-box { background: #fcf0f1; border-left: 4px solid #b32d2e; padding: 15px; margin: 20px 0; }
		.component-list { margin: 16px 0 24px; padding-left: 18px; }
		.stats-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; font-size: 18px; font-weight: bold; color: #2271b1; }
		.post-list { list-style: none; padding: 0; margin: 20px 0; }
		.post-item { padding: 12px; margin-bottom: 10px; background: #f9f9f9; border-left: 3px solid #2271b1; border-radius: 3px; }
		.post-title { font-weight: bold; color: #1d2327; margin-bottom: 5px; }
		.post-meta { font-size: 13px; color: #646970; }
		.button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 3px; font-weight: bold; margin-right: 12px; }
		.button-secondary { background: #50575e; }
		.button-center { text-align: center; margin: 20px 0; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php echo esc_html($header_title); ?></h1>
		</div>
		<div class="email-body">
			<?php echo $body_content; // phpcs:ignore WordPress.Security.EscapeOutput -- caller is responsible for escaping ?>
		</div>
		<div class="email-footer">
			<p><?php echo esc_html(sprintf(
				/* translators: %s: site name */
				__('This email was sent by AI Post Scheduler on %s', 'ai-post-scheduler'),
				$site_name
			)); ?></p>
		</div>
	</div>
</body>
</html>
