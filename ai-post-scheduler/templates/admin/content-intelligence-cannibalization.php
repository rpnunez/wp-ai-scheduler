<?php
/**
 * Cannibalization & Semantic Duplicate Audit Admin Tab Partial
 *
 * Dedicated interface for detecting duplicate articles, keyword cannibalization risks,
 * and Author Topic overlaps.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 *
 * @var array   $banners
 * @var array   $settings
 * @var array[] $consolidations Recent consolidations (AIPS_Consolidation_Service::get_history()).
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="aips-content-cannibalization-tab">

	<!-- Embeddings Disabled Notice -->
	<?php if (!empty($banners['embeddings_disabled'])) : ?>
	<div class="notice notice-info inline aips-banner aips-embeddings-disabled-banner">
		<div class="aips-banner-inner">
			<div>
				<h4 class="aips-banner-title">
					<span class="dashicons dashicons-info aips-banner-icon" aria-hidden="true"></span>
					<?php esc_html_e('Vector Embeddings System is Currently Disabled', 'ai-post-scheduler'); ?>
				</h4>
				<p class="aips-banner-desc">
					<?php esc_html_e('Cannibalization audits require active vector embeddings. Please enable embeddings in settings to perform audit scans.', 'ai-post-scheduler'); ?>
				</p>
			</div>
			<div>
				<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-btn aips-btn-sm aips-btn-primary">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<?php esc_html_e('Enable Embeddings', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Cooldown Alert Banner -->
	<?php
	$cooldown_banner = isset($banners['cooldown']) ? $banners['cooldown'] : array();
	$cooldown_until  = isset($cooldown_banner['until']) ? (int) $cooldown_banner['until'] : 0;
	?>
	<div id="aips-cooldown-banner" class="notice notice-warning inline aips-banner aips-cooldown-banner <?php echo !empty($cooldown_banner['active']) ? '' : 'aips-hidden'; ?>" data-until="<?php echo esc_attr((string) $cooldown_until); ?>">
		<div class="aips-banner-inner">
			<div class="aips-cooldown-content">
				<span class="dashicons dashicons-clock aips-cooldown-icon" aria-hidden="true"></span>
				<div>
					<h4 class="aips-cooldown-title">
						<strong><?php esc_html_e('Embedding API Auto-Cooldown Active', 'ai-post-scheduler'); ?></strong>
						<span id="aips-cooldown-timer-badge" class="aips-badge aips-badge-warning aips-cooldown-badge">
							<?php esc_html_e('Resuming in:', 'ai-post-scheduler'); ?> <span id="aips-cooldown-countdown"><?php echo esc_html(isset($cooldown_banner['remaining_formatted']) ? $cooldown_banner['remaining_formatted'] : '00:00'); ?></span>
						</span>
					</h4>
					<p class="aips-banner-desc aips-cooldown-reason-msg" id="aips-cooldown-reason-msg">
						<?php echo esc_html(isset($cooldown_banner['message']) ? $cooldown_banner['message'] : ''); ?>
					</p>
				</div>
			</div>
			<div>
				<button type="button" id="aips-resume-cooldown-btn" class="aips-btn aips-btn-sm aips-btn-primary">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
					<?php esc_html_e('Resume Now', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Main Cannibalization Audit Panel -->
	<div class="aips-content-panel">
		<div class="aips-panel-header aips-panel-header-flex">
			<div>
				<h3 class="aips-panel-title"><?php esc_html_e('Content Cannibalization & Overlap Matrix', 'ai-post-scheduler'); ?></h3>
				<p class="description aips-panel-header-desc">
					<?php esc_html_e('Cross-compares all published posts and topics against stored vector embeddings using cosine similarity.', 'ai-post-scheduler'); ?>
				</p>
			</div>
			<div class="aips-audit-actions aips-toolbar-right">
				<div class="aips-audit-filter-wrap">
					<label for="aips-audit-entity-type" class="screen-reader-text"><?php esc_html_e('Filter Audit Entity', 'ai-post-scheduler'); ?></label>
					<select id="aips-audit-entity-type" class="aips-form-select aips-select-sm" title="<?php esc_attr_e('Filter audit results by entity', 'ai-post-scheduler'); ?>">
						<option value="all"><?php esc_html_e('All Audits (Posts & Topics)', 'ai-post-scheduler'); ?></option>
						<option value="posts"><?php esc_html_e('Post Duplicates (Post vs Post)', 'ai-post-scheduler'); ?></option>
						<option value="topics"><?php esc_html_e('Topics & Cannibalization (Topic vs Post / Topic)', 'ai-post-scheduler'); ?></option>
					</select>
				</div>
				<button type="button" id="aips-run-audit-btn" class="aips-btn aips-btn-primary">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<?php esc_html_e('Run Audit Scan', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		<div class="aips-panel-body no-padding">
			<div id="aips-audit-loading" class="aips-audit-loading aips-hidden">
				<span class="spinner is-active"></span>
				<?php esc_html_e('Scanning relationship matrix for cannibalization clusters…', 'ai-post-scheduler'); ?>
			</div>

			<table class="aips-table widefat striped" id="aips-cannibalization-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Entity A (Source)', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Entity B (Candidate Duplicate)', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Similarity Score', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Audit Type / Risk', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-cannibalization-tbody">
					<tr>
						<td colspan="5" class="aips-table-empty-cell">
							<?php esc_html_e('Click "Run Audit Scan" above to analyze potential duplicate and cannibalizing posts.', 'ai-post-scheduler'); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<?php
	$consolidations = isset($consolidations) ? (array) $consolidations : array();
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-consolidations-panel',
			'title'       => __('Recent Consolidations', 'ai-post-scheduler'),
			'icon'        => 'dashicons-migrate',
			'description' => __('Consolidating keeps one post of an overlapping pair. The other is moved to draft, its URL redirects to the kept post, and internal links to it are re-pointed. Undo reverses all of it.', 'ai-post-scheduler'),
			'body_class'  => 'no-padding',
		),
		function () use ($consolidations) {
			?>
			<table class="aips-table widefat striped" id="aips-consolidations-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Kept', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Retired (now draft)', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Merged Content', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Links Re-pointed', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('When', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-consolidations-tbody" data-initial="<?php echo esc_attr(wp_json_encode($consolidations)); ?>"></tbody>
			</table>
			<?php
		}
	);
	?>

</div><!-- /.aips-content-cannibalization-tab -->

<!-- =====================================================================
     CLIENT-SIDE HTML TEMPLATES
     ===================================================================== -->

<!-- Template: Audit empty state row -->
<script type="text/html" id="aips-tmpl-indexer-audit-empty">
	<tr>
		<td colspan="5" class="aips-audit-empty-cell">
			{{message}}
		</td>
	</tr>
</script>

<!-- Template: Audit group header for Entity A -->
<script type="text/html" id="aips-tmpl-indexer-audit-group-header">
	<tr class="aips-audit-group-header" data-toggle-target=".{{groupId}}" data-audit-type="{{auditType}}">
		<td colspan="5">
			<span class="dashicons dashicons-arrow-down-alt2 aips-group-toggle-icon aips-audit-group-toggle-icon" aria-hidden="true"></span>
			<strong class="aips-audit-group-title">{{title}}</strong>
			{{entityBadgeHtml}}
			<span class="aips-audit-group-meta">{{postType}} #{{sourceId}}</span>
			<span class="aips-badge {{riskClass}} aips-audit-group-badge">Max: {{riskLabel}} ({{maxSimilarityPct}}%)</span>
		</td>
	</tr>
</script>

<!-- Template: Audit risk tier header -->
<script type="text/html" id="aips-tmpl-indexer-audit-risk-header">
	<tr class="aips-audit-risk-header {{groupId}}" data-toggle-target=".{{riskGroupId}}">
		<td colspan="5" class="aips-audit-risk-cell">
			<span class="dashicons {{iconClass}} aips-group-toggle-icon aips-audit-risk-icon" aria-hidden="true"></span>
			<span class="aips-badge {{riskClass}} aips-audit-risk-badge">{{riskLabel}}</span>
			<span class="aips-audit-risk-count">{{count}} {{candidateLabel}}</span>
		</td>
	</tr>
</script>

<!-- Template: Audit candidate duplicate row -->
<script type="text/html" id="aips-tmpl-indexer-audit-row">
	<tr class="aips-audit-row {{groupId}} {{riskGroupId}} {{collapseClass}}" data-audit-type="{{auditType}}">
		<td class="aips-audit-tree-indent">&rdsh;</td>
		<td>
			<strong>{{title}}</strong>
			{{entityBadgeHtml}}
			<br><small class="aips-audit-target-meta">{{postType}} #{{targetId}} ({{date}})</small>
		</td>
		<td><strong class="aips-audit-similarity-score">{{similarityPct}}%</strong></td>
		<td><span class="aips-badge {{riskClass}}">{{riskLabel}}</span></td>
		<td>{{actions}}</td>
	</tr>
</script>

<!-- Template: Audit action link button -->
<script type="text/html" id="aips-tmpl-indexer-audit-action-btn">
	<a href="{{url}}" class="aips-btn aips-btn-xs aips-btn-secondary aips-audit-action-btn" target="_blank">{{label}}</a>
</script>

<!-- Template: Clean audit scan result row -->
<script type="text/html" id="aips-tmpl-indexer-audit-clean">
	<tr>
		<td colspan="5" class="aips-audit-clean-cell">
			<strong>{{message}}</strong>
		</td>
	</tr>
</script>

<!-- Template: Entity badge -->
<script type="text/html" id="aips-tmpl-indexer-entity-badge">
	<span class="aips-badge aips-badge-{{type}}">{{label}}</span>
</script>

<!-- Consolidation dialog -->
<div id="aips-consolidate-modal" class="aips-modal" style="display:none;">
	<div class="aips-modal-content aips-consolidate-modal-content" role="dialog" aria-modal="true" aria-labelledby="aips-consolidate-title">
		<div class="aips-modal-header">
			<h3 class="aips-modal-title" id="aips-consolidate-title"><?php esc_html_e('Consolidate Overlapping Posts', 'ai-post-scheduler'); ?></h3>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<p class="aips-consolidate-loading" id="aips-consolidate-loading"><span class="spinner is-active"></span> <?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></p>

			<div id="aips-consolidate-body" class="aips-hidden">
				<fieldset class="aips-consolidate-step">
					<legend><strong><?php esc_html_e('1. Which post do you keep?', 'ai-post-scheduler'); ?></strong></legend>
					<p class="description"><?php esc_html_e('The kept post stays published at its URL. The other post is moved to draft and its URL redirects to the kept post.', 'ai-post-scheduler'); ?></p>
					<div id="aips-consolidate-choices" class="aips-consolidate-choices"></div>
				</fieldset>

				<fieldset class="aips-consolidate-step">
					<legend><strong><?php esc_html_e('2. Merge the content (optional)', 'ai-post-scheduler'); ?></strong></legend>
					<p class="description"><?php esc_html_e('AI writes one article from both posts, based on the kept post and adding what only the retired post covers. Review and edit it here before using it.', 'ai-post-scheduler'); ?></p>
					<label class="aips-form-label" for="aips-consolidate-instructions"><?php esc_html_e('Extra instructions for the AI (optional)', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-consolidate-instructions" class="aips-form-input" placeholder="<?php esc_attr_e('e.g. Keep it under 2,000 words', 'ai-post-scheduler'); ?>">
					<p>
						<button type="button" class="aips-btn aips-btn-secondary" id="aips-consolidate-generate">
							<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
							<?php esc_html_e('Generate Merged Draft', 'ai-post-scheduler'); ?>
						</button>
						<span class="aips-text-muted aips-hidden" id="aips-consolidate-ai-off"><?php esc_html_e('The AI provider is not available, so a merged draft cannot be generated.', 'ai-post-scheduler'); ?></span>
					</p>
					<div id="aips-consolidate-merge" class="aips-hidden">
						<label class="aips-form-label" for="aips-consolidate-content"><?php esc_html_e('Merged draft (HTML, editable)', 'ai-post-scheduler'); ?></label>
						<textarea id="aips-consolidate-content" class="aips-form-input aips-consolidate-content" rows="12"></textarea>
						<p><button type="button" class="aips-btn aips-btn-sm aips-btn-ghost" id="aips-consolidate-preview-toggle"><?php esc_html_e('Show formatted preview', 'ai-post-scheduler'); ?></button></p>
						<iframe id="aips-consolidate-preview" class="aips-consolidate-preview aips-hidden" sandbox="" title="<?php esc_attr_e('Merged draft preview', 'ai-post-scheduler'); ?>"></iframe>
					</div>
					<div class="aips-consolidate-modes">
						<label><input type="radio" name="aips-consolidate-mode" value="none" checked> <?php esc_html_e('Don\'t change the kept post\'s content', 'ai-post-scheduler'); ?></label>
						<label><input type="radio" name="aips-consolidate-mode" value="revision" disabled> <?php esc_html_e('Save the merged draft as a revision of the kept post (the live post is unchanged; restore the revision when you are happy with it)', 'ai-post-scheduler'); ?></label>
						<label><input type="radio" name="aips-consolidate-mode" value="rewrite" disabled> <?php esc_html_e('Rewrite the kept post with the merged draft now (the old version stays in its revisions)', 'ai-post-scheduler'); ?></label>
					</div>
				</fieldset>

				<div class="aips-consolidate-summary" id="aips-consolidate-summary"></div>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary" id="aips-consolidate-run" disabled><?php esc_html_e('Consolidate', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<!-- Template: Consolidate action button (audit row) -->
<script type="text/html" id="aips-tmpl-consolidate-btn">
	<button type="button" class="aips-btn aips-btn-xs aips-btn-primary aips-consolidate-open" data-a="{{a}}" data-b="{{b}}"><?php esc_html_e('Consolidate', 'ai-post-scheduler'); ?></button>
</script>

<!-- Template: Keep choice in the dialog -->
<script type="text/html" id="aips-tmpl-consolidate-choice">
	<label class="aips-consolidate-choice">
		<input type="radio" name="aips-consolidate-keep" value="{{id}}" {{checked}}>
		<span>
			<strong>{{title}}</strong>
			<a href="{{url}}" target="_blank" rel="noopener" class="aips-text-muted">{{url}}</a>
			<small class="aips-text-muted">{{meta}}</small>
		</span>
	</label>
</script>

<!-- Template: Recent consolidation row -->
<script type="text/html" id="aips-tmpl-consolidation-row">
	<tr class="{{row_class}}">
		<td><a href="{{keep_url}}" target="_blank" rel="noopener">{{keep_title}}</a></td>
		<td><a href="{{retire_edit}}" target="_blank" rel="noopener">{{retire_title}}</a><br><code class="aips-text-muted">{{retire_url}}</code></td>
		<td>{{content_label}} <a href="{{revision_url}}" class="{{revision_class}}" target="_blank" rel="noopener"><?php esc_html_e('Review revision', 'ai-post-scheduler'); ?></a></td>
		<td>{{links_repointed}}</td>
		<td>{{when}}</td>
		<td class="column-actions">
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-consolidation-undo {{undo_class}}" data-id="{{id}}"><?php esc_html_e('Undo', 'ai-post-scheduler'); ?></button>
			<span class="aips-badge aips-badge-neutral {{undone_class}}"><?php esc_html_e('Undone', 'ai-post-scheduler'); ?></span>
		</td>
	</tr>
</script>

<!-- Template: Recent consolidations empty row -->
<script type="text/html" id="aips-tmpl-consolidation-empty">
	<tr><td colspan="6" class="aips-text-muted">{{message}}</td></tr>
</script>
