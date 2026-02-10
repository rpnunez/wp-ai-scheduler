<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap aips-redesign">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Voices', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description">
                        <?php esc_html_e('Define consistent tone and style templates for AI-generated content.', 'ai-post-scheduler'); ?>
                    </p>
                </div>
                <div class="aips-page-actions">
                    <button class="aips-btn aips-btn-primary aips-add-voice-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e('Add Voice', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Content Panel -->
        <div class="aips-content-panel">
            <div class="aips-voices-container">
                <?php if (!empty($voices)): ?>
                <!-- Filter Bar -->
                <div class="aips-filter-bar">
                    <div class="aips-filter-left">
                        <span class="aips-result-count"><?php printf(esc_html__('%d voices', 'ai-post-scheduler'), count($voices)); ?></span>
                    </div>
                    <div class="aips-filter-right">
                        <label class="screen-reader-text" for="aips-voice-search"><?php esc_html_e('Search Voices:', 'ai-post-scheduler'); ?></label>
                        <input type="search" id="aips-voice-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search voices...', 'ai-post-scheduler'); ?>">
                        <button type="button" id="aips-voice-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                    </div>
                </div>

                <!-- Table -->
                <div class="aips-panel-body no-padding">
                    <table class="aips-table aips-voices-list">
                        <thead>
                            <tr>
                                <th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                                <th class="column-title-prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></th>
                                <th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($voices as $voice): ?>
                            <tr data-voice-id="<?php echo esc_attr($voice->id); ?>">
                                <td class="column-name">
                                    <div class="aips-table-primary">
                                        <strong><?php echo esc_html($voice->name); ?></strong>
                                    </div>
                                </td>
                                <td class="column-title-prompt">
                                    <div class="aips-table-meta">
                                        <?php echo esc_html(substr($voice->title_prompt, 0, 80)) . (strlen($voice->title_prompt) > 80 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td class="column-status">
                                    <?php if ($voice->is_active): ?>
                                        <span class="aips-badge aips-badge-success">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Active', 'ai-post-scheduler'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="aips-badge aips-badge-neutral">
                                            <span class="dashicons dashicons-minus"></span>
                                            <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <div class="aips-action-buttons">
                                        <button class="aips-btn aips-btn-sm aips-edit-voice" data-id="<?php echo esc_attr($voice->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-voice" data-id="<?php echo esc_attr($voice->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- No Search Results -->
                <div id="aips-voice-search-no-results" class="aips-empty-state" style="display: none;">
                    <div class="aips-empty-icon">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <h3 class="aips-empty-title"><?php esc_html_e('No Voices Found', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-description"><?php esc_html_e('No voices match your search criteria.', 'ai-post-scheduler'); ?></p>
                    <button type="button" class="aips-btn aips-btn-primary aips-clear-voice-search-btn">
                        <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
                    </button>
                </div>

                <?php else: ?>
                <!-- Empty State -->
                <div class="aips-empty-state">
                    <div class="aips-empty-icon">
                        <span class="dashicons dashicons-format-quote"></span>
                    </div>
                    <h3 class="aips-empty-title"><?php esc_html_e('No Voices Yet', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-description"><?php esc_html_e('Create a voice to establish consistent tone and style for your generated posts.', 'ai-post-scheduler'); ?></p>
                    <button class="aips-btn aips-btn-primary aips-btn-lg aips-add-voice-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e('Create Voice', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="aips-voice-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content">
            <div class="aips-modal-header">
                <h2 id="aips-voice-modal-title"><?php esc_html_e('Add New Voice', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
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
                        <label for="voice_excerpt_instructions"><?php esc_html_e('Excerpt Instructions (Optional)', 'ai-post-scheduler'); ?></label>
                        <textarea id="voice_excerpt_instructions" name="excerpt_instructions" rows="3" class="large-text" placeholder="<?php esc_attr_e('e.g., Write a compelling summary. Use an engaging tone. Keep the excerpt concise.', 'ai-post-scheduler'); ?>"></textarea>
                        <p class="description"><?php esc_html_e('Optional. These instructions will influence excerpt generation for posts using this voice.', 'ai-post-scheduler'); ?></p>
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
