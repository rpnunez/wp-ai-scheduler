<?php
/**
 * Manage Source Data admin page template.
 *
 * The snapshot table is rendered by AIPS_Source_Data_List_Table (native pagination
 * and Screen Options); this template supplies the page header, the History-style
 * filter bar, and the View Source Data modal.
 *
 * @var AIPS_Source_Data_List_Table $source_data_table
 * @var object|null                 $source
 * @var int                         $source_id
 * @var array                       $sources
 * @var bool                        $is_global_view
 *
 * @package AI_Post_Scheduler
 * @since 2.8.4
 */

if (!defined('ABSPATH')) {
	exit;
}

$source         = isset($source) ? $source : null;
$source_id      = isset($source_id) ? absint($source_id) : 0;
$sources        = isset($sources) && is_array($sources) ? $sources : array();
$is_global_view = isset($is_global_view) ? (bool) $is_global_view : ($source_id <= 0);

if (!isset($source_data_table) || !($source_data_table instanceof AIPS_Source_Data_List_Table)) {
	$source_data_table = new AIPS_Source_Data_List_Table(array('source_id' => $source_id));
	$source_data_table->prepare_items();
}

$filters          = $source_data_table->get_filters();
$search           = $source_data_table->get_search();
$fetch_status     = (string) $filters['fetch_status'];
$http_class       = (int) $filters['http_status_class'];
$fetched_after    = (string) $filters['fetched_after'];
$fetched_before   = (string) $filters['fetched_before'];
$min_char_count   = (int) $filters['min_char_count'];
$max_char_count   = (int) $filters['max_char_count'];
$search_body_text = !empty($filters['search_body_text']);
$filter_source_id = (int) $filters['source_id'];
$source_label     = ($source && !empty($source->label)) ? $source->label : (($source && !empty($source->url)) ? $source->url : __('Unknown Source', 'ai-post-scheduler'));
$total            = $source_data_table->get_total_items();
$base_args        = $is_global_view ? array() : array('source_id' => $source_id);
$base_url         = AIPS_Admin_Menu_Helper::get_page_url('aips-source-data', $base_args);
$back_url         = AIPS_Admin_Menu_Helper::get_page_url('sources');
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container aips-source-data-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Manage Source Data', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php if ($is_global_view): ?>
							<?php esc_html_e('Audit fetched content snapshots saved across all sources.', 'ai-post-scheduler'); ?>
						<?php else: ?>
							<?php
							printf(
								/* translators: %s = source label or URL */
								esc_html__('Manage fetched content snapshots saved for %s.', 'ai-post-scheduler'),
								esc_html($source_label)
							);
							?>
						<?php endif; ?>
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
			<?php if (!$is_global_view && !$source): ?>
				<div class="aips-empty-state">
					<div class="dashicons dashicons-warning aips-empty-state-icon" aria-hidden="true"></div>
					<h3 class="aips-empty-state-title"><?php esc_html_e('Source Not Found', 'ai-post-scheduler'); ?></h3>
					<p class="aips-empty-state-description"><?php esc_html_e('Choose a source from the Sources page to manage its fetched data.', 'ai-post-scheduler'); ?></p>
				</div>
			<?php else: ?>
				<form class="aips-filter-bar aips-history-filter-bar aips-source-data-filter-bar" method="get">
					<input type="hidden" name="page" value="aips-source-data">
					<?php if (!$is_global_view): ?>
						<input type="hidden" name="source_id" value="<?php echo esc_attr($source_id); ?>">
					<?php endif; ?>
					<div class="aips-history-filter-row">
						<div class="aips-history-filter-selects">
							<?php if ($is_global_view): ?>
								<select id="aips-source-data-source-filter" name="filter_source_id" class="aips-form-select" aria-label="<?php esc_attr_e('Source', 'ai-post-scheduler'); ?>">
									<option value="0"><?php esc_html_e('All Sources', 'ai-post-scheduler'); ?></option>
									<?php foreach ($sources as $filter_source): ?>
										<?php $filter_label = !empty($filter_source->label) ? $filter_source->label : $filter_source->url; ?>
										<option value="<?php echo esc_attr((int) $filter_source->id); ?>" <?php selected($filter_source_id, (int) $filter_source->id); ?>><?php echo esc_html($filter_label); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
							<select id="aips-source-data-fetch-status-filter" name="fetch_status" class="aips-form-select" aria-label="<?php esc_attr_e('Fetch Status', 'ai-post-scheduler'); ?>">
								<option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
								<option value="pending" <?php selected($fetch_status, 'pending'); ?>><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></option>
								<option value="success" <?php selected($fetch_status, 'success'); ?>><?php esc_html_e('Success', 'ai-post-scheduler'); ?></option>
								<option value="failed" <?php selected($fetch_status, 'failed'); ?>><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></option>
							</select>
							<select id="aips-source-data-http-class-filter" name="http_status_class" class="aips-form-select" aria-label="<?php esc_attr_e('HTTP Status Class', 'ai-post-scheduler'); ?>">
								<option value="0"><?php esc_html_e('All HTTP Statuses', 'ai-post-scheduler'); ?></option>
								<option value="100" <?php selected($http_class, 100); ?>><?php esc_html_e('1xx Informational', 'ai-post-scheduler'); ?></option>
								<option value="200" <?php selected($http_class, 200); ?>><?php esc_html_e('2xx Success', 'ai-post-scheduler'); ?></option>
								<option value="300" <?php selected($http_class, 300); ?>><?php esc_html_e('3xx Redirection', 'ai-post-scheduler'); ?></option>
								<option value="400" <?php selected($http_class, 400); ?>><?php esc_html_e('4xx Client Error', 'ai-post-scheduler'); ?></option>
								<option value="500" <?php selected($http_class, 500); ?>><?php esc_html_e('5xx Server Error', 'ai-post-scheduler'); ?></option>
							</select>
						</div>
						<div class="aips-history-filter-search">
							<label class="screen-reader-text" for="aips-source-data-search"><?php esc_html_e('Search source data', 'ai-post-scheduler'); ?></label>
							<input type="search" id="aips-source-data-search" name="s" value="<?php echo esc_attr($search); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('URL, title, status, or error…', 'ai-post-scheduler'); ?>">
							<label class="aips-checkbox-label">
								<input type="checkbox" name="search_body_text" value="1" <?php checked($search_body_text); ?>>
								<?php esc_html_e('Search body text', 'ai-post-scheduler'); ?>
							</label>
						</div>
					</div>
					<div class="aips-history-filter-row aips-history-filter-row-dates">
						<div class="aips-date-range-group">
							<label class="screen-reader-text" for="aips-source-data-fetched-after"><?php esc_html_e('Fetched after', 'ai-post-scheduler'); ?></label>
							<input type="date" id="aips-source-data-fetched-after" name="fetched_after" value="<?php echo esc_attr($fetched_after); ?>" class="aips-form-input" aria-label="<?php esc_attr_e('Fetched after', 'ai-post-scheduler'); ?>">
							<span class="aips-date-range-sep" aria-hidden="true">&ndash;</span>
							<label class="screen-reader-text" for="aips-source-data-fetched-before"><?php esc_html_e('Fetched before', 'ai-post-scheduler'); ?></label>
							<input type="date" id="aips-source-data-fetched-before" name="fetched_before" value="<?php echo esc_attr($fetched_before); ?>" class="aips-form-input" aria-label="<?php esc_attr_e('Fetched before', 'ai-post-scheduler'); ?>">
						</div>
						<div class="aips-source-data-char-range">
							<label class="screen-reader-text" for="aips-source-data-min-chars"><?php esc_html_e('Minimum characters', 'ai-post-scheduler'); ?></label>
							<input type="number" id="aips-source-data-min-chars" name="min_char_count" value="<?php echo esc_attr($min_char_count ?: ''); ?>" min="0" step="1" class="aips-form-input" placeholder="<?php esc_attr_e('Min chars', 'ai-post-scheduler'); ?>">
							<span class="aips-date-range-sep" aria-hidden="true">&ndash;</span>
							<label class="screen-reader-text" for="aips-source-data-max-chars"><?php esc_html_e('Maximum characters', 'ai-post-scheduler'); ?></label>
							<input type="number" id="aips-source-data-max-chars" name="max_char_count" value="<?php echo esc_attr($max_char_count ?: ''); ?>" min="0" step="1" class="aips-form-input" placeholder="<?php esc_attr_e('Max chars', 'ai-post-scheduler'); ?>">
						</div>
						<?php if ($source_data_table->has_active_filters()): ?>
							<a class="aips-btn aips-btn-sm aips-btn-ghost aips-source-data-clear" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
						<?php endif; ?>
						<button type="submit" class="aips-btn aips-btn-sm aips-btn-primary aips-history-filter-apply" id="aips-source-data-filter-btn"><span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></button>
					</div>
				</form>

				<div class="aips-panel-body no-padding aips-list-table-wrap">
					<?php $source_data_table->display(); ?>
				</div>

				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php printf(esc_html(_n('%s source data record', '%s source data records', $total, 'ai-post-scheduler')), number_format_i18n($total)); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="aips-source-data-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-source-data-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-source-data-modal-title"><?php esc_html_e('View Source Data', 'ai-post-scheduler'); ?></h2>
			<button type="button" id="aips-source-data-modal-close-header" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-source-data-form" data-aips-async="true" novalidate>
				<input type="hidden" id="aips-source-data-id" name="data_id" value="0">
				<div class="aips-form-row"><label for="aips-source-data-url"><?php esc_html_e('URL', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-url" class="large-text" readonly></div>
				<div class="aips-form-row"><label for="aips-source-data-page-title"><?php esc_html_e('Page Title', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-page-title" name="page_title" class="large-text"></div>
				<div class="aips-form-row"><label for="aips-source-data-meta-description"><?php esc_html_e('Meta Description', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-meta-description" name="meta_description" rows="3" class="large-text"></textarea></div>
				<div class="aips-form-row"><label for="aips-source-data-extracted-text"><?php esc_html_e('Extracted Text', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-extracted-text" name="extracted_text" rows="10" class="large-text code"></textarea></div>
				<div class="aips-form-row"><label for="aips-source-data-raw-html"><?php esc_html_e('Raw HTML', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-raw-html" name="raw_html" rows="8" class="large-text code"></textarea><p class="description"><?php esc_html_e('Raw HTML editing may be restricted depending on your permissions; users without unfiltered HTML access will have unsafe markup sanitized when saving.', 'ai-post-scheduler'); ?></p></div>
				<div class="aips-form-grid aips-form-grid-3">
					<div class="aips-form-row"><label for="aips-source-data-fetch-status"><?php esc_html_e('Fetch Status', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-fetch-status" class="regular-text" readonly></div>
					<div class="aips-form-row"><label for="aips-source-data-http-status"><?php esc_html_e('HTTP Status', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-http-status" class="regular-text" readonly></div>
					<div class="aips-form-row"><label for="aips-source-data-num-used"><?php esc_html_e('Times Used', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-num-used" class="regular-text" readonly></div>
				</div>
				<div class="aips-form-row"><label for="aips-source-data-error-message"><?php esc_html_e('Error Message', 'ai-post-scheduler'); ?></label><textarea id="aips-source-data-error-message" rows="3" class="large-text" readonly></textarea></div>
				<div class="aips-form-grid aips-form-grid-2">
					<div class="aips-form-row"><label for="aips-source-data-fetched-at"><?php esc_html_e('Fetched At', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-fetched-at" class="regular-text" readonly></div>
					<div class="aips-form-row"><label for="aips-source-data-updated-at"><?php esc_html_e('Updated At', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-updated-at" class="regular-text" readonly></div>
				</div>
				<div class="aips-form-grid aips-form-grid-2">
					<div class="aips-form-row"><label for="aips-source-data-created-at"><?php esc_html_e('Created At', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-created-at" class="regular-text" readonly></div>
					<div class="aips-form-row"><label for="aips-source-data-char-count"><?php esc_html_e('Character Count', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-char-count" class="regular-text" readonly></div>
				</div>
				<div class="aips-form-row"><label for="aips-source-data-content-hash"><?php esc_html_e('Content Hash', 'ai-post-scheduler'); ?></label><input type="text" id="aips-source-data-content-hash" class="large-text code" readonly></div>
				<div class="aips-form-row" id="aips-source-data-usage-wrap" style="display:none;">
					<label><?php esc_html_e('Used in generated posts/history', 'ai-post-scheduler'); ?></label>
					<div id="aips-source-data-usage-links" class="aips-source-data-usage-links"></div>
					<p class="description"><?php esc_html_e('These links are based on generation history metadata captured when this snapshot was injected into a prompt.', 'ai-post-scheduler'); ?></p>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" id="aips-source-data-modal-close-footer" class="button"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary" id="aips-save-source-data-btn"><?php esc_html_e('Save Source Data', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<!-- Templates: usage links in the View Source Data modal (rendered by AIPS.Sources.renderSourceDataUsage). -->
<script type="text/html" id="aips-tmpl-source-data-usage-item">
	<li>{{link}} {{history}}</li>
</script>
<script type="text/html" id="aips-tmpl-source-data-usage-link">
	<a href="{{url}}">{{label}}</a>
</script>
<script type="text/html" id="aips-tmpl-source-data-usage-history">
	<a class="aips-btn aips-btn-xs aips-btn-secondary" href="{{url}}">{{label}}</a>
</script>
