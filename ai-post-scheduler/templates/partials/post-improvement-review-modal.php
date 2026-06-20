<?php
/**
 * Post improvement review modal.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div id="aips-post-improvement-review-modal" class="aips-modal" style="display:none;">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large" style="max-width: 1080px; width: 96%;">
		<div class="aips-modal-header">
			<h2><?php esc_html_e('Review Post Improvement Suggestions', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" id="aips-post-improvement-review-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="aips-modal-body">
			<div class="aips-post-improvement-review-loading" style="display:none;">
				<span class="spinner is-active"></span>
				<?php esc_html_e('Loading suggestion details…', 'ai-post-scheduler'); ?>
			</div>
			<div id="aips-post-improvement-review-content"></div>
		</div>
		<div class="aips-modal-footer">
			<div class="aips-modal-footer-right">
				<button type="button" class="button" id="aips-post-improvement-review-dismiss-selected"><?php esc_html_e('Dismiss selected', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button" id="aips-post-improvement-review-accept-all"><?php esc_html_e('Accept all', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button button-primary" id="aips-post-improvement-review-apply-selected"><?php esc_html_e('Apply selected', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>
</div>
