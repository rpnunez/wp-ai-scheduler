<?php
/**
 * Prompt Profile Version History Panel
 *
 * Included from inside the Edit Prompt Profile modal in index.php, after the
 * stage editor workspace. This panel is always visible (no click-to-reveal
 * tab) whenever the modal is open for an EXISTING profile; it is hidden
 * entirely in "Create" mode since a brand-new profile has no history yet.
 * JS (admin-prompt-profiles-diff.js) populates the timeline via AJAX
 * (aips_get_prompt_profile_versions) once a profile is loaded in the modal.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-modal-section aips-profile-version-history" id="aips-profile-version-history" style="display: none;">
	<div class="aips-version-history-header">
		<div>
			<h3 class="aips-version-history-heading">
				<span class="dashicons dashicons-backup"></span>
				<?php esc_html_e('Version History', 'ai-post-scheduler'); ?>
			</h3>
			<p class="aips-stage-subtext"><?php esc_html_e('Every saved edit is snapshotted automatically. Compare any two versions or restore the entire profile (or a single stage) to an earlier state.', 'ai-post-scheduler'); ?></p>
		</div>
		<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost" id="aips-refresh-versions-btn">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e('Refresh', 'ai-post-scheduler'); ?>
		</button>
	</div>

	<div class="aips-versions-timeline-wrapper">
		<table class="aips-table aips-versions-table" id="aips-versions-table">
			<thead>
				<tr>
					<th class="column-version"><?php esc_html_e('Version', 'ai-post-scheduler'); ?></th>
					<th class="column-date"><?php esc_html_e('Date', 'ai-post-scheduler'); ?></th>
					<th class="column-author"><?php esc_html_e('By', 'ai-post-scheduler'); ?></th>
					<th class="column-summary"><?php esc_html_e('Change Summary', 'ai-post-scheduler'); ?></th>
					<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
				</tr>
			</thead>
			<tbody id="aips-versions-timeline-body">
				<tr class="aips-versions-loading-row">
					<td colspan="5"><?php esc_html_e('Loading version history...', 'ai-post-scheduler'); ?></td>
				</tr>
			</tbody>
		</table>
		<div class="aips-versions-empty-state" id="aips-versions-empty-state" style="display: none;">
			<?php esc_html_e('No version history yet. Save a change to this profile to start recording versions.', 'ai-post-scheduler'); ?>
		</div>
	</div>
</div>

<!-- Row template for a single version history entry -->
<script type="text/html" id="aips-tmpl-version-row">
	<tr data-version-id="{{id}}" data-version-number="{{version_number}}">
		<td class="column-version"><span class="aips-badge aips-badge-neutral">v{{version_number}}</span></td>
		<td class="column-date">{{created_at_display}}</td>
		<td class="column-author">{{created_by_display}}</td>
		<td class="column-summary">{{change_summary}}</td>
		<td class="column-actions">
			<div class="aips-action-buttons">
				<button type="button" class="aips-btn aips-btn-xs aips-view-diff-btn" data-version-id="{{id}}">
					<span class="dashicons dashicons-image-rotate"></span>
					<?php esc_html_e('View Diff', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-restore-version-btn" data-version-id="{{id}}" data-version-number="{{version_number}}">
					<span class="dashicons dashicons-undo"></span>
					<?php esc_html_e('Restore', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</td>
	</tr>
</script>

<!-- Modal: Diff Viewer (nested) -->
<div id="aips-version-diff-modal" class="aips-modal" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="aips-version-diff-modal-title">
	<div class="aips-modal-content aips-modal-lg">
		<div class="aips-modal-header">
			<h2 id="aips-version-diff-modal-title"><?php esc_html_e('Compare Versions', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<div class="aips-diff-compare-bar">
				<div class="aips-form-group">
					<label for="aips-diff-version-a" class="aips-form-label"><?php esc_html_e('Version', 'ai-post-scheduler'); ?></label>
					<select id="aips-diff-version-a" class="aips-form-input"></select>
				</div>
				<span class="aips-diff-compare-arrow dashicons dashicons-arrow-right-alt"></span>
				<div class="aips-form-group">
					<label for="aips-diff-version-b" class="aips-form-label"><?php esc_html_e('Compared to', 'ai-post-scheduler'); ?></label>
					<select id="aips-diff-version-b" class="aips-form-input"></select>
				</div>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary" id="aips-run-diff-btn">
					<?php esc_html_e('Compare', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<div id="aips-diff-results" class="aips-diff-results">
				<p class="aips-diff-placeholder"><?php esc_html_e('Select two versions above and click Compare.', 'ai-post-scheduler'); ?></p>
			</div>
		</div>
		<div class="aips-modal-footer">
			<div class="aips-footer-right">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close">
					<?php esc_html_e('Close', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Stage diff block template -->
<script type="text/html" id="aips-tmpl-stage-diff-block">
	<div class="aips-stage-diff-block">
		<div class="aips-stage-diff-header">
			<h4 class="aips-stage-diff-heading">{{stageLabel}}</h4>
			<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-restore-stage-btn" data-stage="{{stageKey}}" data-version-id="{{versionAId}}" data-stage-label="{{stageLabelAttr}}">
				<span class="dashicons dashicons-undo"></span>
				<?php esc_html_e('Restore this stage', 'ai-post-scheduler'); ?>
			</button>
		</div>
		<div class="aips-stage-diff-body">{{segmentsHtml}}</div>
	</div>
</script>
