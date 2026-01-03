<div class="aips-planner-container">
    <div class="aips-card">
        <h3><?php echo esc_html__('Topic Generator', 'ai-post-scheduler'); ?></h3>
        <p><?php echo esc_html__('Generate article ideas based on a niche/keywords, or paste your own list.', 'ai-post-scheduler'); ?></p>

        <div class="aips-form-row">
            <label for="planner-niche"><?php echo esc_html__('Keywords / Niche', 'ai-post-scheduler'); ?></label>
            <input type="text" id="planner-niche" class="regular-text" placeholder="<?php echo esc_attr__('e.g. React.js Tutorials, Healthy Keto Recipes...', 'ai-post-scheduler'); ?>">
        </div>

        <div class="aips-form-actions">
            <button type="button" id="btn-generate-topics" class="button button-primary">
                <?php echo esc_html__('Generate Topics', 'ai-post-scheduler'); ?>
            </button>
            <button type="button" id="btn-fetch-more-topics" class="button button-secondary" style="display:none; margin-left: 10px;">
                <?php echo esc_html__('Fetch More', 'ai-post-scheduler'); ?>
            </button>
            <span class="spinner"></span>
        </div>

        <hr>

        <div class="aips-form-row">
            <label for="planner-manual-topics"><?php echo esc_html__('Or Paste Topics (One per line)', 'ai-post-scheduler'); ?></label>
            <textarea id="planner-manual-topics" class="large-text" rows="3" placeholder="<?php echo esc_attr__('Topic 1&#10;Topic 2&#10;Topic 3', 'ai-post-scheduler'); ?>"></textarea>
            <button type="button" id="btn-parse-manual" class="button button-secondary" style="margin-top: 10px;">
                <?php echo esc_html__('Add to List', 'ai-post-scheduler'); ?>
            </button>
        </div>
    </div>

    <div id="planner-results" class="aips-card" style="display:none; margin-top: 20px;">
        <h3><?php echo esc_html__('Topic Queue', 'ai-post-scheduler'); ?></h3>

        <div class="aips-toolbar">
            <div class="aips-toolbar-left">
                <label><input type="checkbox" id="check-all-topics" checked> <?php echo esc_html__('Select All', 'ai-post-scheduler'); ?></label>
                <span class="selection-count"></span>
            </div>
            <div class="aips-toolbar-right">
                <button type="button" id="btn-copy-topics" class="button button-secondary button-small"><?php echo esc_html__('Copy Selected', 'ai-post-scheduler'); ?></button>
                <button type="button" id="btn-clear-topics" class="button button-link-delete button-small"><?php echo esc_html__('Clear List', 'ai-post-scheduler'); ?></button>
            </div>
        </div>

        <div id="topics-list" class="aips-topics-grid">
            <!-- Topics inserted via JS -->
        </div>

        <div class="aips-form-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <button type="button" id="btn-open-matrix" class="button button-primary button-large">
                <?php echo esc_html__('Schedule Selected Topics', 'ai-post-scheduler'); ?>
            </button>
        </div>
    </div>

    <!-- The Posting Matrix Modal -->
    <div id="aips-matrix-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content aips-modal-large">
            <div class="aips-modal-header">
                <h2><?php esc_html_e('Schedule Configuration (The Matrix)', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            <div class="aips-modal-body">
                <form id="aips-matrix-form">

                    <!-- Template Selection -->
                    <div class="aips-form-row">
                        <label for="matrix-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <select id="matrix-template" name="template_id" required>
                            <option value=""><?php esc_html_e('Select Template...', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template->id); ?>"><?php echo esc_html($template->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('The selected topics will be processed using this template.', 'ai-post-scheduler'); ?></p>
                    </div>

                    <!-- Frequency Configuration -->
                    <div class="aips-form-row">
                        <label for="matrix-frequency"><?php esc_html_e('Frequency Pattern', 'ai-post-scheduler'); ?></label>
                        <select id="matrix-frequency" name="frequency">
                            <option value="daily"><?php esc_html_e('Simple: Daily', 'ai-post-scheduler'); ?></option>
                            <option value="hourly"><?php esc_html_e('Simple: Hourly', 'ai-post-scheduler'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom / Advanced', 'ai-post-scheduler'); ?></option>
                        </select>
                    </div>

                    <!-- Custom Rules Section (Conditional) -->
                    <div id="matrix-custom-rules" style="display:none; background: #f9f9f9; padding: 15px; border: 1px solid #eee; margin-bottom: 15px;">

                        <!-- Time Selection -->
                        <div class="aips-form-row">
                            <label><?php esc_html_e('Time(s) of Day', 'ai-post-scheduler'); ?></label>
                            <div id="matrix-times-container">
                                <input type="time" name="times[]" value="09:00">
                                <button type="button" class="button button-small" id="btn-add-time">+</button>
                            </div>
                            <p class="description"><?php esc_html_e('Add multiple times to post multiple times per day (e.g. 9:00 and 17:00).', 'ai-post-scheduler'); ?></p>
                        </div>

                        <!-- Days of Week -->
                        <div class="aips-form-row">
                            <label><?php esc_html_e('Days of Week', 'ai-post-scheduler'); ?></label>
                            <div class="aips-checkbox-group">
                                <?php
                                $days = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 0];
                                foreach ($days as $label => $val) {
                                    echo '<label style="margin-right:10px;"><input type="checkbox" name="days_of_week[]" value="' . $val . '" checked> ' . $label . '</label>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Day of Month -->
                        <div class="aips-form-row">
                            <label><?php esc_html_e('Day of Month (Optional)', 'ai-post-scheduler'); ?></label>
                            <input type="text" name="day_of_month" placeholder="e.g. 1, 15" class="regular-text">
                            <p class="description"><?php esc_html_e('Enter specific days (comma separated). Overrides Days of Week.', 'ai-post-scheduler'); ?></p>
                        </div>
                    </div>

                    <!-- Review Toggle -->
                    <div class="aips-form-row">
                        <label class="aips-checkbox-label">
                            <input type="checkbox" id="matrix-review-required" name="review_required" value="1">
                            <?php esc_html_e('Require Review?', 'ai-post-scheduler'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('If checked, posts will be set to "Pending" status for approval.', 'ai-post-scheduler'); ?></p>
                    </div>

                    <!-- Start Date -->
                    <div class="aips-form-row">
                        <label for="matrix-start-date"><?php esc_html_e('Start Date', 'ai-post-scheduler'); ?></label>
                        <input type="datetime-local" id="matrix-start-date" name="start_time" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                </form>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
                <button type="button" class="button button-primary" id="btn-save-matrix">
                    <?php esc_html_e('Save Schedule & Queue Topics', 'ai-post-scheduler'); ?>
                </button>
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
</style>
