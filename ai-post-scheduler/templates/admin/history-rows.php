<?php if (!empty($history['items'])): ?>
    <?php foreach ($history['items'] as $item): ?>
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
            <div class="aips-error-message"><?php echo esc_html($item->error_message); ?></div>
            <?php endif; ?>
        </td>
        <td class="column-template">
            <?php echo esc_html($item->template_name ?: '-'); ?>
        </td>
        <td class="column-status">
            <span class="aips-status aips-status-<?php echo esc_attr($item->status); ?>">
                <?php echo esc_html(ucfirst($item->status)); ?>
            </span>
        </td>
        <td class="column-date">
            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?>
        </td>
        <td class="column-actions">
            <?php if ($item->post_id): ?>
            <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" class="button button-small" target="_blank">
                <?php esc_html_e('View', 'ai-post-scheduler'); ?>
            </a>
            <?php endif; ?>
            <button class="button button-small aips-view-details" data-id="<?php echo esc_attr($item->id); ?>">
                <?php esc_html_e('Details', 'ai-post-scheduler'); ?>
            </button>
            <?php if ($item->status === 'failed' && $item->template_id): ?>
            <button class="button button-small aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>">
                <?php esc_html_e('Retry', 'ai-post-scheduler'); ?>
            </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
