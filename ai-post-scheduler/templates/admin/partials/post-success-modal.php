<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<!-- Post Success Modal -->
<div id="aips-post-success-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2><?php esc_html_e('Post Successfully Generated', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<div style="text-align: center; padding: 20px;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #46b450; width: 48px; height: 48px;"></span>
				<p style="font-size: 16px; margin-top: 20px;" id="aips-success-message"><?php esc_html_e('Your post has been successfully generated!', 'ai-post-scheduler'); ?></p>
				<div id="aips-post-link-container" style="margin-top: 20px;">
					<strong><?php esc_html_e('Link to Post:', 'ai-post-scheduler'); ?></strong><br>
					<a href="#" id="aips-post-link" target="_blank" class="button button-primary" style="margin-top: 10px;"><?php esc_html_e('View Post', 'ai-post-scheduler'); ?></a>
				</div>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
