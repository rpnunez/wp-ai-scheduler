<?php
/**
 * History Group Row Partial
 *
 * Renders a summary row for contiguous history items of the same activity type.
 *
 * @var array $group Group descriptor.
 */
if (!defined('ABSPATH')) {
    exit;
}

$group_id = isset($group['group_id']) ? $group['group_id'] : '';
$count = isset($group['count']) ? (int) $group['count'] : 0;
$label = isset($group['label']) ? $group['label'] : __('Activity', 'ai-post-scheduler');
$completed = isset($group['completed_count']) ? (int) $group['completed_count'] : 0;
$failed = isset($group['failed_count']) ? (int) $group['failed_count'] : 0;
$time_range = isset($group['time_range']) ? $group['time_range'] : '';
$post_types = isset($group['post_types']) && is_array($group['post_types']) ? $group['post_types'] : array();
?>
<tr class="aips-history-group-header" data-group-id="<?php echo esc_attr($group_id); ?>" tabindex="0" role="button" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(__('Toggle group for %d %s runs', 'ai-post-scheduler'), $count, $label)); ?>">
    <th scope="row" class="check-column" onclick="event.stopPropagation();">
        <label class="screen-reader-text" for="cb-group-<?php echo esc_attr($group_id); ?>">
            <?php echo esc_html(sprintf(__('Select all %d items in %s group', 'ai-post-scheduler'), $count, $label)); ?>
        </label>
        <input id="cb-group-<?php echo esc_attr($group_id); ?>" type="checkbox" class="aips-history-group-cb" data-group-id="<?php echo esc_attr($group_id); ?>">
    </th>
    <td class="column-title">
        <div class="aips-group-title-row">
            <button type="button" class="aips-history-group-toggle" data-group-id="<?php echo esc_attr($group_id); ?>" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(__('Expand %d %s runs', 'ai-post-scheduler'), $count, $label)); ?>">
                <span class="dashicons dashicons-arrow-right-alt2 aips-group-chevron" aria-hidden="true"></span>
            </button>
            <strong class="aips-history-title aips-group-title-text"><?php echo esc_html($label); ?></strong>
            <span class="aips-badge aips-badge-neutral aips-group-count-badge"><?php echo esc_html(sprintf(_n('%d run', '%d runs', $count, 'ai-post-scheduler'), $count)); ?></span>
        </div>
        <span class="aips-history-subtitle aips-group-subtitle"><?php echo esc_html(sprintf(__('Click to expand %d items', 'ai-post-scheduler'), $count)); ?></span>
    </td>
    <td class="column-post-type">
        <?php if (!empty($post_types)): ?>
            <?php foreach ($post_types as $pt_name): ?>
                <?php $pt_obj = get_post_type_object($pt_name); ?>
                <span class="aips-badge aips-badge-neutral">
                    <?php echo esc_html($pt_obj ? $pt_obj->labels->singular_name : $pt_name); ?>
                </span>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="aips-meta-text">&mdash;</span>
        <?php endif; ?>
    </td>
    <td class="column-status">
        <div class="aips-group-status-wrap">
            <?php if ($completed > 0): ?>
                <span class="aips-badge aips-badge-success">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php echo esc_html(sprintf(__('%d Succeeded', 'ai-post-scheduler'), $completed)); ?>
                </span>
            <?php endif; ?>
            <?php if ($failed > 0): ?>
                <span class="aips-badge aips-badge-error">
                    <span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
                    <?php echo esc_html(sprintf(__('%d Failed', 'ai-post-scheduler'), $failed)); ?>
                </span>
            <?php endif; ?>
            <?php if ($completed === 0 && $failed === 0): ?>
                <span class="aips-badge aips-badge-info">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e('Processing', 'ai-post-scheduler'); ?>
                </span>
            <?php endif; ?>
        </div>
    </td>
    <td class="column-type">
        <strong><?php echo esc_html($label); ?></strong>
        <span class="aips-history-activity-meta">
            <span><?php echo esc_html(sprintf(_n('%d event', '%d events', $count, 'ai-post-scheduler'), $count)); ?></span>
        </span>
    </td>
    <td class="column-date" colspan="2">
        <span class="aips-meta-text"><?php echo wp_kses($time_range, array('ndash' => array())); ?></span>
    </td>
</tr>
