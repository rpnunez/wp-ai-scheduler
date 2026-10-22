<?php
/**
 * Error Template: Embedded Tab / Section Fallback
 *
 * Rendered when an uncaught exception or error occurs while rendering a subtab or rail canvas.
 *
 * @package AI_Post_Scheduler
 * @var string $title User-facing module/tab title.
 */

if (!defined('ABSPATH')) {
	exit;
}

$title = !empty($title) ? $title : __('Module', 'ai-post-scheduler');
?>
<div class="aips-content-panel">
	<div class="aips-panel-body">
		<div class="notice notice-error inline">
			<p><?php echo esc_html(sprintf(__('The %s module is currently unavailable. Please check the system log or try reloading the page.', 'ai-post-scheduler'), $title)); ?></p>
		</div>
	</div>
</div>
