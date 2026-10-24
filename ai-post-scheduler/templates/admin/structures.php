<?php
/**
 * Article Structures partial template for Studio.
 *
 * @package AI_Post_Scheduler
 */

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
	<div class="aips-content-panel aips-panel-with-tabs">
		<!-- Tab Navigation -->
		<div class="aips-tab-nav aips-panel-tab-nav">
			<a href="#aips-structures" class="aips-tab-link active" data-tab="aips-structures"><?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?></a>
			<a href="#aips-structure-sections" class="aips-tab-link" data-tab="aips-structure-sections"><?php esc_html_e('Structure Sections', 'ai-post-scheduler'); ?></a>
		</div>

		<div id="aips-structures-tab" class="aips-tab-content active">
			<div class="aips-structures-container">
				<?php if (!empty($structures)): ?>
				<div class="aips-filter-bar">
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-structure-search"><?php esc_html_e('Search Structures:', 'ai-post-scheduler'); ?></label>
						<input type="search" id="aips-structure-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search structures...', 'ai-post-scheduler'); ?>">
						<button type="button" id="aips-structure-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" style="display: none;"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
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
							<td class="column-name cell-primary">
								<strong><?php echo esc_html($structure->name); ?></strong>
								<?php if (!empty($structure->archetype) && $structure->archetype !== 'custom'): ?>
									<span class="aips-badge aips-badge-info aips-badge-archetype"><?php echo esc_html(ucwords(str_replace('_', ' ', $structure->archetype))); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-description"><?php echo esc_html($structure->description); ?></td>
							<td class="column-active">
								<?php if ($structure->is_active): ?>
									<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
								<?php else: ?>
									<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<div class="cell-actions">
									<div class="aips-btn-group aips-btn-group-inline">
										<button class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
										</button>
										<button class="aips-btn aips-btn-sm aips-btn-secondary aips-test-structure-btn" data-id="<?php echo esc_attr($structure->id); ?>" data-name="<?php echo esc_attr($structure->name); ?>" title="<?php esc_attr_e('Test Structure', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-admin-generic"></span>
											<?php esc_html_e('Test', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-row-action-group">
										<button type="button"
												class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
												aria-haspopup="true"
												aria-expanded="false"
												aria-controls="aips-structure-actions-<?php echo esc_attr($structure->id); ?>"
												title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-ellipsis"></span>
											<span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
										</button>
										<div id="aips-structure-actions-<?php echo esc_attr($structure->id); ?>"
											 class="aips-row-action-menu"
											 hidden>
											<button type="button"
													class="aips-row-action-item aips-clone-structure"
													data-id="<?php echo esc_attr($structure->id); ?>">
												<span class="dashicons dashicons-admin-page"></span>
												<?php esc_html_e('Clone', 'ai-post-scheduler'); ?>
											</button>
											<a class="aips-row-action-item" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule', array('schedule_structure' => $structure->id))); ?>">
												<span class="dashicons dashicons-calendar-alt"></span>
												<?php esc_html_e('Schedule', 'ai-post-scheduler'); ?>
											</a>
											<button type="button"
													class="aips-row-action-item aips-delete-structure aips-text-danger"
													data-id="<?php echo esc_attr($structure->id); ?>">
												<span class="dashicons dashicons-trash"></span>
												<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
											</button>
										</div>
									</div>
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

		<div id="aips-structure-sections-tab" class="aips-tab-content">
			<div class="aips-structures-container">
				<?php if (!empty($sections)): ?>
				<div class="aips-filter-bar">
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-section-search"><?php esc_html_e('Search Sections:', 'ai-post-scheduler'); ?></label>
						<input type="search" id="aips-section-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search sections...', 'ai-post-scheduler'); ?>">
						<button type="button" id="aips-section-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" style="display: none;"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
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
							<td class="column-actions">
								<div class="cell-actions">
									<div class="aips-btn-group aips-btn-group-inline">
										<button class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-section" data-id="<?php echo esc_attr($section->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-row-action-group">
										<button type="button"
												class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
												aria-haspopup="true"
												aria-expanded="false"
												aria-controls="aips-section-actions-<?php echo esc_attr($section->id); ?>"
												title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-ellipsis"></span>
											<span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
										</button>
										<div id="aips-section-actions-<?php echo esc_attr($section->id); ?>"
											 class="aips-row-action-menu"
											 hidden>
											<button type="button"
													class="aips-row-action-item aips-delete-section aips-text-danger"
													data-id="<?php echo esc_attr($section->id); ?>">
												<span class="dashicons dashicons-trash"></span>
												<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
											</button>
										</div>
									</div>
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
	</div>

	<div id="aips-structure-modal" class="aips-modal" style="display: none;">
		<div class="aips-modal-content aips-modal-wide">
			<div class="aips-modal-header">
				<h2 class="aips-modal-title"><?php esc_html_e('Configure Article Structure', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body aips-builder-layout">
				<form id="aips-structure-form" data-aips-async="true" class="aips-builder-form">
					<input type="hidden" name="structure_id" id="structure_id" value="">
					<input type="hidden" name="relational_sections" id="structure_relational_sections_json" value="[]">

					<div class="aips-form-row-group">
						<div class="aips-form-row">
							<label for="structure_name"><?php esc_html_e('Structure Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
							<input type="text" id="structure_name" name="name" required class="regular-text" placeholder="<?php esc_attr_e('e.g. In-Depth Product Review', 'ai-post-scheduler'); ?>">
						</div>

						<div class="aips-form-row">
							<label for="structure_archetype"><?php esc_html_e('Pattern Archetype', 'ai-post-scheduler'); ?></label>
							<select id="structure_archetype" name="archetype" class="regular-text">
								<option value="how_to"><?php esc_html_e('How-To Guide (Step List, HowTo Schema)', 'ai-post-scheduler'); ?></option>
								<option value="review"><?php esc_html_e('Product Review (Scorecard, Pros/Cons, Review Schema)', 'ai-post-scheduler'); ?></option>
								<option value="faq"><?php esc_html_e('FAQ Guide (Accordions, FAQPage Schema)', 'ai-post-scheduler'); ?></option>
								<option value="comparison"><?php esc_html_e('Comparison / Versus Guide (Matrix, Verdict)', 'ai-post-scheduler'); ?></option>
								<option value="listicle"><?php esc_html_e('Listicle / Round-Up (Ranked Cards)', 'ai-post-scheduler'); ?></option>
								<option value="custom" selected><?php esc_html_e('Custom Modular Structure', 'ai-post-scheduler'); ?></option>
							</select>
						</div>
					</div>

					<div class="aips-form-row-group">
						<div class="aips-form-row">
							<label for="structure_editor_mode"><?php esc_html_e('Content Output Format', 'ai-post-scheduler'); ?></label>
							<select id="structure_editor_mode" name="editor_mode" class="regular-text">
								<option value="auto" selected><?php esc_html_e('Auto-Detect (Gutenberg / Classic Shortcode)', 'ai-post-scheduler'); ?></option>
								<option value="gutenberg"><?php esc_html_e('Native Gutenberg Core Blocks', 'ai-post-scheduler'); ?></option>
								<option value="shortcode"><?php esc_html_e('Classic Editor Shortcodes ([aips_*])', 'ai-post-scheduler'); ?></option>
							</select>
						</div>

						<div class="aips-form-row aips-checkbox-row aips-checkbox-row-aligned">
							<label class="aips-checkbox-label">
								<input type="checkbox" id="structure_enable_schema" name="enable_schema" value="1" checked>
								<strong><?php esc_html_e('Generate SEO JSON-LD Schema', 'ai-post-scheduler'); ?></strong>
							</label>
						</div>
					</div>

					<div class="aips-form-row">
						<label for="structure_description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
						<textarea id="structure_description" name="description" rows="2" class="large-text" placeholder="<?php esc_attr_e('Optional internal summary of this pattern structure...', 'ai-post-scheduler'); ?>"></textarea>
					</div>

					<!-- Visual Section Stack / Builder -->
					<div class="aips-builder-section-stack-container">
						<div class="aips-builder-header">
							<h3><?php esc_html_e('Article Sections & Components', 'ai-post-scheduler'); ?></h3>
							<p class="description"><?php esc_html_e('Drag handles to re-order. Click "Configure" to customize heading, component tags, and AI prompts.', 'ai-post-scheduler'); ?></p>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-add-builder-section-btn">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php esc_html_e('Add Section', 'ai-post-scheduler'); ?>
							</button>
						</div>

						<div id="aips-builder-cards-list" class="aips-builder-cards-list">
							<!-- Rendered dynamically by admin.js -->
						</div>
					</div>

					<!-- Collapsible Advanced Prompt Override -->
					<details class="aips-advanced-prompt-details">
						<summary class="aips-advanced-prompt-summary"><?php esc_html_e('Advanced: Custom Prompt Template & Legacy Placeholders', 'ai-post-scheduler'); ?></summary>
						<div class="aips-form-row aips-advanced-prompt-row">
							<label for="prompt_template"><?php esc_html_e('Prompt Template (Optional Override)', 'ai-post-scheduler'); ?></label>
							<textarea id="prompt_template" name="prompt_template" rows="4" class="large-text" placeholder="<?php esc_attr_e('Leave empty to automatically compile from sections above, or use {{topic}} and {{section:key}} placeholders.', 'ai-post-scheduler'); ?>"></textarea>
						</div>
					</details>

					<div class="aips-form-row aips-form-row-spacing">
						<label class="aips-checkbox-label">
							<input type="checkbox" id="structure_is_active" name="is_active" value="1" checked>
							<?php esc_html_e('Active for selection and generation', 'ai-post-scheduler'); ?>
						</label>
					</div>
				</form>

				<!-- Slide-Out Section Config Drawer -->
				<div id="aips-section-drawer" class="aips-section-drawer">
					<div class="aips-drawer-header">
						<h3 id="aips-drawer-title"><?php esc_html_e('Configure Section', 'ai-post-scheduler'); ?></h3>
						<button type="button" class="aips-drawer-close" aria-label="<?php esc_attr_e('Close drawer', 'ai-post-scheduler'); ?>">&times;</button>
					</div>
					<div class="aips-drawer-body">
						<input type="hidden" id="drawer_section_index" value="-1">

						<div class="aips-form-row">
							<label for="drawer_section_key"><?php esc_html_e('Section Key', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
							<input type="text" id="drawer_section_key" class="regular-text" placeholder="<?php esc_attr_e('e.g. steps, pros_cons, faq', 'ai-post-scheduler'); ?>">
						</div>

						<div class="aips-form-row">
							<label for="drawer_heading_text"><?php esc_html_e('Heading Text', 'ai-post-scheduler'); ?></label>
							<input type="text" id="drawer_heading_text" class="regular-text" placeholder="<?php esc_attr_e('e.g. Frequently Asked Questions', 'ai-post-scheduler'); ?>">
						</div>

						<div class="aips-form-row">
							<label for="drawer_heading_level"><?php esc_html_e('Heading Tag', 'ai-post-scheduler'); ?></label>
							<select id="drawer_heading_level" class="regular-text">
								<option value="h2">H2 (Default)</option>
								<option value="h3">H3</option>
								<option value="h4">H4</option>
								<option value="none"><?php esc_html_e('None (No Heading)', 'ai-post-scheduler'); ?></option>
							</select>
						</div>

						<div class="aips-form-row">
							<label for="drawer_component_id"><?php esc_html_e('Pattern Component', 'ai-post-scheduler'); ?></label>
							<select id="drawer_component_id" class="regular-text">
								<option value=""><?php esc_html_e('None / Standard Prose & Markdown', 'ai-post-scheduler'); ?></option>
								<!-- Populated via AJAX / PHP components -->
							</select>
						</div>

						<div class="aips-form-row">
							<label for="drawer_target_word_count"><?php esc_html_e('Target Word Count Guide', 'ai-post-scheduler'); ?></label>
							<input type="number" id="drawer_target_word_count" class="small-text" min="0" step="50" placeholder="e.g. 250">
							<p class="description"><?php esc_html_e('Guides AI on length (0 for flexible).', 'ai-post-scheduler'); ?></p>
						</div>

						<div class="aips-form-row">
							<label for="drawer_custom_prompt"><?php esc_html_e('Section AI Prompt Instructions', 'ai-post-scheduler'); ?></label>
							<textarea id="drawer_custom_prompt" rows="5" class="large-text" placeholder="<?php esc_attr_e('Instruct the AI specifically what to write and highlight for this section...', 'ai-post-scheduler'); ?>"></textarea>
						</div>
					</div>
					<div class="aips-drawer-footer">
						<button type="button" class="aips-btn aips-btn-secondary aips-drawer-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-btn aips-btn-primary aips-drawer-apply-btn"><?php esc_html_e('Apply to Card', 'ai-post-scheduler'); ?></button>
					</div>
				</div>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-primary aips-save-structure"><?php esc_html_e('Save Structure', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>

	<!-- 4-Tab Live Test Modal -->
	<div id="aips-structure-test-modal" class="aips-modal" style="display: none;">
		<div class="aips-modal-content aips-modal-wide">
			<div class="aips-modal-header">
				<h2 class="aips-modal-title"><?php esc_html_e('Live Test Structure', 'ai-post-scheduler'); ?>: <span id="aips-test-structure-title"></span></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<input type="hidden" id="aips-test-target-structure-id" value="">
				<div class="aips-test-control-bar">
					<label for="aips-test-sample-topic"><strong><?php esc_html_e('Sample Topic:', 'ai-post-scheduler'); ?></strong></label>
					<input type="text" id="aips-test-sample-topic" class="regular-text aips-test-topic-input" value="How to Build an Ergonomic Home Office Setup">
					<button type="button" id="aips-run-structure-test-btn" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Run Live Test', 'ai-post-scheduler'); ?>
					</button>
				</div>

				<div class="aips-tab-nav aips-panel-tab-nav" id="aips-test-tabs-nav">
					<a href="#aips-test-tab-prompt" class="aips-tab-link active" data-tab="aips-test-tab-prompt"><?php esc_html_e('1. Assembled Prompt', 'ai-post-scheduler'); ?></a>
					<a href="#aips-test-tab-raw" class="aips-tab-link" data-tab="aips-test-tab-raw"><?php esc_html_e('2. Raw AI Output', 'ai-post-scheduler'); ?></a>
					<a href="#aips-test-tab-preview" class="aips-tab-link" data-tab="aips-test-tab-preview"><?php esc_html_e('3. Visual Preview', 'ai-post-scheduler'); ?></a>
					<a href="#aips-test-tab-schema" class="aips-tab-link" data-tab="aips-test-tab-schema"><?php esc_html_e('4. JSON-LD Schema', 'ai-post-scheduler'); ?></a>
				</div>

				<div class="aips-test-tab-panels">
					<div id="aips-test-tab-prompt" class="aips-tab-content active">
						<pre id="aips-test-prompt-output" class="aips-code-preview"></pre>
					</div>

					<div id="aips-test-tab-raw" class="aips-tab-content">
						<pre id="aips-test-raw-output" class="aips-code-preview"></pre>
					</div>

					<div id="aips-test-tab-preview" class="aips-tab-content">
						<div id="aips-test-visual-output" class="aips-rendered-preview"></div>
					</div>

					<div id="aips-test-tab-schema" class="aips-tab-content">
						<pre id="aips-test-schema-output" class="aips-code-preview aips-schema-preview"></pre>
					</div>
				</div>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>

	<div id="aips-section-modal" class="aips-modal" style="display: none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 class="aips-modal-title"><?php esc_html_e('Add New Prompt Section', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<form id="aips-section-form" data-aips-async="true">
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
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-primary aips-save-section"><?php esc_html_e('Save Section', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>

<?php /* HTML templates used by AIPS.Templates.renderRaw() in admin.js */ ?>

<script type="text/html" id="aips-tmpl-structure-row">
<tr data-structure-id="{{id}}">
	<td class="column-name cell-primary">{{name}}</td>
	<td class="column-description">{{description}}</td>
	<td class="column-active">{{activeBadge}}</td>
	<td class="column-actions">
		<div class="aips-action-buttons">
			<button class="aips-btn aips-btn-sm aips-edit-structure" data-id="{{id}}" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-edit"></span>
				<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
			</button>
			<a class="aips-btn aips-btn-sm aips-btn-ghost" href="{{scheduleUrl}}" title="<?php esc_attr_e('Schedule', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-calendar-alt"></span>
				<span class="screen-reader-text"><?php esc_html_e('Schedule', 'ai-post-scheduler'); ?></span>
			</a>
			<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-structure" data-id="{{id}}" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-trash"></span>
				<span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
			</button>
		</div>
	</td>
</tr>
</script>

<script type="text/html" id="aips-tmpl-section-row">
<tr data-section-id="{{id}}">
	<td class="column-name cell-primary">{{name}}</td>
	<td class="column-key"><code>{{section_key}}</code></td>
	<td class="column-description">{{description}}</td>
	<td>{{activeBadge}}</td>
	<td>
		<div class="aips-action-buttons">
			<button class="aips-btn aips-btn-sm aips-edit-section" data-id="{{id}}" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-edit"></span>
				<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
			</button>
			<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-section" data-id="{{id}}" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-trash"></span>
				<span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
			</button>
		</div>
	</td>
</tr>
</script>

<script type="text/html" id="aips-tmpl-section-option">
<option value="{{section_key}}">{{name}}</option>
</script>

