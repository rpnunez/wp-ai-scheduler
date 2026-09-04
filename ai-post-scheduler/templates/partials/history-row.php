<?php
/**
 * History Row Partial
 *
 * @var object $item History item object.
 */
if (!defined('ABSPATH')) {
    exit;
}

$is_child_row = !empty($is_child_row);
$group_id = !empty($group_id) ? $group_id : '';
$row_classes = array('aips-history-row', 'aips-view-history-logs');
if ($is_child_row) {
    $row_classes[] = 'aips-history-group-child';
}
?>
<tr class="<?php echo esc_attr(implode(' ', $row_classes)); ?>" data-id="<?php echo esc_attr($item->id); ?>" <?php if ($is_child_row): ?>data-group-id="<?php echo esc_attr($group_id); ?>" style="display: none;"<?php endif; ?> tabindex="0" aria-label="<?php echo esc_attr(sprintf(__('Open details for %s', 'ai-post-scheduler'), AIPS_History::get_display_title($item))); ?>">
    <th scope="row" class="check-column">
        <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->id); ?>">
            <?php esc_html_e('Select Item', 'ai-post-scheduler'); ?>
        </label>
        <input id="cb-select-<?php echo esc_attr($item->id); ?>" type="checkbox" class="aips-history-cb" name="history[]" value="<?php echo esc_attr($item->id); ?>" <?php if ($is_child_row): ?>data-group-id="<?php echo esc_attr($group_id); ?>"<?php endif; ?>>
    </th>
    <td class="column-title">
        <?php if ($is_child_row): ?>
        <span class="aips-group-child-indent" aria-hidden="true">&rdsh;&nbsp;</span>
        <?php endif; ?>
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
        <?php if (!empty($item->latest_message)): ?>
        <span class="aips-history-latest-message"><?php echo esc_html(wp_trim_words((string) $item->latest_message, 14)); ?></span>
        <?php endif; ?>
    </td>
    <td class="column-post-type">
        <?php if (!empty($item->post_type)): ?>
        <?php $post_type_obj = get_post_type_object($item->post_type); ?>
        <span class="aips-badge aips-badge-neutral">
            <?php echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : $item->post_type); ?>
        </span>
        <?php else: ?>
        <span class="aips-meta-text">&mdash;</span>
        <?php endif; ?>
    </td>
    <td class="column-status">
        <?php
        $status_class = 'aips-badge ';
        switch ($item->status) {
            case 'completed':
            case 'indexed':
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
        <?php if ($item->error_count > 0 || $item->warning_count > 0): ?>
            <span class="aips-history-issue-counts">
                <?php if ($item->error_count > 0): ?><span class="aips-history-count-error"><?php echo esc_html(sprintf(_n('%d error', '%d errors', $item->error_count, 'ai-post-scheduler'), $item->error_count)); ?></span><?php endif; ?>
                <?php if ($item->warning_count > 0): ?><span><?php echo esc_html(sprintf(_n('%d warning', '%d warnings', $item->warning_count, 'ai-post-scheduler'), $item->warning_count)); ?></span><?php endif; ?>
            </span>
        <?php endif; ?>
    </td>
    <td class="column-type">
        <strong><?php echo !empty($item->creation_method) ? esc_html(AIPS_History::get_creation_method_label($item->creation_method)) : '&mdash;'; ?></strong>
        <span class="aips-history-activity-meta">
            <?php if (!empty($item->duration_label)): ?><span><?php echo esc_html($item->duration_label); ?></span><?php endif; ?>
            <?php if ($item->ai_call_count > 0): ?><span><?php echo esc_html(sprintf(_n('%d AI call', '%d AI calls', $item->ai_call_count, 'ai-post-scheduler'), $item->ai_call_count)); ?></span><?php endif; ?>
        </span>
    </td>
    <td class="column-date">
        <time class="aips-meta-text" datetime="<?php echo esc_attr(wp_date('c', (int) $item->created_at)); ?>" title="<?php echo esc_attr($item->formatted_date); ?>"><?php echo esc_html($item->relative_date); ?></time>
        <div class="cell-actions">
            <div class="aips-row-action-group">
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
                    <?php esc_html_e('View Topic', 'ai-post-scheduler'); ?>
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
