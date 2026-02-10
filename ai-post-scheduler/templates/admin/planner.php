<?php
$default_planner_frequency = 'daily';
?>
<div class="aips-planner-container">
    <!-- Topic Brainstorming Card -->
    <div class="aips-content-panel">
        <div class="aips-panel-header">
            <div class="aips-panel-header-content">
                <span class="dashicons dashicons-lightbulb" style="font-size: 20px; width: 20px; height: 20px; margin-right: 8px;"></span>
                <div>
                    <h3 class="aips-panel-title"><?php echo esc_html__('Topic Brainstorming', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-panel-description"><?php echo esc_html__('Generate article ideas based on a niche, or paste your own list of topics.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>
        <div class="aips-panel-body">
            <div class="aips-form-grid" style="grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="aips-form-field">
                    <label for="planner-niche" class="aips-form-label"><?php echo esc_html__('Niche / Topic', 'ai-post-scheduler'); ?></label>
                    <input type="text" id="planner-niche" class="aips-form-input" placeholder="<?php echo esc_attr__('e.g. React.js Tutorials, Healthy Keto Recipes...', 'ai-post-scheduler'); ?>">
                </div>

                <div class="aips-form-field">
                    <label for="planner-count" class="aips-form-label"><?php echo esc_html__('Number of Topics', 'ai-post-scheduler'); ?></label>
                    <input type="number" id="planner-count" class="aips-form-input" value="10" min="1" max="50" style="width: 100px;">
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <button type="button" id="btn-generate-topics" class="aips-btn aips-btn-primary">
                    <span class="dashicons dashicons-update" style="margin-right: 4px;"></span>
                    <?php echo esc_html__('Generate Topics', 'ai-post-scheduler'); ?>
                </button>
                <span class="spinner"></span>
            </div>

            <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--aips-border-color, #ddd);">

            <div class="aips-form-field">
                <label for="planner-manual-topics" class="aips-form-label"><?php echo esc_html__('Or Paste Topics (One per line)', 'ai-post-scheduler'); ?></label>
                <textarea id="planner-manual-topics" class="aips-form-input" rows="5" placeholder="<?php echo esc_attr__('Topic 1&#10;Topic 2&#10;Topic 3', 'ai-post-scheduler'); ?>"></textarea>
                <button type="button" id="btn-parse-manual" class="aips-btn aips-btn-secondary" style="margin-top: 10px;">
                    <?php echo esc_html__('Add to List', 'ai-post-scheduler'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Review & Schedule Card -->
    <div id="planner-results" class="aips-content-panel" style="display:none; margin-top: 20px;">
        <div class="aips-panel-header">
            <div class="aips-panel-header-content">
                <span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px; margin-right: 8px;"></span>
                <div>
                    <h3 class="aips-panel-title"><?php echo esc_html__('Review & Schedule', 'ai-post-scheduler'); ?></h3>
                </div>
            </div>
        </div>
        <div class="aips-panel-body">
            <div class="aips-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--aips-border-color, #ddd);">
                <div class="aips-toolbar-left">
                    <label style="display: flex; align-items: center; gap: 6px;">
                        <input type="checkbox" id="check-all-topics" style="margin: 0;">
                        <?php echo esc_html__('Select All', 'ai-post-scheduler'); ?>
                    </label>
                    <span class="selection-count" style="margin-left: 16px; color: #646970;"></span>
                </div>
                <div class="aips-toolbar-right" style="display: flex; gap: 8px; align-items: center;">
                    <input type="search" id="planner-topic-search" class="aips-form-input" placeholder="<?php esc_attr_e('Filter topics...', 'ai-post-scheduler'); ?>" style="max-width: 200px;">
                    <button type="button" id="btn-copy-topics" class="aips-btn aips-btn-sm aips-btn-secondary"><?php echo esc_html__('Copy Selected', 'ai-post-scheduler'); ?></button>
                    <button type="button" id="btn-clear-topics" class="aips-btn aips-btn-sm aips-btn-ghost"><?php echo esc_html__('Clear List', 'ai-post-scheduler'); ?></button>
                </div>
            </div>

            <div id="topics-list" class="aips-topics-grid">
                <!-- Topics inserted via JS -->
            </div>

            <div class="aips-schedule-settings" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--aips-border-color, #ddd);">
                <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 600;"><?php echo esc_html__('Bulk Schedule Settings', 'ai-post-scheduler'); ?></h4>

                <div class="aips-form-grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px;">
                    <div class="aips-form-field">
                        <label for="bulk-template" class="aips-form-label"><?php echo esc_html__('Use Template', 'ai-post-scheduler'); ?></label>
                        <select id="bulk-template" class="aips-form-input">
                            <option value=""><?php echo esc_html__('Select a Template...', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template->id); ?>"><?php echo esc_html($template->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="aips-field-description"><?php echo esc_html__('The {{topic}} variable in the template will be replaced by the topic title.', 'ai-post-scheduler'); ?></p>
                    </div>

                    <div class="aips-form-field">
                        <label for="bulk-start-date" class="aips-form-label"><?php echo esc_html__('Start Date', 'ai-post-scheduler'); ?></label>
                        <input type="datetime-local" id="bulk-start-date" class="aips-form-input" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <div class="aips-form-field">
                        <label for="bulk-frequency" class="aips-form-label"><?php echo esc_html__('Frequency', 'ai-post-scheduler'); ?></label>
                        <?php AIPS_Template_Helper::render_frequency_dropdown( 'bulk-frequency', 'bulk-frequency', $default_planner_frequency, __( 'Frequency', 'ai-post-scheduler' ) ); ?>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="button" id="btn-bulk-schedule" class="aips-btn aips-btn-primary aips-btn-lg">
                        <span class="dashicons dashicons-calendar-alt" style="margin-right: 4px;"></span>
                        <?php echo esc_html__('Schedule Selected Topics', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.aips-topics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
}
.topic-item {
    background: #fff;
    padding: 10px;
    border: 1px solid #eee;
    display: flex;
    align-items: center;
}
.topic-checkbox {
    margin-right: 10px !important;
}
.topic-text-input {
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    padding: 3px 5px;
    font-size: 14px;
    color: #1d2327;
}
.topic-text-input:hover {
    border: 1px solid #ccc;
    background: #fff;
}
.topic-text-input:focus {
    border: 1px solid #2271b1;
    background: #fff;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}
.topic-checkbox:checked + .topic-text-input {
    font-weight: 500;
}
.aips-row {
    display: flex;
    gap: 20px;
    align-items: flex-end;
}
.aips-col {
    flex: 1;
}
.aips-col select, .aips-col input {
    width: 100%;
    max-width: 100%;
}
</style>
