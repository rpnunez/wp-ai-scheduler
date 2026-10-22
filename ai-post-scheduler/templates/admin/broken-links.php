<?php
/**
 * Broken Links Admin Tab Partial (Content hub)
 *
 * Internal links that point at pages which no longer exist, with a suggested
 * live replacement for each. Rows are loaded over AJAX by
 * assets/js/admin-broken-links.js.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var int $broken_count Broken internal links at last scan (from AIPS_Link_Index_Repository::get_summary()).
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="aips-link-report-tab" id="aips-broken-links">

	<div class="aips-stats-grid">
		<div class="aips-stat-card">
			<div class="aips-stat-header">
				<span class="aips-stat-label"><?php esc_html_e('Broken Internal Links', 'ai-post-scheduler'); ?></span>
				<span class="dashicons dashicons-dismiss aips-stat-icon" aria-hidden="true"></span>
			</div>
			<div class="aips-stat-value-wrap">
				<span class="aips-stat-value aips-text-danger" id="aips-broken-links-stat-count"><?php echo esc_html((string) $broken_count); ?></span>
			</div>
			<p class="aips-stat-subtext"><?php esc_html_e('Links to pages on this site that no longer exist', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-broken-links-panel',
			'title'       => __('Broken Internal Links', 'ai-post-scheduler'),
			'icon'        => 'dashicons-editor-unlink',
			'description' => __('Links to pages on this site that no longer exist. Each one lists the live posts it most likely meant; re-point it, or remove the link and keep its text. Every fix can be undone.', 'ai-post-scheduler'),
			'actions'     => array(
				array(
					'id'    => 'aips-broken-links-load',
					'label' => __('Find Fixes', 'ai-post-scheduler'),
					'icon'  => 'dashicons-search',
				),
			),
			'body_class'  => 'no-padding',
		),
		function () {
			?>
			<div id="aips-broken-links-loading" class="aips-audit-loading aips-hidden">
				<span class="spinner is-active"></span>
				<?php esc_html_e('Finding replacements for broken links…', 'ai-post-scheduler'); ?>
			</div>
			<table class="aips-table widefat striped aips-hidden" id="aips-broken-links-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('In Post', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Broken Link', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Fix With', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-broken-links-tbody"></tbody>
			</table>
			<div class="aips-pagination aips-hidden" id="aips-broken-links-pagination">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-broken-links-prev" disabled><?php esc_html_e('Previous', 'ai-post-scheduler'); ?></button>
				<span class="aips-pagination-info" id="aips-broken-links-page-info"></span>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-broken-links-next" disabled><?php esc_html_e('Next', 'ai-post-scheduler'); ?></button>
			</div>
			<div class="aips-broken-fixes aips-hidden" id="aips-broken-fixes-wrap">
				<h4><?php esc_html_e('Recent fixes', 'ai-post-scheduler'); ?></h4>
				<ul id="aips-broken-fixes"></ul>
			</div>
			<?php
		}
	);
	?>

	<!-- Manual replacement picker -->
	<div class="aips-modal" id="aips-broken-pick-modal" role="dialog" aria-modal="true" aria-labelledby="aips-broken-pick-title" style="display:none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 id="aips-broken-pick-title"><?php esc_html_e('Choose a post to link to', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<label for="aips-broken-pick-search" class="screen-reader-text"><?php esc_html_e('Search posts', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-broken-pick-search" class="aips-form-input widefat" placeholder="<?php esc_attr_e('Search post titles…', 'ai-post-scheduler'); ?>">
				<ul class="aips-broken-pick-results" id="aips-broken-pick-results"></ul>
			</div>
		</div>
	</div>

	<!-- Row templates (rendered by AIPS.Templates) -->
	<script type="text/html" id="aips-tmpl-broken-row">
		<tr data-row="{{index}}">
			<td><a href="{{source_edit}}">{{source_title}}</a></td>
			<td>
				<strong>{{anchor}}</strong>
				<span class="aips-text-muted aips-broken-url">{{url}}</span>
				<span class="aips-text-muted {{occurrences_class}}">{{occurrences_label}}</span>
			</td>
			<td>
				<label class="screen-reader-text" for="aips-broken-choice-{{index}}"><?php esc_html_e('Fix with', 'ai-post-scheduler'); ?></label>
				<select class="aips-form-select aips-broken-choice" id="aips-broken-choice-{{index}}" data-row="{{index}}"></select>
			</td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-broken-fix" data-row="{{index}}"><?php esc_html_e('Fix', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-broken-option">
		<option value="{{value}}">{{label}}</option>
	</script>

	<script type="text/html" id="aips-tmpl-broken-fix">
		<li>
			<span class="aips-badge {{action_class}}">{{action_label}}</span>
			<a href="{{source_edit}}">{{source_title}}</a>
			<span class="aips-text-muted">{{detail}}</span>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-broken-undo {{undo_class}}" data-fix-id="{{id}}"><?php esc_html_e('Undo', 'ai-post-scheduler'); ?></button>
			<span class="aips-text-muted {{undone_class}}"><?php esc_html_e('Undone', 'ai-post-scheduler'); ?></span>
		</li>
	</script>

	<script type="text/html" id="aips-tmpl-broken-pick-result">
		<li><button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-broken-pick" data-id="{{id}}" data-title="{{title}}">{{title}}</button></li>
	</script>

	<script type="text/html" id="aips-tmpl-broken-links-empty-row">
		<tr><td colspan="{{colspan}}" class="aips-text-muted">{{message}}</td></tr>
	</script>
</div><!-- /#aips-broken-links -->
