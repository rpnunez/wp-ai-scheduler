<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <h1>
        <?php esc_html_e('Voices', 'ai-post-scheduler'); ?>
        <button class="page-title-action aips-add-voice-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
    </h1>
    
    <div class="aips-voices-container">
        <?php if (!empty($voices)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                    <th class="column-title-prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></th>
                    <th class="column-active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($voices as $voice): ?>
                <tr data-voice-id="<?php echo esc_attr($voice->id); ?>">
                    <td class="column-name">
                        <strong><?php echo esc_html($voice->name); ?></strong>
                    </td>
                    <td class="column-title-prompt">
                        <small><?php echo esc_html(substr($voice->title_prompt, 0, 60)) . (strlen($voice->title_prompt) > 60 ? '...' : ''); ?></small>
                    </td>
                    <td class="column-active">
                        <span class="aips-status aips-status-<?php echo $voice->is_active ? 'active' : 'inactive'; ?>">
                            <?php echo $voice->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?>
                        </span>
                    </td>
                    <td class="column-actions">
                        <button class="button aips-edit-voice" data-id="<?php echo esc_attr($voice->id); ?>">
                            <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                        </button>
                        <button class="button button-link-delete aips-delete-voice" data-id="<?php echo esc_attr($voice->id); ?>">
                            <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="aips-empty-state">
            <span class="dashicons dashicons-format-quote"></span>
            <h3><?php esc_html_e('No Voices Yet', 'ai-post-scheduler'); ?></h3>
            <p><?php esc_html_e('Create a voice to establish consistent tone and style for your generated posts.', 'ai-post-scheduler'); ?></p>
            <button class="button button-primary button-large aips-add-voice-btn">
                <?php esc_html_e('Create Voice', 'ai-post-scheduler'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="aips-voice-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content">
            <div class="aips-modal-header">
                <h2 id="aips-voice-modal-title"><?php esc_html_e('Add New Voice', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close">&times;</button>
            </div>
            <div class="aips-modal-body">
                <form id="aips-voice-form">
                    <input type="hidden" name="voice_id" id="voice_id" value="">
                    
                    <div class="aips-form-row">
                        <label for="voice_name"><?php esc_html_e('Voice Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <input type="text" id="voice_name" name="name" required class="regular-text" placeholder="e.g., Professional, Casual, Humorous">
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="voice_title_prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <textarea id="voice_title_prompt" name="title_prompt" rows="3" required class="large-text" placeholder="<?php esc_attr_e('e.g., Generate a compelling blog post title for the following topic. The title should be engaging and SEO-optimized. Return only the title, nothing else.', 'ai-post-scheduler'); ?>"></textarea>
                        <p class="description"><?php esc_html_e('Instructions for generating the post title.', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="voice_content_instructions"><?php esc_html_e('Content Instructions', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <textarea id="voice_content_instructions" name="content_instructions" rows="4" required class="large-text" placeholder="<?php esc_attr_e('e.g., Write in a professional tone. Include practical examples. Use short paragraphs. Add a compelling conclusion.', 'ai-post-scheduler'); ?>"></textarea>
                        <p class="description"><?php esc_html_e('These instructions will be prepended to the template prompt when generating content.', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label class="aips-checkbox-label">
                            <input type="checkbox" id="voice_is_active" name="is_active" value="1" checked>
                            <?php esc_html_e('Voice is active', 'ai-post-scheduler'); ?>
                        </label>
                    </div>
                </form>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
                <button type="button" class="button button-primary aips-save-voice"><?php esc_html_e('Save Voice', 'ai-post-scheduler'); ?></button>
            </div>
        </div>
    </div>
</div>
