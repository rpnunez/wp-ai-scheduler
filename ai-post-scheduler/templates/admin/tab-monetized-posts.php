<?php
/**
 * Monetized Posts Tab Template
 *
 * Displays posts containing affiliate marketing links with filter by offer.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once AIPS_PLUGIN_DIR . 'includes/tables/class-aips-monetized-posts-list-table.php';

$monetized_table = new AIPS_Monetized_Posts_List_Table();
$monetized_table->prepare_items();
?>

<div class="wrap aips-content-panel">
	<div class="aips-panel-header" style="padding:16px 20px;border-bottom:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center;">
		<div>
			<h2 class="aips-heading-md" style="margin:0 0 4px 0;"><?php esc_html_e('Monetized Posts', 'ai-post-scheduler'); ?></h2>
			<p class="aips-text-muted" style="margin:0;font-size:13px;"><?php esc_html_e('Filter and inspect posts containing active affiliate marketing links.', 'ai-post-scheduler'); ?></p>
		</div>
		<div>
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-automations&tab=monetization')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
				<span class="dashicons dashicons-money-alt" style="margin-right:4px;"></span>
				<?php esc_html_e('Manage Affiliate Offers', 'ai-post-scheduler'); ?>
			</a>
		</div>
	</div>

	<form method="get" action="">
		<input type="hidden" name="page" value="aips-generated-posts" />
		<input type="hidden" name="tab" value="aips-monetized-posts" />

		<?php
		$monetized_table->search_box(__('Search Monetized Posts', 'ai-post-scheduler'), 'monetized-posts-search');
		$monetized_table->display();
		?>
	</form>
</div>
