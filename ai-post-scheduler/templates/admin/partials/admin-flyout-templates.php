<?php
/**
 * Client-side templates for shared WordPress admin flyout navigation.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<script type="text/html" id="aips-tmpl-admin-flyout">
	<nav class="aips-admin-flyout" aria-label="{{label}}" data-hub="{{hub}}" hidden>
		<div class="aips-admin-flyout-groups">{{groups}}</div>
	</nav>
</script>
<script type="text/html" id="aips-tmpl-admin-flyout-group">
	<section class="aips-admin-flyout-group">
		<h2 class="aips-admin-flyout-heading">{{label}}</h2>
		<div class="aips-admin-flyout-items" role="menu">{{items}}</div>
	</section>
</script>
<script type="text/html" id="aips-tmpl-admin-flyout-item">
	<a class="aips-admin-flyout-item {{activeClass}}" href="{{url}}" role="menuitem" tabindex="-1" aria-current="{{current}}">
		<span class="dashicons {{icon}} aips-admin-flyout-icon" aria-hidden="true"></span>
		<span class="aips-admin-flyout-copy">
			<span class="aips-admin-flyout-title-row">
				<span class="aips-admin-flyout-title">{{label}}</span>
				<span class="aips-admin-flyout-badge {{badgeClass}}" aria-label="{{badgeLabel}}">{{badgeCount}}</span>
			</span>
			<span class="aips-admin-flyout-description">{{description}}</span>
		</span>
	</a>
</script>
