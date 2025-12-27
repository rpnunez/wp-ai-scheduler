<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="aips-dashboard-container">
    
    <?php if (!class_exists('Meow_MWAI_Core')): ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('AI Engine plugin is not installed or activated. This plugin requires Meow Apps AI Engine to function.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>

    <div class="aips-inner-nav">
        <a href="#" class="aips-inner-tab active" data-target="metrics"><?php esc_html_e('Metrics', 'ai-post-scheduler'); ?></a>
        <a href="#" class="aips-inner-tab" data-target="logs"><?php esc_html_e('Logs', 'ai-post-scheduler'); ?></a>
        <a href="#" class="aips-inner-tab" data-target="automation"><?php esc_html_e('Automation', 'ai-post-scheduler'); ?></a>
    </div>
    
    <!-- Metrics Tab -->
    <div id="aips-dashboard-metrics" class="aips-inner-content active">
        <div class="aips-header-actions" style="margin-bottom: 20px;">
            <button class="button button-secondary" id="aips-refresh-stats">
                <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh Stats', 'ai-post-scheduler'); ?>
            </button>
        </div>

        <div class="aips-stats-grid">
            <div class="aips-stat-card">
                <div class="aips-stat-icon dashicons dashicons-edit"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($stats['total']); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-stat-card">
                <div class="aips-stat-icon dashicons dashicons-chart-bar"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($stats['success_rate']); ?>%</span>
                    <span class="aips-stat-label"><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-stat-card aips-stat-warning">
                <div class="aips-stat-icon dashicons dashicons-warning"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($stats['failed']); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></span>
                </div>
            </div>

            <div class="aips-stat-card">
                <div class="aips-stat-icon dashicons dashicons-clock"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($stats['processing']); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Processing', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($suggestions)): ?>
        <div class="aips-suggestions-container" style="margin-bottom: 20px;">
            <?php foreach ($suggestions as $suggestion): ?>
            <div class="notice notice-<?php echo esc_attr($suggestion['type']); ?> inline" style="margin: 5px 0 15px 0;">
                <p><?php echo esc_html($suggestion['message']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="aips-card">
            <h2><?php esc_html_e('Template Performance', 'ai-post-scheduler'); ?></h2>
            <?php if (!empty($template_performance)): ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Total', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Performance', 'ai-post-scheduler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($template_performance as $temp): ?>
                    <tr>
                        <td><?php echo esc_html($temp['name']); ?></td>
                        <td><?php echo esc_html($temp['total']); ?></td>
                        <td><?php echo esc_html($temp['completed']); ?></td>
                        <td><?php echo esc_html($temp['success_rate']); ?>%</td>
                        <td style="width: 200px;">
                            <div class="aips-progress-bar">
                                <div class="aips-progress-fill <?php echo ($temp['success_rate'] < 50) ? 'low' : (($temp['success_rate'] < 80) ? 'medium' : 'high'); ?>"
                                     style="width: <?php echo esc_attr($temp['success_rate']); ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php esc_html_e('No template data available yet.', 'ai-post-scheduler'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logs Tab -->
    <div id="aips-dashboard-logs" class="aips-inner-content" style="display:none;">
        <div class="aips-card">
            <h2><?php esc_html_e('System Logs', 'ai-post-scheduler'); ?></h2>
            <p><?php esc_html_e('View the latest system activity and debugging information.', 'ai-post-scheduler'); ?></p>

            <div class="aips-log-viewer-controls">
                <button class="button button-secondary" id="aips-fetch-logs">
                    <span class="dashicons dashicons-search"></span> <?php esc_html_e('Fetch Latest Logs', 'ai-post-scheduler'); ?>
                </button>
            </div>
            
            <div id="aips-log-viewer" class="aips-log-console">
                <div class="aips-log-placeholder"><?php esc_html_e('Click "Fetch Latest Logs" to view system events.', 'ai-post-scheduler'); ?></div>
            </div>
        </div>
    </div>

    <!-- Automation Tab -->
    <div id="aips-dashboard-automation" class="aips-inner-content" style="display:none;">
        <div class="aips-card">
            <h2><?php esc_html_e('Automation Settings', 'ai-post-scheduler'); ?></h2>
            <form id="aips-automation-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Performance Monitoring', 'ai-post-scheduler'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="disable_low_performance" value="1" <?php checked($automation_settings['disable_low_performance'], 1); ?>>
                                <?php esc_html_e('Automatically deactivate templates with low success rates', 'ai-post-scheduler'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('If enabled, templates performing poorly will be paused automatically.', 'ai-post-scheduler'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Low Performance Threshold', 'ai-post-scheduler'); ?></th>
                        <td>
                            <input type="number" name="low_performance_threshold" value="<?php echo esc_attr($automation_settings['low_performance_threshold']); ?>" min="1" max="100" step="1" class="small-text"> %
                            <p class="description"><?php esc_html_e('Templates below this success rate will be considered "low performance".', 'ai-post-scheduler'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Minimum Generations', 'ai-post-scheduler'); ?></th>
                        <td>
                            <input type="number" name="min_generations_threshold" value="<?php echo esc_attr($automation_settings['min_generations_threshold']); ?>" min="1" step="1" class="small-text">
                            <p class="description"><?php esc_html_e('Minimum number of generations required before evaluating performance.', 'ai-post-scheduler'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Retry Logic', 'ai-post-scheduler'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_retry_failed" value="1" <?php checked($automation_settings['auto_retry_failed'], 1); ?>>
                                <?php esc_html_e('Automatically retry failed generations', 'ai-post-scheduler'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Retry Limit', 'ai-post-scheduler'); ?></th>
                        <td>
                            <input type="number" name="retry_limit" value="<?php echo esc_attr($automation_settings['retry_limit']); ?>" min="0" max="10" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum number of retry attempts per failed post.', 'ai-post-scheduler'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Automation Settings', 'ai-post-scheduler'); ?></button>
                    <span class="spinner"></span>
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.aips-inner-nav {
    border-bottom: 1px solid #ccc;
    margin-bottom: 20px;
    padding-bottom: 0;
}
.aips-inner-tab {
    display: inline-block;
    padding: 10px 15px;
    text-decoration: none;
    color: #444;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -1px;
    font-weight: 500;
}
.aips-inner-tab.active {
    background: #fff;
    border-color: #ccc;
    color: #000;
    font-weight: 600;
}
.aips-inner-tab:hover {
    background: #f1f1f1;
}
.aips-progress-bar {
    background: #eee;
    height: 10px;
    border-radius: 5px;
    overflow: hidden;
    width: 100%;
}
.aips-progress-fill {
    height: 100%;
    background: #46b450;
    width: 0;
    transition: width 0.3s ease;
}
.aips-progress-fill.medium { background: #ffb900; }
.aips-progress-fill.low { background: #dc3232; }

.aips-log-console {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    padding: 15px;
    height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    margin-top: 15px;
    white-space: pre-wrap;
}
.aips-log-placeholder {
    color: #666;
    text-align: center;
    padding-top: 100px;
}
</style>
