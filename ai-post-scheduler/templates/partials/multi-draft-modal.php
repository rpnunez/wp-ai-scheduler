<?php
/**
 * Multi-Draft Compare Modal Partial Template
 *
 * Reusable modal for generating and comparing multiple draft variants.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<!-- Multi-Draft Compare Modal -->
<div id="aips-multi-draft-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-multi-draft-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large" style="display: flex; flex-direction: column; max-height: 90vh;">
		<div class="aips-modal-header">
			<h2 id="aips-multi-draft-modal-title"><?php esc_html_e('Generate & Compare Draft Variants', 'ai-post-scheduler'); ?></h2>
			<button type="button" id="aips-multi-draft-close" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aips-modal-body" style="flex: 1; overflow-y: auto; padding: 20px 24px;">

			<!-- Step 1: Config -->
			<div id="aips-multi-draft-step-config">
				<p><?php esc_html_e('Generate multiple independent draft variants using the same template and topic. Compare title, excerpt, and content side-by-side, then pick the best from each section to build your final draft.', 'ai-post-scheduler'); ?></p>
				<div class="aips-mdc-config-row">
					<label for="aips-multi-draft-count"><?php esc_html_e('Number of Variants:', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-multi-draft-count" class="aips-form-input aips-mdc-count-input"
						value="2" min="2" max="3" step="1">
				</div>
				<div id="aips-multi-draft-cost-estimate" class="aips-mdc-cost-notice" aria-live="polite"></div>
			</div>

			<!-- Step 2: Generating -->
			<div id="aips-multi-draft-step-generating" style="display: none;">
				<div class="aips-mdc-generating">
					<span class="spinner is-active"></span>
					<p class="aips-mdc-generating-text">
						<?php esc_html_e('Generating draft variants using AI. This may take a minute or two…', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

			<!-- Step 3: Compare -->
			<div id="aips-multi-draft-step-compare" style="display: none;">
				<p class="description">
					<?php esc_html_e('Select the version of each section you prefer. You can mix and match sections from different variants to build the ideal draft.', 'ai-post-scheduler'); ?>
				</p>
				<div id="aips-multi-draft-compare-body"></div>
			</div>

		</div><!-- /.aips-modal-body -->

		<div class="aips-modal-footer">
			<button type="button" id="aips-multi-draft-cancel" class="aips-btn aips-btn-secondary">
				<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
			</button>
			<button type="button" id="aips-multi-draft-generate" class="aips-btn aips-btn-primary">
				<span class="dashicons dashicons-randomize"></span>
				<?php esc_html_e('Generate Variants', 'ai-post-scheduler'); ?>
			</button>
			<button type="button" id="aips-multi-draft-apply" class="aips-btn aips-btn-primary" style="display: none;">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e('Apply Selected Draft', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>
