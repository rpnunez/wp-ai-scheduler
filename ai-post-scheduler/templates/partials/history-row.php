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
    <td class="column-container">
        <?php if (!empty($item->container_link)): ?>
        <a href="<?php echo esc_url($item->container_link); ?>">
            <?php echo esc_html($item->container_label); ?>
        </a>
        <?php else: ?>
        <?php echo esc_html($item->container_label); ?>
        <?php endif; ?>
        <?php if ($item->status === 'failed' && $item->error_message): ?>
        <div class="aips-error-message" style="font-size: 12px; color: #dc3232; margin-top: 4px;"><?php echo esc_html($item->error_message); ?></div>
        <?php endif; ?>
    </td>
    <td class="column-domain">
        <span class="aips-meta-text"><?php echo esc_html($item->domain_label); ?></span>
    </td>
    <td class="column-actor">
        <span class="aips-meta-text"><?php echo esc_html($item->actor_label); ?></span>
    </td>
    <td class="column-type">
        <span class="aips-meta-text"><?php echo esc_html($item->container_type_label); ?></span>
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
                <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-view-history-logs" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Logs', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('View Logs', 'ai-post-scheduler'); ?>">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('View Logs', 'ai-post-scheduler'); ?>
                </button>
                <?php 
                $has_secondary = !empty($item->template_id) || !empty($item->topic_id) || !empty($item->post_id) || ($item->status === 'failed' && !empty($item->template_id));
                if ($has_secondary):
                ?>
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="aips-history-row-actions-<?php echo esc_attr($item->id); ?>"
                        title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
                        <span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($has_secondary): ?>
                <div id="aips-history-row-actions-<?php echo esc_attr($item->id); ?>" class="aips-row-action-menu" hidden>
                    <?php if (!empty($item->template_id) || !empty($item->topic_id)): ?>
                        <button type="button" class="aips-row-action-item aips-view-session" data-history-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Session', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <span><?php esc_html_e('View Session', 'ai-post-scheduler'); ?></span>
                        </button>
                    <?php endif; ?>

                    <?php if ($item->post_id): ?>
                        <a class="aips-row-action-item" href="<?php echo esc_url(get_permalink($item->post_id)); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('View Post', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-external"></span>
                            <span><?php esc_html_e('View Post', 'ai-post-scheduler'); ?></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($item->status === 'failed' && $item->template_id): ?>
                        <button type="button" class="aips-row-action-item aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Retry Generation', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <span><?php esc_html_e('Retry', 'ai-post-scheduler'); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </td>
</tr>
