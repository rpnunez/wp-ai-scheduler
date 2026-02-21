<?php
/**
 * History Pagination Partial
 *
 * AJAX-based pagination with page number buttons. Used by render_pagination_html().
 *
 * @var array $history History result with total, pages, current_page.
 */
if (!defined('ABSPATH')) {
    exit;
}
$current = (int) $history['current_page'];
$pages = (int) $history['pages'];
$start = max(1, $current - 3);
$end = min($pages, $current + 3);
?>
<div class="aips-history-pagination aips-panel-footer">
    <span class="aips-history-pagination-info">
        <?php printf(esc_html__('%d items', 'ai-post-scheduler'), $history['total']); ?>
    </span>
    <?php if ($pages > 1): ?>
    <div class="aips-history-pagination-links">
        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" data-page="<?php echo esc_attr($current - 1); ?>" <?php echo $current <= 1 ? 'disabled' : ''; ?> aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
        </button>
        <span class="aips-history-page-numbers">
            <?php if ($start > 1): ?>
            <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" data-page="1">1</button>
            <?php if ($start > 2): ?><span class="aips-history-page-ellipsis">…</span><?php endif;
            endif;
            for ($p = $start; $p <= $end; $p++):
                $active = ($p === $current);
            ?>
            <button type="button" class="aips-btn aips-btn-sm <?php echo $active ? 'aips-btn-primary' : 'aips-btn-secondary'; ?> aips-history-page-link" data-page="<?php echo esc_attr($p); ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><?php echo esc_html($p); ?></button>
            <?php endfor;
            if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
            <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" data-page="<?php echo esc_attr($pages); ?>"><?php echo esc_html($pages); ?></button>
            <?php endif; ?>
        </span>
        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" data-page="<?php echo esc_attr($current + 1); ?>" <?php echo $current >= $pages ? 'disabled' : ''; ?> aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-arrow-right-alt2"></span>
        </button>
    </div>
    <?php endif; ?>
</div>
