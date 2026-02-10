<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!isset($sections) || !is_array($sections)) {
	$sections = array();
}
?>
<div class="wrap aips-wrap aips-redesign">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Prompt Sections', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Create and manage reusable prompt sections that can be inserted into your templates using placeholders.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<button class="aips-btn aips-btn-primary aips-add-section-btn">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Section', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Content Panel -->
		<div class="aips-content-panel">
			<?php if (!empty($sections)) : ?>
			<!-- Filter Bar -->
			<div class="aips-filter-bar">
				<input type="search" id="aips-section-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search sections...', 'ai-post-scheduler'); ?>">
			</div>

			<!-- Table -->
			<div class="aips-panel-body no-padding">
				<table class="aips-table">
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
							<td class="column-key">
								<div class="aips-variable-code-cell">
									<code><?php echo esc_html($section->section_key); ?></code>
									<button type="button" class="aips-copy-btn" data-clipboard-text="{{section:<?php echo esc_attr($section->section_key); ?>}}" aria-label="<?php esc_attr_e('Copy placeholder', 'ai-post-scheduler'); ?>" title="<?php esc_attr_e('Copy placeholder', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
								</div>
							</td>
							<td class="column-description"><?php echo esc_html($section->description); ?></td>
							<td class="column-active">
								<?php if ($section->is_active) : ?>
									<span class="aips-badge aips-badge-success">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
									</span>
								<?php else : ?>
									<span class="aips-badge aips-badge-neutral">
										<span class="dashicons dashicons-minus"></span>
										<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<button class="aips-btn aips-btn-sm aips-btn-ghost aips-edit-section" data-id="<?php echo esc_attr($section->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-section" data-id="<?php echo esc_attr($section->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- No Search Results State -->
			<div id="aips-section-search-no-results" class="aips-empty-state" style="display: none;">
				<span class="dashicons dashicons-search" style="font-size: 64px; width: 64px; height: 64px;"></span>
				<h3><?php esc_html_e('No Sections Found', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('No prompt sections match your search criteria.', 'ai-post-scheduler'); ?></p>
				<button type="button" class="aips-btn aips-btn-primary aips-clear-section-search-btn">
					<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<?php else : ?>
			<!-- Empty State -->
			<div class="aips-empty-state">
				<span class="dashicons dashicons-editor-table" style="font-size: 64px; width: 64px; height: 64px;"></span>
				<h3><?php esc_html_e('No Prompt Sections', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create reusable prompt sections that can be inserted into your article structures using placeholders like {{section:key}}.', 'ai-post-scheduler'); ?></p>
				<button class="aips-btn aips-btn-primary aips-add-section-btn">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e('Create First Section', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Section Modal (kept as-is for JavaScript compatibility) --><div id="aips-section-modal" class="aips-modal" style="display: none;">
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

