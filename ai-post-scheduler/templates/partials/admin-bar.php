<?php
/**
 * Admin Bar client-side templates.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<script type="text/html" id="aips-tmpl-admin-bar-no-notifications">
	<li id="wp-admin-bar-aips-toolbar-no-notifications" class="aips-toolbar-no-notifications ab-empty-item">
		<span class="ab-item aips-toolbar-empty">{{text}}</span>
	</li>
</script>

<script type="text/html" id="aips-tmpl-admin-bar-header">
	<li id="wp-admin-bar-aips-toolbar-notifications-header" class="aips-toolbar-notif-header ab-empty-item">
		<div class="ab-item ab-empty-item">
			<span class="aips-toolbar-notif-heading">{{headingText}}</span>
			<button class="aips-mark-all-read" type="button" data-nonce="{{nonce}}">{{markAllText}}</button>
		</div>
	</li>
</script>

<script type="text/html" id="aips-tmpl-admin-bar-notification-title">
	<span class="aips-notif-title">{{title}}</span>
</script>

<script type="text/html" id="aips-tmpl-admin-bar-notification-message-link">
	<a href="{{url}}">{{message}}</a>
</script>

<script type="text/html" id="aips-tmpl-admin-bar-notification-row">
	<li id="wp-admin-bar-aips-notif-{{id}}" class="aips-toolbar-notification ab-empty-item{{levelClass}}" data-notif-id="{{id}}">
		<div class="ab-item ab-empty-item">
			{{titleHtml}}
			<span class="aips-notif-message">{{messageHtml}}</span>
			<button class="aips-mark-read" type="button" data-id="{{id}}" data-nonce="{{nonce}}" title="{{markReadText}}" aria-label="{{markReadText}}">
				<span class="dashicons dashicons-yes-alt"></span>
			</button>
		</div>
	</li>
</script>

<script type="text/html" id="aips-tmpl-admin-bar-toast-message">
	<strong>{{title}}</strong>: {{message}}
</script>

<script type="text/html" id="aips-tmpl-admin-bar-toast-link-message">
	<strong>{{title}}</strong>: <a href="{{url}}">{{message}}</a>
</script>
