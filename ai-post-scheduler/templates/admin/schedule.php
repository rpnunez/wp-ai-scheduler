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
<div class="wrap aips-wrap">
    <h1>
        <?php esc_html_e('Post Schedules', 'ai-post-scheduler'); ?>
        <button class="page-title-action aips-add-schedule-btn"><?php esc_html_e('Add New Schedule', 'ai-post-scheduler'); ?></button>
    </h1>
    
    <!-- View Switcher -->
    <div class="aips-view-switcher" style="margin: 20px 0;">
        <button type="button" class="button button-primary aips-view-btn" data-view="calendar">
            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Calendar View', 'ai-post-scheduler'); ?>
        </button>
        <button type="button" class="button button-secondary aips-view-btn" data-view="list">
            <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('List View', 'ai-post-scheduler'); ?>
        </button>
    </div>

    <!-- Calendar View Container -->
    <div id="aips-calendar-view" class="aips-view-container">
        <div class="aips-calendar-controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div class="aips-calendar-nav">
                <button type="button" class="button" id="prev-month">&laquo;</button>
                <span id="current-month-label" style="font-size: 1.2em; font-weight: bold; margin: 0 15px;"></span>
                <button type="button" class="button" id="next-month">&raquo;</button>
            </div>
            <div class="aips-calendar-legend">
                <span class="legend-item"><span class="legend-dot" style="background:#00a32a;"></span> <?php esc_html_e('Published', 'ai-post-scheduler'); ?></span>
                <span class="legend-item"><span class="legend-dot" style="background:#dba617;"></span> <?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></span>
                <span class="legend-item"><span class="legend-dot" style="background:#2271b1;"></span> <?php esc_html_e('Scheduled', 'ai-post-scheduler'); ?></span>
                <span class="legend-item"><span class="legend-dot" style="background:#dcdcde;"></span> <?php esc_html_e('Projected', 'ai-post-scheduler'); ?></span>
            </div>
        </div>

        <div class="aips-calendar-grid" id="aips-calendar-grid">
            <!-- Calendar Grid (Generated via JS) -->
            <p class="aips-loading"><?php esc_html_e('Loading calendar...', 'ai-post-scheduler'); ?></p>
        </div>
    </div>

    <!-- List View Container (Hidden by default) -->
    <div id="aips-list-view" class="aips-view-container" style="display: none;">
        <?php if (empty($templates)): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('You need to create at least one active template before you can schedule posts.', 'ai-post-scheduler'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aips-templates')); ?>"><?php esc_html_e('Create Template', 'ai-post-scheduler'); ?></a></p>
        </div>
        <?php endif; ?>

        <div class="aips-schedules-container">
            <?php if (!empty($schedules)): ?>
            <!-- Existing Table Code ... -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                        <th class="column-structure"><?php esc_html_e('Structure', 'ai-post-scheduler'); ?></th>
                        <th class="column-frequency"><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                        <th class="column-next-run"><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                        <th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($schedules as $schedule):
                    ?>
                    <tr data-schedule-id="<?php echo esc_attr($schedule->id); ?>">
                        <td class="column-template">
                            <?php echo esc_html($schedule->template_name ?: __('Unknown', 'ai-post-scheduler')); ?>
                            <?php if(!empty($schedule->topic)) echo '<br><small>Topic: '.esc_html($schedule->topic).'</small>'; ?>
                        </td>
                        <td class="column-structure">
                            <?php echo $schedule->article_structure_id ? 'Custom' : 'Default'; ?>
                        </td>
                        <td class="column-frequency">
                            <?php
                                if (isset($schedule->schedule_type) && $schedule->schedule_type === 'advanced') {
                                    echo '<strong>' . esc_html__('Advanced', 'ai-post-scheduler') . '</strong>';
                                } else {
                                    echo esc_html(ucfirst(str_replace('_', ' ', $schedule->frequency)));
                                }
                            ?>
                        </td>
                        <td class="column-next-run">
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->next_run))); ?>
                        </td>
                        <td class="column-status">
                             <label class="aips-toggle">
                                <input type="checkbox" class="aips-toggle-schedule" data-id="<?php echo esc_attr($schedule->id); ?>" <?php checked($schedule->is_active, 1); ?>>
                                <span class="aips-toggle-slider"></span>
                            </label>
                        </td>
                        <td class="column-actions">
                            <button class="button button-link-delete aips-delete-schedule" data-id="<?php echo esc_attr($schedule->id); ?>">
                                <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="aips-empty-state">
                <p><?php esc_html_e('No active schedules found.', 'ai-post-scheduler'); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Shared Modal for Adding Schedule -->
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
                            uasort($cron_schedules, function($a, $b) { return $a['interval'] - $b['interval']; });
                            foreach ($cron_schedules as $key => $schedule) {
                                echo '<option value="' . esc_attr($key) . '" ' . selected('daily', $key, false) . '>' . esc_html($schedule['display']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="schedule_start_time"><?php esc_html_e('Start Time', 'ai-post-scheduler'); ?></label>
                        <input type="datetime-local" id="schedule_start_time" name="start_time">
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

<style>
/* Calendar Grid Styles */
.aips-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #ccd0d4;
    border: 1px solid #ccd0d4;
}
.aips-calendar-header {
    background: #f0f0f1;
    padding: 10px;
    text-align: center;
    font-weight: bold;
}
.aips-calendar-day {
    background: #fff;
    min-height: 120px;
    padding: 5px;
    position: relative;
}
.aips-calendar-day.other-month {
    background: #f6f7f7;
    color: #a7aaad;
}
.aips-day-number {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 12px;
    color: #646970;
}
.aips-event {
    display: block;
    margin: 2px 0;
    padding: 2px 4px;
    border-radius: 3px;
    color: #fff;
    font-size: 11px;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}
.aips-event:hover {
    opacity: 0.9;
}
.legend-item { margin-right: 15px; font-size: 12px; }
.legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
</style>
