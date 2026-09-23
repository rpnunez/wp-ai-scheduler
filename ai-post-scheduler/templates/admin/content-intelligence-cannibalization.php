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
 * @var array $banners
 * @var array $settings
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
