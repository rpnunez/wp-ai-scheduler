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
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Define how your AI-generated content is organized with customizable article structures and sections.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-secondary aips-add-section-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add Structure Section', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-primary aips-add-structure-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add New Structure', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Tab Navigation -->
		<div class="aips-tab-nav">
			<a href="#aips-structures" class="aips-tab-link active" data-tab="aips-structures"><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></a>
			<a href="#aips-structure-sections" class="aips-tab-link" data-tab="aips-structure-sections"><?php esc_html_e('Structure Sections', 'ai-post-scheduler'); ?></a>
		</div>

	<div id="aips-structures-tab" class="aips-tab-content active">
		<div class="aips-content-panel">
		<div class="aips-structures-container">
			<?php if (!empty($structures)): ?>
			<div class="aips-filter-bar">
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-structure-search"><?php esc_html_e('Search Structures:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-structure-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search structures...', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-structure-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<div class="aips-panel-body no-padding">
			<table class="aips-table aips-structures-list">
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
						<td class="column-name cell-primary"><?php echo esc_html($structure->name); ?></td>
						<td class="column-description"><?php echo esc_html($structure->description); ?></td>
						<td class="column-active">
							<?php if ($structure->is_active): ?>
								<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-default">
							<?php if ($structure->is_default): ?>
								<span class="aips-badge aips-badge-info"><?php esc_html_e('Default', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="cell-meta">—</span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<div class="aips-action-buttons">
								<button class="aips-btn aips-btn-sm aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit"></span>
									<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
								</button>
								<a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('aips-schedule', array('schedule_structure' => $structure->id))); ?>" title="<?php esc_attr_e('Schedule', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-calendar-alt"></span>
									<span class="screen-reader-text"><?php esc_html_e('Schedule', 'ai-post-scheduler'); ?></span>
								</a>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-structure" data-id="<?php echo esc_attr($structure->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
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
					$count = count( $structures );
					printf(
						esc_html(
							_n(
								'%s structure',
								'%s structures',
								$count,
								'ai-post-scheduler'
							)
						),
						number_format_i18n( $count )
					);
					?>
				</span>
			</div>

			<div id="aips-structure-search-no-results" class="aips-empty-state" style="display: none;">
				<span class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></span>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Structures Found', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('No article structures match your search criteria.', 'ai-post-scheduler'); ?></p>
				<div class="aips-empty-state-actions">
					<button type="button" class="aips-btn aips-btn-primary aips-clear-structure-search-btn">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
			<?php else: ?>
			<div class="aips-empty-state">
				<span class="dashicons dashicons-layout" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Article Structures', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create article structures to customize how templates assemble content.', 'ai-post-scheduler'); ?></p>
				<button class="aips-btn aips-btn-primary aips-add-structure-btn"><?php esc_html_e('Create Structure', 'ai-post-scheduler'); ?></button>
			</div>
			<?php endif; ?>
		</div>
	</div>
	</div>

	<div id="aips-structure-sections-tab" class="aips-tab-content" style="display:none;">
		<div class="aips-content-panel">
		<div class="aips-structures-container">
			<?php if (!empty($sections)): ?>
			<div class="aips-filter-bar">
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-section-search"><?php esc_html_e('Search Sections:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-section-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search sections...', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-section-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
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
									<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
								</button>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-section" data-id="<?php echo esc_attr($section->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
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
					$section_count = count( $sections );
					printf(
						esc_html( _n( '%s section', '%s sections', $section_count, 'ai-post-scheduler' ) ),
						number_format_i18n( $section_count )
					);
					?>
				</span>
			</div>

			<div id="aips-section-search-no-results" class="aips-empty-state" style="display: none;">
				<span class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></span>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Sections Found', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('No structure sections match your search criteria.', 'ai-post-scheduler'); ?></p>
				<div class="aips-empty-state-actions">
					<button type="button" class="aips-btn aips-btn-primary aips-clear-section-search-btn">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
			<?php else : ?>
			<div class="aips-empty-state">
				<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Prompt Sections', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create prompt sections to reuse across article structures.', 'ai-post-scheduler'); ?></p>
				<button class="aips-btn aips-btn-primary aips-add-section-btn"><?php esc_html_e('Create Section', 'ai-post-scheduler'); ?></button>
			</div>
			<?php endif; ?>
		</div>
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
	</div><!-- .aips-page-container -->
</div><!-- .wrap -->

