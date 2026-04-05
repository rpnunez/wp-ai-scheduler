<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Post Templates', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Create and manage AI post generation templates with custom prompts and settings.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <button class="aips-btn aips-btn-primary aips-add-template-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Template', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (!empty($templates)): ?>
        <!-- Content Panel with Filter Bar -->
        <div class="aips-content-panel">
            <!-- Filter Bar -->
            <div class="aips-filter-bar">
                <div class="aips-filter-right">
                    <label class="screen-reader-text" for="aips-template-search"><?php esc_html_e('Search Templates:', 'ai-post-scheduler'); ?></label>
                    <input type="search" id="aips-template-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search templates...', 'ai-post-scheduler'); ?>">
                    <button type="button" id="aips-template-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                </div>
            </div>
            
            <!-- Templates Table -->
            <div class="aips-panel-body no-padding aips-templates-list">
                <table class="aips-table">
                    <thead>
                        <tr>
                            <th class="column-name"><?php esc_html_e('Template Name', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></th>
                            <th class="column-category"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Statistics', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history_service = new AIPS_History();
                        $templates_class = new AIPS_Templates();

                        // Pre-fetch stats to avoid N+1 queries
                        $all_generated_counts = $history_service->get_all_template_stats();
                        $all_pending_stats = $templates_class->get_all_pending_stats();

                        foreach ($templates as $template):
                            $generated_count = isset($all_generated_counts[$template->id]) ? $all_generated_counts[$template->id] : 0;
                            $pending_stats = isset($all_pending_stats[$template->id]) ? $all_pending_stats[$template->id] : array('today' => 0, 'week' => 0, 'month' => 0);
                        ?>
                        <tr data-template-id="<?php echo esc_attr($template->id); ?>">
                            <td class="column-name">
                                <div class="cell-primary"><?php echo esc_html($template->name); ?></div>
                            </td>
                            <td>
                                <span class="aips-badge aips-badge-neutral">
                                    <?php echo esc_html(ucfirst($template->post_status)); ?>
                                </span>
                            </td>
                            <td class="column-category">
                                <?php 
                                if ($template->post_category) {
                                    $cat = get_category($template->post_category);
                                    echo esc_html($cat ? $cat->name : '-');
                                } else {
                                    echo '<span class="cell-meta">—</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div>
                                        <strong style="font-size: 14px;"><?php echo esc_html($generated_count); ?></strong>
                                        <span class="cell-meta"><?php esc_html_e('generated', 'ai-post-scheduler'); ?></span>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'aips-generated-posts', 'template_id' => absint( $template->id ) ), admin_url( 'admin.php' ) ) ); ?>" style="font-size: 12px; margin-left: 4px;">
                                            <?php esc_html_e('(view)', 'ai-post-scheduler'); ?>
                                        </a>
                                    </div>
                                    <div class="cell-meta" style="font-size: 11px;">
                                        <?php esc_html_e('Pending:', 'ai-post-scheduler'); ?>
                                        <?php esc_html_e('Today:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['today']); ?> |
                                        <?php esc_html_e('Week:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['week']); ?> |
                                        <?php esc_html_e('Month:', 'ai-post-scheduler'); ?> <?php echo esc_html($pending_stats['month']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($template->is_active): ?>
                                <span class="aips-badge aips-badge-success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('Active', 'ai-post-scheduler'); ?>
                                </span>
                                <?php else: ?>
                                <span class="aips-badge aips-badge-neutral">
                                    <span class="dashicons dashicons-minus"></span>
                                    <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cell-actions">
                                    <button class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                                    </button>
                                    <button class="aips-btn aips-btn-sm aips-btn-secondary aips-run-now" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Run Now', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-controls-play"></span>
                                        <?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
                                    </button>
                                    <a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule', array('schedule_template' => $template->id))); ?>" title="<?php esc_attr_e('Schedule', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Schedule', 'ai-post-scheduler'); ?></span>
                                    </a>
                                    <button class="aips-btn aips-btn-sm aips-btn-ghost aips-clone-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Clone', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Clone', 'ai-post-scheduler'); ?></span>
                                    </button>
                                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- No Search Results State -->
                <div id="aips-template-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
                    <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Templates Found', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('No templates match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <button type="button" class="aips-btn aips-btn-primary aips-clear-search-btn">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Table footer -->
            <div class="tablenav">
                <span class="aips-table-footer-count">
                    <?php
                    $template_count = count( $templates );
                    printf(
                        esc_html(
                            _n(
                                '%s template',
                                '%s templates',
                                $template_count,
                                'ai-post-scheduler'
                            )
                        ),
                        number_format_i18n( $template_count )
                    );
                    ?>
                </span>
            </div>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="aips-content-panel">
            <div class="aips-panel-body">
                <div class="aips-empty-state">
                    <div class="dashicons dashicons-media-document aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No Templates Yet', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('Templates define how your AI-generated posts are structured. Create your first template to start generating content automatically.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <button class="aips-btn aips-btn-primary aips-add-template-btn">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include modals
include AIPS_PLUGIN_DIR . 'templates/admin/partials/template-wizard-modal.php';
include AIPS_PLUGIN_DIR . 'templates/admin/partials/test-result-modal.php';
include AIPS_PLUGIN_DIR . 'templates/admin/partials/post-success-modal.php';
?>
