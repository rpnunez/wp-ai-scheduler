<?php
/**
 * AI Assistance Templates Partial
 *
 * Shared HTML templates/modal used by AIPS.AIAssistance across admin pages.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<!-- AI Assistance: Combined Assist + History Button Template -->
<script type="text/html" id="aips-tmpl-ai-assist-btn">
<div class="aips-ai-assist-btn-group">
	<button type="button"
		class="aips-btn aips-btn-sm aips-btn-ghost aips-ai-assist-btn"
		data-field-id="{{fieldId}}"
		title="<?php esc_attr_e('Get AI suggestion', 'ai-post-scheduler'); ?>"
		aria-label="<?php esc_attr_e('Get AI suggestion for this field', 'ai-post-scheduler'); ?>">
		<span class="aips-ai-sparkle" aria-hidden="true">&#10024;</span>
		<span class="aips-ai-assist-btn-label"><?php esc_html_e('AI Suggest', 'ai-post-scheduler'); ?></span>
	</button>
	<button type="button"
		class="aips-btn aips-btn-sm aips-btn-ghost aips-ai-assist-history-btn"
		data-field-id="{{fieldId}}"
		style="display:none"
		title="<?php esc_attr_e('View AI suggestion history', 'ai-post-scheduler'); ?>"
		aria-label="<?php esc_attr_e('View AI suggestion history for this field', 'ai-post-scheduler'); ?>">
		<span class="dashicons dashicons-backup" aria-hidden="true"></span>
		<span class="screen-reader-text"><?php esc_html_e('View history', 'ai-post-scheduler'); ?></span>
	</button>
</div>
</script>

<!-- AI Assistance: History Modal -->
<div id="aips-ai-assist-history-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-ai-assist-history-modal-title">
	<div class="aips-modal-content aips-modal-large">
		<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
		<h2 id="aips-ai-assist-history-modal-title"><?php esc_html_e('AI Suggestion History', 'ai-post-scheduler'); ?></h2>
		<p id="aips-ai-assist-history-field-label" class="description"></p>
		<div class="aips-tab-nav" id="aips-ai-assist-history-tabs">
			<a href="#" class="aips-tab-link active" data-tab="aips-ai-assist-history-session"><?php esc_html_e('This Session', 'ai-post-scheduler'); ?></a>
			<a href="#" class="aips-tab-link" data-tab="aips-ai-assist-history-alltime"><?php esc_html_e('All Time', 'ai-post-scheduler'); ?></a>
		</div>
		<div id="aips-ai-assist-history-session-tab" class="aips-tab-content">
			<p class="description"><?php esc_html_e('Loading...', 'ai-post-scheduler'); ?></p>
		</div>
		<div id="aips-ai-assist-history-alltime-tab" class="aips-tab-content" style="display:none;">
			<p class="description"><?php esc_html_e('Loading...', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
</div>

<!-- AI Assistance: History Item Template -->
<script type="text/html" id="aips-tmpl-ai-assist-history-item">
<div class="aips-ai-assist-history-item">
	<div class="aips-ai-assist-history-response">{{response}}</div>
	<div class="aips-ai-assist-history-meta">{{created_at}}</div>
	<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-ai-assist-history-use"
		data-field-id="{{fieldId}}"
		data-record-id="{{id}}">
		<?php esc_html_e('Use This Value', 'ai-post-scheduler'); ?>
	</button>
</div>
</script>
