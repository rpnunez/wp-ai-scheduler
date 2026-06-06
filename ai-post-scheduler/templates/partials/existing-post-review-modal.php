<?php
/**
 * Existing post review modal.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
exit;
}
?>
<div id="aips-existing-post-review-modal" class="aips-modal" style="display:none;">
<div class="aips-modal-overlay"></div>
<div class="aips-modal-content aips-modal-large" style="max-width: 1080px; width: 96%;">
<div class="aips-modal-header">
<h2><?php esc_html_e('Review Existing Post Suggestions', 'ai-post-scheduler'); ?></h2>
<button type="button" class="aips-modal-close" id="aips-existing-post-review-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
<span class="dashicons dashicons-no-alt"></span>
</button>
</div>
<div class="aips-modal-body">
<div class="aips-existing-review-loading" style="display:none;">
<span class="spinner is-active"></span>
<?php esc_html_e('Loading suggestion details…', 'ai-post-scheduler'); ?>
</div>
<div id="aips-existing-review-content"></div>
</div>
<div class="aips-modal-footer">
<div class="aips-modal-footer-right">
<button type="button" class="button" id="aips-existing-review-dismiss-selected"><?php esc_html_e('Dismiss selected', 'ai-post-scheduler'); ?></button>
<button type="button" class="button" id="aips-existing-review-accept-all"><?php esc_html_e('Accept all', 'ai-post-scheduler'); ?></button>
<button type="button" class="button button-primary" id="aips-existing-review-apply-selected"><?php esc_html_e('Apply selected', 'ai-post-scheduler'); ?></button>
</div>
</div>
</div>
</div>
