<?php
/**
 * Standard Event Email Body Template
 *
 * Used for standard event notifications (e.g. template generated, manual generation
 * completed, post ready for review, periodic digests/reports).
 *
 * Placeholders:
 *   {{notification_title}}   Title of the event notification.
 *   {{notification_message}} Summary description of the event.
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
<p><?php echo esc_html__('AI Post Scheduler has a new notification for your review.', 'ai-post-scheduler'); ?></p>
<div class="alert-box">
	<strong><?php echo esc_html__('Update:', 'ai-post-scheduler'); ?></strong> {{notification_title}}<br>
	<strong><?php echo esc_html__('Summary:', 'ai-post-scheduler'); ?></strong> {{notification_message}}
</div>
{{details_html}}
<p class="button-center">
	<a href="{{action_url}}" class="button">{{action_label}}</a>
</p>
