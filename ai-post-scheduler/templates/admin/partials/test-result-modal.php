<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<!-- Test Result Modal -->
<div id="aips-test-result-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2><?php esc_html_e('Test Generation Result', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<div id="aips-test-result-container">
				<div class="aips-form-row">
					<p id="aips-test-title-label"><strong><?php esc_html_e('Generated Title:', 'ai-post-scheduler'); ?></strong></p>
					<div id="aips-test-title" class="aips-preview-box" aria-labelledby="aips-test-title-label" style="background: #f0f0f1; padding: 10px; border: 1px solid #c3c4c7;"></div>
				</div>

				<div class="aips-form-row">
					<p id="aips-test-excerpt-label"><strong><?php esc_html_e('Generated Excerpt:', 'ai-post-scheduler'); ?></strong></p>
					<div id="aips-test-excerpt" class="aips-preview-box" aria-labelledby="aips-test-excerpt-label" style="background: #f0f0f1; padding: 10px; border: 1px solid #c3c4c7;"></div>
				</div>

				<div class="aips-form-row" id="aips-test-image-row" style="display: none;">
					<p id="aips-test-image-label"><strong><?php esc_html_e('Image Preview (Prompt/Keywords):', 'ai-post-scheduler'); ?></strong></p>
					<div id="aips-test-image" class="aips-preview-box" aria-labelledby="aips-test-image-label" style="background: #f0f0f1; padding: 10px; border: 1px solid #c3c4c7;"></div>
				</div>

				<div class="aips-form-row">
					<p id="aips-test-content-label"><strong><?php esc_html_e('Generated Content:', 'ai-post-scheduler'); ?></strong></p>
					<div id="aips-test-content" class="aips-preview-box" aria-labelledby="aips-test-content-label" style="background: #f0f0f1; padding: 10px; border: 1px solid #c3c4c7; max-height: 400px; overflow-y: auto; white-space: pre-wrap;"></div>
				</div>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
