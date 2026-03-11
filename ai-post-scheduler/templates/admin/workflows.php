<?php
if (!defined('ABSPATH')) {
    exit;
}
// $statuses, $workflows, $edit_workflow, and $message are provided by AIPS_Workflow_Controller::render_page().
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Workflows', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Group AI-generated content into repeatable workstreams and move them through the lifecycle before publishing.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
                <p><?php echo esc_html($message['text']); ?></p>
            </div>
        <?php endif; ?>

        <div class="aips-content-panel">
            <div class="aips-panel-header">
                <h2><?php esc_html_e('Existing Workflows', 'ai-post-scheduler'); ?></h2>
                <p class="aips-panel-description"><?php esc_html_e('View or edit the workflows that manage review, approval, and publishing stages.', 'ai-post-scheduler'); ?></p>
            </div>
            <div class="aips-panel-body">
                <?php if (!empty($workflows)): ?>
                    <table class="aips-table aips-workflows-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Created', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Updated', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workflows as $workflow): ?>
                                <tr>
                                    <td class="column-name cell-primary"><?php echo esc_html($workflow->name); ?></td>
                                    <td><?php echo esc_html($workflow->description); ?></td>
                                    <td>
                                        <span class="aips-badge aips-badge-info"><?php echo esc_html(AIPS_Workflow_Service::get_status_label($workflow->status)); ?></span>
                                    </td>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $workflow->created_at)); ?></td>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $workflow->updated_at)); ?></td>
                                    <td>
                                        <a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url(add_query_arg('workflow_id', $workflow->id, admin_url('admin.php?page=aips-workflows'))); ?>">
                                            <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                                        </a>
                                        <form class="aips-inline-form aips-delete-workflow-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="aips_delete_workflow">
                                            <input type="hidden" name="workflow_id" value="<?php echo esc_attr($workflow->id); ?>">
                                            <?php wp_nonce_field('aips_delete_workflow'); ?>
                                            <button type="submit" class="aips-btn aips-btn-sm aips-btn-danger"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="aips-empty-state">
                        <span class="dashicons dashicons-schedule" aria-hidden="true"></span>
                        <h3><?php esc_html_e('No workflows yet', 'ai-post-scheduler'); ?></h3>
                        <p><?php esc_html_e('Start by creating a workflow to map how generated drafts move through review and publishing.', 'ai-post-scheduler'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="aips-content-panel" style="margin-top: 24px;">
            <div class="aips-panel-header">
                <h2><?php echo $edit_workflow ? esc_html__('Edit Workflow', 'ai-post-scheduler') : esc_html__('Create Workflow', 'ai-post-scheduler'); ?></h2>
                <p class="aips-panel-description"><?php esc_html_e('Define the workflow name, description, and the default lifecycle status.', 'ai-post-scheduler'); ?></p>
            </div>
            <div class="aips-panel-body">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="aips_save_workflow">
                    <input type="hidden" name="workflow_id" value="<?php echo esc_attr($edit_workflow ? $edit_workflow->id : 0); ?>">
                    <?php wp_nonce_field('aips_save_workflow'); ?>

                    <label for="workflow-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></label>
                    <input id="workflow-name" class="aips-form-input" name="workflow_name" type="text" value="<?php echo esc_attr($edit_workflow ? $edit_workflow->name : ''); ?>" required>

                    <label for="workflow-description" style="margin-top: 1em;"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
                    <textarea id="workflow-description" class="aips-form-input aips-form-textarea" name="workflow_description" rows="3"><?php echo esc_textarea($edit_workflow ? $edit_workflow->description : ''); ?></textarea>

                    <label for="workflow-status" style="margin-top: 1em;"><?php esc_html_e('Default Status', 'ai-post-scheduler'); ?></label>
                    <select id="workflow-status" name="workflow_status" class="aips-form-input">
                        <?php foreach ($statuses as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($edit_workflow ? $edit_workflow->status : '', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="aips-form-actions" style="margin-top: 20px;">
                        <button type="submit" class="aips-btn aips-btn-primary">
                            <?php echo $edit_workflow ? esc_html__('Update Workflow', 'ai-post-scheduler') : esc_html__('Create Workflow', 'ai-post-scheduler'); ?>
                        </button>
                        <?php if ($edit_workflow): ?>
                            <a class="aips-btn aips-btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=aips-workflows')); ?>">
                                <?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
