<?php
$default_planner_frequency = 'daily';
$editions_repository = new AIPS_Editions_Repository();
$planner_editions = $editions_repository->get_active();
?>
<div class="aips-planner-container">
    <!-- Topic Brainstorming Card -->
    <div class="aips-content-panel">
        <div class="aips-panel-header">
            <div class="aips-panel-header-content">
                <span class="dashicons dashicons-lightbulb dashicons-icon-lg"></span>
                <div>
                    <h3 class="aips-panel-title"><?php echo esc_html__('Topic Brainstorming', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-panel-description"><?php echo esc_html__('Generate article ideas based on a niche, or paste your own list of topics.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>
        <div class="aips-panel-body">
            <div class="aips-form-grid aips-form-grid aips-planner-form-grid-2-1">
                <div class="aips-form-field">
                    <label for="planner-niche" class="aips-form-label"><?php echo esc_html__('Niche / Topic', 'ai-post-scheduler'); ?></label>
                    <input type="text" id="planner-niche" class="aips-form-input" placeholder="<?php echo esc_attr__('e.g. React.js Tutorials, Healthy Keto Recipes...', 'ai-post-scheduler'); ?>">
                </div>

                <div class="aips-form-field">
                    <label for="planner-count" class="aips-form-label"><?php echo esc_html__('Number of Topics', 'ai-post-scheduler'); ?></label>
                    <input type="number" id="planner-count" class="aips-form-input" value="10" min="1" max="50" class="aips-planner-count-input">
                </div>
            </div>

            <div >
                <button type="button" id="btn-generate-topics" class="aips-btn aips-btn-primary">
                    <span class="dashicons dashicons-update" ></span>
                    <?php echo esc_html__('Generate Topics', 'ai-post-scheduler'); ?>
                </button>
                <span class="spinner"></span>
            </div>

            <hr class="aips-planner-divider">

            <div class="aips-form-field">
                <label for="planner-manual-topics" class="aips-form-label"><?php echo esc_html__('Or Paste Topics (One per line)', 'ai-post-scheduler'); ?></label>
                <textarea id="planner-manual-topics" class="aips-form-input" rows="5" placeholder="<?php echo esc_attr__('Topic 1&#10;Topic 2&#10;Topic 3', 'ai-post-scheduler'); ?>"></textarea>
                <button type="button" id="btn-parse-manual" class="aips-btn aips-btn-secondary" >
                    <?php echo esc_html__('Add to List', 'ai-post-scheduler'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Review & Schedule Card -->
    <div id="planner-results" class="aips-content-panel aips-planner-results">
        <div class="aips-panel-header">
            <div class="aips-panel-header-content">
                <span class="dashicons dashicons-yes-alt dashicons-icon-lg"></span>
                <div>
                    <h3 class="aips-panel-title"><?php echo esc_html__('Review & Schedule', 'ai-post-scheduler'); ?></h3>
                </div>
            </div>
        </div>
        <div class="aips-panel-body">
            <div class="aips-toolbar aips-planner-toolbar">
                <div class="aips-toolbar-left">
                    <label class="aips-planner-select-all">
                        <input type="checkbox" id="check-all-topics" >
                        <?php echo esc_html__('Select All', 'ai-post-scheduler'); ?>
                    </label>
                    <span class="selection-count aips-planner-selection-count"></span>
                </div>
                <div class="aips-toolbar-right aips-planner-toolbar-right">
                    <label class="screen-reader-text" for="planner-topic-search"><?php esc_html_e('Filter topics:', 'ai-post-scheduler'); ?></label>
                    <input type="search" id="planner-topic-search" class="aips-form-input aips-planner-topic-search" placeholder="<?php esc_attr_e('Filter topics...', 'ai-post-scheduler'); ?>">
                    <button type="button" id="planner-topic-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                    <button type="button" id="btn-copy-topics" class="aips-btn aips-btn-sm aips-btn-secondary"><?php echo esc_html__('Copy Selected', 'ai-post-scheduler'); ?></button>
                    <button type="button" id="btn-clear-topics" class="aips-btn aips-btn-sm aips-btn-ghost"><?php echo esc_html__('Clear List', 'ai-post-scheduler'); ?></button>
                </div>
            </div>

            <div id="topics-list" class="aips-topics-grid">
                <!-- Topics inserted via JS -->
            </div>

            <div class="aips-schedule-settings aips-planner-schedule-settings">
                <h4 ><?php echo esc_html__('Bulk Schedule Settings', 'ai-post-scheduler'); ?></h4>

                <div class="aips-form-grid aips-form-grid aips-planner-schedule-grid">
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

                    <div class="aips-form-field">
                        <label for="bulk-edition" class="aips-form-label"><?php echo esc_html__('Edition Package', 'ai-post-scheduler'); ?></label>
                        <select id="bulk-edition" class="aips-form-input">
                            <option value=""><?php echo esc_html__('None — schedule as standalone posts', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($planner_editions as $edition): ?>
                                <option value="<?php echo esc_attr($edition->id); ?>">
                                    <?php echo esc_html(sprintf('%1$s (%2$d/%3$d filled)', $edition->name, $edition->completeness['slots_filled'], $edition->required_slots)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="aips-field-description"><?php echo esc_html__('Choose an edition to map selected planner topics into the next open package slots in order.', 'ai-post-scheduler'); ?></p>
                    </div>
                </div>

                <?php if (!empty($planner_editions)): ?>
                <div class="aips-content-panel" style="margin-top:16px; box-shadow:none; border:1px solid #dcdcde;">
                    <div class="aips-panel-body">
                        <h4 style="margin-top:0;"><?php esc_html_e('Edition Readiness Snapshot', 'ai-post-scheduler'); ?></h4>
                        <div style="display:grid; gap:12px;">
                            <?php foreach ($planner_editions as $edition): ?>
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; border-bottom:1px solid #f0f0f1; padding-bottom:12px;">
                                <div>
                                    <strong><?php echo esc_html($edition->name); ?></strong>
                                    <div class="aips-muted" style="margin-top:4px;"><?php echo esc_html(sprintf(__('Target: %1$s · Owner: %2$s · Channel: %3$s', 'ai-post-scheduler'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($edition->target_publish_date)), $edition->owner, $edition->channel_type)); ?></div>
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <span class="aips-badge aips-badge-neutral"><?php echo esc_html(sprintf(__('Filled %1$d/%2$d', 'ai-post-scheduler'), $edition->completeness['slots_filled'], $edition->required_slots)); ?></span>
                                    <span class="aips-badge aips-badge-info"><?php echo esc_html(sprintf(__('Review %d', 'ai-post-scheduler'), $edition->completeness['ready_for_review'])); ?></span>
                                    <span class="aips-badge <?php echo $edition->completeness['blocked_by_missing_sourcing'] ? 'aips-badge-warning' : 'aips-badge-success'; ?>"><?php echo esc_html(sprintf(__('Missing sourcing %d', 'ai-post-scheduler'), $edition->completeness['blocked_by_missing_sourcing'])); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="aips-planner-actions">
                    <button type="button" id="btn-bulk-schedule" class="aips-btn aips-btn-primary aips-btn-lg">
                        <span class="dashicons dashicons-calendar-alt" ></span>
                        <?php echo esc_html__('Schedule Selected Topics', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>
        </div>
    </div>
</div>


