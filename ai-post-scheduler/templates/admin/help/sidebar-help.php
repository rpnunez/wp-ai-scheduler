<?php
/**
 * Contextual Help: Standard Sidebar Links
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-help-sidebar-content">
	<p><strong><?php esc_html_e('Need Assistance?', 'ai-post-scheduler'); ?></strong></p>
	<p><a href="<?php echo esc_url(admin_url('site-health.php')); ?>"><?php esc_html_e('Tools > Site Health', 'ai-post-scheduler'); ?></a></p>
	<p><a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings&tab=settings-ai')); ?>"><?php esc_html_e('AI Engine Settings', 'ai-post-scheduler'); ?></a></p>
	<p><a href="<?php echo esc_url(admin_url('admin.php?page=aips-system-status')); ?>"><?php esc_html_e('System Diagnostics', 'ai-post-scheduler'); ?></a></p>
	<hr />
	<p><?php printf(esc_html__('AI Post Scheduler v%s', 'ai-post-scheduler'), esc_html(defined('AIPS_VERSION') ? AIPS_VERSION : '3.7.20')); ?></p>
</div>
