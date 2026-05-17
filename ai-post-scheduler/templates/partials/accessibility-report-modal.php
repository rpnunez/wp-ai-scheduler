<?php
/**
 * Accessibility Report Modal Partial Template
 *
 * Reusable modal for displaying the Accessibility Report for a generated post.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<!-- Accessibility Report Modal -->
<div id="aips-accessibility-report-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<div class="aips-modal-header-title">
				<h2 id="aips-accessibility-report-modal-title"><?php esc_html_e('Accessibility Report', 'ai-post-scheduler'); ?></h2>
				<p id="aips-accessibility-report-modal-subtitle" class="aips-modal-subtitle"></p>
			</div>
			<div class="aips-modal-header-actions">
				<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
		</div>
		<div class="aips-modal-body">
			<div id="aips-accessibility-report-content"></div>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-access-report-loading">
	<div class="aips-access-report-message aips-access-report-message-loading">
		<span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span>
		<span>{{text}}</span>
	</div>
</script>

<script type="text/html" id="aips-tmpl-access-report-error">
	<div class="aips-access-report-message aips-access-report-message-error">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<span>{{message}}</span>
	</div>
</script>

<script type="text/html" id="aips-tmpl-access-report-empty">
	<div class="aips-access-report-message aips-access-report-message-empty">
		<span class="dashicons dashicons-info" aria-hidden="true"></span>
		<span>{{message}}</span>
	</div>
</script>

<script type="text/html" id="aips-tmpl-access-report-shell">
	<div class="aips-access-report">
		<div class="aips-access-report-top">
			<div class="aips-access-report-top-left">
				<span class="dashicons dashicons-universal-access-alt" aria-hidden="true"></span>
				<strong>{{report_title}}</strong>
			</div>
			<div class="aips-access-report-top-right">{{status_badge}}</div>
		</div>
		<div class="aips-access-report-grid">{{cards_html}}</div>
		<div class="aips-access-report-section">
			<h3>{{findings_title}}</h3>
			{{findings_html}}
		</div>
	</div>
</script>

<script type="text/html" id="aips-tmpl-access-report-badge">
	<span class="aips-badge aips-badge-{{status}}">
		<span class="dashicons dashicons-{{icon}}" aria-hidden="true"></span>
		{{text}}
	</span>
</script>

<script type="text/html" id="aips-tmpl-access-report-card">
	<div class="aips-access-report-card aips-access-report-card-{{status}}">
		<div class="aips-access-report-card-title">
			<span class="dashicons dashicons-{{icon}}" aria-hidden="true"></span>
			<span>{{title}}</span>
		</div>
		<div class="aips-access-report-card-body">{{lines_html}}</div>
	</div>
</script>

<script type="text/html" id="aips-tmpl-access-report-card-lines">
	<ul class="aips-access-report-card-lines">{{items_html}}</ul>
</script>

<script type="text/html" id="aips-tmpl-access-report-card-line">
	<li>{{text}}</li>
</script>

<script type="text/html" id="aips-tmpl-access-report-findings-list">
	<ul class="aips-access-report-findings">{{items_html}}</ul>
</script>

<script type="text/html" id="aips-tmpl-access-report-finding-item">
	<li>
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<span>{{text}}</span>
	</li>
</script>

<script type="text/html" id="aips-tmpl-access-report-findings-empty">
	<div class="aips-access-report-findings-empty">{{message}}</div>
</script>
