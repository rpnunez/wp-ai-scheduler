<?php
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $sections is defined for static analysis and for direct includes
if (!isset($sections) || !is_array($sections)) {
    $sections = array();
}
?>
<div class="wrap aips-wrap">
    <h1>
        <?php esc_html_e('Article Structures', 'ai-post-scheduler'); ?>
        <button class="page-title-action aips-add-structure-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
    </h1>

    <div class="aips-structures-container">
        <?php if (!empty($structures)): ?>
        <table class="wp-list-table widefat fixed striped">
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
                <?php foreach ($structures as $structure): ?>
                <tr data-structure-id="<?php echo esc_attr($structure->id); ?>">
                    <td><?php echo esc_html($structure->name); ?></td>
                    <td><?php echo esc_html($structure->description); ?></td>
                    <td><?php echo esc_html( $structure->is_active ? __('Yes', 'ai-post-scheduler') : __('No', 'ai-post-scheduler') ); ?></td>
                    <td><?php echo esc_html( $structure->is_default ? __('Yes', 'ai-post-scheduler') : __('No', 'ai-post-scheduler') ); ?></td>
                    <td>
                        <button class="button aips-edit-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
                        <button class="button button-link-delete aips-delete-structure" data-id="<?php echo esc_attr($structure->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="aips-empty-state">
            <span class="dashicons dashicons-layout" aria-hidden="true"></span>
            <h3><?php esc_html_e('No Article Structures', 'ai-post-scheduler'); ?></h3>
            <p><?php esc_html_e('Create article structures to customize how templates assemble content.', 'ai-post-scheduler'); ?></p>
            <button class="button button-primary aips-add-structure-btn"><?php esc_html_e('Create Structure', 'ai-post-scheduler'); ?></button>
        </div>
        <?php endif; ?>
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
                            <option value="<?php echo esc_attr($section->key); ?>"><?php echo esc_html($section->label); ?></option>
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
</div>

