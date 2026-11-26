<?php
/**
 * Standard Alert Email Body Template
 *
 * Used for high-priority error and alert notifications (e.g. generation failed,
 * quota alerts, system errors).
 *
 * Placeholders:
 *   {{notification_title}}   Title of the alert.
 *   {{notification_message}} Summary description of the issue.
 *   {{details_html}}         Optional extra details or metadata block.
 *   {{action_url}}           Target URL for the primary call-to-action button.
 *   {{action_label}}         Label text for the primary call-to-action button.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<p><?php echo esc_html__('A high-priority notification was triggered by AI Post Scheduler.', 'ai-post-scheduler'); ?></p>
<div class="alert-box">
	<strong><?php echo esc_html__('Alert:', 'ai-post-scheduler'); ?></strong> {{notification_title}}<br>
	<strong><?php echo esc_html__('Summary:', 'ai-post-scheduler'); ?></strong> {{notification_message}}
</div>
{{details_html}}
<p class="button-center">
	<a href="{{action_url}}" class="button">{{action_label}}</a>
</p>
