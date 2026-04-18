<?php
if (!defined('ABSPATH')) {
    exit;
}

// Type-label helper for unified schedule types.
$schedule_type_labels = array(
    'template_schedule' => __('Template', 'ai-post-scheduler'),
    'author_topic_gen'  => __('Topic Gen', 'ai-post-scheduler'),
    'author_post_gen'   => __('Post Gen', 'ai-post-scheduler'),
);
?>
<div class="wrap aips-wrap">
    <?php if (!class_exists('Meow_MWAI_Core')): ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('AI Engine plugin is not installed or activated. This plugin requires AI Engine to function.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>

    <div class="aips-page-container" id="aips-dashboard-panel">

        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Dashboard', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Overview of your AI content generation activity and quick actions.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions" style="flex-wrap: wrap;">
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Templates', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Schedules', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('authors')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e('Authors', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('settings')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('Settings', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" class="aips-btn aips-btn-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main 50/50 Grid -->
        <div class="aips-grid aips-grid-cols-2">

            <!-- Left: Upcoming Scheduled Activity -->
            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Upcoming Scheduled Activity', 'ai-post-scheduler'); ?></h2>
                    <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-ghost aips-btn-sm">
                        <?php esc_html_e('View All', 'ai-post-scheduler'); ?> &rarr;
                    </a>
                </div>
                <div class="aips-panel-body <?php echo empty($upcoming) ? '' : 'no-padding'; ?>">
                    <?php if (!empty($upcoming)): ?>
                    <table class="aips-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $item): ?>
                            <tr>
                                <td>
                                    <div class="cell-primary"><?php echo esc_html(isset($item['title']) ? $item['title'] : __('Unknown', 'ai-post-scheduler')); ?></div>
                                    <?php if (!empty($item['subtitle'])): ?>
                                    <div class="cell-meta"><?php echo esc_html($item['subtitle']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $type_key   = isset($item['type']) ? $item['type'] : '';
                                    $type_label = isset($schedule_type_labels[$type_key]) ? $schedule_type_labels[$type_key] : esc_html__('Schedule', 'ai-post-scheduler');
                                    ?>
                                    <span class="aips-badge aips-badge-neutral"><?php echo esc_html($type_label); ?></span>
                                </td>
                                <td><?php echo esc_html(!empty($item['next_run']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['next_run'])) : __('—', 'ai-post-scheduler')); ?></td>
                                <td>
                                    <span class="aips-badge aips-badge-info"><?php echo esc_html(ucfirst(isset($item['frequency']) ? $item['frequency'] : '')); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="aips-empty-state">
                        <div class="dashicons dashicons-calendar-alt aips-empty-state-icon" aria-hidden="true"></div>
                        <h3 class="aips-empty-state-title"><?php esc_html_e('No Active Schedules', 'ai-post-scheduler'); ?></h3>
                        <p class="aips-empty-state-description"><?php esc_html_e('Get started by creating your first schedule to automate content generation.', 'ai-post-scheduler'); ?></p>
                        <div class="aips-empty-state-actions">
                            <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-primary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e('Create Schedule', 'ai-post-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Compact Stats + Recent Activity (stacked) -->
            <div>

                <!-- Compact Stats List -->
                <div class="aips-content-panel" style="margin-bottom: 20px;">
                    <div class="aips-panel-body" style="padding: 12px 20px;">
                        <ul class="aips-dashboard-stats-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;">

                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts')); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-edit" style="color:var(--aips-primary);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-gray-900);"><?php echo esc_html($total_generated); ?></strong>
                            </a></li>

                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts') . '#aips-pending-review'); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-visibility" style="color:var(--aips-gray-500);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Pending Reviews', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-gray-900);"><?php echo esc_html($pending_reviews); ?></strong>
                            </a></li>

                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-clock" style="color:var(--aips-gray-500);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Active Schedules', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-gray-900);"><?php echo esc_html($pending_scheduled); ?></strong>
                            </a></li>

                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-media-document" style="color:var(--aips-gray-500);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Active Templates', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-gray-900);"><?php echo esc_html($total_templates); ?></strong>
                            </a></li>

                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('authors')); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-list-view" style="color:var(--aips-gray-500);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Topics in Queue', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-gray-900);"><?php echo esc_html($topics_in_queue); ?></strong>
                            </a></li>

                            <?php if ($partial_generations > 0): ?>
                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('s' => 'partial'))); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-warning" style="color:var(--aips-warning);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Partial Generations', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-warning);"><?php echo esc_html($partial_generations); ?></strong>
                            </a></li>
                            <?php endif; ?>

                            <?php if ($failed_count > 0): ?>
                            <li><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('s' => 'failed'))); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:4px 0;">
                                <span class="dashicons dashicons-dismiss" style="color:var(--aips-error);font-size:18px;width:20px;height:20px;"></span>
                                <span style="flex:1;font-size:13px;"><?php esc_html_e('Failed Generations', 'ai-post-scheduler'); ?></span>
                                <strong style="font-size:15px;color:var(--aips-error);"><?php echo esc_html($failed_count); ?></strong>
                            </a></li>
                            <?php endif; ?>

                        </ul>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="aips-content-panel">
                    <div class="aips-panel-header">
                        <h2 class="aips-panel-title"><?php esc_html_e('Recent Activity', 'ai-post-scheduler'); ?></h2>
                        <?php if (!empty($recent_posts)): ?>
                        <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('history')); ?>" class="aips-btn aips-btn-ghost aips-btn-sm">
                            <?php esc_html_e('View All', 'ai-post-scheduler'); ?> &rarr;
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="aips-panel-body <?php echo empty($recent_posts) ? '' : 'no-padding'; ?>">
                        <?php if (!empty($recent_posts)): ?>
                        <table class="aips-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
                                    <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                    <th><?php esc_html_e('Date', 'ai-post-scheduler'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_posts as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($item->post_id): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="cell-primary">
                                            <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                                        </a>
                                        <?php else: ?>
                                        <div class="cell-primary"><?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'neutral';
                                        if ($item->status === 'completed') {
                                            $status_class = 'success';
                                        } elseif ($item->status === 'failed') {
                                            $status_class = 'error';
                                        } elseif ($item->status === 'pending') {
                                            $status_class = 'warning';
                                        }
                                        ?>
                                        <span class="aips-badge aips-badge-<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html(ucfirst($item->status)); ?>
                                        </span>
                                    </td>
                                    <td class="cell-meta"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->created_at))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="aips-empty-state">
                            <div class="dashicons dashicons-admin-post aips-empty-state-icon" aria-hidden="true"></div>
                            <h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Yet', 'ai-post-scheduler'); ?></h3>
                            <p class="aips-empty-state-description"><?php esc_html_e('Start generating content by creating templates and schedules.', 'ai-post-scheduler'); ?></p>
                            <div class="aips-empty-state-actions">
                                <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" class="aips-btn aips-btn-primary">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                                </a>
                                <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-secondary">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Charts Row -->
        <div class="aips-grid aips-grid-cols-2">

            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Post Generations by Day', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <div class="aips-dashboard-chart-wrap" style="position:relative;height:220px;">
                        <canvas id="aips-chart-posts-by-day" aria-label="<?php esc_attr_e('Post Generations by Day', 'ai-post-scheduler'); ?>" role="img"></canvas>
                    </div>
                </div>
            </div>

            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Topic Generations by Day', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <div class="aips-dashboard-chart-wrap" style="position:relative;height:220px;">
                        <canvas id="aips-chart-topics-by-day" aria-label="<?php esc_attr_e('Topic Generations by Day', 'ai-post-scheduler'); ?>" role="img"></canvas>
                    </div>
                </div>
            </div>

        </div>

        <div class="aips-grid aips-grid-cols-2">

            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('AI Error Rate (%)', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <div class="aips-dashboard-chart-wrap" style="position:relative;height:200px;">
                        <canvas id="aips-chart-error-rate" aria-label="<?php esc_attr_e('AI Error Rate', 'ai-post-scheduler'); ?>" role="img"></canvas>
                    </div>
                </div>
            </div>

            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Generation Overview (14 days)', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <ul style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <li style="text-align:center;">
                            <div style="font-size:32px;font-weight:600;color:var(--aips-gray-900);"><?php echo esc_html(array_sum($chart_completed)); ?></div>
                            <div style="font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:.05em;"><?php esc_html_e('Posts Completed', 'ai-post-scheduler'); ?></div>
                        </li>
                        <li style="text-align:center;">
                            <div style="font-size:32px;font-weight:600;color:var(--aips-error);"><?php echo esc_html(array_sum($chart_failed)); ?></div>
                            <div style="font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:.05em;"><?php esc_html_e('Posts Failed', 'ai-post-scheduler'); ?></div>
                        </li>
                        <li style="text-align:center;">
                            <div style="font-size:32px;font-weight:600;color:var(--aips-success);"><?php echo esc_html(array_sum($chart_topics)); ?></div>
                            <div style="font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:.05em;"><?php esc_html_e('Topics Created', 'ai-post-scheduler'); ?></div>
                        </li>
                        <li style="text-align:center;">
                            <?php
                            $total_in_period = array_sum($chart_completed) + array_sum($chart_failed);
                            $success_pct     = $total_in_period > 0 ? round((array_sum($chart_completed) / $total_in_period) * 100) : 100;
                            ?>
                            <div style="font-size:32px;font-weight:600;color:var(--aips-primary);"><?php echo esc_html($success_pct); ?>%</div>
                            <div style="font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:.05em;"><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></div>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
window.aipsDashboardChartData = <?php echo wp_json_encode($chart_data); ?>;
</script>
