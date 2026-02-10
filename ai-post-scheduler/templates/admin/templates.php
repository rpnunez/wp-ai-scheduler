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
                    <h1 class="aips-page-title"><?php esc_html_e('Post Templates', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Create and manage AI post generation templates with custom prompts and settings.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <button class="aips-btn aips-btn-primary aips-add-template-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Template', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (!empty($templates)): ?>
        <!-- Content Panel with Filter Bar -->
        <div class="aips-content-panel">
            <!-- Filter Bar -->
            <div class="aips-filter-bar">
                <label class="screen-reader-text" for="aips-template-search"><?php esc_html_e('Search Templates:', 'ai-post-scheduler'); ?></label>
                <input type="search" id="aips-template-search" class="aips-form-input" style="max-width: 300px;" placeholder="<?php esc_attr_e('Search templates...', 'ai-post-scheduler'); ?>">
                <button type="button" id="aips-template-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
            </div>
            
            <!-- Templates Table -->
            <div class="aips-panel-body no-padding">
                <table class="aips-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Template Name', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Category', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Statistics', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history_service = new AIPS_History();
                        $templates_class = new AIPS_Templates();

                        // Pre-fetch stats to avoid N+1 queries
                        $all_generated_counts = $history_service->get_all_template_stats();
                        $all_pending_stats = $templates_class->get_all_pending_stats();

                        foreach ($templates as $template):
                            $generated_count = isset($all_generated_counts[$template->id]) ? $all_generated_counts[$template->id] : 0;
                            $pending_stats = isset($all_pending_stats[$template->id]) ? $all_pending_stats[$template->id] : array('today' => 0, 'week' => 0, 'month' => 0);
                        ?>
                        <tr data-template-id="<?php echo esc_attr($template->id); ?>">
                            <td>
                                <div class="cell-primary"><?php echo esc_html($template->name); ?></div>
                            </td>
                            <td>
                                <span class="aips-badge aips-badge-neutral">
                                    <?php echo esc_html(ucfirst($template->post_status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($template->post_category) {
                                    $cat = get_category($template->post_category);
                                    echo esc_html($cat ? $cat->name : '-');
                                } else {
                                    echo '<span class="cell-meta">—</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div>
                                        <strong style="font-size: 14px;"><?php echo esc_html($generated_count); ?></strong>
                                        <span class="cell-meta"><?php esc_html_e('generated', 'ai-post-scheduler'); ?></span>
                                        <a href="#" class="aips-view-template-posts" data-id="<?php echo esc_attr($template->id); ?>" style="font-size: 12px; margin-left: 4px;">
                                            <?php esc_html_e('(view)', 'ai-post-scheduler'); ?>
                                        </a>
                                    </div>
                                    <div class="cell-meta" style="font-size: 11px;">
                                        <?php esc_html_e('Pending:', 'ai-post-scheduler'); ?>
                                        <?php esc_html_e('Today:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['today']); ?> |
                                        <?php esc_html_e('Week:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['week']); ?> |
                                        <?php esc_html_e('Month:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['month']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($template->is_active): ?>
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
                            <td>
                                <div class="cell-actions">
                                    <button class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                                    </button>
                                    <button class="aips-btn aips-btn-sm aips-btn-secondary aips-run-now" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Run Now', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-controls-play"></span>
                                        <?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
                                    </button>
                                    <button class="aips-btn aips-btn-sm aips-btn-ghost aips-clone-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Clone', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Clone', 'ai-post-scheduler'); ?></span>
                                    </button>
                                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- No Search Results State -->
                <div id="aips-template-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
                    <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Templates Found', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('No templates match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <button type="button" class="aips-btn aips-btn-primary aips-clear-search-btn">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="aips-content-panel">
            <div class="aips-panel-body">
                <div class="aips-empty-state">
                    <div class="dashicons dashicons-media-document aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Templates Yet', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('Templates define how your AI-generated posts are structured. Create your first template to start generating content automatically.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <button class="aips-btn aips-btn-primary aips-add-template-btn">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Keep the original modal markup below (not redesigned yet) -->
    <div id="aips-template-modal" class="aips-modal aips-wizard-modal" style="display: none;">
        <div class="aips-modal-content aips-modal-large">
            <div class="aips-modal-header">
                <h2 id="aips-modal-title"><?php esc_html_e('Add New Template', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            
            <!-- Wizard Progress Indicator -->
            <div class="aips-wizard-progress">
                <div class="aips-wizard-step" data-step="1">
                    <div class="aips-step-number">1</div>
                    <div class="aips-step-label"><?php esc_html_e('Basic Info', 'ai-post-scheduler'); ?></div>
                </div>
                <div class="aips-wizard-step" data-step="2">
                    <div class="aips-step-number">2</div>
                    <div class="aips-step-label"><?php esc_html_e('Title & Excerpt', 'ai-post-scheduler'); ?></div>
                </div>
                <div class="aips-wizard-step" data-step="3">
                    <div class="aips-step-number">3</div>
                    <div class="aips-step-label"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></div>
                </div>
                <div class="aips-wizard-step" data-step="4">
                    <div class="aips-step-number">4</div>
                    <div class="aips-step-label"><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></div>
                </div>
                <div class="aips-wizard-step" data-step="5">
                    <div class="aips-step-number">5</div>
                    <div class="aips-step-label"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></div>
                </div>
            </div>
            
            <div class="aips-modal-body">
                <form id="aips-template-form">
                    <input type="hidden" name="template_id" id="template_id" value="">
                    
                    <!-- Step 1: Basic Info (Name + Description) -->
                    <div class="aips-wizard-step-content" data-step="1">
                        <h3>
                            <?php esc_html_e('Basic Information', 'ai-post-scheduler'); ?>
                            <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Give your template a unique name and optional description to help organize your templates.', 'ai-post-scheduler'); ?>"></span>
                        </h3>
                        <p class="description"><?php esc_html_e('Give your template a name and brief description to help you identify it later.', 'ai-post-scheduler'); ?></p>
                        
                        <div class="aips-form-row">
                            <label for="template_name">
                                <?php esc_html_e('Template Name', 'ai-post-scheduler'); ?> <span class="required">*</span>
                                <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Enter a unique, descriptive name for this template. This helps you identify it when creating schedules.', 'ai-post-scheduler'); ?>"></span>
                            </label>
                            <input type="text" id="template_name" name="name" required class="regular-text" placeholder="<?php esc_attr_e('e.g., Tech News Blog Post', 'ai-post-scheduler'); ?>">
                            <p class="description"><?php esc_html_e('A descriptive name for your template', 'ai-post-scheduler'); ?></p>
                        </div>
                        
                        <div class="aips-form-row">
                            <label for="template_description">
                                <?php esc_html_e('Template Description', 'ai-post-scheduler'); ?>
                                <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Optional notes about what this template is used for, target audience, or any special instructions.', 'ai-post-scheduler'); ?>"></span>
                            </label>
                            <textarea id="template_description" name="description" rows="4" class="large-text" placeholder="<?php esc_attr_e('Optional: Describe what this template is used for...', 'ai-post-scheduler'); ?>"></textarea>
                            <p class="description"><?php esc_html_e('Optional. Helps you remember the purpose of this template.', 'ai-post-scheduler'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Step 2: Title & Excerpt -->
                    <div class="aips-wizard-step-content" data-step="2" style="display: none;">
                        <h3>
                            <?php esc_html_e('Title & Excerpt Settings', 'ai-post-scheduler'); ?>
                            <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Configure how AI generates titles and excerpts for your posts. Leave blank to auto-generate from content.', 'ai-post-scheduler'); ?>"></span>
                        </h3>
                        <p class="description"><?php esc_html_e('Configure how the AI generates titles and excerpts for your posts.', 'ai-post-scheduler'); ?></p>
                        
                        <div class="aips-form-row">
                            <label for="title_prompt">
                                <?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?>
                                <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Optional. Instruct the AI how to generate titles. Leave empty to auto-generate based on content. Supports AI variables like {{Framework1}}.', 'ai-post-scheduler'); ?>"></span>
                            </label>
                            <input type="text" id="title_prompt" name="title_prompt" class="regular-text aips-ai-var-input" placeholder="<?php esc_attr_e('Leave empty to auto-generate from content prompt', 'ai-post-scheduler'); ?>">
                            <p class="description">
                                <?php esc_html_e('Supports AI Variables: Use custom variables like {{PHPFramework1Name}} that AI will dynamically resolve based on your content. Example: "PHP Framework Comparison: {{Framework1}} vs. {{Framework2}}"', 'ai-post-scheduler'); ?>
                            </p>
                        </div>

                        <!-- AI Variables Panel -->
                        <div class="aips-form-row aips-ai-variables-panel" style="display: none;">
                            <div class="aips-ai-variables-header">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <strong><?php esc_html_e('AI Variables Detected', 'ai-post-scheduler'); ?></strong>
                                <span class="aips-ai-variables-hint"><?php esc_html_e('(Click to copy)', 'ai-post-scheduler'); ?></span>
                            </div>
                            <div class="aips-ai-variables-list" id="aips-ai-variables-list">
                                <!-- AI Variables will be rendered here by JavaScript -->
                            </div>
                            <div class="aips-ai-variables-info">
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php esc_html_e('These variables will be dynamically resolved by AI based on your generated content. Each post generation may produce different values.', 'ai-post-scheduler'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="aips-form-row aips-ai-variables-instructions">
                            <details class="aips-collapsible">
                                <summary>
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <?php esc_html_e('How to use AI Variables', 'ai-post-scheduler'); ?>
                                </summary>
                                <div class="aips-collapsible-content">
                                    <p><?php esc_html_e('AI Variables allow you to create dynamic, context-aware titles. The AI will automatically fill in values based on the content it generates.', 'ai-post-scheduler'); ?></p>
                                    <h4><?php esc_html_e('Examples:', 'ai-post-scheduler'); ?></h4>
                                    <ul>
                                        <li><code>{{Framework1}} vs {{Framework2}}</code> → <em>"Laravel vs Symfony"</em></li>
                                        <li><code>Top {{Number}} {{Topic}} Tips</code> → <em>"Top 10 SEO Tips"</em></li>
                                        <li><code>{{ProductName}} Review: Is it worth it?</code> → <em>"iPhone 15 Pro Review: Is it worth it?"</em></li>
                                    </ul>
                                    <h4><?php esc_html_e('Tips:', 'ai-post-scheduler'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('Use descriptive variable names (e.g., {{PHPFramework}} instead of {{X}})', 'ai-post-scheduler'); ?></li>
                                        <li><?php esc_html_e('AI Variables work best with comparison or list-based content prompts', 'ai-post-scheduler'); ?></li>
                                        <li><?php esc_html_e('System variables like {{date}}, {{site_name}} are NOT AI Variables', 'ai-post-scheduler'); ?></li>
                                    </ul>
                                </div>
                            </details>
                        </div>
                    </div>
                    
                    <!-- Step 3: Content -->
                    <div class="aips-wizard-step-content" data-step="3" style="display: none;">
                        <h3>
                            <?php esc_html_e('Content Settings', 'ai-post-scheduler'); ?>
                            <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Define the main content prompt that guides AI to generate your blog post content.', 'ai-post-scheduler'); ?>"></span>
                        </h3>
                        <p class="description"><?php esc_html_e('Define the content prompt that guides AI to generate your post content.', 'ai-post-scheduler'); ?></p>
                        
                        <div class="aips-form-row">
                            <label for="prompt_template">
                                <?php esc_html_e('Content Prompt', 'ai-post-scheduler'); ?> <span class="required">*</span>
                                <span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Required. Detailed instructions for the AI about what content to generate. Be specific about topic, style, length, and target audience.', 'ai-post-scheduler'); ?>"></span>
                            </label>
                            <textarea id="prompt_template" name="prompt_template" rows="8" required class="large-text" placeholder="<?php esc_attr_e('Write a detailed blog post about...', 'ai-post-scheduler'); ?>"></textarea>
                            <p class="description">
                                <?php esc_html_e('Available variables: {{date}}, {{year}}, {{month}}, {{day}}, {{time}}, {{site_name}}, {{site_description}}, {{random_number}}', 'ai-post-scheduler'); ?>
                            </p>
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
                    </div>
                    
                    <!-- Step 4: Featured Image -->
                    <div class="aips-wizard-step-content" data-step="4" style="display: none;">
                        <h3><?php esc_html_e('Featured Image Options', 'ai-post-scheduler'); ?></h3>
                        <p class="description"><?php esc_html_e('Configure whether and how to generate featured images for your posts.', 'ai-post-scheduler'); ?></p>
                        
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
                    </div>
                    
                    <!-- Step 5: Summary & Post Settings -->
                    <div class="aips-wizard-step-content" data-step="5" style="display: none;">
                        <h3><?php esc_html_e('Review & Post Settings', 'ai-post-scheduler'); ?></h3>
                        <p class="description"><?php esc_html_e('Review your template configuration and set post publishing options.', 'ai-post-scheduler'); ?></p>
                        
                        <!-- Summary Display -->
                        <div class="aips-template-summary">
                            <h4><?php esc_html_e('Template Summary', 'ai-post-scheduler'); ?></h4>
                            <div class="aips-summary-grid">
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Template Name:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_name">-</span>
                                </div>
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Description:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_description">-</span>
                                </div>
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Title Prompt:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_title_prompt"><?php esc_html_e('Auto-generate from content', 'ai-post-scheduler'); ?></span>
                                </div>
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Content Prompt:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_content_prompt">-</span>
                                </div>
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Voice:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_voice"><?php esc_html_e('None', 'ai-post-scheduler'); ?></span>
                                </div>
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Post Quantity:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_quantity">1</span>
                                </div>
                                <div class="aips-summary-item">
                                    <strong><?php esc_html_e('Featured Image:', 'ai-post-scheduler'); ?></strong>
                                    <span id="summary_featured_image"><?php esc_html_e('No', 'ai-post-scheduler'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Post Settings -->
                        <h4 style="margin-top: 20px;"><?php esc_html_e('Post Settings', 'ai-post-scheduler'); ?></h4>
                        
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
                    </div>
                </form>
            </div>
            <div class="aips-modal-footer aips-wizard-footer">
                <div class="aips-footer-left">
                    <button type="button" class="button aips-wizard-back" style="display: none;">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php esc_html_e('Back', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-footer-center">
                    <button type="button" class="button aips-save-draft-template" title="<?php esc_attr_e('Save current progress as inactive template', 'ai-post-scheduler'); ?>">
                        <span class="dashicons dashicons-cloud-saved"></span>
                        <?php esc_html_e('Save Draft', 'ai-post-scheduler'); ?>
                    </button>
                    <button type="button" class="button aips-preview-prompts" title="<?php esc_attr_e('Preview the prompts that will be sent to AI', 'ai-post-scheduler'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Preview Prompts', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-footer-right">
                    <button type="button" class="button aips-modal-close">
                        <?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
                    </button>
                    <button type="button" class="button button-primary aips-wizard-next">
                        <?php esc_html_e('Next', 'ai-post-scheduler'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                    <button type="button" class="button button-primary aips-save-template" style="display: none;">
                        <?php esc_html_e('Save Template', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Preview Drawer -->
            <div class="aips-preview-drawer" id="aips-preview-drawer">
                <div class="aips-preview-drawer-toggle">
                    <button type="button" class="aips-preview-drawer-handle" aria-label="<?php esc_attr_e('Toggle preview drawer', 'ai-post-scheduler'); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <span class="aips-preview-drawer-label"><?php esc_html_e('Prompt Preview', 'ai-post-scheduler'); ?></span>
                    </button>
                </div>
                <div class="aips-preview-drawer-content" style="display: none;">
                    <div class="aips-preview-loading" style="display: none;">
                        <span class="spinner is-active"></span>
                        <span><?php esc_html_e('Generating preview...', 'ai-post-scheduler'); ?></span>
                    </div>
                    <div class="aips-preview-error" style="display: none;"></div>
                    <div class="aips-preview-sections" style="display: none;">
                        <div class="aips-preview-metadata">
                            <div class="aips-preview-meta-item" id="aips-preview-voice" style="display: none;">
                                <strong><?php esc_html_e('Voice:', 'ai-post-scheduler'); ?></strong>
                                <span class="aips-preview-voice-name"></span>
                            </div>
                            <div class="aips-preview-meta-item" id="aips-preview-structure" style="display: none;">
                                <strong><?php esc_html_e('Article Structure:', 'ai-post-scheduler'); ?></strong>
                                <span class="aips-preview-structure-name"></span>
                            </div>
                            <div class="aips-preview-meta-item">
                                <strong><?php esc_html_e('Sample Topic:', 'ai-post-scheduler'); ?></strong>
                                <span class="aips-preview-sample-topic"></span>
                            </div>
                        </div>
                        
                        <div class="aips-preview-section">
                            <h4><?php esc_html_e('Content Prompt', 'ai-post-scheduler'); ?></h4>
                            <div class="aips-preview-prompt-text" id="aips-preview-content-prompt"></div>
                        </div>
                        
                        <div class="aips-preview-section">
                            <h4><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></h4>
                            <div class="aips-preview-prompt-text" id="aips-preview-title-prompt"></div>
                        </div>
                        
                        <div class="aips-preview-section">
                            <h4><?php esc_html_e('Excerpt Prompt', 'ai-post-scheduler'); ?></h4>
                            <div class="aips-preview-prompt-text" id="aips-preview-excerpt-prompt"></div>
                        </div>
                        
                        <div class="aips-preview-section" id="aips-preview-image-section" style="display: none;">
                            <h4><?php esc_html_e('Image Prompt', 'ai-post-scheduler'); ?></h4>
                            <div class="aips-preview-prompt-text" id="aips-preview-image-prompt"></div>
                        </div>
                    </div>
                </div>
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

    <div id="aips-post-success-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content">
            <div class="aips-modal-header">
                <h2><?php esc_html_e('Post Successfully Generated', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            <div class="aips-modal-body">
                <div style="text-align: center; padding: 20px;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #46b450; width: 48px; height: 48px;"></span>
                    <p style="font-size: 16px; margin-top: 20px;" id="aips-success-message"><?php esc_html_e('Your post has been successfully generated!', 'ai-post-scheduler'); ?></p>
                    <div id="aips-post-link-container" style="margin-top: 20px;">
                        <strong><?php esc_html_e('Link to Post:', 'ai-post-scheduler'); ?></strong><br>
                        <a href="#" id="aips-post-link" target="_blank" class="button button-primary" style="margin-top: 10px;"><?php esc_html_e('View Post', 'ai-post-scheduler'); ?></a>
                    </div>
                </div>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
            </div>
        </div>
    </div>
</div>
