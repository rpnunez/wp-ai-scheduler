<?php
/**
 * Blueprints tab: Sections
 *
 * Renders the Prompt Sections list within the Blueprints tabbed page.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($sections) || !is_array($sections)) {
	$sections = array();
}
?>

<div class="aips-content-panel">
	<div class="aips-structures-container">
		<?php if (!empty($sections)): ?>
		<div class="aips-filter-bar">
			<div class="aips-filter-right">
				<label class="screen-reader-text" for="aips-section-search"><?php esc_html_e('Search Sections:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-section-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search sections...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-section-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>
		</div>

		<div class="aips-panel-body no-padding">
			<table class="aips-table aips-sections-list">
				<thead>
					<tr>
						<th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Key', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($sections as $section) : ?>
					<tr data-section-id="<?php echo esc_attr($section->id); ?>">
						<td class="column-name cell-primary"><?php echo esc_html($section->name); ?></td>
						<td class="column-key"><code><?php echo esc_html($section->section_key); ?></code></td>
						<td class="column-description"><?php echo esc_html($section->description); ?></td>
						<td>
							<?php if ($section->is_active): ?>
								<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="aips-action-buttons">
								<button class="aips-btn aips-btn-sm aips-edit-section" data-id="<?php echo esc_attr($section->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-section" data-id="<?php echo esc_attr($section->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
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
				$section_count = count($sections);
				printf(esc_html(_n('%s section', '%s sections', $section_count, 'ai-post-scheduler')), number_format_i18n($section_count));
				?>
			</span>
		</div>
		<?php else: ?>
		<div class="aips-empty-state">
			<div class="dashicons dashicons-editor-table aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Prompt Sections', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('Create prompt sections to reuse across article structures.', 'ai-post-scheduler'); ?></p>
			<div class="aips-empty-state-actions">
				<button class="aips-btn aips-btn-primary aips-add-section-btn"><?php esc_html_e('Create Section', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Section Modal -->
<div id="aips-section-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-section-modal-title"><?php esc_html_e('Add New Prompt Section', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-section-form">
				<input type="hidden" name="section_id" id="section_id" value="">
				<div class="aips-form-row">
					<label for="section_name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<input type="text" id="section_name" name="name" required class="regular-text">
				</div>
				<div class="aips-form-row">
					<label for="section_key"><?php esc_html_e('Key', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<input type="text" id="section_key" name="section_key" required class="regular-text" placeholder="<?php esc_attr_e('e.g. introduction, steps, tips', 'ai-post-scheduler'); ?>">
				</div>
				<div class="aips-form-row">
					<label for="section_description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
					<textarea id="section_description" name="description" rows="3" class="large-text"></textarea>
				</div>
				<div class="aips-form-row">
					<label for="section_content"><?php esc_html_e('Content', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<textarea id="section_content" name="content" rows="6" required class="large-text" placeholder="<?php esc_attr_e('Prompt text for this section', 'ai-post-scheduler'); ?>"></textarea>
				</div>
				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="section_is_active" name="is_active" value="1" checked>
						<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary aips-save-section"><?php esc_html_e('Save Section', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-section-row">
<tr data-section-id="{{id}}">
	<td class="column-name cell-primary">{{name}}</td>
	<td class="column-key"><code>{{section_key}}</code></td>
	<td class="column-description">{{description}}</td>
	<td>{{activeBadge}}</td>
	<td>
		<div class="aips-action-buttons">
			<button class="aips-btn aips-btn-sm aips-edit-section" data-id="{{id}}"><span class="dashicons dashicons-edit"></span></button>
			<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-section" data-id="{{id}}"><span class="dashicons dashicons-trash"></span></button>
		</div>
	</td>
</tr>
</script>
