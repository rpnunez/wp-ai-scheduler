<?php
/**
 * History Row Partial
 *
 * @var object $item History item object.
 */
if (!defined('ABSPATH')) {
    exit;
}
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
        <span class="aips-meta-text"><?php
        if (!empty($item->template_name)) {
            echo esc_html($item->template_name);
        } elseif (!empty($item->template_id)) {
            echo esc_html(sprintf(__('Template #%d (deleted)', 'ai-post-scheduler'), $item->template_id));
        } elseif (!empty($item->topic_id)) {
            echo esc_html__('From Topic', 'ai-post-scheduler');
        } else {
            echo '-';
        }
        ?></span>
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
            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
            <?php echo esc_html(ucfirst($item->status)); ?>
        </span>
    </td>
    <td class="column-date">
        <span class="aips-meta-text"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?></span>
    </td>
    <td class="column-actions">
        <div class="aips-btn-group aips-btn-group-inline">
        <?php if ($item->post_id): ?>
            <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" title="<?php esc_attr_e('View Post', 'ai-post-scheduler'); ?>">
                <span class="dashicons dashicons-external"></span>
                <?php esc_html_e('View', 'ai-post-scheduler'); ?>
            </a>
        <?php elseif ($item->status === 'processing'): ?>
            <button class="aips-btn aips-btn-sm aips-btn-secondary aips-view-session" data-history-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Session', 'ai-post-scheduler'); ?>">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e('View Session', 'ai-post-scheduler'); ?>
            </button>
        <?php endif; ?>

        <button class="aips-btn aips-btn-sm aips-btn-secondary aips-view-details" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Details', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('Details', 'ai-post-scheduler'); ?>
        </button>
        
        <?php if ($item->status === 'failed' && $item->template_id): ?>
            <button class="aips-btn aips-btn-sm aips-btn-secondary aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Retry Generation', 'ai-post-scheduler'); ?>">
                <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Retry', 'ai-post-scheduler'); ?>
        </button>
        <?php endif; ?>

        </div>
    </td>
</tr>
