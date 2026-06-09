<?php
/**
 * Source Data admin page template.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.4
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($source_data) || !is_array($source_data)) {
	$source_data = array(
		'items'        => array(),
		'total'        => 0,
		'pages'        => 0,
		'current_page' => 1,
		'per_page'     => 20,
	);
}

$source       = isset($source) ? $source : null;
$source_id    = isset($source_id) ? absint($source_id) : 0;
$search       = isset($search) ? (string) $search : '';
$source_label = ($source && !empty($source->label)) ? $source->label : (($source && !empty($source->url)) ? $source->url : __('Unknown Source', 'ai-post-scheduler'));
$items        = isset($source_data['items']) && is_array($source_data['items']) ? $source_data['items'] : array();
$total        = isset($source_data['total']) ? (int) $source_data['total'] : 0;
$pages        = isset($source_data['pages']) ? (int) $source_data['pages'] : 0;
$current      = isset($source_data['current_page']) ? max(1, (int) $source_data['current_page']) : 1;
$base_url     = AIPS_Admin_Menu_Helper::get_page_url('aips-source-data', array('source_id' => $source_id));
$back_url     = AIPS_Admin_Menu_Helper::get_page_url('sources');

$build_page_url = static function($page_number) use ($base_url, $search) {
	$args = array(
		'source_data_paged' => absint($page_number),
	);
	if ('' !== $search) {
		$args['s'] = $search;
	}
	return add_query_arg($args, $base_url);
};
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container aips-source-data-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('View Source Data', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php
						printf(
							/* translators: %s = source label or URL */
							esc_html__('Manage fetched content snapshots saved for %s.', 'ai-post-scheduler'),
							esc_html($source_label)
						);
						?>
					</p>
				</div>
				<div class="aips-page-actions">
					<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url($back_url); ?>">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e('Back to Sources', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<?php if (!$source): ?>
				<div class="aips-empty-state">
					<div class="dashicons dashicons-warning aips-empty-state-icon" aria-hidden="true"></div>
					<h3 class="aips-empty-state-title"><?php esc_html_e('Source Not Found', 'ai-post-scheduler'); ?></h3>
					<p class="aips-empty-state-description"><?php esc_html_e('Choose a source from the Sources page to manage its fetched data.', 'ai-post-scheduler'); ?></p>
				</div>
			<?php else: ?>
				<form class="aips-filter-bar" method="get">
					<input type="hidden" name="page" value="aips-source-data">
					<input type="hidden" name="source_id" value="<?php echo esc_attr($source_id); ?>">
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-source-data-search"><?php esc_html_e('Search Source Data:', 'ai-post-scheduler'); ?></label>
						<input type="search" id="aips-source-data-search" name="s" value="<?php echo esc_attr($search); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search source data…', 'ai-post-scheduler'); ?>">
						<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Search', 'ai-post-scheduler'); ?></button>
						<?php if ('' !== $search): ?>
							<a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
						<?php endif; ?>
					</div>
				</form>

				<div class="aips-panel-body no-padding">
					<?php if (!empty($items)): ?>
					<table class="aips-table aips-source-data-table" id="aips-source-data-table">
						<thead>
							<tr>
								<th class="column-run-date"><?php esc_html_e('Run Date/Time', 'ai-post-scheduler'); ?></th>
								<th class="column-content"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></th>
								<th class="column-size"><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th>
								<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $item): ?>
								<?php
								$data_id    = isset($item->id) ? (int) $item->id : 0;
								$text       = isset($item->extracted_text) ? (string) $item->extracted_text : '';
								$snippet    = function_exists('mb_substr') ? mb_substr($text, 0, 100) : substr($text, 0, 100);
								$snippet    = strlen($text) > 100 ? $snippet . '…' : $snippet;
								$bytes      = strlen($text) + strlen(isset($item->raw_html) ? (string) $item->raw_html : '');
								$size_kb    = $bytes > 0 ? number_format_i18n($bytes / 1024, 1) : '0.0';
								$fetched_at = isset($item->fetched_at) ? (int) $item->fetched_at : 0;
								$run_date   = $fetched_at ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $fetched_at) : __('Not recorded', 'ai-post-scheduler');
								?>
								<tr data-source-data-id="<?php echo esc_attr($data_id); ?>">
									<td class="column-run-date cell-primary"><?php echo esc_html($run_date); ?></td>
									<td class="column-content">
										<?php if ('' !== $snippet): ?>
											<?php echo esc_html($snippet); ?>
										<?php elseif (!empty($item->error_message)): ?>
											<span class="cell-meta"><?php echo esc_html(wp_trim_words($item->error_message, 18, '…')); ?></span>
										<?php else: ?>
											<span class="cell-meta">—</span>
										<?php endif; ?>
									</td>
									<td class="column-size"><?php echo esc_html($size_kb); ?> <?php esc_html_e('KB', 'ai-post-scheduler'); ?></td>
									<td class="column-actions cell-actions">
										<div class="aips-row-action-group">
											<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-view-source-data" data-id="<?php echo esc_attr($data_id); ?>">
												<span class="dashicons dashicons-visibility"></span>
												<?php esc_html_e('View', 'ai-post-scheduler'); ?>
											</button>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-source-data" data-id="<?php echo esc_attr($data_id); ?>">
												<span class="dashicons dashicons-trash"></span>
												<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else: ?>
						<div class="aips-empty-state">
							<div class="dashicons dashicons-archive aips-empty-state-icon" aria-hidden="true"></div>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Source Data Found', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php echo '' !== $search ? esc_html__('No saved source data matches your search.', 'ai-post-scheduler') : esc_html__('This source does not have saved fetched data yet.', 'ai-post-scheduler'); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php printf(esc_html(_n('%s source data record', '%s source data records', $total, 'ai-post-scheduler')), number_format_i18n($total)); ?>
					</span>
					<?php if ($pages > 1): ?>
						<?php $start = max(1, $current - 3); $end = min($pages, $current + 3); ?>
						<div class="aips-history-pagination-links">
							<?php if ($current > 1): ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_page_url($current - 1)); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
							<?php endif; ?>
							<?php for ($p = $start; $p <= $end; $p++): ?>
								<?php if ($p === $current): ?>
									<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page"><?php echo esc_html($p); ?></span>
								<?php else: ?>
									<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_page_url($p)); ?>"><?php echo esc_html($p); ?></a>
								<?php endif; ?>
							<?php endfor; ?>
							<?php if ($current < $pages): ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_page_url($current + 1)); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="aips-source-data-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-source-data-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-source-data-modal-title"><?php esc_html_e('View Source Data', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-source-data-form" novalidate>
				<input type="hidden" id="aips-source-data-id" name="data_id" value="0">
				<div class="aips-form-grid aips-form-grid-2">
					<div class="aips-form-row"><label for="aips-source-data-display-id"><?php esc_html_e('ID', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-display-id" class="regular-text" readonly></div>
					<div class="aips-form-row"><label for="aips-source-data-source-id"><?php esc_html_e('Source ID', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-source-id" class="regular-text" readonly></div>
				</div>
				<div class="aips-form-row"><label for="aips-source-data-url"><?php esc_html_e('URL', 'ai-post-scheduler'); ?></label><input type="url" id="aips-source-data-url" name="url" class="large-text"></div>
				<div class="aips-form-row"><label for="aips-source-data-page-title"><?php esc_html_e('Page Title', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-page-title" name="page_title" class="large-text"></div>
				<div class="aips-form-row"><label for="aips-source-data-meta-description"><?php esc_html_e('Meta Description', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-meta-description" name="meta_description" rows="3" class="large-text"></textarea></div>
				<div class="aips-form-row"><label for="aips-source-data-extracted-text"><?php esc_html_e('Extracted Text', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-extracted-text" name="extracted_text" rows="10" class="large-text code"></textarea></div>
				<div class="aips-form-row"><label for="aips-source-data-raw-html"><?php esc_html_e('Raw HTML', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-raw-html" name="raw_html" rows="8" class="large-text code"></textarea></div>
				<div class="aips-form-row"><label for="aips-source-data-fetch-status"><?php esc_html_e('Fetch Status', 'ai-post-scheduler'); ?></label><select id="aips-source-data-fetch-status" name="fetch_status" class="aips-form-select"><option value="pending"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></option><option value="success"><?php esc_html_e('Success', 'ai-post-scheduler'); ?></option><option value="failed"><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></option></select></div>
				<div class="aips-form-grid aips-form-grid-2">
					<div class="aips-form-row"><label for="aips-source-data-http-status"><?php esc_html_e('HTTP Status', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-http-status" name="http_status" class="small-text" min="0" step="1"></div>
					<div class="aips-form-row"><label for="aips-source-data-num-used"><?php esc_html_e('Times Used', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-num-used" class="small-text" readonly></div>
				</div>
				<div class="aips-form-row"><label for="aips-source-data-error-message"><?php esc_html_e('Error Message', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-error-message" name="error_message" rows="3" class="large-text"></textarea></div>
				<div class="aips-form-row"><label for="aips-source-data-fetched-at"><?php esc_html_e('Fetched At Timestamp', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-fetched-at" name="fetched_at" class="regular-text" min="0" step="1"><p class="description"><?php esc_html_e('Unix timestamp for when this source data was fetched.', 'ai-post-scheduler'); ?></p></div>
				<div class="aips-form-grid aips-form-grid-2">
					<div class="aips-form-row"><label for="aips-source-data-char-count"><?php esc_html_e('Character Count', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-char-count" class="regular-text" readonly></div>
					<div class="aips-form-row"><label for="aips-source-data-created-at"><?php esc_html_e('Created At', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-created-at" class="regular-text" readonly></div>
				</div>
				<div class="aips-form-row"><label for="aips-source-data-updated-at"><?php esc_html_e('Updated At', 'ai-post-scheduler'); ?></label><input type="number" id="aips-source-data-updated-at" class="regular-text" readonly></div>
				<div class="aips-form-row"><label for="aips-source-data-content-hash"><?php esc_html_e('Content Hash', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-content-hash" class="large-text code" readonly></div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary" id="aips-save-source-data-btn"><?php esc_html_e('Save Source Data', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
