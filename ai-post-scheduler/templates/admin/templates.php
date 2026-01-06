<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <h1>
        <?php esc_html_e('Post Templates', 'ai-post-scheduler'); ?>
        <button class="page-title-action aips-add-template-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
    </h1>
    
    <div class="aips-templates-container">
        <div class="aips-templates-list">
            <?php if (!empty($templates)): ?>
            <div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
                <label class="screen-reader-text" for="aips-template-search"><?php esc_html_e('Search Templates:', 'ai-post-scheduler'); ?></label>
                <input type="search" id="aips-template-search" class="regular-text" placeholder="<?php esc_attr_e('Search templates...', 'ai-post-scheduler'); ?>">
                <button type="button" id="aips-template-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                        <th class="column-status"><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></th>
                        <th class="column-category"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></th>
                        <th class="column-stats"><?php esc_html_e('Statistics', 'ai-post-scheduler'); ?></th>
                        <th class="column-active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = new AIPS_History();
                    $templates_class = new AIPS_Templates();

                    // Pre-fetch stats to avoid N+1 queries
                    $all_generated_counts = $history->get_all_template_stats();
                    $all_pending_stats = $templates_class->get_all_pending_stats();

                    foreach ($templates as $template):
                        $generated_count = isset($all_generated_counts[$template->id]) ? $all_generated_counts[$template->id] : 0;
                        $pending_stats = isset($all_pending_stats[$template->id]) ? $all_pending_stats[$template->id] : array('today' => 0, 'week' => 0, 'month' => 0);
                    ?>
                    <tr data-template-id="<?php echo esc_attr($template->id); ?>">
                        <td class="column-name">
                            <strong><?php echo esc_html($template->name); ?></strong>
                        </td>
                        <td class="column-status">
                            <?php echo esc_html(ucfirst($template->post_status)); ?>
                        </td>
                        <td class="column-category">
                            <?php 
                            if ($template->post_category) {
                                $cat = get_category($template->post_category);
                                echo esc_html($cat ? $cat->name : '-');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="column-stats">
                            <div>
                                <strong><?php esc_html_e('Generated:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($generated_count); ?>
                                <a href="#" class="aips-view-template-posts" data-id="<?php echo esc_attr($template->id); ?>" style="font-size: 0.9em;">
                                    <?php esc_html_e('(View Posts)', 'ai-post-scheduler'); ?>
                                </a>
                            </div>
                            <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                                <strong><?php esc_html_e('Pending:', 'ai-post-scheduler'); ?></strong><br>
                                <?php esc_html_e('Today:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['today']); ?><br>
                                <?php esc_html_e('Week:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['week']); ?><br>
                                <?php esc_html_e('Month:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['month']); ?>
                            </div>
                        </td>
                        <td class="column-active">
                            <span class="aips-status aips-status-<?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                                <?php echo $template->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <button class="button aips-edit-template" data-id="<?php echo esc_attr($template->id); ?>">
                                <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                            </button>
                            <button class="button aips-run-now" data-id="<?php echo esc_attr($template->id); ?>">
                                <?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
                            </button>
                            <button class="button button-link-delete aips-delete-template" data-id="<?php echo esc_attr($template->id); ?>">
                                <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="aips-template-search-no-results" class="aips-empty-state" style="display: none;">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <h3><?php esc_html_e('No Templates Found', 'ai-post-scheduler'); ?></h3>
                <p><?php esc_html_e('No templates match your search criteria.', 'ai-post-scheduler'); ?></p>
                <button type="button" class="button button-primary aips-clear-search-btn">
                    <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
                </button>
            </div>
            <?php else: ?>
            <div class="aips-empty-state">
                <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                <h3><?php esc_html_e('No Templates Yet', 'ai-post-scheduler'); ?></h3>
                <p><?php esc_html_e('Create your first template to start generating AI posts.', 'ai-post-scheduler'); ?></p>
                <button class="button button-primary button-large aips-add-template-btn">
                    <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="aips-template-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content">
            <div class="aips-modal-header">
                <h2 id="aips-modal-title"><?php esc_html_e('Add New Template', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            <div class="aips-modal-body">
                <form id="aips-template-form">
                    <input type="hidden" name="template_id" id="template_id" value="">
                    
                    <div class="aips-form-row">
                        <label for="template_name"><?php esc_html_e('Template Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <input type="text" id="template_name" name="name" required class="regular-text">
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="prompt_template"><?php esc_html_e('Content Prompt', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <textarea id="prompt_template" name="prompt_template" rows="6" required class="large-text" placeholder="<?php esc_attr_e('Write a detailed blog post about...', 'ai-post-scheduler'); ?>"></textarea>
                        <p class="description">
                            <?php esc_html_e('Available variables: {{date}}, {{year}}, {{month}}, {{day}}, {{time}}, {{site_name}}, {{site_description}}, {{random_number}}', 'ai-post-scheduler'); ?>
                        </p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="title_prompt"><?php esc_html_e('Title Prompt (Optional)', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="title_prompt" name="title_prompt" class="regular-text" placeholder="<?php esc_attr_e('Leave empty to auto-generate from content prompt', 'ai-post-scheduler'); ?>">
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="voice_id"><?php esc_html_e('Voice', 'ai-post-scheduler'); ?></label>
                        <div class="aips-voice-selector">
                            <input type="text" id="voice_search" class="regular-text" placeholder="<?php esc_attr_e('Search voices...', 'ai-post-scheduler'); ?>" style="margin-bottom: 8px;">
                            <select id="voice_id" name="voice_id" class="regular-text">
                                <option value="0"><?php esc_html_e('No Voice (Use Default)', 'ai-post-scheduler'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Optional. A voice provides pre-configured title and content instructions.', 'ai-post-scheduler'); ?></p>
                        </div>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="post_quantity"><?php esc_html_e('Number of Posts to Generate', 'ai-post-scheduler'); ?></label>
                        <input type="number" id="post_quantity" name="post_quantity" min="1" max="20" value="1" class="small-text">
                        <p class="description"><?php esc_html_e('Generate 1-20 posts when running this template. Useful for batch generation.', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label class="aips-checkbox-label">
                            <input type="checkbox" id="generate_featured_image" name="generate_featured_image" value="1">
                            <?php esc_html_e('Generate Featured Image?', 'ai-post-scheduler'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('If checked, a featured image will be attached to the generated post.', 'ai-post-scheduler'); ?></p>
                    </div>

                    <div class="aips-featured-image-settings" style="display: none;">
                        <div class="aips-form-row">
                            <label for="featured_image_source"><?php esc_html_e('Featured Image Source', 'ai-post-scheduler'); ?></label>
                            <select id="featured_image_source" name="featured_image_source">
                                <option value="ai_prompt"><?php esc_html_e('Generate with AI Prompt', 'ai-post-scheduler'); ?></option>
                                <option value="unsplash"><?php esc_html_e('Unsplash (keywords)', 'ai-post-scheduler'); ?></option>
                                <option value="media_library"><?php esc_html_e('Media Library (select images)', 'ai-post-scheduler'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose how the featured image should be sourced for this template.', 'ai-post-scheduler'); ?></p>
                        </div>

                        <div class="aips-form-row aips-image-source aips-image-source-ai">
                            <label for="image_prompt"><?php esc_html_e('Image Prompt', 'ai-post-scheduler'); ?></label>
                            <textarea id="image_prompt" name="image_prompt" rows="3" class="large-text" placeholder="<?php esc_attr_e('Describe the image you want generated...', 'ai-post-scheduler'); ?>"></textarea>
                            <p class="description"><?php esc_html_e('Used when generating the image with AI.', 'ai-post-scheduler'); ?></p>
                        </div>

                        <div class="aips-form-row aips-image-source aips-image-source-unsplash" style="display: none;">
                            <label for="featured_image_unsplash_keywords"><?php esc_html_e('Unsplash Keywords', 'ai-post-scheduler'); ?></label>
                            <input type="text" id="featured_image_unsplash_keywords" name="featured_image_unsplash_keywords" class="regular-text" placeholder="<?php esc_attr_e('e.g. sunrise, mountains, drone view', 'ai-post-scheduler'); ?>">
                            <p class="description"><?php esc_html_e('Unsplash will return a random image that matches these keywords.', 'ai-post-scheduler'); ?></p>
                        </div>

                        <div class="aips-form-row aips-image-source aips-image-source-media" style="display: none;">
                            <label><?php esc_html_e('Media Library Images', 'ai-post-scheduler'); ?></label>
                            <div class="aips-media-library-picker">
                                <input type="hidden" id="featured_image_media_ids" name="featured_image_media_ids" value="">
                                <button type="button" class="button" id="featured_image_media_select"><?php esc_html_e('Select Images', 'ai-post-scheduler'); ?></button>
                                <button type="button" class="button-link" id="featured_image_media_clear"><?php esc_html_e('Clear Selection', 'ai-post-scheduler'); ?></button>
                                <div id="featured_image_media_preview" class="description" style="margin-top: 6px;"><?php esc_html_e('No images selected.', 'ai-post-scheduler'); ?></div>
                            </div>
                            <p class="description"><?php esc_html_e('One image will be chosen at random from the selected media library items.', 'ai-post-scheduler'); ?></p>
                        </div>
                    </div>
                    
                    <div class="aips-form-columns">
                        <div class="aips-form-row">
                            <label for="post_status"><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></label>
                            <select id="post_status" name="post_status">
                                <option value="draft"><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
                                <option value="pending"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
                                <option value="publish"><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
                            </select>
                        </div>
                        
                        <div class="aips-form-row">
                            <label for="post_category"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></label>
                            <select id="post_category" name="post_category">
                                <option value="0"><?php esc_html_e('Select Category', 'ai-post-scheduler'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="post_tags"><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="post_tags" name="post_tags" class="regular-text" placeholder="<?php esc_attr_e('tag1, tag2, tag3', 'ai-post-scheduler'); ?>">
                        <p class="description"><?php esc_html_e('Comma-separated list of tags', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="post_author"><?php esc_html_e('Author', 'ai-post-scheduler'); ?></label>
                        <select id="post_author" name="post_author">
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user->ID, get_current_user_id()); ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="aips-form-row">
                        <label class="aips-checkbox-label">
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <?php esc_html_e('Template is active', 'ai-post-scheduler'); ?>
                        </label>
                    </div>
                </form>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-test-template"><?php esc_html_e('Test Generate', 'ai-post-scheduler'); ?></button>
                <button type="button" class="button aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
                <button type="button" class="button button-primary aips-save-template"><?php esc_html_e('Save Template', 'ai-post-scheduler'); ?></button>
            </div>
        </div>
    </div>
    
    <div id="aips-test-result-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content aips-modal-large">
            <div class="aips-modal-header">
                <h2><?php esc_html_e('Test Generation Result', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            <div class="aips-modal-body">
                <div id="aips-test-content"></div>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
            </div>
        </div>
    </div>

    <div id="aips-template-posts-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content aips-modal-large">
            <div class="aips-modal-header">
                <h2><?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            <div class="aips-modal-body">
                <div id="aips-template-posts-content">
                    <p class="aips-loading"><?php esc_html_e('Loading...', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
            </div>
        </div>
    </div>
</div>
