<?php
/**
 * View Session Modal Partial Template
 *
 * Reusable modal for displaying post generation session data including logs and AI calls.
 * Include this template in any admin page that needs View Session functionality.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<!-- Session View Modal -->
<div id="aips-session-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2><?php esc_html_e('View Session', 'ai-post-scheduler'); ?></h2>
			<div class="aips-modal-header-actions">
				<button class="button button-primary aips-copy-session-json">
					<?php esc_html_e('Copy Session JSON', 'ai-post-scheduler'); ?>
				</button>
				<button class="button aips-download-session-json">
					<?php esc_html_e('Download Session JSON', 'ai-post-scheduler'); ?>
				</button>
				<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
		</div>
		<div class="aips-modal-body">
			<div class="aips-session-info">
				<p><strong><?php esc_html_e('Post:', 'ai-post-scheduler'); ?></strong> <span id="aips-session-title"></span></p>
				<p><strong><?php esc_html_e('Generated:', 'ai-post-scheduler'); ?></strong> <span id="aips-session-created"></span></p>
				<p><strong><?php esc_html_e('Completed:', 'ai-post-scheduler'); ?></strong> <span id="aips-session-completed"></span></p>
			</div>
			
			<div class="aips-tabs">
				<ul class="aips-tab-nav">
					<li><a href="#aips-tab-logs" class="active"><?php esc_html_e('Logs', 'ai-post-scheduler'); ?></a></li>
					<li><a href="#aips-tab-ai"><?php esc_html_e('AI', 'ai-post-scheduler'); ?></a></li>
				</ul>
				
				<div id="aips-tab-logs" class="aips-tab-content active">
					<div id="aips-logs-list"></div>
				</div>
				
				<div id="aips-tab-ai" class="aips-tab-content" style="display: none;">
					<div id="aips-ai-list"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Make history type constants available to the JS file
window.AIPS_History_Type = {
	LOG: <?php echo AIPS_History_Type::LOG; ?>,
	ERROR: <?php echo AIPS_History_Type::ERROR; ?>,
	WARNING: <?php echo AIPS_History_Type::WARNING; ?>,
	INFO: <?php echo AIPS_History_Type::INFO; ?>,
	AI_REQUEST: <?php echo AIPS_History_Type::AI_REQUEST; ?>,
	AI_RESPONSE: <?php echo AIPS_History_Type::AI_RESPONSE; ?>,
	DEBUG: <?php echo AIPS_History_Type::DEBUG; ?>,
	ACTIVITY: <?php echo AIPS_History_Type::ACTIVITY; ?>,
	SESSION_METADATA: <?php echo AIPS_History_Type::SESSION_METADATA; ?>
};

// Make AJAX nonce available to the JS file
// Note: The nonce should always be available from localization
// This fallback is only for edge cases where localization fails
if (typeof window.aipsAjaxNonce === 'undefined') {
	window.aipsAjaxNonce = '<?php echo esc_js(wp_create_nonce('aips_ajax_nonce')); ?>';
}
</script>
