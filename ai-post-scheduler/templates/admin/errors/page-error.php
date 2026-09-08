<?php
/**
 * Error Template: Top-Level Admin Page Fallback
 *
 * Rendered when an uncaught exception or error occurs while loading a top-level admin page.
 *
 * @package AI_Post_Scheduler
 * @var string $title User-facing module/page title.
 */

if (!defined('ABSPATH')) {
	exit;
}

$title = !empty($title) ? $title : __('Page', 'ai-post-scheduler');
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-content-panel">
			<div class="aips-panel-body">
				<div class="aips-empty-state">
					<span class="dashicons dashicons-warning aips-page-error-icon"></span>
					<h3><?php echo esc_html(sprintf(__('The %s page is currently unavailable', 'ai-post-scheduler'), $title)); ?></h3>
					<p class="aips-muted"><?php esc_html_e('An unexpected error occurred while loading this page. Details have been logged for diagnostics.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>
