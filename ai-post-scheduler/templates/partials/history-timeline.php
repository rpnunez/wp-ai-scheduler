<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!empty($timeline_items)): ?>
    <div class="aips-history-timeline-list">
        <?php foreach ($timeline_items as $item): ?>
            <?php
            $created_at = isset($item->created_at) ? (int) $item->created_at : 0;
            $group_label = isset($history_handler) && method_exists($history_handler, 'get_timeline_group_label')
                ? $history_handler->get_timeline_group_label($created_at, isset($now_timestamp) ? (int) $now_timestamp : null)
                : __('Older', 'ai-post-scheduler');
            $title = !empty($item->event_label) ? $item->event_label : (!empty($item->generated_title) ? $item->generated_title : __('Generation Event', 'ai-post-scheduler'));
            $status = !empty($item->status) ? $item->status : __('unknown', 'ai-post-scheduler');
            $domain = !empty($item->event_domain) ? $item->event_domain : __('unknown', 'ai-post-scheduler');
            $formatted_date = !empty($item->formatted_date) ? $item->formatted_date : '';
            ?>
            <article class="aips-history-timeline-item">
                <header class="aips-history-timeline-item-header">
                    <strong class="aips-history-timeline-item-title"><?php echo esc_html($title); ?></strong>
                    <span class="aips-badge aips-badge-neutral aips-history-timeline-group"><?php echo esc_html($group_label); ?></span>
                </header>
                <div class="aips-history-timeline-item-meta">
                    <?php if ($formatted_date !== ''): ?>
                        <span><?php echo esc_html($formatted_date); ?></span>
                    <?php endif; ?>
                    <span class="aips-badge aips-badge-neutral"><?php echo esc_html($domain); ?></span>
                    <span class="aips-badge aips-badge-neutral"><?php echo esc_html($status); ?></span>
                </div>
                <?php if (!empty($item->error_message)): ?>
                    <p class="aips-history-timeline-item-error"><?php echo esc_html($item->error_message); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="aips-meta-text"><?php esc_html_e('No events yet.', 'ai-post-scheduler'); ?></p>
<?php endif; ?>
