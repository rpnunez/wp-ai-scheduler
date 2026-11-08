<?php
/**
 * Contextual Help: Pending Review.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-tab-content">
	<p><strong><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></strong></p>
	<p><?php esc_html_e('Drafts waiting for approval appear as cards or a list. Selection is shared between views, including bulk publish.', 'ai-post-scheduler'); ?></p>
	<ul>
		<li><?php esc_html_e('Use Cards for scanning titles, images, and status; use List for denser bulk actions.', 'ai-post-scheduler'); ?></li>
		<li><?php esc_html_e('AI Edit on a card or list row regenerates a single component (title, slug, excerpt, content, featured image, or post images) without leaving this screen.', 'ai-post-scheduler'); ?></li>
		<li><?php esc_html_e('Large bulk publishes may run in the background. Watch progress under Diagnostics → Background Processes.', 'ai-post-scheduler'); ?></li>
	</ul>
</div>
