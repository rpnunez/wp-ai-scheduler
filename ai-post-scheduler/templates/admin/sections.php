<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!isset($sections) || !is_array($sections)) {
	$sections = array();
}
?>
<div class="wrap aips-wrap">
	<h1>
		<?php esc_html_e('Prompt Sections', 'ai-post-scheduler'); ?>
		<button class="page-title-action aips-add-section-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
	</h1>

	<div class="aips-structures-container">
		<?php if (!empty($sections)) : ?>
		<div class="aips-search-box">
			<label class="screen-reader-text" for="aips-section-search"><?php esc_html_e('Search Sections:', 'ai-post-scheduler'); ?></label>
			<input type="search" id="aips-section-search" class="regular-text" placeholder="<?php esc_attr_e('Search sections...', 'ai-post-scheduler'); ?>">
			<button type="button" id="aips-section-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
		</div>

		<table class="wp-list-table widefat fixed striped aips-sections-table">
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
				<?php foreach ($sections as $section) : ?>
				<tr data-section-id="<?php echo esc_attr($section->id); ?>">
					<td class="column-name"><?php echo esc_html($section->name); ?></td>
					<td class="column-key">
						<code><?php echo esc_html($section->section_key); ?></code>
					</td>
					<td class="column-description"><?php echo esc_html($section->description); ?></td>
					<td><?php echo $section->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?></td>
					<td>
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
