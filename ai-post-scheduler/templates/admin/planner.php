<?php
$default_planner_frequency = 'daily';
?>
<div class="aips-planner-container">
    <div class="aips-card">
        <h3><?php echo esc_html__('Topic Brainstorming', 'ai-post-scheduler'); ?></h3>
        <p><?php echo esc_html__('Generate article ideas based on a niche, or paste your own list of topics.', 'ai-post-scheduler'); ?></p>

        <div class="aips-form-row">
            <label for="planner-niche"><?php echo esc_html__('Niche / Topic', 'ai-post-scheduler'); ?></label>
            <input type="text" id="planner-niche" class="regular-text" placeholder="<?php echo esc_attr__('e.g. React.js Tutorials, Healthy Keto Recipes...', 'ai-post-scheduler'); ?>">
        </div>

        <div class="aips-form-row">
            <label for="planner-count"><?php echo esc_html__('Number of Topics', 'ai-post-scheduler'); ?></label>
            <input type="number" id="planner-count" class="small-text" value="10" min="1" max="50">
        </div>

        <div class="aips-form-actions">
            <button type="button" id="btn-generate-topics" class="button button-primary">
                <?php echo esc_html__('Generate Topics', 'ai-post-scheduler'); ?>
            </button>
            <span class="spinner"></span>
        </div>

        <hr>

        <div class="aips-form-row">
            <label for="planner-manual-topics"><?php echo esc_html__('Or Paste Topics (One per line)', 'ai-post-scheduler'); ?></label>
            <textarea id="planner-manual-topics" class="large-text" rows="5" placeholder="<?php echo esc_attr__('Topic 1&#10;Topic 2&#10;Topic 3', 'ai-post-scheduler'); ?>"></textarea>
            <button type="button" id="btn-parse-manual" class="button button-secondary" style="margin-top: 10px;">
                <?php echo esc_html__('Add to List', 'ai-post-scheduler'); ?>
            </button>
        </div>
    </div>

    <div id="planner-results" class="aips-card" style="display:none; margin-top: 20px;">
        <h3><?php echo esc_html__('Review & Schedule', 'ai-post-scheduler'); ?></h3>

        <div class="aips-toolbar">
            <div class="aips-toolbar-left">
                <label><input type="checkbox" id="check-all-topics"> <?php echo esc_html__('Select All', 'ai-post-scheduler'); ?></label>
                <span class="selection-count"></span>
            </div>
            <div class="aips-toolbar-right">
                <input type="search" id="planner-topic-search" placeholder="<?php echo esc_attr__('Filter topics...', 'ai-post-scheduler'); ?>" style="margin-right: 10px; max-width: 200px;">
                <button type="button" id="btn-copy-topics" class="button button-secondary button-small"><?php echo esc_html__('Copy Selected', 'ai-post-scheduler'); ?></button>
                <button type="button" id="btn-clear-topics" class="button button-link-delete button-small"><?php echo esc_html__('Clear List', 'ai-post-scheduler'); ?></button>
            </div>
        </div>

        <div id="topics-list" class="aips-topics-grid">
            <!-- Topics inserted via JS -->
        </div>

        <div class="aips-schedule-settings" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4><?php echo esc_html__('Bulk Schedule Settings', 'ai-post-scheduler'); ?></h4>

            <div class="aips-row">
                <div class="aips-col">
                    <label for="bulk-template"><?php echo esc_html__('Use Template', 'ai-post-scheduler'); ?></label>
                    <select id="bulk-template">
                        <option value=""><?php echo esc_html__('Select a Template...', 'ai-post-scheduler'); ?></option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo esc_attr($template->id); ?>"><?php echo esc_html($template->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('The {{topic}} variable in the template will be replaced by the topic title.', 'ai-post-scheduler'); ?></p>
                </div>

                <div class="aips-col">
                    <label for="bulk-start-date"><?php echo esc_html__('Start Date', 'ai-post-scheduler'); ?></label>
                    <input type="datetime-local" id="bulk-start-date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>

                <div class="aips-col">
                    <label for="bulk-frequency"><?php echo esc_html__('Frequency', 'ai-post-scheduler'); ?></label>
                    <?php AIPS_Template_Helper::render_frequency_dropdown( 'bulk-frequency', 'bulk-frequency', $default_planner_frequency, __( 'Frequency', 'ai-post-scheduler' ) ); ?>
                </div>
            </div>

            <div class="aips-form-actions" style="margin-top: 20px;">
                <button type="button" id="btn-bulk-schedule" class="button button-primary button-large">
                    <?php echo esc_html__('Schedule Selected Topics', 'ai-post-scheduler'); ?>
                </button>
                <span class="spinner"></span>
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
