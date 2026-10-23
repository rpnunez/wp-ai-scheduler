<?php
/**
 * Contextual Help: Settings & Engine Connection
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-tab-content">
	<p><strong><?php esc_html_e('AI Provider & Engine Configuration', 'ai-post-scheduler'); ?></strong></p>
	<p><?php esc_html_e('AI Post Scheduler connects to your language models through Meow Apps AI Engine or direct API integrations:', 'ai-post-scheduler'); ?></p>
	<ul>
		<li><strong><?php esc_html_e('AI Engine Integration:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Ensure Meow Apps AI Engine is active and an API key is connected under Meow Apps > AI Engine.', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('Model Selection:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Choose models suited for your content requirements. For long-form drafting, higher-capacity models are recommended; for quick topic expansion, fast/lightweight models offer lower latency.', 'ai-post-scheduler'); ?></li>
		<li><strong><?php esc_html_e('Token Budgets:', 'ai-post-scheduler'); ?></strong> <?php esc_html_e('Set daily and monthly token thresholds to protect against unexpected API spend.', 'ai-post-scheduler'); ?></li>
	</ul>
</div>
