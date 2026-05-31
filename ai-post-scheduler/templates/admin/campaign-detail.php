<?php
/**
 * Campaign Detail Admin Template
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$campaigns_url = admin_url('admin.php?page=aips-campaigns');
$generated_posts_url = add_query_arg(array('page' => 'aips-generated-posts', 'campaign_id' => absint($campaign->id)), admin_url('admin.php'));
?>
<div class="wrap aips-wrap aips-admin-page">
	<div class="aips-page-container aips-campaigns-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php echo esc_html($campaign->name); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Campaign overview and settings.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<a href="<?php echo esc_url($campaigns_url); ?>" class="aips-btn aips-btn-secondary"><?php esc_html_e('Back to Campaigns', 'ai-post-scheduler'); ?></a>
				</div>
			</div>
		</div>

		<?php if (isset($_GET['updated'])) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Campaign updated.', 'ai-post-scheduler'); ?></p></div>
		<?php endif; ?>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Overview', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<p><strong><?php esc_html_e('Mode:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($campaign->campaign_mode); ?></p>
				<p><strong><?php esc_html_e('Last Run:', 'ai-post-scheduler'); ?></strong> <?php echo !empty($campaign->last_run) ? esc_html(AIPS_DateTime::formatRelativeOrAbsolute($campaign->last_run, get_option('date_format') . ' ' . get_option('time_format'))) : esc_html__('Never', 'ai-post-scheduler'); ?></p>
				<p><strong><?php esc_html_e('Next Run:', 'ai-post-scheduler'); ?></strong> <?php echo !empty($campaign->next_run) ? esc_html(AIPS_DateTime::formatRelativeOrAbsolute($campaign->next_run, get_option('date_format') . ' ' . get_option('time_format'))) : esc_html__('Not scheduled', 'ai-post-scheduler'); ?></p>
				<p><strong><?php esc_html_e('Generated Posts:', 'ai-post-scheduler'); ?></strong> <a href="<?php echo esc_url($generated_posts_url); ?>"><?php echo esc_html((int) $campaign->generated_posts_count); ?></a></p>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Settings', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<form method="post">
					<?php wp_nonce_field('aips_campaign_detail_save_' . absint($campaign->id), 'aips_campaign_detail_nonce'); ?>
					<p>
						<label for="campaign_name"><strong><?php esc_html_e('Campaign Name', 'ai-post-scheduler'); ?></strong></label><br />
						<input type="text" id="campaign_name" name="campaign_name" class="regular-text" value="<?php echo esc_attr($campaign->name); ?>" required />
					</p>
					<p>
						<label for="content_goal"><strong><?php esc_html_e('Content Goal', 'ai-post-scheduler'); ?></strong></label><br />
						<textarea id="content_goal" name="content_goal" rows="3" class="large-text"><?php echo esc_textarea($campaign->content_goal); ?></textarea>
					</p>
					<p><button type="submit" class="aips-btn aips-btn-primary"><?php esc_html_e('Save Campaign', 'ai-post-scheduler'); ?></button></p>
				</form>
			</div>
		</div>


		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Lifecycle', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<form method="post" class="aips-campaign-detail-lifecycle-actions">
					<?php wp_nonce_field('aips_campaign_detail_save_' . absint($campaign->id), 'aips_campaign_detail_nonce'); ?>
					<?php if ((int) $campaign->is_archived === 1) : ?>
						<button type="submit" class="aips-btn aips-btn-secondary" name="detail_action" value="restore"><?php esc_html_e('Restore Campaign', 'ai-post-scheduler'); ?></button>
				<?php elseif ((int) $campaign->is_active === 1) : ?>
						<button type="submit" class="aips-btn aips-btn-secondary" name="detail_action" value="pause"><?php esc_html_e('Pause Campaign', 'ai-post-scheduler'); ?></button>
						<button type="submit" class="aips-btn aips-btn-danger" name="detail_action" value="archive"><?php esc_html_e('Archive Campaign', 'ai-post-scheduler'); ?></button>
					<?php else : ?>
						<button type="submit" class="aips-btn aips-btn-secondary" name="detail_action" value="resume"><?php esc_html_e('Resume Campaign', 'ai-post-scheduler'); ?></button>
						<button type="submit" class="aips-btn aips-btn-danger" name="detail_action" value="archive"><?php esc_html_e('Archive Campaign', 'ai-post-scheduler'); ?></button>
					<?php endif; ?>
				</form>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Owned Resources', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<p><strong><?php esc_html_e('Templates:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html((int) count($templates)); ?></p>
				<p><strong><?php esc_html_e('Schedules:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html((int) count($schedules)); ?></p>
				<?php if (!empty($templates)) : ?>
					<ul>
						<?php foreach ($templates as $template) : ?>
							<li><a href="<?php echo esc_url(add_query_arg(array('page' => 'aips-templates', 'edit' => absint($template->id)), admin_url('admin.php'))); ?>"><?php echo esc_html($template->name); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
