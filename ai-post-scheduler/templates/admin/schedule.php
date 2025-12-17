<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$scheduler = new AIPS_Scheduler();
$schedules = $scheduler->get_all_schedules();

$templates_handler = new AIPS_Templates();
$templates = $templates_handler->get_all(true);
?>
<div class="wrap aips-wrap">
    <h1>
        <?php esc_html_e('Post Schedules', 'ai-post-scheduler'); ?>
        <button class="page-title-action aips-add-schedule-btn"><?php esc_html_e('Add New', 'ai-post-scheduler'); ?></button>
    </h1>
    
    <?php if (empty($templates)): ?>
    <div class="notice notice-warning">
        <p><?php esc_html_e('You need to create at least one active template before you can schedule posts.', 'ai-post-scheduler'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=aips-templates')); ?>"><?php esc_html_e('Create Template', 'ai-post-scheduler'); ?></a></p>
    </div>
    <?php endif; ?>
    
    <div class="aips-schedules-container">
        <?php if (!empty($schedules)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                    <th class="column-frequency"><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                    <th class="column-next-run"><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                    <th class="column-last-run"><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
                    <th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $schedule): ?>
                <tr data-schedule-id="<?php echo esc_attr($schedule->id); ?>">
                    <td class="column-template">
                        <?php echo esc_html($schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler')); ?>
                    </td>
                    <td class="column-frequency">
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $schedule->frequency))); ?>
                    </td>
                    <td class="column-next-run">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->next_run))); ?>
                    </td>
                    <td class="column-last-run">
                        <?php 
                        if ($schedule->last_run) {
                            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($schedule->last_run)));
                        } else {
                            esc_html_e('Never', 'ai-post-scheduler');
                        }
                        ?>
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
            <span class="dashicons dashicons-calendar-alt"></span>
            <h3><?php esc_html_e('No Schedules Yet', 'ai-post-scheduler'); ?></h3>
            <p><?php esc_html_e('Create a schedule to automatically generate posts on a regular basis.', 'ai-post-scheduler'); ?></p>
            <?php if (!empty($templates)): ?>
            <button class="button button-primary button-large aips-add-schedule-btn">
                <?php esc_html_e('Create Schedule', 'ai-post-scheduler'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="aips-schedule-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content">
            <div class="aips-modal-header">
                <h2><?php esc_html_e('Add New Schedule', 'ai-post-scheduler'); ?></h2>
                <button class="aips-modal-close">&times;</button>
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
                            <option value="hourly"><?php esc_html_e('Hourly', 'ai-post-scheduler'); ?></option>
                            <option value="every_6_hours"><?php esc_html_e('Every 6 Hours', 'ai-post-scheduler'); ?></option>
                            <option value="every_12_hours"><?php esc_html_e('Every 12 Hours', 'ai-post-scheduler'); ?></option>
                            <option value="daily" selected><?php esc_html_e('Daily', 'ai-post-scheduler'); ?></option>
                            <option value="weekly"><?php esc_html_e('Weekly', 'ai-post-scheduler'); ?></option>
                        </select>
                    </div>
                    
                    <div class="aips-form-row">
                        <label for="schedule_start_time"><?php esc_html_e('Start Time', 'ai-post-scheduler'); ?></label>
                        <input type="datetime-local" id="schedule_start_time" name="start_time">
                        <p class="description"><?php esc_html_e('Leave empty to start from now', 'ai-post-scheduler'); ?></p>
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
