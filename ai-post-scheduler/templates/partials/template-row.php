<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Partial for rendering a single template row in the admin templates table.
 *
 * @var object $template
 * @var int $generated_count
 * @var array $pending_stats
 */
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
        <div class="aips-stats-group">
            <div class="aips-stat-item" title="<?php esc_attr_e('Total Posts Generated', 'ai-post-scheduler'); ?>">
                <span class="dashicons dashicons-edit"></span>
                <strong><?php echo esc_html($generated_count); ?></strong>
            </div>
            <div class="aips-stat-item" title="<?php esc_attr_e('Pending Today / This Week / This Month', 'ai-post-scheduler'); ?>">
                <span class="dashicons dashicons-clock"></span>
                <span><?php echo esc_html($pending_stats['today'] . ' / ' . $pending_stats['week'] . ' / ' . $pending_stats['month']); ?></span>
            </div>
        </div>
    </td>
    <td>
        <?php if ($template->is_active): ?>
            <span class="aips-badge aips-badge-success">
                <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?>
            </span>
        <?php else: ?>
            <span class="aips-badge aips-badge-neutral">
                <span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
            </span>
        <?php endif; ?>
    </td>
    <td class="column-actions">
        <div class="aips-action-buttons">
            <button class="aips-btn aips-btn-sm aips-edit-template" data-id="<?php echo esc_attr($template->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
                <span class="dashicons dashicons-edit"></span>
                <span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
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
