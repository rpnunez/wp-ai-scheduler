<?php
/**
 * Content page tab navigation.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$active_content_tab = isset($active_content_tab) ? sanitize_key($active_content_tab) : 'generated_posts';
$generated_posts_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
$history_url = AIPS_Admin_Menu_Helper::get_page_url('history');
$failed_runs_url = AIPS_Admin_Menu_Helper::get_page_url('history', array(
	'status' => 'failed',
	'content_tab' => 'failed_runs',
));
$uses_local_content_tabs = in_array($active_content_tab, array('generated_posts', 'review_queue', 'partial_generations'), true);
?>
<div class="aips-tab-nav">
	<?php if ($uses_local_content_tabs): ?>
		<a href="#aips-generated-posts" class="aips-tab-link <?php echo $active_content_tab === 'generated_posts' ? 'active' : ''; ?>" data-tab="aips-generated-posts"><?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?></a>
		<a href="#aips-pending-review" class="aips-tab-link <?php echo $active_content_tab === 'review_queue' ? 'active' : ''; ?>" data-tab="aips-pending-review"><?php esc_html_e('Drafts / Review Queue', 'ai-post-scheduler'); ?></a>
		<a href="#aips-partial-generations" class="aips-tab-link <?php echo $active_content_tab === 'partial_generations' ? 'active' : ''; ?>" data-tab="aips-partial-generations"><?php esc_html_e('Partial Generations', 'ai-post-scheduler'); ?></a>
	<?php else: ?>
		<a href="<?php echo esc_url($generated_posts_url); ?>#aips-generated-posts" class="aips-tab-link"><?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?></a>
		<a href="<?php echo esc_url($generated_posts_url); ?>#aips-pending-review" class="aips-tab-link"><?php esc_html_e('Drafts / Review Queue', 'ai-post-scheduler'); ?></a>
		<a href="<?php echo esc_url($generated_posts_url); ?>#aips-partial-generations" class="aips-tab-link"><?php esc_html_e('Partial Generations', 'ai-post-scheduler'); ?></a>
	<?php endif; ?>
	<a href="<?php echo esc_url($history_url); ?>" class="aips-tab-link <?php echo $active_content_tab === 'history' ? 'active' : ''; ?>"><?php esc_html_e('History', 'ai-post-scheduler'); ?></a>
	<a href="<?php echo esc_url($failed_runs_url); ?>" class="aips-tab-link <?php echo $active_content_tab === 'failed_runs' ? 'active' : ''; ?>"><?php esc_html_e('Failed Runs', 'ai-post-scheduler'); ?></a>
</div>
