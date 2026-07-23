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
        <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->id); ?>">
            <?php esc_html_e('Select Item', 'ai-post-scheduler'); ?>
        </label>
        <input id="cb-select-<?php echo esc_attr($item->id); ?>" type="checkbox" class="aips-history-cb" name="history[]" value="<?php echo esc_attr($item->id); ?>">
    </th>
    <td class="column-title">
        <?php $display_title = AIPS_History::get_display_title( $item ); ?>
        <?php if ($item->post_id): ?>
        <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
            <strong class="aips-history-title"><?php echo esc_html($display_title); ?></strong>
        </a>
        <?php else: ?>
        <strong class="aips-history-title"><?php echo esc_html($display_title); ?></strong>
        <?php endif; ?>
        <?php if (!empty($item->template_name)): ?>
        <span class="aips-history-subtitle"><?php echo esc_html($item->template_name); ?></span>
        <?php elseif (!empty($item->template_id)): ?>
        <span class="aips-history-subtitle"><?php echo esc_html(sprintf(__('Template #%d (deleted)', 'ai-post-scheduler'), $item->template_id)); ?></span>
        <?php endif; ?>
        <?php if (!empty($item->creation_method)): ?>
        <span class="aips-badge aips-badge-neutral aips-creation-method-badge">
            <?php echo esc_html(AIPS_History::get_creation_method_label($item->creation_method)); ?>
        </span>
        <?php endif; ?>
        <?php if ($item->status === 'failed' && $item->error_message): ?>
        <div class="aips-error-message" style="font-size: 12px; color: #dc3232; margin-top: 4px;"><?php echo esc_html($item->error_message); ?></div>
        <?php endif; ?>
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
        <span class="aips-meta-text"><?php echo esc_html($item->formatted_date); ?></span>
    </td>
    <td class="column-actions">
        <div class="cell-actions">
            <div class="aips-row-action-group">
                <button type="button"
                        class="aips-btn aips-btn-sm aips-btn-secondary aips-view-history-logs"
                        data-id="<?php echo esc_attr($item->id); ?>">
                    <?php esc_html_e('View Logs', 'ai-post-scheduler'); ?>
                </button>
                <button type="button"
                        class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="aips-history-row-actions-<?php echo esc_attr($item->id); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
                </button>
            </div>
            <div id="aips-history-row-actions-<?php echo esc_attr($item->id); ?>"
                 class="aips-row-action-menu"
                 hidden>
                <?php if (!empty($item->template_id) || !empty($item->topic_id)): ?>
                <button type="button"
                        class="aips-row-action-item aips-view-session"
                        data-history-id="<?php echo esc_attr($item->id); ?>">
                    <?php esc_html_e('View Session', 'ai-post-scheduler'); ?>
                </button>
                <?php endif; ?>
                <?php if ($item->post_id): ?>
                <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>"
                   class="aips-row-action-item"
                   target="_blank"
                   rel="noopener noreferrer">
                    <?php esc_html_e('View Post', 'ai-post-scheduler'); ?>
                </a>
                <?php endif; ?>
                <?php if ($item->status === 'failed' && $item->template_id): ?>
                <button type="button"
                        class="aips-row-action-item aips-retry-generation"
                        data-id="<?php echo esc_attr($item->id); ?>">
                    <?php esc_html_e('Retry', 'ai-post-scheduler'); ?>
                </button>
                <?php endif; ?>
                <button type="button"
                        class="aips-row-action-item aips-delete-history"
                        data-id="<?php echo esc_attr($item->id); ?>">
                    <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                </button>
            </div>
        </div>
    </td>
</tr>
