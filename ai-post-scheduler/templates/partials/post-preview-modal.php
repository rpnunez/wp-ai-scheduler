<?php
/**
 * Post Preview Modal Partial Template
 *
 * Reusable modal for displaying a post preview via inline content or an iframe.
 * Include this template in any admin page that needs Post Preview functionality.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<!-- Post Preview Modal -->
<div id="aips-post-preview-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content" style="width: 90%; max-width: 1200px; height: 90vh;">
		<div class="aips-modal-header">
			<h2><?php esc_html_e('Post Preview', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="aips-modal-body" style="height: calc(100% - 60px); padding: 0;">
			<div id="aips-preview-content-container" style="padding: 30px; height: 100%; overflow-y: auto; box-sizing: border-box; display: none;"></div>
			<iframe id="aips-post-preview-iframe" src="" style="width: 100%; height: 100%; border: none; display: none;"></iframe>
		</div>
	</div>
</div>
