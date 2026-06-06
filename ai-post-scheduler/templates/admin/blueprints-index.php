<?php
/**
 * Blueprints tab: Structures
 *
 * Renders the Article Structures list within the Blueprints tabbed page.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($structures) || !is_array($structures)) {
	$structures = array();
}
if (!isset($sections) || !is_array($sections)) {
	$sections = array();
}
?>

<div class="aips-content-panel">
	<div class="aips-structures-container">
		<?php if (!empty($structures)): ?>
		<div class="aips-filter-bar">
			<div class="aips-filter-right">
				<label class="screen-reader-text" for="aips-structure-search"><?php esc_html_e('Search Structures:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-structure-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search structures...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-structure-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>
		</div>

		<div class="aips-panel-body no-padding">
			<table class="aips-table aips-structures-list">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th class="column-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
						<th class="column-active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
						<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($structures as $structure): ?>
					<tr data-structure-id="<?php echo esc_attr($structure->id); ?>">
						<td class="column-name cell-primary"><?php echo esc_html($structure->name); ?></td>
						<td class="column-description"><?php echo esc_html($structure->description); ?></td>
						<td class="column-active">
							<?php if ($structure->is_active): ?>
								<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<div class="aips-action-buttons">
								<button class="aips-btn aips-btn-sm aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('aips-schedule', array('schedule_structure' => $structure->id))); ?>" title="<?php esc_attr_e('Schedule', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-calendar-alt"></span>
								</a>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-structure" data-id="<?php echo esc_attr($structure->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
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
				$count = count($structures);
				printf(esc_html(_n('%s structure', '%s structures', $count, 'ai-post-scheduler')), number_format_i18n($count));
				?>
			</span>
		</div>

		<div id="aips-structure-search-no-results" class="aips-empty-state" style="display: none;">
			<span class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></span>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Structures Found', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('No article structures match your search criteria.', 'ai-post-scheduler'); ?></p>
		</div>
		<?php else: ?>
		<div class="aips-empty-state">
			<div class="dashicons dashicons-layout aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Article Structures', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('Create article structures to customize how templates assemble content.', 'ai-post-scheduler'); ?></p>
			<div class="aips-empty-state-actions">
				<button class="aips-btn aips-btn-primary aips-add-structure-btn"><?php esc_html_e('Create Structure', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Structure Modal -->
<div id="aips-structure-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-structure-modal-title"><?php esc_html_e('Add New Article Structure', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-structure-form">
				<input type="hidden" name="structure_id" id="structure_id" value="">
				<div class="aips-form-row">
					<label for="structure_name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<input type="text" id="structure_name" name="name" required class="regular-text">
				</div>
				<div class="aips-form-row">
					<label for="structure_description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
					<textarea id="structure_description" name="description" rows="3" class="large-text"></textarea>
				</div>
				<div class="aips-form-row">
					<label for="structure_sections"><?php esc_html_e('Sections (Select one or more)', 'ai-post-scheduler'); ?></label>
					<select id="structure_sections" name="sections[]" multiple size="10" class="aips-multiselect">
						<?php foreach ($sections as $section): ?>
						<option value="<?php echo esc_attr($section->section_key); ?>"><?php echo esc_html($section->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aips-form-row">
					<label for="prompt_template"><?php esc_html_e('Prompt Template', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<textarea id="prompt_template" name="prompt_template" rows="6" required class="large-text" placeholder="<?php esc_attr_e('Use {{section:key}} placeholders to inject section content', 'ai-post-scheduler'); ?>"></textarea>
				</div>
				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="structure_is_active" name="is_active" value="1" checked>
						<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary aips-save-structure"><?php esc_html_e('Save Structure', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-structure-row">
<tr data-structure-id="{{id}}">
	<td class="column-name cell-primary">{{name}}</td>
	<td class="column-description">{{description}}</td>
	<td class="column-active">{{activeBadge}}</td>
	<td class="column-actions">
		<div class="aips-action-buttons">
			<button class="aips-btn aips-btn-sm aips-edit-structure" data-id="{{id}}"><span class="dashicons dashicons-edit"></span></button>
			<a class="aips-btn aips-btn-sm aips-btn-ghost" href="{{scheduleUrl}}"><span class="dashicons dashicons-calendar-alt"></span></a>
			<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-structure" data-id="{{id}}"><span class="dashicons dashicons-trash"></span></button>
		</div>
	</td>
</tr>
</script>

<script type="text/html" id="aips-tmpl-section-option">
<option value="{{section_key}}">{{name}}</option>
</script>
