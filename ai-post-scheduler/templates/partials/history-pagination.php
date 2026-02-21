<?php
/**
 * History Pagination Partial
 *
 * @var array  $history        History data array.
 * @var string $url            URL for pagination links.
 * @var bool   $is_history_tab Whether this is displayed in a tab.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="<?php echo !$is_history_tab ? 'aips-panel-footer' : 'tablenav bottom'; ?>" id="aips-history-pagination">
    <div class="tablenav-pages">
        <span class="displaying-num">
             <?php printf(
                 esc_html__('%d items', 'ai-post-scheduler'),
                 $history['total']
             ); ?>
         </span>
         <span class="pagination-links">
             <?php
            if ($history['current_page'] > 1): ?>
            <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $history['current_page'] - 1, $url)); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Previous page', 'ai-post-scheduler'); ?></span>
                <span aria-hidden="true">&lsaquo;</span>
            </a>
            <?php endif; ?>

            <span class="paging-input">
                <span class="tablenav-paging-text">
                    <?php echo esc_html($history['current_page']); ?>
                    <?php esc_html_e('of', 'ai-post-scheduler'); ?>
                    <span class="total-pages"><?php echo esc_html($history['pages']); ?></span>
                </span>
            </span>

            <?php if ($history['current_page'] < $history['pages']): ?>
            <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $history['current_page'] + 1, $url)); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Next page', 'ai-post-scheduler'); ?></span>
                <span aria-hidden="true">&rsaquo;</span>
            </a>
            <?php endif; ?>
        </span>
    </div>
</div>
