<?php
/**
 * Post Improvements suggestion tab.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$post_improvements_data = isset($post_improvements_data) && is_array($post_improvements_data) ? $post_improvements_data : array('items' => array(), 'total' => 0, 'pages' => 1, 'current_page' => 1);
$items = isset($post_improvements_data['items']) ? $post_improvements_data['items'] : array();
?>

<div class="aips-post-improvements-toolbar">
	<div class="aips-post-improvements-toolbar-left">
		<input type="search" id="aips-post-improvements-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search suggestions…', 'ai-post-scheduler'); ?>" value="<?php echo isset($post_improvement_search_query) ? esc_attr($post_improvement_search_query) : ''; ?>" />
		<button type="button" id="aips-post-improvements-search-btn" class="aips-btn aips-btn-secondary aips-btn-sm"><?php esc_html_e('Search', 'ai-post-scheduler'); ?></button>
	</div>
	<div class="aips-post-improvements-toolbar-right">
		<span class="aips-badge aips-badge-neutral"><?php echo esc_html(sprintf(_n('%d pending suggestion', '%d pending suggestions', (int) $post_improvements_data['total'], 'ai-post-scheduler'), (int) $post_improvements_data['total'])); ?></span>
	</div>
</div>

<?php if (empty($items)) : ?>
	<div class="aips-empty-state" id="aips-post-improvements-empty-state">
		<h3><?php esc_html_e('No pending post-improvement suggestions', 'ai-post-scheduler'); ?></h3>
		<p><?php esc_html_e('Run a scan schedule to generate suggestions for post improvements.', 'ai-post-scheduler'); ?></p>
	</div>
<?php else : ?>
	<table class="aips-table aips-post-improvements-table" id="aips-post-improvements-table">
		<thead>
			<tr>
				<th><?php esc_html_e('Post', 'ai-post-scheduler'); ?></th>
				<th><?php esc_html_e('Priority', 'ai-post-scheduler'); ?></th>
				<th><?php esc_html_e('Severity', 'ai-post-scheduler'); ?></th>
				<th><?php esc_html_e('Pending Items', 'ai-post-scheduler'); ?></th>
				<th><?php esc_html_e('Last Scanned', 'ai-post-scheduler'); ?></th>
				<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
				<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($items as $item) : ?>
				<tr data-suggestion-id="<?php echo esc_attr((int) $item->id); ?>" data-post-id="<?php echo esc_attr((int) $item->post_id); ?>">
					<td>
						<strong><?php echo esc_html($item->post_title); ?></strong>
						<div class="aips-meta-subtle">#<?php echo esc_html((int) $item->post_id); ?></div>
					</td>
					<td><span class="aips-badge aips-badge-neutral"><?php echo esc_html(ucfirst((string) $item->priority)); ?></span></td>
					<td><span class="aips-badge aips-badge-neutral"><?php echo esc_html(ucfirst((string) $item->severity)); ?></span></td>
					<td><?php echo esc_html((int) $item->pending_items); ?></td>
					<td><?php echo esc_html(AIPS_DateTime::formatRelativeOrAbsolute((int) $item->last_scanned_at, get_option('date_format') . ' ' . get_option('time_format'))); ?></td>
					<td>
						<span class="aips-badge <?php echo 'pending' === $item->status ? 'aips-badge-warning' : 'aips-badge-neutral'; ?>">
							<?php echo esc_html(ucfirst((string) $item->status)); ?>
						</span>
					</td>
					<td>
						<div class="aips-btn-group aips-btn-group-inline">
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-post-improvement-review"><?php esc_html_e('Review', 'ai-post-scheduler'); ?></button>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-post-improvement-apply-all"><?php esc_html_e('Apply all', 'ai-post-scheduler'); ?></button>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-post-improvement-dismiss-all"><?php esc_html_e('Dismiss all', 'ai-post-scheduler'); ?></button>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
