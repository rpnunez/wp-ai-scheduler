<?php
if (!defined('ABSPATH')) {
    exit;
}

$templates_handler = new AIPS_Templates();
$templates = $templates_handler->get_all(true);

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
        <p>
            <a class="button button-secondary"
               hx-get="<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=aips_htmx_get_schedules"
               hx-headers='{"X-WP-Nonce":"<?php echo esc_attr(wp_create_nonce('aips_ajax_nonce')); ?>"}'
               hx-trigger="click"
               hx-target="#aips-schedules-container"
               hx-swap="innerHTML">
               <?php echo esc_html__('Load schedules', 'ai-post-scheduler'); ?>
            </a>
        </p>

        <div id="aips-schedules-container"
             hx-get="<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=aips_htmx_get_schedules"
             hx-trigger="load"
             hx-headers='{"X-WP-Nonce":"<?php echo esc_attr(wp_create_nonce('aips_ajax_nonce')); ?>"}'
             hx-swap="innerHTML">
            <p><?php echo esc_html__('Loading schedules…', 'ai-post-scheduler'); ?></p>
        </div>

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
