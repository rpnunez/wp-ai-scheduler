<?php
/**
 * Classic Editor "Internal Links" meta box.
 *
 * A shell rendered by AIPS_Internal_Links_Editor_Panel; contents are loaded
 * and rendered by assets/js/admin-internal-links-metabox.js.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 *
 * @var WP_Post $post Post being edited.
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-link-panel" id="aips-link-panel" data-post-id="<?php echo esc_attr((string) $post->ID); ?>">
	<p class="aips-link-panel-status" id="aips-link-panel-status"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></p>
	<div id="aips-link-panel-body" hidden></div>
</div>

<script type="text/html" id="aips-tmpl-link-panel-body">
	<ul class="aips-link-panel-counts">
		<li><strong>{{inbound}}</strong> <span>{{inbound_label}}</span></li>
		<li><strong>{{outbound}}</strong> <span>{{outbound_label}}</span></li>
		<li><strong>{{external}}</strong> <span>{{external_label}}</span></li>
		<li class="{{broken_class}}"><strong>{{broken}}</strong> <span>{{broken_label}}</span></li>
	</ul>
	<p class="aips-link-panel-orphan {{orphan_class}}">{{orphan_label}}</p>
	<p class="aips-link-panel-heading {{sources_class}}">{{linked_from_label}}</p>
	<ul class="aips-link-panel-sources" id="aips-link-panel-sources"></ul>
	<p class="aips-link-panel-heading">{{suggestions_label}}</p>
	<p class="description {{well_linked_class}}">{{well_linked_label}}</p>
	<ul class="aips-link-panel-suggestions" id="aips-link-panel-suggestions"></ul>
	<p>
		<button type="button" class="button {{suggest_class}}" id="aips-link-panel-suggest">{{suggest_label}}</button>
		<a href="{{report_url}}" class="aips-link-panel-report">{{report_label}}</a>
	</p>
	<p class="aips-link-panel-message" id="aips-link-panel-message" role="status"></p>
</script>

<script type="text/html" id="aips-tmpl-link-panel-source">
	<li><a href="{{edit}}">{{title}}</a> <span class="description">“{{anchor}}”</span></li>
</script>

<script type="text/html" id="aips-tmpl-link-panel-suggestion">
	<li class="aips-link-panel-suggestion aips-link-suggestion-{{status}}">
		<a href="{{source_edit}}">{{source_title}}</a>
		<span class="aips-link-panel-confidence">{{confidence}}%</span>
		<span class="description aips-link-panel-context">{{context}}</span>
		<span class="aips-link-panel-actions">
			<button type="button" class="button button-small button-primary aips-link-panel-apply {{pending_class}}" data-id="{{id}}" {{apply_disabled}}>{{insert_label}}</button>
			<button type="button" class="button button-small aips-link-panel-dismiss {{pending_class}}" data-id="{{id}}">{{dismiss_label}}</button>
			<span class="aips-link-panel-inserted {{inserted_class}}">{{inserted_label}}</span>
			<button type="button" class="button button-small aips-link-panel-revert {{inserted_class}}" data-id="{{id}}">{{undo_label}}</button>
		</span>
	</li>
</script>
