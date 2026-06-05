<?php
/**
 * Blueprints tab: Presets
 *
 * Renders the Blueprint Presets management UI within the Blueprints tabbed page.
 * A preset bundles a structure, voice, and slice selection into a reusable configuration.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($blueprint_presets) || !is_array($blueprint_presets)) {
	$blueprint_presets = array();
}
if (!isset($structures) || !is_array($structures)) {
	$structures = array();
}
if (!isset($voices) || !is_array($voices)) {
	$voices = array();
}
if (!isset($post_slices) || !is_array($post_slices)) {
	$post_slices = array();
}
?>

<div class="aips-content-panel">
	<div class="aips-blueprint-presets-container">
		<?php if (!empty($blueprint_presets)): ?>
		<div class="aips-filter-bar">
			<div class="aips-filter-right">
				<label class="screen-reader-text" for="aips-preset-search"><?php esc_html_e('Search Presets:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-preset-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search presets...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-preset-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>
		</div>

		<div class="aips-panel-body no-padding">
			<table class="aips-table aips-blueprint-presets-table" id="aips-blueprint-presets-table">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th class="column-structure"><?php esc_html_e('Structure', 'ai-post-scheduler'); ?></th>
						<th class="column-voice"><?php esc_html_e('Voice', 'ai-post-scheduler'); ?></th>
						<th class="column-slices"><?php esc_html_e('Slices', 'ai-post-scheduler'); ?></th>
						<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($blueprint_presets as $preset): ?>
					<?php
					$preset_id   = isset($preset->id) ? (int) $preset->id : 0;
					$preset_name = isset($preset->name) ? (string) $preset->name : '';
					$is_active   = !empty($preset->is_active) ? 1 : 0;
					$is_default  = !empty($preset->is_default) ? 1 : 0;

					// Resolve structure name.
					$structure_name = '—';
					if (!empty($preset->structure_id)) {
						foreach ($structures as $s) {
							if ((int) $s->id === (int) $preset->structure_id) {
								$structure_name = $s->name;
								break;
							}
						}
					}

					// Resolve voice name.
					$voice_name = '—';
					if (!empty($preset->voice_id)) {
						foreach ($voices as $v) {
							if ((int) $v->id === (int) $preset->voice_id) {
								$voice_name = $v->name;
								break;
							}
						}
					}

					// Resolve slice names.
					$slice_names = array();
					$slice_ids = !empty($preset->slice_ids) ? json_decode($preset->slice_ids, true) : array();
					if (is_array($slice_ids)) {
						foreach ($slice_ids as $sid) {
							foreach ($post_slices as $ps) {
								if ((int) $ps->id === (int) $sid) {
									$slice_names[] = $ps->name;
									break;
								}
							}
						}
					}
					$slice_display = !empty($slice_names) ? implode(', ', $slice_names) : '—';
					?>
					<tr data-preset-id="<?php echo esc_attr($preset_id); ?>">
						<td class="column-name cell-primary">
							<?php echo esc_html($preset_name); ?>
							<?php if ($is_default): ?>
								<span class="aips-badge aips-badge-info"><?php esc_html_e('Default', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-structure"><?php echo esc_html($structure_name); ?></td>
						<td class="column-voice"><?php echo esc_html($voice_name); ?></td>
						<td class="column-slices"><?php echo esc_html($slice_display); ?></td>
						<td class="column-status">
							<?php if ($is_active): ?>
								<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<div class="aips-action-buttons">
								<button type="button" class="aips-btn aips-btn-sm aips-edit-blueprint-preset" data-id="<?php echo esc_attr($preset_id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-blueprint-preset" data-id="<?php echo esc_attr($preset_id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="tablenav">
			<span class="aips-table-footer-count">
				<?php
				$preset_count = count($blueprint_presets);
				printf(esc_html(_n('%s preset', '%s presets', $preset_count, 'ai-post-scheduler')), number_format_i18n($preset_count));
				?>
			</span>
		</div>
		<?php else: ?>
		<div class="aips-empty-state">
			<div class="dashicons dashicons-layout aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Blueprint Presets', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description">
				<?php esc_html_e('Blueprint presets bundle a structure, voice, and slice selection into a reusable configuration you can assign to templates and schedules.', 'ai-post-scheduler'); ?>
			</p>
			<div class="aips-empty-state-actions">
				<button type="button" class="aips-btn aips-btn-primary" id="aips-add-blueprint-preset-empty-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e('Create Your First Preset', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Blueprint Preset Modal -->
<div id="aips-blueprint-preset-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-blueprint-preset-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-blueprint-preset-modal-title"><?php esc_html_e('Add Blueprint Preset', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-blueprint-preset-form" novalidate>
				<input type="hidden" name="preset_id" id="aips-blueprint-preset-id" value="0">

				<div class="aips-form-row">
					<label for="aips-blueprint-preset-name"><?php esc_html_e('Preset Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<input type="text" id="aips-blueprint-preset-name" name="name" required class="regular-text" placeholder="<?php esc_attr_e('e.g. Technical Tutorial (Formal)', 'ai-post-scheduler'); ?>">
				</div>

				<div class="aips-form-row">
					<label for="aips-blueprint-preset-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
					<textarea id="aips-blueprint-preset-description" name="description" rows="3" class="large-text"></textarea>
				</div>

				<div class="aips-form-row">
					<label for="aips-blueprint-preset-structure"><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></label>
					<select id="aips-blueprint-preset-structure" name="structure_id" class="regular-text">
						<option value=""><?php esc_html_e('— None —', 'ai-post-scheduler'); ?></option>
						<?php foreach ($structures as $structure): ?>
						<option value="<?php echo esc_attr($structure->id); ?>"><?php echo esc_html($structure->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="aips-form-row">
					<label for="aips-blueprint-preset-voice"><?php esc_html_e('Voice', 'ai-post-scheduler'); ?></label>
					<select id="aips-blueprint-preset-voice" name="voice_id" class="regular-text">
						<option value=""><?php esc_html_e('— None —', 'ai-post-scheduler'); ?></option>
						<?php foreach ($voices as $voice): ?>
						<option value="<?php echo esc_attr($voice->id); ?>"><?php echo esc_html($voice->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="aips-form-row">
					<label for="aips-blueprint-preset-slices"><?php esc_html_e('Post Slices', 'ai-post-scheduler'); ?></label>
					<select id="aips-blueprint-preset-slices" name="slice_ids[]" multiple size="6" class="aips-multiselect">
						<?php foreach ($post_slices as $slice): ?>
						<option value="<?php echo esc_attr($slice->id); ?>"><?php echo esc_html($slice->name); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e('Hold Ctrl (Cmd on Mac) to select multiple slices.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-blueprint-preset-is-active" name="is_active" value="1" checked>
						<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
					</label>
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-blueprint-preset-is-default" name="is_default" value="1">
						<?php esc_html_e('Set as default preset', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary" id="aips-save-blueprint-preset-btn"><?php esc_html_e('Save Preset', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
