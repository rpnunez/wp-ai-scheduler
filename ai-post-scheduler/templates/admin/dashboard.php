<?php
if (!defined('ABSPATH')) {
    exit;
}

// Fallbacks for contexts that do not provide all dashboard variables.
if (!isset($schedule_type_labels) || !is_array($schedule_type_labels)) {
    $schedule_type_labels = array(
        'template_schedule' => __('Template', 'ai-post-scheduler'),
        'author_topic_gen'  => __('Topic Gen', 'ai-post-scheduler'),
        'author_post_gen'   => __('Post Gen', 'ai-post-scheduler'),
    );
}

if (!isset($chart_completed) || !is_array($chart_completed)) {
    $chart_completed = (isset($chart_data['completed']) && is_array($chart_data['completed'])) ? $chart_data['completed'] : array();
}
if (!isset($chart_failed) || !is_array($chart_failed)) {
    $chart_failed = (isset($chart_data['failed']) && is_array($chart_data['failed'])) ? $chart_data['failed'] : array();
}
if (!isset($chart_topics) || !is_array($chart_topics)) {
    $chart_topics = (isset($chart_data['topics']) && is_array($chart_data['topics'])) ? $chart_data['topics'] : array();
}

$total_generated     = isset($total_generated) ? (int) $total_generated : 0;
$pending_reviews     = isset($pending_reviews) ? (int) $pending_reviews : 0;
$topics_in_queue     = isset($topics_in_queue) ? (int) $topics_in_queue : 0;
$partial_generations = isset($partial_generations) ? (int) $partial_generations : 0;
$failed_count        = isset($failed_count) ? (int) $failed_count : 0;
$upcoming            = isset($upcoming) && is_array($upcoming) ? $upcoming : array();
$recent_posts        = isset($recent_posts) && is_array($recent_posts) ? $recent_posts : array();

if (!isset($chart_data) || !is_array($chart_data)) {
    $chart_data = array(
        'labels'    => array(),
        'completed' => $chart_completed,
        'failed'    => $chart_failed,
        'errorRate' => array(),
        'topics'    => $chart_topics,
    );
}
?>
<div id="aips-dashboard-panel" class="aips-hub-content-stack">
        <?php if (!class_exists('Meow_MWAI_Core')): ?>
        <div class="aips-hub-notice-stack">
            <div class="notice notice-error">
                <p><?php esc_html_e('AI Engine plugin is not installed or activated. This plugin requires AI Engine to function.', 'ai-post-scheduler'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Compact Stats List (full width, above tables) -->
        <div class="aips-content-panel aips-dashboard-stats-panel">
            <div class="aips-panel-body aips-dashboard-stats-body">
                <ul class="aips-dashboard-stats-list">

                    <li class="aips-stat-item"><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts')); ?>" class="aips-stat-link">
                        <span class="dashicons dashicons-edit aips-stat-icon aips-stat-icon--primary"></span>
                        <span class="aips-stat-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
                        <strong class="aips-stat-value"><?php echo esc_html($total_generated); ?></strong>
                    </a></li>

                    <li class="aips-stat-item"><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('subtab' => 'aips-pending-review'))); ?>" class="aips-stat-link">
                        <span class="dashicons dashicons-visibility aips-stat-icon"></span>
                        <span class="aips-stat-label"><?php echo esc_html( _n( 'Pending Review', 'Pending Reviews', $pending_reviews, 'ai-post-scheduler' ) ); ?></span>
                        <strong class="aips-stat-value"><?php echo esc_html($pending_reviews); ?></strong>
                    </a></li>

                    <li class="aips-stat-item"><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('authors')); ?>" class="aips-stat-link">
                        <span class="dashicons dashicons-list-view aips-stat-icon"></span>
                        <span class="aips-stat-label"><?php esc_html_e('Topics in Queue', 'ai-post-scheduler'); ?></span>
                        <strong class="aips-stat-value"><?php echo esc_html($topics_in_queue); ?></strong>
                    </a></li>

                    <?php if ($partial_generations > 0): ?>
                    <li class="aips-stat-item"><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('s' => 'partial'))); ?>" class="aips-stat-link">
                        <span class="dashicons dashicons-warning aips-stat-icon aips-stat-icon--warning"></span>
                        <span class="aips-stat-label"><?php esc_html_e('Partial Generations', 'ai-post-scheduler'); ?></span>
                        <strong class="aips-stat-value aips-stat-value--warning"><?php echo esc_html($partial_generations); ?></strong>
                    </a></li>
                    <?php endif; ?>

                    <?php if ($failed_count > 0): ?>
                    <li class="aips-stat-item"><a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('s' => 'failed'))); ?>" class="aips-stat-link">
                        <span class="dashicons dashicons-dismiss aips-stat-icon aips-stat-icon--error"></span>
                        <span class="aips-stat-label"><?php esc_html_e('Failed Generations', 'ai-post-scheduler'); ?></span>
                        <strong class="aips-stat-value aips-stat-value--error"><?php echo esc_html($failed_count); ?></strong>
                    </a></li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>

        <!-- Main 50/50 Grid -->
        <div class="aips-grid aips-grid-cols-2">

            <!-- Left: Upcoming Scheduled Activity -->
            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Upcoming Activity', 'ai-post-scheduler'); ?></h2>
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
                                <td><?php echo esc_html( isset( $item['next_run_formatted'] ) ? $item['next_run_formatted'] : '—' ); ?></td>
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

            <!-- Right: Recent Activity -->
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
                                    <td class="cell-meta"><?php echo esc_html($item->created_at_formatted); ?></td>
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

        <!-- Charts Row -->
        <div class="aips-grid aips-grid-cols-2">

            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Post Generations by Day', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <div class="aips-dashboard-chart-wrap aips-dashboard-chart-wrap--medium">
                        <canvas id="aips-chart-posts-by-day" aria-label="<?php esc_attr_e('Post Generations by Day', 'ai-post-scheduler'); ?>" role="img"></canvas>
                    </div>
                </div>
            </div>

            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Topic Generations by Day', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <div class="aips-dashboard-chart-wrap aips-dashboard-chart-wrap--medium">
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
                    <div class="aips-dashboard-chart-wrap aips-dashboard-chart-wrap--small">
                        <canvas id="aips-chart-error-rate" aria-label="<?php esc_attr_e('AI Error Rate', 'ai-post-scheduler'); ?>" role="img"></canvas>
                    </div>
                </div>
            </div>

            <div class="aips-content-panel aips-dashboard-overview-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Generation Overview (14 days)', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body aips-dashboard-overview-body">
                    <?php
                    $total_in_period = array_sum($chart_completed) + array_sum($chart_failed);
                    $success_pct     = $total_in_period > 0 ? round((array_sum($chart_completed) / $total_in_period) * 100) : 100;
                    ?>
                    <ul class="aips-dashboard-overview-grid">
                        <li class="aips-overview-cell aips-overview-cell--top-left">
                            <div class="aips-overview-stat-number"><?php echo esc_html(array_sum($chart_completed)); ?></div>
                            <div class="aips-overview-stat-label"><?php esc_html_e('Posts Completed', 'ai-post-scheduler'); ?></div>
                        </li>
                        <li class="aips-overview-cell aips-overview-cell--top-right">
                            <div class="aips-overview-stat-number aips-overview-stat-number--error"><?php echo esc_html(array_sum($chart_failed)); ?></div>
                            <div class="aips-overview-stat-label"><?php esc_html_e('Posts Failed', 'ai-post-scheduler'); ?></div>
                        </li>
                        <li class="aips-overview-cell aips-overview-cell--bottom-left">
                            <div class="aips-overview-stat-number aips-overview-stat-number--success"><?php echo esc_html(array_sum($chart_topics)); ?></div>
                            <div class="aips-overview-stat-label"><?php esc_html_e('Topics Created', 'ai-post-scheduler'); ?></div>
                        </li>
                        <li class="aips-overview-cell">
                            <div class="aips-overview-stat-number aips-overview-stat-number--primary"><?php echo esc_html($success_pct); ?>%</div>
                            <div class="aips-overview-stat-label"><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></div>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
</div>
<script>
window.aipsDashboardChartData = <?php echo wp_json_encode($chart_data); ?>;
</script>
