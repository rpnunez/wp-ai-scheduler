<?php
/**
 * Content Components admin page template.
 *
 * @package AI_Post_Scheduler
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container aips-content-components-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Content Components', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e('Create reusable CTA cards, FAQs, pros/cons sections, and summaries with rule-based auto-insertion.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-content-component-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add Content Component', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="aips-author-topics-stats" id="aips-content-components-stats">
			<div class="aips-stat-card">
				<span class="aips-stat-value" id="aips-cc-stat-total">0</span>
				<span class="aips-stat-label"><?php esc_html_e('Total Components', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-approved">
				<span class="aips-stat-value" id="aips-cc-stat-active">0</span>
				<span class="aips-stat-label"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-rejected">
				<span class="aips-stat-value" id="aips-cc-stat-inactive">0</span>
				<span class="aips-stat-label"><?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-generated">
				<span class="aips-stat-value" id="aips-cc-stat-needs-review">0</span>
				<span class="aips-stat-label"><?php esc_html_e('Needs Review', 'ai-post-scheduler'); ?></span>
			</div>
		</div>

		<div class="aips-content-panel" id="aips-content-components-panel">
			<div class="aips-topics-tabs aips-page-tabs">
				<button class="aips-tab-link active" data-tab="all">
					<?php esc_html_e('All', 'ai-post-scheduler'); ?>
				</button>
				<button class="aips-tab-link" data-tab="active">
					<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
				</button>
				<button class="aips-tab-link" data-tab="inactive">
					<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
				</button>
				<button class="aips-tab-link" data-tab="needs_review">
					<?php esc_html_e('Needs Review', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<div class="aips-filter-bar">
				<div class="aips-filter-left aips-content-components-filters">
					<select id="aips-content-component-type-filter" class="aips-form-select">
						<option value="all"><?php esc_html_e('All Types', 'ai-post-scheduler'); ?></option>
						<option value="cta"><?php esc_html_e('CTA', 'ai-post-scheduler'); ?></option>
						<option value="faq"><?php esc_html_e('FAQ', 'ai-post-scheduler'); ?></option>
						<option value="pros_cons"><?php esc_html_e('Pros/Cons', 'ai-post-scheduler'); ?></option>
						<option value="summary"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></option>
						<option value="disclaimer"><?php esc_html_e('Disclaimer', 'ai-post-scheduler'); ?></option>
						<option value="internal_link_pod"><?php esc_html_e('Internal Link Pod', 'ai-post-scheduler'); ?></option>
						<option value="custom"><?php esc_html_e('Custom', 'ai-post-scheduler'); ?></option>
					</select>
					<select id="aips-content-component-usage-filter" class="aips-form-select">
						<option value="all"><?php esc_html_e('All Usage', 'ai-post-scheduler'); ?></option>
						<option value="used"><?php esc_html_e('Used', 'ai-post-scheduler'); ?></option>
						<option value="never_used"><?php esc_html_e('Never Used', 'ai-post-scheduler'); ?></option>
					</select>
				</div>
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-content-component-search"><?php esc_html_e('Search Content Components:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-content-component-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search components...', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-content-component-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<div class="aips-panel-body no-padding">
				<div id="aips-content-components-content"></div>
			</div>
		</div>

		<div class="tablenav">
			<span class="aips-table-footer-count" id="aips-content-components-result-count"></span>
		</div>
	</div>
</div>

<div id="aips-content-component-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-content-component-modal-title">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-content-component-modal-title"><?php esc_html_e('Add Content Component', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-content-component-form" novalidate>
				<input type="hidden" name="component_id" id="aips-content-component-id" value="0">

				<div class="aips-content-component-layout">
					<div class="aips-content-component-main">
						<div class="aips-form-row">
							<label for="aips-content-component-title">
								<?php esc_html_e('Title', 'ai-post-scheduler'); ?>
								<span class="required" aria-hidden="true">*</span>
							</label>
							<input type="text" id="aips-content-component-title" name="title" required class="regular-text">
						</div>

						<div class="aips-form-row">
							<label for="aips-content-component-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
							<textarea id="aips-content-component-description" name="description" rows="2" class="large-text"></textarea>
						</div>

						<div class="aips-content-component-grid">
							<div class="aips-form-row">
								<label for="aips-content-component-type"><?php esc_html_e('Component Type', 'ai-post-scheduler'); ?></label>
								<select id="aips-content-component-type" name="component_type" class="aips-form-select">
									<option value="cta"><?php esc_html_e('CTA Card', 'ai-post-scheduler'); ?></option>
									<option value="faq"><?php esc_html_e('FAQ', 'ai-post-scheduler'); ?></option>
									<option value="pros_cons"><?php esc_html_e('Pros/Cons', 'ai-post-scheduler'); ?></option>
									<option value="summary"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></option>
									<option value="disclaimer"><?php esc_html_e('Disclaimer', 'ai-post-scheduler'); ?></option>
									<option value="internal_link_pod"><?php esc_html_e('Internal Link Pod', 'ai-post-scheduler'); ?></option>
									<option value="custom"><?php esc_html_e('Custom', 'ai-post-scheduler'); ?></option>
								</select>
							</div>

							<div class="aips-form-row">
								<label class="aips-checkbox-label aips-content-component-active-toggle">
									<input type="checkbox" id="aips-content-component-is-active" name="is_active" value="1" checked>
									<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
								</label>
							</div>
						</div>

						<div class="aips-form-row">
							<label for="aips-content-component-content"><?php esc_html_e('Component Content', 'ai-post-scheduler'); ?></label>
							<textarea id="aips-content-component-content" name="content" rows="10" class="large-text code"></textarea>
							<p class="description"><?php esc_html_e('Use HTML or plain text that should be inserted when rules match.', 'ai-post-scheduler'); ?></p>
						</div>

						<div class="aips-content-component-rules-wrap" id="aips-content-component-rules-wrap">
							<h3><?php esc_html_e('Rules', 'ai-post-scheduler'); ?></h3>
							<p class="description"><?php esc_html_e('Define WHEN this component should be inserted and WHAT to do.', 'ai-post-scheduler'); ?></p>

							<div class="aips-content-component-grid">
								<div class="aips-form-row">
									<label for="aips-content-component-rules-logic"><?php esc_html_e('Condition Join', 'ai-post-scheduler'); ?></label>
									<select id="aips-content-component-rules-logic" class="aips-form-select">
										<option value="and"><?php esc_html_e('AND (all conditions must match)', 'ai-post-scheduler'); ?></option>
										<option value="or"><?php esc_html_e('OR (any condition can match)', 'ai-post-scheduler'); ?></option>
									</select>
								</div>

								<div class="aips-form-row">
									<label for="aips-content-component-rules-action"><?php esc_html_e('Placement', 'ai-post-scheduler'); ?></label>
									<select id="aips-content-component-rules-action" class="aips-form-select"></select>
								</div>
							</div>

							<div class="aips-content-component-grid">
								<div class="aips-form-row">
									<label for="aips-content-component-date-start"><?php esc_html_e('Start Window', 'ai-post-scheduler'); ?></label>
									<input type="text" id="aips-content-component-date-start" class="regular-text" placeholder="2026-11-20 00:00:00">
								</div>
								<div class="aips-form-row">
									<label for="aips-content-component-date-end"><?php esc_html_e('End Window', 'ai-post-scheduler'); ?></label>
									<input type="text" id="aips-content-component-date-end" class="regular-text" placeholder="2026-12-01 23:59:59">
								</div>
							</div>

							<div class="aips-form-row">
								<label for="aips-content-component-date-timezone"><?php esc_html_e('Window Timezone', 'ai-post-scheduler'); ?></label>
								<input type="text" id="aips-content-component-date-timezone" class="regular-text" placeholder="<?php echo esc_attr( wp_timezone_string() ); ?>">
							</div>

							<div id="aips-content-component-rules-list"></div>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-add-content-component-rule">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e('Add Condition', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>

					<aside class="aips-content-component-side">
						<div class="aips-content-component-card aips-content-component-qa">
							<label class="aips-content-component-qa-label"><?php esc_html_e('QA Gate', 'ai-post-scheduler'); ?></label>
							<div id="aips-content-component-qa-status" class="aips-badge aips-badge-neutral"><?php esc_html_e('Untested', 'ai-post-scheduler'); ?></div>
							<p id="aips-content-component-qa-notes" class="description"></p>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-content-component-run-qa">
								<?php esc_html_e('Run QA Validation', 'ai-post-scheduler'); ?>
							</button>
						</div>

						<div class="aips-content-component-card">
							<h3><?php esc_html_e('Rule Summary', 'ai-post-scheduler'); ?></h3>
							<p id="aips-content-component-rule-summary" class="aips-content-component-rule-summary"><?php esc_html_e('No rules yet.', 'ai-post-scheduler'); ?></p>
						</div>

						<div class="aips-content-component-card aips-content-component-preview-wrap">
							<h3><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></h3>
							<div id="aips-content-component-preview" class="aips-content-component-preview"></div>
						</div>

						<div class="aips-content-component-card">
							<h3><?php esc_html_e('Analytics', 'ai-post-scheduler'); ?></h3>
							<div id="aips-content-component-analytics" class="aips-content-component-analytics"></div>
						</div>
					</aside>
				</div>

				<div class="aips-content-component-simulate-wrap">
					<div class="aips-content-component-simulate-header">
						<h3><?php esc_html_e('Simulate', 'ai-post-scheduler'); ?></h3>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-content-component-dry-run-btn"><?php esc_html_e('Run Simulation', 'ai-post-scheduler'); ?></button>
					</div>

					<div class="aips-content-component-dry-run-form">
						<div class="aips-form-row">
							<label for="aips-content-component-dry-run-post-id"><?php esc_html_e('Existing Post ID', 'ai-post-scheduler'); ?></label>
							<input type="number" id="aips-content-component-dry-run-post-id" class="small-text" min="0" step="1">
						</div>
						<div class="aips-form-row">
							<label for="aips-content-component-dry-run-region"><?php esc_html_e('Region', 'ai-post-scheduler'); ?></label>
							<input type="text" id="aips-content-component-dry-run-region" class="regular-text" placeholder="US">
						</div>
						<div class="aips-form-row">
							<label for="aips-content-component-dry-run-locale"><?php esc_html_e('Locale', 'ai-post-scheduler'); ?></label>
							<input type="text" id="aips-content-component-dry-run-locale" class="regular-text" placeholder="<?php echo esc_attr( get_locale() ); ?>">
						</div>
						<div class="aips-form-row">
							<label for="aips-content-component-dry-run-categories"><?php esc_html_e('Categories', 'ai-post-scheduler'); ?></label>
							<input type="text" id="aips-content-component-dry-run-categories" class="regular-text" placeholder="<?php esc_attr_e('sales, marketing', 'ai-post-scheduler'); ?>">
						</div>
						<div class="aips-form-row">
							<label for="aips-content-component-dry-run-tags"><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></label>
							<input type="text" id="aips-content-component-dry-run-tags" class="regular-text" placeholder="<?php esc_attr_e('investing, saas', 'ai-post-scheduler'); ?>">
						</div>
						<div class="aips-form-row">
							<label for="aips-content-component-dry-run-persona"><?php esc_html_e('Author Persona', 'ai-post-scheduler'); ?></label>
							<input type="text" id="aips-content-component-dry-run-persona" class="regular-text" placeholder="<?php esc_attr_e('founder, consultant', 'ai-post-scheduler'); ?>">
						</div>
					</div>

					<div class="aips-form-row">
						<label for="aips-content-component-dry-run-draft-body"><?php esc_html_e('Draft Body', 'ai-post-scheduler'); ?></label>
						<textarea id="aips-content-component-dry-run-draft-body" rows="8" class="large-text"></textarea>
					</div>

					<div id="aips-content-component-dry-run-results" class="aips-content-component-dry-run-results">
						<p class="description"><?php esc_html_e('Run a simulation to preview matched components and insertions.', 'ai-post-scheduler'); ?></p>
					</div>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary" id="aips-save-content-component-btn">
				<?php esc_html_e('Save Content Component', 'ai-post-scheduler'); ?>
			</button>
		</div>

		<div class="aips-content-panel aips-backfill-panel" id="aips-content-components-backfill-panel">
			<div class="aips-backfill-panel-header" id="aips-backfill-toggle" role="button" tabindex="0"
				aria-expanded="false" aria-controls="aips-backfill-panel-body"
				style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; padding:12px 16px;">
				<h2 style="margin:0; font-size:1rem;"><?php esc_html_e('Backfill Existing Posts', 'ai-post-scheduler'); ?></h2>
				<span class="dashicons dashicons-arrow-down-alt2 aips-backfill-toggle-icon"></span>
			</div>
			<div class="aips-panel-body aips-backfill-panel-body" id="aips-backfill-panel-body" style="display:none; padding:16px;">
				<p class="description" style="margin-bottom:16px;">
					<?php esc_html_e('Preview and apply Content Component injection to posts published before components were set up.', 'ai-post-scheduler'); ?>
				</p>

				<div class="aips-backfill-form" style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
					<div class="aips-form-row">
						<label for="aips-backfill-post-type"><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></label>
						<select id="aips-backfill-post-type" class="aips-form-select">
							<option value="post"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></option>
							<option value="page"><?php esc_html_e('Page', 'ai-post-scheduler'); ?></option>
						</select>
					</div>
					<div class="aips-form-row">
						<label for="aips-backfill-limit"><?php esc_html_e('Limit (max 200)', 'ai-post-scheduler'); ?></label>
						<input type="number" id="aips-backfill-limit" class="small-text" value="50" min="1" max="200" step="1">
					</div>
					<div class="aips-form-row" style="flex:1; min-width:200px;">
						<label for="aips-backfill-post-ids"><?php esc_html_e('Specific Post IDs (optional)', 'ai-post-scheduler'); ?></label>
						<input type="text" id="aips-backfill-post-ids" class="regular-text" placeholder="<?php esc_attr_e('e.g. 1, 2, 3', 'ai-post-scheduler'); ?>">
					</div>
				</div>

				<div class="aips-backfill-actions" style="margin-bottom:16px;">
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-backfill-preview-btn">
						<?php esc_html_e('Preview Backfill', 'ai-post-scheduler'); ?>
					</button>
				</div>

				<div id="aips-backfill-preview-results"></div>

				<div id="aips-backfill-apply-wrap" style="display:none; margin-top:16px;">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-backfill-apply-btn">
						<?php esc_html_e('Apply Backfill', 'ai-post-scheduler'); ?>
					</button>
				</div>

				<div id="aips-backfill-apply-results"></div>
			</div>
		</div>
	</div>
</div>

<div id="aips-content-component-example-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-content-component-example-modal-title">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-content-component-example-modal-title"><?php esc_html_e('Choose a Starter Example', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<p class="description"><?php esc_html_e('Pick one of these runtime-generated examples to prefill the component form.', 'ai-post-scheduler'); ?></p>
			<div id="aips-content-component-example-list" class="aips-content-component-example-list"></div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary" id="aips-content-component-refresh-examples-btn"><?php esc_html_e('Refresh Examples', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-content-components-table">
<table class="aips-table aips-content-components-table">
	<thead>
		<tr>
			<th class="column-title">{{titleLabel}}</th>
			<th class="column-type">{{typeLabel}}</th>
			<th class="column-rules">{{rulesLabel}}</th>
			<th class="column-usage">{{usageLabel}}</th>
			<th class="column-status">{{statusLabel}}</th>
			<th class="column-qa">{{qaLabel}}</th>
			<th class="column-updated">{{updatedLabel}}</th>
			<th class="column-actions">{{actionsLabel}}</th>
		</tr>
	</thead>
	<tbody>{{rows}}</tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-content-component-row">
<tr data-component-id="{{id}}">
	<td class="column-title cell-primary">
		<strong>{{title}}</strong>
		<div class="cell-meta">{{description}}</div>
	</td>
	<td class="column-type">{{componentType}}</td>
	<td class="column-rules"><div class="aips-content-component-table-summary">{{ruleSummary}}</div></td>
	<td class="column-usage">{{usageSummary}}</td>
	<td class="column-status">{{statusBadge}}</td>
	<td class="column-qa">{{qaBadge}}</td>
	<td class="column-updated">{{updatedAt}}</td>
	<td class="column-actions">
		<div class="cell-actions">
			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-content-component" data-id="{{id}}">{{editLabel}}</button>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-content-component" data-id="{{id}}" data-active="{{isActive}}">{{toggleLabel}}</button>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-content-component" data-id="{{id}}">{{deleteLabel}}</button>
		</div>
	</td>
</tr>
</script>

<script type="text/html" id="aips-tmpl-content-components-empty">
<div class="aips-empty-state">
	<div class="dashicons dashicons-layout aips-empty-state-icon" aria-hidden="true"></div>
	<h3 class="aips-empty-state-title">{{title}}</h3>
	<p class="aips-empty-state-description">{{description}}</p>
	<div class="aips-empty-state-actions">
		<button type="button" class="aips-btn aips-btn-primary" id="aips-add-content-component-empty-btn">{{buttonLabel}}</button>
	</div>
</div>
</script>

<script type="text/html" id="aips-tmpl-content-component-rule-row">
<div class="aips-content-component-rule-row" data-index="{{index}}">
	<div class="aips-content-component-rule-join">{{joinLabel}}</div>
	<select class="aips-form-select aips-cc-rule-field">{{fieldOptions}}</select>
	<select class="aips-form-select aips-cc-rule-operator">{{operatorOptions}}</select>
	<input type="text" class="aips-form-input aips-cc-rule-values" value="{{values}}" placeholder="{{valuePlaceholder}}">
	<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-remove-content-component-rule">{{removeLabel}}</button>
</div>
</script>

<script type="text/html" id="aips-tmpl-content-component-example-card">
<article class="aips-content-component-example-card">
	<div class="aips-content-component-example-meta">
		<span class="aips-badge aips-badge-neutral">{{typeLabel}}</span>
	</div>
	<h3>{{name}}</h3>
	<p>{{description}}</p>
	<div class="aips-content-component-example-snippet">{{snippet}}</div>
	<div class="aips-content-component-example-hints">{{hints}}</div>
	<button type="button" class="aips-btn aips-btn-secondary aips-use-content-component-example" data-example-key="{{key}}">{{useLabel}}</button>
</article>
</script>
