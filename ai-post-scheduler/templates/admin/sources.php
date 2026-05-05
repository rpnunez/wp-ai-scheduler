<?php
/**
 * Sources admin page template.
 *
 * Displays the Trusted Sources management UI where admins can add, edit,
 * delete, and toggle URLs that the AI is encouraged to read from and cite
 * when generating post content. Also provides Source Groups management.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Ensure $sources is defined for static analysis and for direct includes.
if (!isset($sources) || !is_array($sources)) {
	$sources = array();
}

// $source_groups, $source_group_name_map, and $source_term_ids_map are passed from render_sources_page().
// Provide safe defaults so the template works if included standalone.
if (!isset($source_groups) || !is_array($source_groups)) {
	$source_groups = get_terms(array('taxonomy' => 'aips_source_group', 'hide_empty' => false));
	if (is_wp_error($source_groups)) {
		$source_groups = array();
	}
}
if (!isset($source_group_name_map) || !is_array($source_group_name_map)) {
	$source_group_name_map = array();
	foreach ($source_groups as $group) {
		$source_group_name_map[(int) $group->term_id] = $group->name;
	}
}
if (!isset($source_term_ids_map) || !is_array($source_term_ids_map)) {
	$source_term_ids_map = array();
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Trusted Sources', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Add URLs that the AI should reference and cite when generating post content. Assign Sources to Source Groups to allow Authors and Templates to selectively include them in their prompts.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-manage-source-groups-btn">
						<span class="dashicons dashicons-category"></span>
						<?php esc_html_e('Manage Groups', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-source-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add Source', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<?php if (!empty($sources)): ?>

			<div class="aips-filter-bar">
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-source-search"><?php esc_html_e('Search Sources:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-source-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search sources…', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-source-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<div class="aips-panel-body no-padding">
				<table class="aips-table aips-sources-table" id="aips-sources-table">
					<thead>
						<tr>
							<th class="column-label"><?php esc_html_e('Label', 'ai-post-scheduler'); ?></th>
							<th class="column-url"><?php esc_html_e('URL', 'ai-post-scheduler'); ?></th>
							<th class="column-groups"><?php esc_html_e('Groups', 'ai-post-scheduler'); ?></th>
							<th class="column-fetch-status"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></th>
							<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($sources as $source):
							$src_id     = (int) $source->id;
							$term_ids   = isset($source_term_ids_map[$src_id]) ? $source_term_ids_map[$src_id] : array();
							$group_names = array();
							foreach ($term_ids as $tid) {
								$tid = (int) $tid;
								if (isset($source_group_name_map[$tid])) {
									$group_names[] = $source_group_name_map[$tid];
								}
							}
						$fetch_interval        = isset($source->fetch_interval)   ? $source->fetch_interval   : '';
						$last_fetched_at       = isset($source->last_fetched_at)  ? $source->last_fetched_at  : '';
						$next_fetch_at         = isset($source->next_fetch_at)    ? $source->next_fetch_at    : '';
						$fetch_data            = isset($source_fetch_data_map[$src_id]) ? $source_fetch_data_map[$src_id] : null;
						$fetch_status_val      = $fetch_data ? $fetch_data->fetch_status : '';
						$content_count_val     = isset($source_content_count_map[$src_id]) ? (int) $source_content_count_map[$src_id] : 0;
						?>
						<tr data-source-id="<?php echo esc_attr($source->id); ?>"
							data-url="<?php echo esc_attr($source->url); ?>"
							data-label="<?php echo esc_attr($source->label); ?>"
							data-description="<?php echo esc_attr($source->description); ?>"
							data-active="<?php echo esc_attr($source->is_active); ?>"
							data-fetch-interval="<?php echo esc_attr($fetch_interval); ?>"
							data-term-ids="<?php echo esc_attr(wp_json_encode($term_ids)); ?>">
							<td class="column-label cell-primary">
								<?php echo esc_html(!empty($source->label) ? $source->label : '—'); ?>
							</td>
							<td class="column-url">
								<a href="<?php echo esc_url($source->url); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html($source->url); ?>
								</a>
							</td>
							<td class="column-groups">
								<?php if (!empty($group_names)): ?>
									<?php foreach ($group_names as $gname): ?>
										<span class="aips-badge aips-badge-neutral" style="margin:2px 2px 2px 0;"><?php echo esc_html($gname); ?></span>
									<?php endforeach; ?>
								<?php else: ?>
									<span class="cell-meta">—</span>
								<?php endif; ?>
							</td>
							<td class="column-fetch-status">
								<?php if ($content_count_val > 0): ?>
									<span class="aips-badge aips-badge-success" title="<?php echo esc_attr($last_fetched_at); ?>">
										<span class="dashicons dashicons-archive"></span>
										<?php
										printf(
											/* translators: %d = number of archived content snapshots */
											esc_html(_n('%d content', '%d content', $content_count_val, 'ai-post-scheduler')),
											$content_count_val
										);
										?>
									</span>
								<?php elseif ($fetch_status_val === 'failed'): ?>
									<span class="aips-badge aips-badge-warning">
										<span class="dashicons dashicons-warning"></span>
										<?php esc_html_e('Failed', 'ai-post-scheduler'); ?>
									</span>
								<?php elseif ($fetch_status_val === 'pending'): ?>
									<span class="aips-badge aips-badge-neutral">
										<span class="dashicons dashicons-clock"></span>
										<?php esc_html_e('Pending', 'ai-post-scheduler'); ?>
									</span>
								<?php else: ?>
									<span class="cell-meta">
										<?php echo $fetch_interval ? esc_html__('Scheduled', 'ai-post-scheduler') : '—'; ?>
									</span>
								<?php endif; ?>
								<?php if ($fetch_interval): ?>
									<div class="cell-meta" style="font-size:11px; margin-top:2px;">
										<?php echo esc_html($fetch_interval); ?>
									</div>
								<?php endif; ?>
							</td>
							<td class="column-status">
								<?php if ($source->is_active): ?>
									<span class="aips-badge aips-badge-success">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
									</span>
								<?php else: ?>
									<span class="aips-badge aips-badge-neutral">
										<span class="dashicons dashicons-minus"></span>
										<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<div class="aips-action-buttons">
									<button class="aips-btn aips-btn-sm aips-edit-source"
										data-id="<?php echo esc_attr($source->id); ?>"
										title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-edit"></span>
										<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
									</button>
									<button class="aips-btn aips-btn-sm aips-btn-secondary aips-fetch-source-now"
										data-id="<?php echo esc_attr($source->id); ?>"
										title="<?php esc_attr_e('Fetch content now', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-download"></span>
										<span class="screen-reader-text"><?php esc_html_e('Fetch Now', 'ai-post-scheduler'); ?></span>
									</button>
									<button class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-source"
										data-id="<?php echo esc_attr($source->id); ?>"
										data-active="<?php echo esc_attr($source->is_active); ?>"
										title="<?php echo $source->is_active ? esc_attr__('Deactivate', 'ai-post-scheduler') : esc_attr__('Activate', 'ai-post-scheduler'); ?>">
										<span class="dashicons <?php echo $source->is_active ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"></span>
										<span class="screen-reader-text">
											<?php echo $source->is_active ? esc_html__('Deactivate', 'ai-post-scheduler') : esc_html__('Activate', 'ai-post-scheduler'); ?>
										</span>
									</button>
									<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-source"
										data-id="<?php echo esc_attr($source->id); ?>"
										title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-trash"></span>
										<span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
									</button>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Table footer -->
			<div class="tablenav">
				<span class="aips-table-footer-count">
					<?php
					$count = count($sources);
					printf(
						esc_html(_n('%s source', '%s sources', $count, 'ai-post-scheduler')),
						number_format_i18n($count)
					);
					?>
				</span>
			</div>

			<div id="aips-source-search-no-results" class="aips-empty-state" style="display:none;">
				<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Sources Found', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('No sources match your search criteria.', 'ai-post-scheduler'); ?></p>
				<div class="aips-empty-state-actions">
					<button type="button" class="aips-btn aips-btn-ghost" id="aips-source-search-clear-2">
						<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>

			<?php else: ?>
			<div class="aips-empty-state" id="aips-sources-empty">
				<div class="dashicons dashicons-admin-links aips-empty-state-icon" aria-hidden="true"></div>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Trusted Sources Yet', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('Add URLs that the AI should reference when generating post content. Assign them to Source Groups so Authors and Templates can selectively include them in their prompts.', 'ai-post-scheduler'); ?></p>
				<div class="aips-empty-state-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-source-empty-btn">
						<?php esc_html_e('Add Your First Source', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
			<?php endif; ?>
		</div>

	</div><!-- .aips-page-container -->
</div><!-- .wrap -->

<!-- Add / Edit Source Modal -->
<div id="aips-source-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-source-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-source-modal-title"><?php esc_html_e('Add New Source', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-source-form" novalidate>
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
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary" id="aips-save-source-btn">
				<?php esc_html_e('Save Source', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>

<!-- Manage Source Groups Modal -->
<div id="aips-groups-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-groups-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-groups-modal-title"><?php esc_html_e('Manage Source Groups', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
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
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
