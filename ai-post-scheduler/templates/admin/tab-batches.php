<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="aips-diagnostics-batches">
    <h2><?php esc_html_e('Active Batch Runs', 'ai-post-scheduler'); ?></h2>

    <p class="description"><?php esc_html_e('Shows pending, running, and partially completed batch runs.', 'ai-post-scheduler'); ?></p>

    <p>
        <a href="<?php echo esc_url( AIPS_Diagnostics_Controller::get_tab_url('batches') ); ?>" class="button">
            <?php esc_html_e('Refresh', 'ai-post-scheduler'); ?>
        </a>
    </p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Batch UUID', 'ai-post-scheduler'); ?></th>
                <th><?php esc_html_e('Schedule ID', 'ai-post-scheduler'); ?></th>
                <th><?php esc_html_e('Correlation ID', 'ai-post-scheduler'); ?></th>
                <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                <th><?php esc_html_e('Completed / Total', 'ai-post-scheduler'); ?></th>
                <th><?php esc_html_e('Resume Index', 'ai-post-scheduler'); ?></th>
                <th><?php esc_html_e('Updated', 'ai-post-scheduler'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($batch_runs)) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No active batch runs found.', 'ai-post-scheduler'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($batch_runs as $run) : ?>
                    <tr>
                        <td><?php echo esc_html(isset($run->batch_uuid) ? $run->batch_uuid : ''); ?></td>
                        <td><?php echo esc_html(isset($run->schedule_id) ? $run->schedule_id : ''); ?></td>
                        <td><?php echo esc_html(isset($run->correlation_id) ? $run->correlation_id : ''); ?></td>
                        <td><?php echo esc_html(isset($run->status) ? $run->status : ''); ?></td>
                        <td><?php echo esc_html((isset($run->completed) ? (int) $run->completed : 0) . ' / ' . (isset($run->total) ? (int) $run->total : 0)); ?></td>
                        <td><?php echo esc_html(isset($run->resume_index) ? (int) $run->resume_index : ''); ?></td>
                        <td><?php echo esc_html(isset($run->updated_at) ? $run->updated_at : ''); ?></td>
                        <td>
                            <?php if (!empty($run->id)) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin:0;padding:0;">
                                    <?php wp_nonce_field('aips_cancel_batch_run'); ?>
                                    <input type="hidden" name="action" value="aips_cancel_batch_run" />
                                    <input type="hidden" name="batch_id" value="<?php echo esc_attr((int) $run->id); ?>" />
                                    <button type="submit" class="button aips-btn aips-btn-danger" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this batch run?', 'ai-post-scheduler')); ?>');"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
