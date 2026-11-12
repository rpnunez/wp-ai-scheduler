<?php
/**
 * Link Rules Admin Tab Partial (Content hub)
 *
 * "Always link this keyword to that post" rules, applied when posts are
 * displayed. Rules load and save over AJAX (assets/js/admin-link-rules.js).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 *
 * @var bool   $enabled      Whether rules are applied (Settings → Internal Linking).
 * @var int    $max_per_post Maximum rule links per post.
 * @var string $settings_url Internal Linking settings URL.
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-link-rules-tab aips-link-report-tab" id="aips-link-rules">

	<?php if (!$enabled) : ?>
	<div class="notice notice-warning inline aips-banner">
		<div class="aips-banner-inner">
			<div>
				<h4 class="aips-banner-title">
					<span class="dashicons dashicons-warning aips-banner-icon" aria-hidden="true"></span>
					<?php esc_html_e('Keyword link rules are turned off', 'ai-post-scheduler'); ?>
				</h4>
				<p class="aips-banner-desc"><?php esc_html_e('You can still edit rules, but they are not added to your posts until you turn them back on.', 'ai-post-scheduler'); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url($settings_url); ?>" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Open Settings', 'ai-post-scheduler'); ?></a>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-link-rules-panel',
			'title'       => __('Keyword Link Rules', 'ai-post-scheduler'),
			'icon'        => 'dashicons-admin-links',
			'description' => sprintf(
				/* translators: %d: maximum rule links per post */
				__('Link a keyword to a post everywhere it appears in your published content. Rules are added when a post is displayed, never saved into it, so turning a rule off removes its links instantly. Up to %d rule links are added per post, only in body text, never in headings or existing links, and never from a post to itself.', 'ai-post-scheduler'),
				$max_per_post
			),
			'body_class'  => 'no-padding',
		),
		function () {
			?>
			<form class="aips-link-rules-form" id="aips-link-rules-form">
				<input type="hidden" id="aips-link-rule-id" value="">
				<div class="aips-link-rules-field">
					<label for="aips-link-rule-keyword"><?php esc_html_e('Keyword or phrase', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-link-rule-keyword" class="aips-form-input" maxlength="100" placeholder="<?php esc_attr_e('e.g. keyword research', 'ai-post-scheduler'); ?>" required>
				</div>
				<div class="aips-link-rules-field aips-link-rules-target">
					<label for="aips-link-rule-target-search"><?php esc_html_e('Link to post', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-link-rule-target-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search post titles…', 'ai-post-scheduler'); ?>" autocomplete="off">
					<input type="hidden" id="aips-link-rule-target-id" value="">
					<ul class="aips-link-rules-results" id="aips-link-rule-results"></ul>
				</div>
				<div class="aips-link-rules-field">
					<label for="aips-link-rule-max"><?php esc_html_e('Links per post', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-link-rule-max" class="small-text" min="1" max="10" value="1">
				</div>
				<div class="aips-link-rules-field aips-link-rules-submit">
					<button type="submit" class="aips-btn aips-btn-primary" id="aips-link-rule-save"><?php esc_html_e('Add Rule', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-btn aips-btn-ghost aips-hidden" id="aips-link-rule-cancel"><?php esc_html_e('Cancel Edit', 'ai-post-scheduler'); ?></button>
				</div>
			</form>

			<table class="aips-table widefat striped" id="aips-link-rules-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Keyword', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Links To', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Per Post', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-link-rules-tbody"></tbody>
			</table>
			<p class="description aips-link-rules-note"><?php esc_html_e('The Link Report counts rule links after posts are scanned; run a full link scan there after big rule changes.', 'ai-post-scheduler'); ?></p>
			<?php
		}
	);
	?>

	<script type="text/html" id="aips-tmpl-link-rule-row">
		<tr>
			<td><strong>{{keyword}}</strong></td>
			<td><a href="{{target_url}}" target="_blank" rel="noopener">{{target_title}}</a> <span class="aips-badge aips-badge-danger {{missing_class}}"><?php esc_html_e('Target unavailable', 'ai-post-scheduler'); ?></span></td>
			<td>{{max_per_post}}</td>
			<td><span class="aips-badge {{status_class}}">{{status_label}}</span></td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-link-rule-edit" data-id="{{id}}"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-link-rule-toggle" data-id="{{id}}" data-enabled="{{toggle_to}}">{{toggle_label}}</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-link-rule-delete" data-id="{{id}}"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-rule-result">
		<li><button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-link-rule-pick" data-id="{{id}}" data-title="{{title}}">{{title}}</button></li>
	</script>

	<script type="text/html" id="aips-tmpl-link-rule-empty">
		<tr><td colspan="5" class="aips-text-muted">{{message}}</td></tr>
	</script>
</div>
