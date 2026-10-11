<?php
/**
 * History Pagination Partial
 *
 * Standard WordPress-style pagination with page jump input.
 * Used by render_pagination_html().
 *
 * @var array $history History result with total, pages, current_page.
 */
if (!defined('ABSPATH')) {
    exit;
}
$current = (int) $history['current_page'];
$pages = (int) $history['pages'];
?>
<div class="aips-history-pagination tablenav-pages">
    <span class="displaying-num">
        <?php printf(esc_html__('%d items', 'ai-post-scheduler'), (int) $history['total']); ?>
    </span>
    <?php if ($pages > 1): ?>
    <span class="pagination-links">
        <button type="button" class="button aips-history-page-nav aips-history-page-first" data-page="1" <?php echo $current <= 1 ? 'disabled' : ''; ?> title="<?php esc_attr_e('First page', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('First page', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
        </button>
        <button type="button" class="button aips-history-page-nav aips-history-page-prev" data-page="<?php echo esc_attr(max(1, $current - 1)); ?>" <?php echo $current <= 1 ? 'disabled' : ''; ?> title="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        </button>
        <span class="paging-input">
            <label for="aips-history-current-page-selector" class="screen-reader-text"><?php esc_html_e('Current Page', 'ai-post-scheduler'); ?></label>
            <input class="current-page aips-history-page-input" id="aips-history-current-page-selector" type="number" min="1" max="<?php echo esc_attr($pages); ?>" name="paged" value="<?php echo esc_attr($current); ?>" size="<?php echo max(2, strlen((string) $pages)); ?>" aria-describedby="table-paging">
            <span class="tablenav-paging-text"> <?php echo esc_html_x('of', 'paging', 'ai-post-scheduler'); ?> <span class="total-pages"><?php echo esc_html($pages); ?></span></span>
        </span>
        <button type="button" class="button aips-history-page-nav aips-history-page-next" data-page="<?php echo esc_attr(min($pages, $current + 1)); ?>" <?php echo $current >= $pages ? 'disabled' : ''; ?> title="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
        </button>
        <button type="button" class="button aips-history-page-nav aips-history-page-last" data-page="<?php echo esc_attr($pages); ?>" <?php echo $current >= $pages ? 'disabled' : ''; ?> title="<?php esc_attr_e('Last page', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Last page', 'ai-post-scheduler'); ?>">
            <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
        </button>
    </span>
    <?php endif; ?>
</div>
