<?php
/**
 * Contextual Help: Schedule Cadence & Optimization
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-tab-content">
	<p><strong><?php esc_html_e('Schedule Cadence & Concurrency', 'ai-post-scheduler'); ?></strong></p>
	<p><?php esc_html_e('Schedules run autonomously via WordPress Cron. Understanding cadence and jitter ensures reliable generation without API rate limiting:', 'ai-post-scheduler'); ?></p>
	<ul>
		<li><strong><?php esc_html_e('Cadence / Interval:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Controls how often this schedule triggers (e.g. hourly, daily, twice daily).', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('Execution Jitter:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Randomizes generation by a few minutes around the scheduled time to mimic organic human posting patterns and prevent burst limits on AI providers.', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('Batch Size:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('The number of topics processed per scheduled execution window.', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('Resilience & Retries:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('If an AI API request times out or is throttled, the exponential backoff policy retries the item before marking it for manual review.', 'ai-post-scheduler'); ?></li>
	</ul>
</div>
