<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Developer Tools', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Generate test data and template scaffolds using AI to quickly prototype and test your workflow.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <!-- Content Panel -->
        <div class="aips-content-panel">
            <div class="aips-panel-body">
                <form id="aips-dev-scaffold-form">
                    <div class="aips-form-section">
                        <h3 class="aips-form-section-title">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Generate Template Scaffold', 'ai-post-scheduler'); ?>
                        </h3>
                        <p class="aips-field-description" style="margin-bottom: 20px;">
                            <?php esc_html_e('Create a complete template setup (Voice, Structure, Template) based on a topic.', 'ai-post-scheduler'); ?>
                        </p>

                        <div class="aips-form-row">
                            <label for="topic"><?php esc_html_e('Topic / Niche', 'ai-post-scheduler'); ?></label>
                            <input type="text" id="topic" name="topic" class="aips-form-input" placeholder="<?php esc_attr_e('e.g. Urban Gardening, SaaS Marketing', 'ai-post-scheduler'); ?>" required>
                            <p class="aips-field-description"><?php esc_html_e('The main topic to base the prompts and structure on.', 'ai-post-scheduler'); ?></p>
                        </div>

                        <div class="aips-form-row">
                            <label><?php esc_html_e('Options', 'ai-post-scheduler'); ?></label>
                            <div class="aips-checkbox-group">
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_voice" name="include_voice" value="true">
                                    <?php esc_html_e('Generate Voice/Persona', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_structure" name="include_structure" value="true">
                                    <?php esc_html_e('Generate Article Structure', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_title_prompt" name="include_title_prompt" value="true" checked>
                                    <?php esc_html_e('Include Title Prompt', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_content_prompt" name="include_content_prompt" value="true" checked>
                                    <?php esc_html_e('Include Content Prompt', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_image_prompt" name="include_image_prompt" value="true" checked>
                                    <?php esc_html_e('Include Image Prompt', 'ai-post-scheduler'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="aips-form-actions">
                            <button type="submit" id="aips-dev-scaffold-submit" class="aips-btn aips-btn-primary aips-btn-lg">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php esc_html_e('Generate Scaffold', 'ai-post-scheduler'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin-top: 4px;"></span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Panel -->
        <div id="aips-dev-scaffold-results" class="aips-content-panel" style="margin-top: 20px; display: none;">
            <div class="aips-panel-header">
                <h3><?php esc_html_e('Generated Scaffold', 'ai-post-scheduler'); ?></h3>
            </div>
            <div class="aips-panel-body">
                <div id="aips-dev-scaffold-log" style="background: #f0f0f1; padding: 15px; border: 1px solid #c3c4c7; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.6;"></div>
            </div>
        </div>
    </div>
</div>
