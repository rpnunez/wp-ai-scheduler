<?php
/**
 * Contextual Help: Campaigns.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-tab-content">
	<p><strong><?php esc_html_e('Campaigns', 'ai-post-scheduler'); ?></strong></p>
	<p><?php esc_html_e('A campaign groups templates, schedules, and generated posts under one parent record.', 'ai-post-scheduler'); ?></p>
	<ul>
		<li><?php esc_html_e('Create a campaign with the wizard, then attach templates and schedules from the campaign detail screen.', 'ai-post-scheduler'); ?></li>
		<li><?php esc_html_e('Archive pauses listing without deleting history. Restore returns the campaign to the active table.', 'ai-post-scheduler'); ?></li>
		<li><?php esc_html_e('Delete is only available when the campaign has no remaining generated posts or active schedules.', 'ai-post-scheduler'); ?></li>
	</ul>
</div>
