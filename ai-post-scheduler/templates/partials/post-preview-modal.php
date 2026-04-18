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
<div id="aips-post-preview-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-post-preview-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-post-preview-modal-title"><?php esc_html_e('Post Preview', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="aips-modal-body">
			<div id="aips-preview-content-container" style="display: none;"></div>
			<iframe id="aips-post-preview-iframe" src="" title="<?php echo esc_attr__('Post preview content', 'ai-post-scheduler'); ?>" style="display: none;"></iframe>
		</div>
	</div>
</div>

<!-- Post Preview: loading state template -->
<script type="text/html" id="aips-tmpl-preview-loading">
	<div class="aips-preview-loading">
		<span class="spinner is-active aips-preview-spinner"></span>
		<p class="aips-preview-loading-text">{{message}}</p>
	</div>
</script>

<!-- Post Preview: post content template -->
<script type="text/html" id="aips-tmpl-preview-content">
	<h1 class="aips-preview-title">{{title}}</h1>
	{{featured_image}}
	{{excerpt}}
	<div class="aips-preview-body">{{content}}</div>
	{{edit_footer}}
</script>

<!-- Post Preview: featured image block (only rendered when an image exists) -->
<script type="text/html" id="aips-tmpl-preview-image">
	<div class="aips-preview-image">
		<img src="{{src}}" alt="">
	</div>
</script>

<!-- Post Preview: excerpt block (only rendered when an excerpt exists) -->
<script type="text/html" id="aips-tmpl-preview-excerpt">
	<div class="aips-preview-excerpt">
		<strong><?php esc_html_e('Excerpt:', 'ai-post-scheduler'); ?></strong> {{excerpt}}
	</div>
</script>

<!-- Post Preview: edit footer link (only rendered when an edit URL exists) -->
<script type="text/html" id="aips-tmpl-preview-edit-footer">
	<div class="aips-preview-edit-footer">
		<a href="{{edit_url}}" target="_blank" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?></a>
	</div>
</script>
