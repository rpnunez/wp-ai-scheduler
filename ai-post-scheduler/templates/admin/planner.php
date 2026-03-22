<?php
$default_planner_frequency = 'daily';
$planner_service = isset($planner_service) && $planner_service instanceof AIPS_Unified_Schedule_Service ? $planner_service : new AIPS_Unified_Schedule_Service();
$planner_insights = isset($planner_insights) && is_array($planner_insights) ? $planner_insights : $planner_service->get_planner_mix_insights();
$mix_rules = isset($planner_insights['rules']) ? $planner_insights['rules'] : AIPS_Site_Context::get_editorial_mix_rules();
$current_report = isset($planner_insights['current_report']) ? $planner_insights['current_report'] : array();
$initial_suggestions = isset($planner_insights['suggestions']) ? $planner_insights['suggestions'] : array();
$initial_warnings = isset($current_report['warnings']) ? $current_report['warnings'] : array();
$initial_beats = !empty($current_report['beats']) ? array_slice($current_report['beats'], 0, 3, true) : array();
$initial_formats = !empty($current_report['formats']) ? $current_report['formats'] : array();
?>
<div class="aips-planner-container">
    <div class="aips-content-panel">
        <div class="aips-panel-header">
            <div class="aips-panel-header-content">
                <span class="dashicons dashicons-chart-pie dashicons-icon-lg"></span>
                <div>
                    <h3 class="aips-panel-title"><?php echo esc_html__('Editorial Mix Guardrails', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-panel-description"><?php echo esc_html__('Site-wide mix rules steer the planner toward a healthier weekly balance across beats, formats, and evergreen coverage.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>
        <div class="aips-panel-body">
            <div class="aips-toolbar" style="gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                <span class="aips-badge aips-badge-info"><?php echo esc_html(sprintf(__('Max beat share: %d%%', 'ai-post-scheduler'), $mix_rules['max_beat_share'])); ?></span>
                <span class="aips-badge aips-badge-info"><?php echo esc_html(sprintf(__('Evergreen floor: %d%%', 'ai-post-scheduler'), $mix_rules['min_evergreen_quota'])); ?></span>
                <span class="aips-badge aips-badge-info"><?php echo esc_html(sprintf(__('Max same-topic repeats: %d', 'ai-post-scheduler'), $mix_rules['max_same_topic_repeats'])); ?></span>
                <?php foreach ($mix_rules['target_format_mix'] as $format => $target) : ?>
                    <span class="aips-badge aips-badge-neutral"><?php echo esc_html(sprintf(__('%1$s target: %2$d%%', 'ai-post-scheduler'), ucfirst($format), $target)); ?></span>
                <?php endforeach; ?>
            </div>

            <div class="aips-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
                <div class="aips-card" style="padding:16px; border:1px solid #e2e8f0; border-radius:10px; background:#fff;">
                    <h4 style="margin-top:0;"><?php echo esc_html__('Upcoming Calendar Balance', 'ai-post-scheduler'); ?></h4>
                    <p style="margin-top:0;color:#64748b;"><?php echo esc_html__('Warnings are based on the next 7 days of active scheduled items.', 'ai-post-scheduler'); ?></p>
                    <div id="planner-balance-overview">
                        <?php if (!empty($initial_warnings)) : ?>
                            <?php foreach ($initial_warnings as $warning) : ?>
                                <div class="notice notice-warning inline" style="margin:0 0 8px 0;">
                                    <p><?php echo esc_html($warning['message']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="notice notice-success inline" style="margin:0 0 8px 0;">
                                <p><?php echo esc_html__('The current upcoming calendar is within the configured balance thresholds.', 'ai-post-scheduler'); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($initial_beats)) : ?>
                            <ul style="margin:12px 0 0 18px;">
                                <?php foreach ($initial_beats as $beat => $data) : ?>
                                    <li><?php echo esc_html(sprintf(__('%1$s: %2$d items (%3$s%%)', 'ai-post-scheduler'), ucfirst($beat), $data['count'], number_format_i18n($data['share'], 1))); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="aips-card" style="padding:16px; border:1px solid #e2e8f0; border-radius:10px; background:#fff;">
                    <h4 style="margin-top:0;"><?php echo esc_html__('Format Targets Snapshot', 'ai-post-scheduler'); ?></h4>
                    <div style="display:grid; gap:8px;">
                        <?php foreach ($mix_rules['target_format_mix'] as $format => $target) :
                            $actual = isset($initial_formats[ $format ]['share']) ? $initial_formats[ $format ]['share'] : 0;
                        ?>
                            <div>
                                <strong><?php echo esc_html(ucfirst($format)); ?></strong>
                                <div style="font-size:12px; color:#64748b;"><?php echo esc_html(sprintf(__('Actual %1$s%% / target %2$d%%', 'ai-post-scheduler'), number_format_i18n($actual, 1), $target)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="aips-card" style="padding:16px; border:1px solid #e2e8f0; border-radius:10px; background:#fff;">
                    <h4 style="margin-top:0;"><?php echo esc_html__('Rebalance Plan Suggestions', 'ai-post-scheduler'); ?></h4>
                    <div id="planner-rebalance-suggestions">
                        <?php if (!empty($initial_suggestions)) : ?>
                            <ul style="margin:0; padding-left:18px; display:grid; gap:8px;">
                                <?php foreach ($initial_suggestions as $suggestion) : ?>
                                    <li>
                                        <strong><?php echo esc_html($suggestion['title']); ?></strong>
                                        <div style="font-size:12px; color:#64748b;">
                                            <?php echo esc_html(sprintf(__('%1$s suggestion • %2$s • score %3$d • %4$s', 'ai-post-scheduler'), !empty($suggestion['source_label']) ? $suggestion['source_label'] : __('Queue', 'ai-post-scheduler'), ucfirst($suggestion['format']), $suggestion['score'], $suggestion['impact'])); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="margin:0;color:#64748b;"><?php echo esc_html__('As you add topics, the planner will recommend alternatives from research and approved-topic queues when a plan drifts out of balance.', 'ai-post-scheduler'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            <div class="aips-form-grid aips-planner-form-grid-2-1">
                <div class="aips-form-field">
                    <label for="planner-niche" class="aips-form-label"><?php echo esc_html__('Niche / Topic', 'ai-post-scheduler'); ?></label>
                    <input type="text" id="planner-niche" class="aips-form-input" placeholder="<?php echo esc_attr__('e.g. React.js Tutorials, Healthy Keto Recipes...', 'ai-post-scheduler'); ?>">
                </div>

                <div class="aips-form-field">
                    <label for="planner-count" class="aips-form-label"><?php echo esc_html__('Number of Topics', 'ai-post-scheduler'); ?></label>
                    <input type="number" id="planner-count" class="aips-form-input aips-planner-count-input" value="10" min="1" max="50">
                </div>
            </div>

            <div>
                <button type="button" id="btn-generate-topics" class="aips-btn aips-btn-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php echo esc_html__('Generate Topics', 'ai-post-scheduler'); ?>
                </button>
                <span class="spinner"></span>
            </div>

            <hr class="aips-planner-divider">

            <div class="aips-form-field">
                <label for="planner-manual-topics" class="aips-form-label"><?php echo esc_html__('Or Paste Topics (One per line)', 'ai-post-scheduler'); ?></label>
                <textarea id="planner-manual-topics" class="aips-form-input" rows="5" placeholder="<?php echo esc_attr__('Topic 1&#10;Topic 2&#10;Topic 3', 'ai-post-scheduler'); ?>"></textarea>
                <button type="button" id="btn-parse-manual" class="aips-btn aips-btn-secondary">
                    <?php echo esc_html__('Add to List', 'ai-post-scheduler'); ?>
                </button>
            </div>
        </div>
    </div>

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
                        <input type="checkbox" id="check-all-topics">
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

            <div class="aips-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin:18px 0;">
                <div class="aips-card" style="padding:16px; border:1px solid #e2e8f0; border-radius:10px; background:#fff;">
                    <h4 style="margin-top:0;"><?php echo esc_html__('Candidate Mix Scorecard', 'ai-post-scheduler'); ?></h4>
                    <div id="planner-candidate-insights">
                        <p style="margin:0;color:#64748b;"><?php echo esc_html__('Select topics to preview whether each proposed item improves or worsens the weekly editorial mix.', 'ai-post-scheduler'); ?></p>
                    </div>
                </div>

                <div class="aips-card" style="padding:16px; border:1px solid #e2e8f0; border-radius:10px; background:#fff;">
                    <h4 style="margin-top:0;"><?php echo esc_html__('Projected Calendar After Scheduling', 'ai-post-scheduler'); ?></h4>
                    <div id="planner-projected-overview">
                        <p style="margin:0;color:#64748b;"><?php echo esc_html__('The planner will forecast beat concentration, evergreen quota, and format balance once you choose a template, start date, and cadence.', 'ai-post-scheduler'); ?></p>
                    </div>
                </div>
            </div>

            <div class="aips-schedule-settings aips-planner-schedule-settings">
                <h4><?php echo esc_html__('Bulk Schedule Settings', 'ai-post-scheduler'); ?></h4>

                <div class="aips-form-grid aips-planner-schedule-grid">
                    <div class="aips-form-field">
                        <label for="bulk-template" class="aips-form-label"><?php echo esc_html__('Use Template', 'ai-post-scheduler'); ?></label>
                        <select id="bulk-template" class="aips-form-input">
                            <option value=""><?php echo esc_html__('Select a Template...', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($templates as $template) : ?>
                                <option value="<?php echo esc_attr($template->id); ?>"><?php echo esc_html($template->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="aips-field-description"><?php echo esc_html__('The {{topic}} variable in the template will be replaced by the topic title.', 'ai-post-scheduler'); ?></p>
                    </div>

                    <div class="aips-form-field">
                        <label for="bulk-start-date" class="aips-form-label"><?php echo esc_html__('Start Date', 'ai-post-scheduler'); ?></label>
                        <input type="datetime-local" id="bulk-start-date" class="aips-form-input" value="<?php echo esc_attr(date('Y-m-d\TH:i')); ?>">
                    </div>

                    <div class="aips-form-field">
                        <label for="bulk-frequency" class="aips-form-label"><?php echo esc_html__('Frequency', 'ai-post-scheduler'); ?></label>
                        <?php AIPS_Template_Helper::render_frequency_dropdown('bulk-frequency', 'bulk-frequency', $default_planner_frequency, __('Frequency', 'ai-post-scheduler')); ?>
                    </div>
                </div>

                <div class="aips-planner-actions">
                    <button type="button" id="btn-bulk-schedule" class="aips-btn aips-btn-primary aips-btn-lg">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html__('Schedule Selected Topics', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>
        </div>
    </div>
</div>
