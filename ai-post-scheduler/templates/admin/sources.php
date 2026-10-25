<?php
/**
 * Sources admin page template.
 *
 * Displays the Trusted Sources management UI where admins can add, edit,
 * delete, and toggle URLs that the AI is encouraged to read from and cite
 * when generating post content. Also provides Source Groups management.
 *
 * The table itself is rendered by AIPS_Sources_List_Table (server-side filtering,
 * pagination and Screen Options); this template supplies the bulk-action toolbar,
 * the filter bar, and the modals.
 *
 * @var AIPS_Sources_List_Table $sources_table
 * @var array                   $source_groups
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Defaults so the template works when included standalone.
if (!isset($sources_table) || !($sources_table instanceof AIPS_Sources_List_Table)) {
	$sources_table = new AIPS_Sources_List_Table();
	$sources_table->prepare_items();
}
if (!isset($source_groups) || !is_array($source_groups)) {
	$source_groups = $sources_table->get_source_groups();
}

$source_filters = $sources_table->get_filters();
$has_sources    = $sources_table->get_unfiltered_total() > 0;
$sources_total  = $sources_table->get_total_items();
?>
			<div class="aips-content-panel">
				<?php if ($has_sources): ?>

				<!-- Toolbar (Bulk Actions) -->
				<div class="aips-panel-toolbar">
					<div class="aips-toolbar-left aips-btn-group aips-btn-group-inline">
						<select id="aips-source-bulk-action" class="aips-form-select">
							<option value=""><?php esc_html_e('Bulk actions', 'ai-post-scheduler'); ?></option>
							<option value="activate"><?php esc_html_e('Activate', 'ai-post-scheduler'); ?></option>
							<option value="deactivate"><?php esc_html_e('Deactivate', 'ai-post-scheduler'); ?></option>
							<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
						</select>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-source-bulk-apply"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
					</div>
				</div>

				<!-- Filter Bar -->
				<form method="get" class="aips-filter-bar aips-history-filter-bar aips-sources-filter-bar">
					<input type="hidden" name="page" value="aips-automations">
					<input type="hidden" name="tab" value="sources">
					<div class="aips-history-filter-row">
						<div class="aips-history-filter-selects">
							<?php if (!empty($source_groups)): ?>
							<select id="aips-source-filter-group" name="group" class="aips-form-select" aria-label="<?php esc_attr_e('Group', 'ai-post-scheduler'); ?>">
								<option value=""><?php esc_html_e('All Groups', 'ai-post-scheduler'); ?></option>
								<?php foreach ($source_groups as $group): ?>
								<option value="<?php echo esc_attr($group->term_id); ?>" <?php selected($source_filters['group'], (int) $group->term_id); ?>><?php echo esc_html($group->name); ?></option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
							<select id="aips-source-filter-status" name="status" class="aips-form-select" aria-label="<?php esc_attr_e('Status', 'ai-post-scheduler'); ?>">
								<option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
								<option value="active" <?php selected($source_filters['status'], 'active'); ?>><?php esc_html_e('Active', 'ai-post-scheduler'); ?></option>
								<option value="inactive" <?php selected($source_filters['status'], 'inactive'); ?>><?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></option>
							</select>
						</div>
						<div class="aips-history-filter-search">
							<label class="screen-reader-text" for="aips-source-search"><?php esc_html_e('Search Sources:', 'ai-post-scheduler'); ?></label>
							<input type="search" id="aips-source-search" name="s" class="aips-form-input" placeholder="<?php esc_attr_e('Search sources…', 'ai-post-scheduler'); ?>" value="<?php echo esc_attr($source_filters['search']); ?>">
						</div>
					</div>
					<div class="aips-history-filter-row aips-history-filter-row-dates">
						<button type="submit" class="aips-btn aips-btn-sm aips-btn-primary aips-history-filter-apply" id="aips-source-filter-btn"><span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></button>
						<?php if ($sources_table->has_active_filters()): ?>
						<a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('sources')); ?>"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
						<?php endif; ?>
					</div>
				</form>

				<div class="aips-panel-body no-padding aips-list-table-wrap">
					<?php $sources_table->display(); ?>
				</div>

				<!-- Table footer -->
				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php
						printf(
							esc_html(_n('%s source', '%s sources', $sources_total, 'ai-post-scheduler')),
							number_format_i18n($sources_total)
						);
						?>
					</span>
				</div>

				<?php else: ?>
				<?php $sources_table->no_items(); ?>
				<?php endif; ?>
			</div>

<!-- Add / Edit Source Modal -->
<div id="aips-source-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Add New Source', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-source-form" data-aips-async="true" novalidate>
				<input type="hidden" name="source_id" id="aips-source-id" value="0">

				<div class="aips-form-row">
					<label for="aips-source-url">
						<?php esc_html_e('URL', 'ai-post-scheduler'); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
					<input type="url" id="aips-source-url" name="url" required class="regular-text"
						placeholder="<?php esc_attr_e('https://example.com', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('The full URL of the website (e.g. https://example.com or https://example.com/blog).', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-source-label"><?php esc_html_e('Label', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-source-label" name="label" class="regular-text"
						placeholder="<?php esc_attr_e('e.g. Official Docs, Industry Leader', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('Optional short name to help you identify this source.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-source-description"><?php esc_html_e('Notes', 'ai-post-scheduler'); ?></label>
					<textarea id="aips-source-description" name="description" rows="3" class="large-text"
						placeholder="<?php esc_attr_e('Optional notes about why this source is trusted.', 'ai-post-scheduler'); ?>"></textarea>
				</div>

				<!-- Fetch Interval -->
				<?php
				$interval_calc      = new AIPS_Interval_Calculator();
				$interval_displays  = $interval_calc->get_all_interval_displays();
				?>
				<div class="aips-form-row">
					<label for="aips-source-fetch-interval"><?php esc_html_e('Auto-Fetch Frequency', 'ai-post-scheduler'); ?></label>
					<select id="aips-source-fetch-interval" name="fetch_interval" class="aips-form-select">
						<option value=""><?php esc_html_e('— No automatic fetching —', 'ai-post-scheduler'); ?></option>
						<?php foreach ($interval_displays as $key => $label): ?>
							<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e('How often to automatically fetch and cache this source\'s content. Leave blank to disable auto-fetching (you can still use "Fetch Now" manually).', 'ai-post-scheduler'); ?></p>
				</div>

				<!-- Source Groups -->
				<div class="aips-form-row">
					<label><?php esc_html_e('Source Groups', 'ai-post-scheduler'); ?></label>
					<div id="aips-source-groups-checkboxes" class="aips-checkbox-group">
						<?php if (!empty($source_groups)): ?>
							<?php foreach ($source_groups as $group): ?>
								<label class="aips-checkbox-label" style="display:block; margin-bottom:4px;">
									<input type="checkbox"
										name="term_ids[]"
										class="aips-source-group-checkbox"
										value="<?php echo esc_attr($group->term_id); ?>">
									<?php echo esc_html($group->name); ?>
								</label>
							<?php endforeach; ?>
						<?php else: ?>
							<p class="description" id="aips-no-groups-msg">
								<?php esc_html_e('No Source Groups exist yet. Create groups using the "Manage Groups" button.', 'ai-post-scheduler'); ?>
							</p>
						<?php endif; ?>
					</div>
					<p class="description"><?php esc_html_e('Assign this source to one or more Source Groups. Authors and Templates can then choose which groups to include in their prompts.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-source-is-active" name="is_active" value="1" checked>
						<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary" id="aips-save-source-btn">
				<?php esc_html_e('Save Source', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>

<!-- Manage Source Groups Modal -->
<div id="aips-groups-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Manage Source Groups', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<p class="description" style="margin-bottom:16px;"><?php esc_html_e('Source Groups let you categorize sources. Authors and Templates can then specify which groups to include in their AI prompts.', 'ai-post-scheduler'); ?></p>

			<!-- Existing groups list -->
			<div id="aips-groups-list" style="margin-bottom:20px;">
				<?php if (!empty($source_groups)): ?>
					<table class="aips-table" style="width:100%;">
						<thead><tr>
							<th><?php esc_html_e('Group Name', 'ai-post-scheduler'); ?></th>
							<th style="width:80px;"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr></thead>
						<tbody id="aips-groups-table-body">
							<?php foreach ($source_groups as $group): ?>
								<tr data-term-id="<?php echo esc_attr($group->term_id); ?>">
									<td><?php echo esc_html($group->name); ?></td>
									<td>
										<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-source-group"
											data-term-id="<?php echo esc_attr($group->term_id); ?>"
											title="<?php esc_attr_e('Delete group', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else: ?>
					<p id="aips-groups-empty-msg" class="description"><?php esc_html_e('No Source Groups yet.', 'ai-post-scheduler'); ?></p>
				<?php endif; ?>
			</div>

			<!-- Add new group inline -->
			<div style="display:flex; gap:8px; align-items:center;">
				<input type="text" id="aips-new-group-name" class="regular-text"
					placeholder="<?php esc_attr_e('New group name…', 'ai-post-scheduler'); ?>" style="flex:1;">
				<button type="button" class="aips-btn aips-btn-primary" id="aips-add-group-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e('Add Group', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<?php include AIPS_PLUGIN_DIR . 'templates/partials/ai-assistance.php'; ?>
