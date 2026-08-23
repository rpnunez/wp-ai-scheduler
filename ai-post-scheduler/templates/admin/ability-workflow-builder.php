<?php
/**
 * Ability Workflow Builder admin page template.
 *
 * Hidden page (consolidated under the visible "Ability Workflows" submenu
 * item) for editing a single workflow's steps and viewing its run history.
 * All data is loaded via AJAX; this template only renders the page shell,
 * modals, and the AIPS.Templates row templates used by the companion JS.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$aips_workflow_id   = isset($_GET['workflow_id']) ? absint($_GET['workflow_id']) : 0;
$aips_workflows_url = admin_url('admin.php?page=aips-ability-workflows');

if ($aips_workflow_id <= 0) {
	?>
	<div class="wrap aips-wrap">
		<div class="aips-page-container">
			<div class="aips-empty-state">
				<h3 class="aips-empty-state-title"><?php esc_html_e('No workflow selected', 'ai-post-scheduler'); ?></h3>
				<div class="aips-empty-state-actions">
					<a class="aips-btn aips-btn-primary" href="<?php echo esc_url($aips_workflows_url); ?>">
						<?php esc_html_e('Back to Ability Workflows', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
	<?php
	return;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title" id="aips-builder-workflow-name"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<a href="<?php echo esc_url($aips_workflows_url); ?>">&larr; <?php esc_html_e('Back to Ability Workflows', 'ai-post-scheduler'); ?></a>
					</p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-builder-run-now-btn">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-primary" id="aips-save-steps-btn">
						<?php esc_html_e('Save Steps', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="aips-tab-nav">
			<a href="#" class="aips-tab-link active" data-tab="steps"><?php esc_html_e('Steps', 'ai-post-scheduler'); ?></a>
			<a href="#" class="aips-tab-link" data-tab="runs"><?php esc_html_e('Runs', 'ai-post-scheduler'); ?></a>
		</div>

		<div class="aips-content-panel" id="aips-builder-tab-steps">
			<div class="aips-page-actions" style="padding:12px 16px;">
				<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm" id="aips-add-step-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e('Add Step', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<div class="aips-panel-body no-padding">
				<table class="aips-table" id="aips-steps-table">
					<thead>
						<tr>
							<th style="width:60px;"><?php esc_html_e('Order', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Step', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Ability', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Output Alias', 'ai-post-scheduler'); ?></th>
							<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody id="aips-steps-table-body"></tbody>
				</table>
			</div>
			<div id="aips-steps-empty" class="aips-empty-state" style="display:none;">
				<h3 class="aips-empty-state-title"><?php esc_html_e('No steps yet', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('Add a step to choose an Ability and map its inputs.', 'ai-post-scheduler'); ?></p>
			</div>
		</div>

		<div class="aips-content-panel" id="aips-builder-tab-runs" style="display:none;">
			<div class="aips-panel-body no-padding">
				<table class="aips-table" id="aips-runs-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Run', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Started', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Finished', 'ai-post-scheduler'); ?></th>
							<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody id="aips-runs-table-body"></tbody>
				</table>
			</div>
			<div id="aips-runs-empty" class="aips-empty-state" style="display:none;">
				<h3 class="aips-empty-state-title"><?php esc_html_e('No runs yet', 'ai-post-scheduler'); ?></h3>
			</div>
		</div>

	</div>
</div>

<!-- Step Edit Modal -->
<div id="aips-step-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title" id="aips-step-modal-title"><?php esc_html_e('Add Step', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-step-form" novalidate>
				<input type="hidden" id="aips-step-index" value="-1">

				<div class="aips-form-row">
					<label for="aips-step-key">
						<?php esc_html_e('Step Key', 'ai-post-scheduler'); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
					<input type="text" id="aips-step-key" class="regular-text" placeholder="<?php esc_attr_e('e.g. generate_outline', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('Unique identifier for this step (letters, numbers, underscores).', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-step-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-step-name" class="regular-text">
				</div>

				<div class="aips-form-row">
					<label for="aips-step-ability">
						<?php esc_html_e('Ability', 'ai-post-scheduler'); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
					<select id="aips-step-ability" class="aips-form-select"></select>
				</div>

				<div class="aips-form-row">
					<label for="aips-step-depends-on"><?php esc_html_e('Depends On', 'ai-post-scheduler'); ?></label>
					<select id="aips-step-depends-on" class="aips-form-select" multiple size="4"></select>
					<p class="description"><?php esc_html_e('This step only runs after all selected steps complete successfully.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-step-output-alias"><?php esc_html_e('Output Alias', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-step-output-alias" class="regular-text" placeholder="<?php esc_attr_e('e.g. outline', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('Later steps reference this step\'s output as {{steps.<alias>.output.<field>}}.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label><?php esc_html_e('Input Mapping', 'ai-post-scheduler'); ?></label>
					<table class="aips-table" id="aips-input-map-table">
						<thead><tr>
							<th><?php esc_html_e('Field', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Value / Template', 'ai-post-scheduler'); ?></th>
							<th style="width:40px;"></th>
						</tr></thead>
						<tbody id="aips-input-map-table-body"></tbody>
					</table>
					<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost" id="aips-add-input-map-row-btn">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add Field', 'ai-post-scheduler'); ?>
					</button>
					<p class="description"><?php esc_html_e('Use {{trigger.field}} or {{steps.<alias>.output.field}} to reference earlier data.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label><?php esc_html_e('Conditions', 'ai-post-scheduler'); ?></label>
					<select id="aips-condition-operator" class="aips-form-select" style="max-width:120px;">
						<option value="AND"><?php esc_html_e('AND', 'ai-post-scheduler'); ?></option>
						<option value="OR"><?php esc_html_e('OR', 'ai-post-scheduler'); ?></option>
					</select>
					<table class="aips-table" id="aips-condition-rules-table">
						<thead><tr>
							<th><?php esc_html_e('Left (value or {{token}})', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Operator', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Right', 'ai-post-scheduler'); ?></th>
							<th style="width:40px;"></th>
						</tr></thead>
						<tbody id="aips-condition-rules-table-body"></tbody>
					</table>
					<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost" id="aips-add-condition-rule-btn">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add Rule', 'ai-post-scheduler'); ?>
					</button>
					<p class="description"><?php esc_html_e('Leave empty to always run this step (subject to Depends On).', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-step-on-success"><?php esc_html_e('On Success', 'ai-post-scheduler'); ?></label>
					<select id="aips-step-on-success" class="aips-form-select">
						<option value="continue"><?php esc_html_e('Continue', 'ai-post-scheduler'); ?></option>
						<option value="stop"><?php esc_html_e('Stop workflow (success)', 'ai-post-scheduler'); ?></option>
						<option value="skip"><?php esc_html_e('Skip dependent steps', 'ai-post-scheduler'); ?></option>
					</select>
				</div>

				<div class="aips-form-row">
					<label for="aips-step-on-failure"><?php esc_html_e('On Failure', 'ai-post-scheduler'); ?></label>
					<select id="aips-step-on-failure" class="aips-form-select">
						<option value="stop"><?php esc_html_e('Stop workflow (failed)', 'ai-post-scheduler'); ?></option>
						<option value="continue"><?php esc_html_e('Continue', 'ai-post-scheduler'); ?></option>
						<option value="skip"><?php esc_html_e('Skip dependent steps', 'ai-post-scheduler'); ?></option>
					</select>
				</div>

				<div class="aips-form-row">
					<label for="aips-step-retry-attempts"><?php esc_html_e('Retry Attempts', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-step-retry-attempts" min="0" max="10" value="0" class="small-text">
				</div>

				<div class="aips-form-row">
					<label for="aips-step-retry-backoff"><?php esc_html_e('Retry Backoff (seconds)', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-step-retry-backoff" min="1" max="3600" value="5" class="small-text">
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary" id="aips-save-step-btn">
				<?php esc_html_e('Save Step', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>

<!-- Run Detail Modal -->
<div id="aips-run-detail-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Run Details', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<table class="aips-table" id="aips-run-detail-table">
				<thead><tr>
					<th><?php esc_html_e('Step', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Ability', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Output / Error', 'ai-post-scheduler'); ?></th>
				</tr></thead>
				<tbody id="aips-run-detail-table-body"></tbody>
			</table>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-step-row">
	<tr data-index="{{index}}">
		<td>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-move-step-up" data-index="{{index}}" title="<?php esc_attr_e('Move up', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
			</button>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-move-step-down" data-index="{{index}}" title="<?php esc_attr_e('Move down', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</button>
		</td>
		<td class="cell-primary">{{step_key}}<div class="cell-meta">{{name}}</div></td>
		<td>{{ability_name}}</td>
		<td>{{output_alias}}</td>
		<td class="column-actions">
			<div class="aips-action-buttons">
				<button type="button" class="aips-btn aips-btn-sm aips-edit-step" data-index="{{index}}" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-edit"></span>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-remove-step" data-index="{{index}}" title="<?php esc_attr_e('Remove', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-input-map-row">
	<tr data-row-index="{{row_index}}">
		<td><input type="text" class="regular-text aips-input-map-field" value="{{field}}"></td>
		<td><input type="text" class="regular-text aips-input-map-value" value="{{value}}"></td>
		<td><button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-remove-input-map-row" data-row-index="{{row_index}}">&times;</button></td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-condition-rule-row">
	<tr data-row-index="{{row_index}}">
		<td><input type="text" class="regular-text aips-rule-left" value="{{left}}"></td>
		<td>
			<select class="aips-form-select aips-rule-operator">
				<option value="equals">equals</option>
				<option value="not_equals">not_equals</option>
				<option value="contains">contains</option>
				<option value="not_contains">not_contains</option>
				<option value="greater_than">greater_than</option>
				<option value="less_than">less_than</option>
				<option value="is_empty">is_empty</option>
				<option value="is_not_empty">is_not_empty</option>
				<option value="in">in</option>
				<option value="not_in">not_in</option>
			</select>
		</td>
		<td><input type="text" class="regular-text aips-rule-right" value="{{right}}"></td>
		<td><button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-remove-condition-rule" data-row-index="{{row_index}}">&times;</button></td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-run-row">
	<tr data-run-id="{{id}}">
		<td class="cell-primary">#{{id}}</td>
		<td><span class="aips-badge {{status_badge_class}}">{{status}}</span></td>
		<td>{{started_display}}</td>
		<td>{{finished_display}}</td>
		<td class="column-actions">
			<button type="button" class="aips-btn aips-btn-sm aips-view-run" data-id="{{id}}" title="<?php esc_attr_e('View details', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-visibility"></span>
			</button>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-step-run-row">
	<tr>
		<td class="cell-primary">{{step_key}}</td>
		<td>{{ability_name}}</td>
		<td><span class="aips-badge {{status_badge_class}}">{{status}}</span></td>
		<td><pre style="white-space:pre-wrap; margin:0;">{{payload}}</pre></td>
	</tr>
</script>

<script>
	window.aipsAbilityWorkflowId = <?php echo (int) $aips_workflow_id; ?>;
</script>
