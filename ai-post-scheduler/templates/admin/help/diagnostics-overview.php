<?php
/**
 * Contextual Help: Diagnostics hub.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-tab-content">
	<p><strong><?php esc_html_e('Diagnostics', 'ai-post-scheduler'); ?></strong></p>
	<p><?php esc_html_e('Use this hub to inspect plugin health without changing generation settings.', 'ai-post-scheduler'); ?></p>
	<ul>
		<li><strong><?php esc_html_e('System Info:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Environment, plugin version, table counts, and AI provider details. Copy the markdown report when filing support issues.', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('AI Errors:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Classified generation failures with export and pruning. Secrets are redacted in exports.', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('Background Processes:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Live Heartbeat status for indexing, embeddings, and bulk generation jobs.', 'ai-post-scheduler'); ?></li>
	</ul>
</div>
