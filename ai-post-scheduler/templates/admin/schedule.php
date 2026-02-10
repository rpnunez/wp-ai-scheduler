<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$scheduler = new AIPS_Scheduler();
$schedules = $scheduler->get_all_schedules();

$templates_handler = new AIPS_Templates();
$templates = $templates_handler->get_all(true);

// Get article structures and rotation patterns
$structure_manager = new AIPS_Article_Structure_Manager();
$article_structures = $structure_manager->get_active_structures();

$template_type_selector = new AIPS_Template_Type_Selector();
$rotation_patterns = $template_type_selector->get_rotation_patterns();
?>
<div class="wrap aips-wrap aips-redesign">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Post Schedules', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Automate post generation by setting up recurring schedules for your templates.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <button class="aips-btn aips-btn-primary aips-add-schedule-btn" <?php echo empty($templates) ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Schedule', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (empty($templates)): ?>
        <div class="aips-content-panel">
            <div class="aips-panel-body">
                <div class="aips-empty-state">
                    <div class="dashicons dashicons-info aips-empty-state-icon" aria-hidden="true" style="color: var(--aips-warning);"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Templates Available', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('You need to create at least one active template before you can schedule posts.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aips-templates')); ?>" class="aips-btn aips-btn-primary">
                            <span class="dashicons dashicons-media-document"></span>
                            <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (!empty($schedules)): ?>
        <!-- Content Panel with Filter Bar -->
        <div class="aips-content-panel">
            <!-- Filter Bar -->
            <div class="aips-filter-bar">
                <label class="screen-reader-text" for="aips-schedule-search"><?php esc_html_e('Search Schedules:', 'ai-post-scheduler'); ?></label>
                <input type="search" id="aips-schedule-search" class="aips-form-input" style="max-width: 300px;" placeholder="<?php esc_attr_e('Search schedules...', 'ai-post-scheduler'); ?>">
                <button type="button" id="aips-schedule-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
            </div>
            
            <!-- Schedules Table -->
            <div class="aips-panel-body no-padding">
                <table class="aips-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Get article structure repository for lookup
                        $structure_repo = new AIPS_Article_Structure_Repository();
                        foreach ($schedules as $schedule): 
                            // Get structure info
                            $structure_display = __('Default', 'ai-post-scheduler');
                            if (!empty($schedule->article_structure_id)) {
                                $structure = $structure_repo->get_by_id($schedule->article_structure_id);
                                if ($structure) {
                                    $structure_display = $structure->name;
                                }
                            } else if (!empty($schedule->rotation_pattern)) {
                                $structure_display = __('Rotating', 'ai-post-scheduler');
                            }
                        ?>
                        <tr data-schedule-id="<?php echo esc_attr($schedule->id); ?>"
                            data-template-id="<?php echo esc_attr($schedule->template_id); ?>"
                            data-frequency="<?php echo esc_attr($schedule->frequency); ?>"
                            data-topic="<?php echo esc_attr($schedule->topic); ?>"
                            data-article-structure-id="<?php echo esc_attr($schedule->article_structure_id); ?>"
                            data-rotation-pattern="<?php echo esc_attr($schedule->rotation_pattern); ?>">
                            <td>
                                <div class="cell-primary"><?php echo esc_html($schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler')); ?></div>
                            </td>
                            <td>
                                <div>
                                    <?php echo esc_html($structure_display); ?>
                                    <?php if (!empty($schedule->rotation_pattern)): ?>
                                        <div class="cell-meta"><?php echo esc_html(ucfirst(str_replace('_', ' ', $schedule->rotation_pattern))); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="aips-badge aips-badge-info">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $schedule->frequency))); ?>
                                </span>
                            </td>
                            <td>
                                <div class="cell-meta">
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->next_run))); ?>
                                </div>
                            </td>
                            <td>
                                <div class="cell-meta">
                                    <?php 
                                    if ($schedule->last_run) {
                                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->last_run)));
                                    } else {
                                        esc_html_e('Never', 'ai-post-scheduler');
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $status = isset($schedule->status) ? $schedule->status : 'active';
                                switch ($status) {
                                    case 'failed':
                                        $badge_class = 'aips-badge-error';
                                        $icon = 'dashicons-warning';
                                        $text = __('Failed', 'ai-post-scheduler');
                                        break;
                                    case 'inactive':
                                        $badge_class = 'aips-badge-neutral';
                                        $icon = 'dashicons-minus';
                                        $text = __('Inactive', 'ai-post-scheduler');
                                        break;
                                    case 'active':
                                    default:
                                        $badge_class = 'aips-badge-success';
                                        $icon = 'dashicons-yes-alt';
                                        $text = __('Active', 'ai-post-scheduler');
                                        break;
                                }
                                ?>
                                <div class="aips-schedule-status-wrapper" style="display: flex; align-items: center; gap: 8px;">
                                    <span class="aips-badge <?php echo esc_attr($badge_class); ?>">
                                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                        <?php echo esc_html($text); ?>
                                    </span>
                                    <label class="aips-toggle">
                                        <input type="checkbox" class="aips-toggle-schedule" aria-label="<?php esc_attr_e('Toggle schedule status', 'ai-post-scheduler'); ?>" data-id="<?php echo esc_attr($schedule->id); ?>" <?php checked($schedule->is_active, 1); ?>>
                                        <span class="aips-toggle-slider"></span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="cell-actions">
                                    <button class="aips-btn aips-btn-sm aips-btn-ghost aips-clone-schedule" aria-label="<?php esc_attr_e('Clone schedule', 'ai-post-scheduler'); ?>" title="<?php esc_attr_e('Clone', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-schedule" data-id="<?php echo esc_attr($schedule->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- No Search Results State -->
                <div id="aips-schedule-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
                    <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Found', 'ai-post-scheduler'); ?></h3>
                <!-- No Search Results State -->
                <div id="aips-schedule-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
                    <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Found', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('No schedules match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <button type="button" class="aips-btn aips-btn-primary aips-clear-schedule-search-btn">
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
                    <div class="dashicons dashicons-calendar-alt aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Yet', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('Create a schedule to automatically generate posts on a regular basis using your templates.', 'ai-post-scheduler'); ?></p>
                    <?php if (!empty($templates)): ?>
                    <div class="aips-empty-state-actions">
                        <button class="aips-btn aips-btn-primary aips-add-schedule-btn">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Create Schedule', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Keep original modal markup below (not redesigned yet) -->
    </div>
    
    <div id="aips-schedule-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content">
            <div class="aips-modal-header">
                <h2 id="aips-schedule-modal-title"><?php esc_html_e('Add New Schedule', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            </div>
            <div class="aips-modal-body">
                <form id="aips-schedule-form">
                    <input type="hidden" name="schedule_id" id="schedule_id" value="">
                    
                    <div class="aips-form-row">
                        <label for="schedule_template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
                        <select id="schedule_template" name="template_id" required>
                            <option value=""><?php esc_html_e('Select Template', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo esc_attr($template->id); ?>"><?php echo esc_html($template->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="schedule_frequency"><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></label>
                        <select id="schedule_frequency" name="frequency">
                            <?php
                            $cron_schedules = wp_get_schedules();

                            // Sort by interval
                            uasort($cron_schedules, function($a, $b) {
                                return $a['interval'] - $b['interval'];
                            });

                            foreach ($cron_schedules as $key => $schedule) {
                                echo '<option value="' . esc_attr($key) . '" ' . selected('daily', $key, false) . '>' . esc_html($schedule['display']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="schedule_start_time"><?php esc_html_e('Start Time', 'ai-post-scheduler'); ?></label>
                        <input type="datetime-local" id="schedule_start_time" name="start_time">
                        <p class="description"><?php esc_html_e('Leave empty to start from now', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="schedule_topic"><?php esc_html_e('Topic (Optional)', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="schedule_topic" name="topic" class="regular-text">
                        <p class="description"><?php esc_html_e('Optional topic to pass to template variables', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="article_structure_id"><?php esc_html_e('Article Structure (Optional)', 'ai-post-scheduler'); ?></label>
                        <select id="article_structure_id" name="article_structure_id">
                            <option value=""><?php esc_html_e('Use Default', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($article_structures as $structure): ?>
                            <option value="<?php echo esc_attr($structure->id); ?>">
                                <?php echo esc_html($structure->name); ?>
                                <?php if (!empty($structure->is_default)): ?> (<?php esc_html_e('Default', 'ai-post-scheduler'); ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select a specific article structure or leave as default', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="rotation_pattern"><?php esc_html_e('Rotation Pattern (Optional)', 'ai-post-scheduler'); ?></label>
                        <select id="rotation_pattern" name="rotation_pattern">
                            <option value=""><?php esc_html_e('No Rotation', 'ai-post-scheduler'); ?></option>
                            <?php foreach ($rotation_patterns as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Automatically alternate between different article structures', 'ai-post-scheduler'); ?></p>
                    </div>
                    
                    <div class="aips-form-row">
                        <label class="aips-checkbox-label">
                            <input type="checkbox" id="schedule_is_active" name="is_active" value="1" checked>
                            <?php esc_html_e('Schedule is active', 'ai-post-scheduler'); ?>
                        </label>
                    </div>
                </form>
            </div>
            <div class="aips-modal-footer">
                <button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
                <button type="button" class="button button-primary aips-save-schedule"><?php esc_html_e('Save Schedule', 'ai-post-scheduler'); ?></button>
            </div>
        </div>
    </div>
</div>
