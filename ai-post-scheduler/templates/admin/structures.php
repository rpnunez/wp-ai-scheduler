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

// Ensure $categories is defined
if (!isset($categories) || !is_array($categories)) {
	$categories = array();
}

// Helper function to group items by category
function aips_group_by_category($items, $categories) {
	$grouped = array();
	$category_map = array();
	
	// Build category map
	foreach ($categories as $cat) {
		$category_map[$cat->term_id] = $cat->name;
		$grouped[$cat->term_id] = array();
	}
	
	// Add uncategorized group
	$grouped[0] = array();
	
	// Group items
	foreach ($items as $item) {
		$cat_id = isset($item->category_id) && $item->category_id ? $item->category_id : 0;
		$grouped[$cat_id][] = $item;
	}
	
	return $grouped;
}

$structures_by_category = aips_group_by_category($structures, $categories);
$sections_by_category = aips_group_by_category($sections, $categories);
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></h1>

	<div class="nav-tab-wrapper">
		<a href="#aips-structures" class="nav-tab nav-tab-active" data-tab="aips-structures"><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></a>
		<a href="#aips-structure-sections" class="nav-tab" data-tab="aips-structure-sections"><?php esc_html_e('Structure Sections', 'ai-post-scheduler'); ?></a>
		<a href="#aips-categories" class="nav-tab" data-tab="aips-categories"><?php esc_html_e('Categories', 'ai-post-scheduler'); ?></a>
	</div>

	<div id="aips-structures-tab" class="aips-tab-content active">
		<h2>
			<?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?>
			<button class="page-title-action aips-add-structure-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
		</h2>

		<div class="aips-structures-container">
			<?php if (!empty($structures)): ?>
				<?php
				// Display structures grouped by category
				foreach ($categories as $category):
					if (empty($structures_by_category[$category->term_id])) {
						continue;
					}
				?>
				<h3 class="aips-category-heading"><?php echo esc_html($category->name); ?></h3>
				<table class="wp-list-table widefat fixed striped aips-category-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Default', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($structures_by_category[$category->term_id] as $structure): ?>
						<tr data-structure-id="<?php echo esc_attr($structure->id); ?>">
							<td><?php echo esc_html($structure->name); ?></td>
							<td><?php echo esc_html($structure->description); ?></td>
							<td><?php echo $structure->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
							<td><?php echo $structure->is_default ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
							<td>
								<button class="button aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
								<button class="button button-link-delete aips-delete-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endforeach; ?>
				
				<?php if (!empty($structures_by_category[0])): ?>
				<h3 class="aips-category-heading"><?php esc_html_e('Uncategorized', 'ai-post-scheduler'); ?></h3>
				<table class="wp-list-table widefat fixed striped aips-category-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Default', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($structures_by_category[0] as $structure): ?>
						<tr data-structure-id="<?php echo esc_attr($structure->id); ?>">
							<td><?php echo esc_html($structure->name); ?></td>
							<td><?php echo esc_html($structure->description); ?></td>
							<td><?php echo $structure->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
							<td><?php echo $structure->is_default ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
							<td>
								<button class="button aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
								<button class="button button-link-delete aips-delete-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
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
				<?php
				// Display sections grouped by category
				foreach ($categories as $category):
					if (empty($sections_by_category[$category->term_id])) {
						continue;
					}
				?>
				<h3 class="aips-category-heading"><?php echo esc_html($category->name); ?></h3>
				<table class="wp-list-table widefat fixed striped aips-category-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Key', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($sections_by_category[$category->term_id] as $section) : ?>
						<tr data-section-id="<?php echo esc_attr($section->id); ?>">
							<td><?php echo esc_html($section->name); ?></td>
							<td><code><?php echo esc_html($section->section_key); ?></code></td>
							<td><?php echo esc_html($section->description); ?></td>
							<td><?php echo $section->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
							<td>
								<button class="button aips-edit-section" data-id="<?php echo esc_attr($section->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
								<button class="button button-link-delete aips-delete-section" data-id="<?php echo esc_attr($section->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endforeach; ?>
				
				<?php if (!empty($sections_by_category[0])): ?>
				<h3 class="aips-category-heading"><?php esc_html_e('Uncategorized', 'ai-post-scheduler'); ?></h3>
				<table class="wp-list-table widefat fixed striped aips-category-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Key', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($sections_by_category[0] as $section) : ?>
						<tr data-section-id="<?php echo esc_attr($section->id); ?>">
							<td><?php echo esc_html($section->name); ?></td>
							<td><code><?php echo esc_html($section->section_key); ?></code></td>
							<td><?php echo esc_html($section->description); ?></td>
							<td><?php echo $section->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
							<td>
								<button class="button aips-edit-section" data-id="<?php echo esc_attr($section->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
								<button class="button button-link-delete aips-delete-section" data-id="<?php echo esc_attr($section->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
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

	<div id="aips-categories-tab" class="aips-tab-content" style="display:none;">
		<h2>
			<?php esc_html_e('Categories', 'ai-post-scheduler'); ?>
			<button class="page-title-action aips-add-category-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
		</h2>

		<div class="aips-structures-container">
			<?php if (!empty($categories)): ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($categories as $category): ?>
					<tr data-category-id="<?php echo esc_attr($category->term_id); ?>">
						<td><?php echo esc_html($category->name); ?></td>
						<td><?php echo esc_html($category->description); ?></td>
						<td>
							<button class="button aips-edit-category" data-id="<?php echo esc_attr($category->term_id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
							<button class="button button-link-delete aips-delete-category" data-id="<?php echo esc_attr($category->term_id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else: ?>
			<div class="aips-empty-state">
				<span class="dashicons dashicons-category" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Categories', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create categories to organize your article structures and sections.', 'ai-post-scheduler'); ?></p>
				<button class="button button-primary aips-add-category-btn"><?php esc_html_e('Create Category', 'ai-post-scheduler'); ?></button>
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
						<label for="structure_category_id"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></label>
						<select id="structure_category_id" name="category_id" class="regular-text">
							<option value="0"><?php esc_html_e('-- No Category --', 'ai-post-scheduler'); ?></option>
							<?php foreach ($categories as $category): ?>
							<option value="<?php echo esc_attr($category->term_id); ?>">
								<?php echo esc_html($category->name); ?>
							</option>
							<?php endforeach; ?>
						</select>
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
						<label for="section_category_id"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></label>
						<select id="section_category_id" name="category_id" class="regular-text">
							<option value="0"><?php esc_html_e('-- No Category --', 'ai-post-scheduler'); ?></option>
							<?php foreach ($categories as $category): ?>
							<option value="<?php echo esc_attr($category->term_id); ?>">
								<?php echo esc_html($category->name); ?>
							</option>
							<?php endforeach; ?>
						</select>
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

	<div id="aips-category-modal" class="aips-modal" style="display: none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 id="aips-category-modal-title"><?php esc_html_e('Add New Category', 'ai-post-scheduler'); ?></h2>
				<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<form id="aips-category-form">
					<input type="hidden" name="term_id" id="category_term_id" value="">

					<div class="aips-form-row">
						<label for="category_name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
						<input type="text" id="category_name" name="name" required class="regular-text">
					</div>

					<div class="aips-form-row">
						<label for="category_description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
						<textarea id="category_description" name="description" rows="3" class="large-text"></textarea>
					</div>
				</form>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button button-primary aips-save-category"><?php esc_html_e('Save Category', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>
</div>

