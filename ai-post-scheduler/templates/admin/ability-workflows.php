<?php
/**
 * Ability Workflows admin page template.
 *
 * Lists existing Ability Workflows. Data is loaded and rendered entirely
 * via AJAX (AIPS.Templates) so search/filter/create/duplicate/archive/
 * delete/run actions never require a full page reload.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$aips_builder_base_url = admin_url('admin.php?page=aips-ability-workflow-builder');
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Ability Workflows', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Build multi-step automations out of installed WordPress Abilities, AI Engine abilities, and this plugin\'s own capabilities.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-workflow-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Create Workflow', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar">
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-workflow-search"><?php esc_html_e('Search Workflows:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-workflow-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search workflows…', 'ai-post-scheduler'); ?>">
					<select id="aips-workflow-status-filter" class="aips-form-select">
						<option value=""><?php esc_html_e('All statuses', 'ai-post-scheduler'); ?></option>
						<option value="draft"><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
						<option value="active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></option>
						<option value="paused"><?php esc_html_e('Paused', 'ai-post-scheduler'); ?></option>
						<option value="archived"><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></option>
					</select>
				</div>
			</div>

			<div class="aips-panel-body no-padding">
				<table class="aips-table aips-ability-workflows-table" id="aips-ability-workflows-table">
					<thead>
						<tr>
							<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
							<th class="column-trigger"><?php esc_html_e('Trigger', 'ai-post-scheduler'); ?></th>
							<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<th class="column-updated"><?php esc_html_e('Updated', 'ai-post-scheduler'); ?></th>
							<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody id="aips-ability-workflows-table-body">
					</tbody>
				</table>
			</div>

			<div class="tablenav">
				<span class="aips-table-footer-count" id="aips-ability-workflows-count"></span>
			</div>

			<div id="aips-ability-workflows-empty" class="aips-empty-state" style="display:none;">
				<div class="dashicons dashicons-networking aips-empty-state-icon" aria-hidden="true"></div>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Ability Workflows Yet', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('Create a workflow to chain Abilities together with input mapping and conditional logic.', 'ai-post-scheduler'); ?></p>
				<div class="aips-empty-state-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-workflow-empty-btn">
						<?php esc_html_e('Create Your First Workflow', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- Add / Edit Workflow Modal -->
<div id="aips-workflow-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title" id="aips-workflow-modal-title"><?php esc_html_e('Create Workflow', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-workflow-form" novalidate>
				<input type="hidden" name="workflow_id" id="aips-workflow-id" value="0">

				<div class="aips-form-row">
					<label for="aips-workflow-name">
						<?php esc_html_e('Name', 'ai-post-scheduler'); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
					<input type="text" id="aips-workflow-name" name="name" required class="regular-text">
				</div>

				<div class="aips-form-row">
					<label for="aips-workflow-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
					<textarea id="aips-workflow-description" name="description" rows="3" class="large-text"></textarea>
				</div>

				<div class="aips-form-row">
					<label for="aips-workflow-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></label>
					<select id="aips-workflow-status" name="status" class="aips-form-select">
						<option value="draft"><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
						<option value="active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></option>
						<option value="paused"><?php esc_html_e('Paused', 'ai-post-scheduler'); ?></option>
						<option value="archived"><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></option>
					</select>
				</div>

				<div class="aips-form-row">
					<label for="aips-workflow-trigger-type"><?php esc_html_e('Trigger', 'ai-post-scheduler'); ?></label>
					<select id="aips-workflow-trigger-type" name="trigger_type" class="aips-form-select">
						<option value="manual"><?php esc_html_e('Manual (Run Now)', 'ai-post-scheduler'); ?></option>
						<option value="scheduled"><?php esc_html_e('Scheduled (coming soon)', 'ai-post-scheduler'); ?></option>
					</select>
				</div>

				<div class="aips-form-row">
					<label for="aips-workflow-max-steps"><?php esc_html_e('Max Steps', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-workflow-max-steps" name="max_steps" min="1" max="100" value="20" class="small-text">
				</div>

				<div class="aips-form-row">
					<label for="aips-workflow-max-runtime"><?php esc_html_e('Max Runtime (seconds)', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-workflow-max-runtime" name="max_runtime_seconds" min="1" max="3600" value="120" class="small-text">
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-workflow-allow-destructive" name="allow_destructive_abilities" value="1">
						<?php esc_html_e('Allow destructive abilities', 'ai-post-scheduler'); ?>
					</label>
					<p class="description"><?php esc_html_e('When unchecked, steps using an ability flagged as destructive will fail instead of running.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-workflow-log-payloads" name="log_payloads" value="1" checked>
						<?php esc_html_e('Log step inputs/outputs for audit history', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary" id="aips-save-workflow-btn">
				<?php esc_html_e('Save Workflow', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-workflow-row">
	<tr data-workflow-id="{{id}}" data-status="{{status}}">
		<td class="column-name cell-primary">
			<a href="{{builder_url}}">{{name}}</a>
			<div class="cell-meta">{{description}}</div>
		</td>
		<td class="column-trigger">{{trigger_type}}</td>
		<td class="column-status">
			<span class="aips-badge {{status_badge_class}}">{{status_label}}</span>
		</td>
		<td class="column-updated">{{updated_display}}</td>
		<td class="column-actions">
			<div class="aips-action-buttons">
				<a class="aips-btn aips-btn-sm" href="{{builder_url}}" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-edit"></span>
				</a>
				<button class="aips-btn aips-btn-sm aips-btn-secondary aips-run-workflow-now" data-id="{{id}}" title="<?php esc_attr_e('Run now', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-controls-play"></span>
				</button>
				<button class="aips-btn aips-btn-sm aips-btn-ghost aips-duplicate-workflow" data-id="{{id}}" title="<?php esc_attr_e('Duplicate', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-admin-page"></span>
				</button>
				<button class="aips-btn aips-btn-sm aips-btn-ghost aips-archive-workflow" data-id="{{id}}" title="<?php esc_attr_e('Archive', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-archive"></span>
				</button>
				<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-workflow" data-id="{{id}}" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</td>
	</tr>
</script>

<script>
	window.aipsAbilityWorkflowBuilderBaseUrl = <?php echo wp_json_encode($aips_builder_base_url); ?>;
</script>
