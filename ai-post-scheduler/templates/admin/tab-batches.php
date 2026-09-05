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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="aips-batches-form">
    <?php wp_nonce_field('aips_cancel_batch_run_bulk'); ?>
    <input type="hidden" name="action" value="aips_cancel_batch_run_bulk" />
    <table class="widefat striped">
        <thead>
            <tr>
                <th><input type="checkbox" id="aips-select-all-batches" /></th>
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
                        <td><input type="checkbox" name="batch_ids[]" value="<?php echo esc_attr((int) $run->id); ?>" class="aips-batch-checkbox" /></td>
                        <td><?php echo esc_html(isset($run->batch_uuid) ? $run->batch_uuid : ''); ?></td>
                        <td><?php echo esc_html(isset($run->schedule_id) ? $run->schedule_id : ''); ?></td>
                        <td><?php if (!empty($run->correlation_id)) : ?><a href="<?php echo esc_url( add_query_arg( array('tab' => 'insights', 'correlation_id' => $run->correlation_id), admin_url('admin.php?page=' . AIPS_Diagnostics_Controller::PAGE_SLUG) ) ); ?>"><?php echo esc_html($run->correlation_id); ?></a><?php else : echo ''; endif; ?></td>
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

    <p style="margin-top:8px;">
        <button type="button" id="aips-bulk-cancel-button" class="button aips-btn aips-btn-danger"><?php esc_html_e('Cancel Selected', 'ai-post-scheduler'); ?></button>
    </p>
    </form>
</div>

<!-- Bulk cancel confirmation modal -->
<div id="aips-bulk-cancel-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; right:0; bottom:0; background: rgba(0,0,0,0.4);">
    <div style="background:#fff; width:480px; max-width:90%; margin:10% auto; padding:20px; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.2);">
        <h3><?php esc_html_e('Confirm cancel selected batch runs', 'ai-post-scheduler'); ?></h3>
        <p><?php esc_html_e('This will mark the selected batch runs as cancelled and prevent them from being resumed. This action cannot be undone from the UI.', 'ai-post-scheduler'); ?></p>
        <div style="text-align:right; margin-top:16px;">
            <button type="button" id="aips-bulk-cancel-cancel" class="button"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
            <button type="button" id="aips-bulk-cancel-confirm" class="button aips-btn aips-btn-danger" style="margin-left:8px;"><?php esc_html_e('Confirm Cancel', 'ai-post-scheduler'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
(function(){
    var selectAll = document.getElementById('aips-select-all-batches');
    if (!selectAll) return;
    selectAll.addEventListener('change', function(){
        var checkboxes = document.querySelectorAll('.aips-batch-checkbox');
        for (var i=0;i<checkboxes.length;i++) { checkboxes[i].checked = selectAll.checked; }
    });

    var bulkButton = document.getElementById('aips-bulk-cancel-button');
    var modal = document.getElementById('aips-bulk-cancel-modal');
    var modalCancel = document.getElementById('aips-bulk-cancel-cancel');
    var modalConfirm = document.getElementById('aips-bulk-cancel-confirm');
    var form = document.getElementById('aips-batches-form');

    if (!bulkButton || !modal || !modalCancel || !modalConfirm || !form) return;

    bulkButton.addEventListener('click', function(e){
        // Ensure at least one checkbox is selected
        var anyChecked = false;
        var checkboxes = document.querySelectorAll('.aips-batch-checkbox');
        for (var i=0;i<checkboxes.length;i++) { if (checkboxes[i].checked) { anyChecked = true; break; } }
        if (!anyChecked) {
            alert('<?php echo esc_js(__('Please select one or more batch runs to cancel.', 'ai-post-scheduler')); ?>');
            return;
        }
        modal.style.display = 'block';
    });

    modalCancel.addEventListener('click', function(){ modal.style.display = 'none'; });
    modalConfirm.addEventListener('click', function(){ form.submit(); });

    // Close modal when clicking outside the modal content
    modal.addEventListener('click', function(e){ if (e.target === modal) { modal.style.display = 'none'; } });

})();
</script>
