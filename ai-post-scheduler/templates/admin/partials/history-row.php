<?php
if (!defined('ABSPATH')) {
    exit;
}

// Expects $item and $is_history_tab to be defined
?>
<tr>
    <th scope="row" class="check-column">
        <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->id); ?>"><?php esc_html_e('Select Item', 'ai-post-scheduler'); ?></label>
        <input id="cb-select-<?php echo esc_attr($item->id); ?>" type="checkbox" name="history[]" value="<?php echo esc_attr($item->id); ?>">
    </th>
    <td class="column-title">
        <?php if ($item->post_id): ?>
        <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
            <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
        </a>
        <?php else: ?>
        <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
        <?php endif; ?>
        <?php if ($item->status === 'failed' && $item->error_message): ?>
        <div class="aips-error-message" style="font-size: 12px; color: #dc3232; margin-top: 4px;"><?php echo esc_html($item->error_message); ?></div>
        <?php endif; ?>
    </td>
    <td class="column-template">
        <span class="aips-meta-text"><?php echo esc_html($item->template_name ?: '-'); ?></span>
    </td>
    <td class="column-status">
        <?php
        $status_class = 'aips-badge ';
        switch ($item->status) {
            case 'completed':
                $status_class .= 'aips-badge-success';
                $icon = 'yes-alt';
                break;
            case 'failed':
                $status_class .= 'aips-badge-error';
                $icon = 'dismiss';
                break;
            case 'processing':
                $status_class .= 'aips-badge-info';
                $icon = 'update';
                break;
            default:
                $status_class .= 'aips-badge-neutral';
                $icon = 'minus';
        }
        ?>
        <span class="<?php echo esc_attr($status_class); ?>">
            <?php if (!$is_history_tab): ?><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><?php endif; ?>
            <?php echo esc_html(ucfirst($item->status)); ?>
        </span>
    </td>
    <td class="column-date">
        <span class="aips-meta-text"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?></span>
    </td>
    <td class="column-actions">
        <div class="aips-btn-group">
            <?php if ($item->post_id): ?>
            <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" class="<?php echo !$is_history_tab ? 'aips-btn aips-btn-sm' : 'button button-small'; ?>" target="_blank" title="<?php esc_attr_e('View Post', 'ai-post-scheduler'); ?>">
                <?php if (!$is_history_tab): ?><span class="dashicons dashicons-external"></span><?php else: ?><?php esc_html_e('View', 'ai-post-scheduler'); ?><?php endif; ?>
            </a>
            <?php endif; ?>
            <button class="<?php echo !$is_history_tab ? 'aips-btn aips-btn-sm' : 'button button-small'; ?> aips-view-details" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Details', 'ai-post-scheduler'); ?>">
                <?php if (!$is_history_tab): ?><span class="dashicons dashicons-info"></span><?php else: ?><?php esc_html_e('Details', 'ai-post-scheduler'); ?><?php endif; ?>
            </button>
            <?php if ($item->status === 'failed' && $item->template_id): ?>
            <button class="<?php echo !$is_history_tab ? 'aips-btn aips-btn-sm' : 'button button-small'; ?> aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Retry Generation', 'ai-post-scheduler'); ?>">
                <?php if (!$is_history_tab): ?><span class="dashicons dashicons-update"></span><?php else: ?><?php esc_html_e('Retry', 'ai-post-scheduler'); ?><?php endif; ?>
            </button>
            <?php endif; ?>
        </div>
    </td>
</tr>
