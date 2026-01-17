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
        <div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
            <label class="screen-reader-text" for="aips-schedule-search"><?php esc_html_e('Search Schedules:', 'ai-post-scheduler'); ?></label>
            <input type="search" id="aips-schedule-search" class="regular-text" placeholder="<?php esc_attr_e('Search schedules...', 'ai-post-scheduler'); ?>">
            <button type="button" id="aips-schedule-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                    <th class="column-structure"><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></th>
                    <th class="column-frequency"><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                    <th class="column-next-run"><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                    <th class="column-last-run"><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
                    <th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
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
                    <td class="column-template">
                        <?php echo esc_html($schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler')); ?>
                    </td>
                    <td class="column-structure">
                        <?php echo esc_html($structure_display); ?>
                        <?php if (!empty($schedule->rotation_pattern)): ?>
                            <br><small style="color: #666;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $schedule->rotation_pattern))); ?></small>
                        <?php endif; ?>
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
                        <?php 
                        $status = isset($schedule->status) ? $schedule->status : 'active';
                        $status_class = '';
                        $status_icon = '';
                        $status_text = '';
                        
                        switch ($status) {
                            case 'failed':
                                $status_class = 'aips-status-failed';
                                $status_icon = 'dashicons-warning';
                                $status_text = __('Failed', 'ai-post-scheduler');
                                break;
                            case 'inactive':
                                $status_class = 'aips-status-inactive';
                                $status_icon = 'dashicons-marker';
                                $status_text = __('Inactive', 'ai-post-scheduler');
                                break;
                            case 'active':
                            default:
                                $status_class = 'aips-status-active';
                                $status_icon = 'dashicons-yes';
                                $status_text = __('Active', 'ai-post-scheduler');
                                break;
                        }
                        ?>
                        <div class="aips-schedule-status-wrapper">
                            <span class="aips-schedule-status <?php echo esc_attr($status_class); ?>">
                                <span class="dashicons <?php echo esc_attr($status_icon); ?>"></span>
                                <?php echo esc_html($status_text); ?>
                            </span>
                            <label class="aips-toggle">
                                <input type="checkbox" class="aips-toggle-schedule" aria-label="<?php esc_attr_e('Toggle schedule status', 'ai-post-scheduler'); ?>" data-id="<?php echo esc_attr($schedule->id); ?>" <?php checked($schedule->is_active, 1); ?>>
                                <span class="aips-toggle-slider"></span>
                            </label>
                        </div>
                    </td>
                    <td class="column-actions">
                        <button class="button aips-clone-schedule" aria-label="<?php esc_attr_e('Clone schedule', 'ai-post-scheduler'); ?>">
                            <?php esc_html_e('Clone', 'ai-post-scheduler'); ?>
                        </button>
                        <button class="button button-link-delete aips-delete-schedule" data-id="<?php echo esc_attr($schedule->id); ?>" aria-label="<?php esc_attr_e('Delete schedule', 'ai-post-scheduler'); ?>">
                            <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="aips-schedule-search-no-results" class="aips-empty-state" style="display: none;">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <h3><?php esc_html_e('No Schedules Found', 'ai-post-scheduler'); ?></h3>
            <p><?php esc_html_e('No schedules match your search criteria.', 'ai-post-scheduler'); ?></p>
            <button type="button" class="button button-primary aips-clear-schedule-search-btn">
                <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
            </button>
        </div>
        <?php else: ?>
        <div class="aips-empty-state">
            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
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
