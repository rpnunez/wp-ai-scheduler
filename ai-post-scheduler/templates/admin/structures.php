<?php
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $structures is defined for static analysis and for direct includes
if (!isset($structures) || !is_array($structures)) {
    $structures = array();
}

// Ensure $sections is defined for static analysis and for direct includes
if (!isset($sections) || !is_array($sections)) {
	$sections = array();
}
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></h1>

	<div class="nav-tab-wrapper">
		<a href="#aips-structures" class="nav-tab nav-tab-active" data-tab="aips-structures"><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></a>
		<a href="#aips-structure-sections" class="nav-tab" data-tab="aips-structure-sections"><?php esc_html_e('Structure Sections', 'ai-post-scheduler'); ?></a>
	</div>

	<div id="aips-structures-tab" class="aips-tab-content active">
		<h2>
			<?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?>
			<button class="page-title-action aips-add-structure-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
		</h2>

		<div class="aips-structures-container">
			<?php if (!empty($structures)): ?>
			<div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
				<label class="screen-reader-text" for="aips-structure-search"><?php esc_html_e('Search Structures:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-structure-search" class="regular-text" placeholder="<?php esc_attr_e('Search structures...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-structure-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>

			<table class="wp-list-table widefat fixed striped aips-structures-list">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th class="column-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
						<th class="column-active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
						<th class="column-default"><?php esc_html_e('Default', 'ai-post-scheduler'); ?></th>
						<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($structures as $structure): ?>
					<tr data-structure-id="<?php echo esc_attr($structure->id); ?>">
						<td class="column-name"><strong><?php echo esc_html($structure->name); ?></strong></td>
						<td class="column-description"><?php echo esc_html($structure->description); ?></td>
						<td class="column-active"><?php echo $structure->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
						<td class="column-default"><?php echo $structure->is_default ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
						<td>
							<button class="button aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
							<button class="button button-link-delete aips-delete-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div id="aips-structure-search-no-results" class="aips-empty-state" style="display: none;">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Structures Found', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('No article structures match your search criteria.', 'ai-post-scheduler'); ?></p>
				<button type="button" class="button button-primary aips-clear-structure-search-btn">
					<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<?php else: ?>
			<div class="aips-empty-state">
				<span class="dashicons dashicons-layout" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Article Structures', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create article structures to customize how templates assemble content.', 'ai-post-scheduler'); ?></p>
				<button class="button button-primary aips-add-structure-btn"><?php esc_html_e('Create Structure', 'ai-post-scheduler'); ?></button>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div id="aips-structure-sections-tab" class="aips-tab-content" style="display:none;">
		<h2>
			<?php esc_html_e('Structure Sections', 'ai-post-scheduler'); ?>
			<button class="page-title-action aips-add-section-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
		</h2>

		<div class="aips-structures-container">
			<?php if (!empty($sections)): ?>
			<div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
				<label class="screen-reader-text" for="aips-section-search"><?php esc_html_e('Search Sections:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-section-search" class="regular-text" placeholder="<?php esc_attr_e('Search sections...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-section-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>

			<table class="wp-list-table widefat fixed striped aips-sections-list">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th class="column-key"><?php esc_html_e('Key', 'ai-post-scheduler'); ?></th>
						<th class="column-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
						<th class="column-active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
						<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($sections as $section) : ?>
					<tr data-section-id="<?php echo esc_attr($section->id); ?>">
						<td class="column-name"><strong><?php echo esc_html($section->name); ?></strong></td>
						<td class="column-key"><code><?php echo esc_html($section->section_key); ?></code></td>
						<td class="column-description"><?php echo esc_html($section->description); ?></td>
						<td class="column-active"><?php echo $section->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
						<td class="column-actions">
							<button class="button aips-edit-section" data-id="<?php echo esc_attr($section->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
							<button class="button button-link-delete aips-delete-section" data-id="<?php echo esc_attr($section->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div id="aips-section-search-no-results" class="aips-empty-state" style="display: none;">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Sections Found', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('No prompt sections match your search criteria.', 'ai-post-scheduler'); ?></p>
				<button type="button" class="button button-primary aips-clear-section-search-btn">
					<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<?php else : ?>
			<div class="aips-empty-state">
				<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Prompt Sections', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create prompt sections to reuse across article structures.', 'ai-post-scheduler'); ?></p>
				<button class="button button-primary aips-add-section-btn"><?php esc_html_e('Create Section', 'ai-post-scheduler'); ?></button>
			</div>
			<?php endif; ?>
		</div>
	</div>

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
							<option value="<?php echo esc_attr($section->section_key); ?>">
								<?php echo esc_html($section->name); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e('Choose sections that make up this article structure. Hold Ctrl (Cmd on Mac) to select multiple items.', 'ai-post-scheduler'); ?></p>
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

					<div class="aips-form-row">
						<label class="aips-checkbox-label">
							<input type="checkbox" id="structure_is_default" name="is_default" value="1">
							<?php esc_html_e('Set as Default', 'ai-post-scheduler'); ?>
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
						<p class="description"><?php esc_html_e('Use a unique key. Lowercase letters, numbers, and underscores recommended.', 'ai-post-scheduler'); ?></p>
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
</div>

