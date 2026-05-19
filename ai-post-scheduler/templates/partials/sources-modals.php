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
				<?php if (!isset($interval_displays) || !is_array($interval_displays)) {
					$interval_displays = array();
				} ?>
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
