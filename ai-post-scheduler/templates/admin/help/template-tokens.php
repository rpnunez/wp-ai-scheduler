<?php
/**
 * Contextual Help: Template Dynamic Tokens
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-tab-content">
	<p><strong><?php esc_html_e('Dynamic Prompt Tokens', 'ai-post-scheduler'); ?></strong></p>
	<p><?php esc_html_e('You can insert dynamic merge tokens into your templates. During generation, AI Post Scheduler will automatically replace them with contextual data:', 'ai-post-scheduler'); ?></p>
	<ul>
		<li><code>{topic}</code> — <?php esc_html_e('The target topic or article subject chosen for this generation cycle.', 'ai-post-scheduler'); ?></li>
		<li><code>{author_name}</code> — <?php esc_html_e('The display name of the assigned AI Persona / Author.', 'ai-post-scheduler'); ?></li>
		<li><code>{author_bio}</code> — <?php esc_html_e('The professional biography and credentials of the assigned author.', 'ai-post-scheduler'); ?></li>
		<li><code>{author_tone}</code> — <?php esc_html_e('The selected voice tone, style guidelines, and perspective.', 'ai-post-scheduler'); ?></li>
		<li><code>{target_keyword}</code> — <?php esc_html_e('The primary target SEO keyword for semantic optimization.', 'ai-post-scheduler'); ?></li>
		<li><code>{secondary_keywords}</code> — <?php esc_html_e('A comma-separated list of supporting semantic keywords.', 'ai-post-scheduler'); ?></li>
		<li><code>{structure}</code> — <?php esc_html_e('The article outline or section hierarchy if a Structure blueprint is attached.', 'ai-post-scheduler'); ?></li>
		<li><code>{site_name}</code> — <?php esc_html_e('The current WordPress site title.', 'ai-post-scheduler'); ?></li>
		<li><code>{date}</code> — <?php esc_html_e('The current formatted publishing date.', 'ai-post-scheduler'); ?></li>
	</ul>
	<p><em><?php esc_html_e('Tip: System Prompts define persona rules and formatting constraints; User Prompts should focus on the specific topic and outline.', 'ai-post-scheduler'); ?></em></p>
</div>
