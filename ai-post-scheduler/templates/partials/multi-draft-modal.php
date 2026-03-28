<?php
/**
 * Multi-Draft Compare Modal Partial Template
 *
 * Side-by-side comparison UI that lets editors generate 2–3 independent
 * variants of post components (title, excerpt, content) and cherry-pick the
 * best version—or merge sections—before applying selections back to the
 * AI Edit modal.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$max_variants = (int) get_option('aips_max_draft_variants', 3);
?>

<!-- Multi-Draft Compare Modal -->
<div id="aips-multi-draft-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-multi-draft-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-xl">
		<div class="aips-modal-header">
			<h2 id="aips-multi-draft-modal-title">
				<span class="dashicons dashicons-columns" aria-hidden="true"></span>
				<?php esc_html_e('Compare Drafts', 'ai-post-scheduler'); ?>
			</h2>
			<button type="button" class="aips-modal-close" id="aips-multi-draft-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>

		<div class="aips-modal-body aips-multi-draft-body">

			<!-- Configuration Panel (shown before generation) -->
			<div class="aips-multi-draft-config" id="aips-multi-draft-config">
				<div class="aips-multi-draft-config-inner">
					<h3><?php esc_html_e('Generate Draft Variants', 'ai-post-scheduler'); ?></h3>
					<p class="description">
						<?php esc_html_e('Generate multiple independent AI drafts and compare them side-by-side. Pick the best title, excerpt, or content from any variant.', 'ai-post-scheduler'); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="aips-multi-draft-variant-count"><?php esc_html_e('Number of Variants', 'ai-post-scheduler'); ?></label>
							</th>
							<td>
								<select id="aips-multi-draft-variant-count" class="aips-form-select">
									<option value="2"><?php esc_html_e('2 variants', 'ai-post-scheduler'); ?></option>
									<?php if ($max_variants >= 3) : ?>
									<option value="3" selected="selected"><?php esc_html_e('3 variants', 'ai-post-scheduler'); ?></option>
									<?php endif; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Components to Generate', 'ai-post-scheduler'); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e('Select components to generate', 'ai-post-scheduler'); ?></legend>
									<label class="aips-multi-draft-component-check">
										<input type="checkbox" class="aips-multi-draft-component-cb" value="title" checked>
										<?php esc_html_e('Title', 'ai-post-scheduler'); ?>
									</label>
									<label class="aips-multi-draft-component-check">
										<input type="checkbox" class="aips-multi-draft-component-cb" value="excerpt" checked>
										<?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?>
									</label>
									<label class="aips-multi-draft-component-check">
										<input type="checkbox" class="aips-multi-draft-component-cb" value="content" checked>
										<?php esc_html_e('Content', 'ai-post-scheduler'); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>

					<!-- Cost Estimate -->
					<div class="aips-multi-draft-cost-estimate" id="aips-multi-draft-cost-estimate">
						<div class="aips-multi-draft-cost-inner">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							<span id="aips-multi-draft-cost-text"></span>
						</div>
					</div>

					<div class="aips-multi-draft-config-actions">
						<button type="button" class="aips-btn aips-btn-secondary" id="aips-multi-draft-cancel-config">
							<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-multi-draft-generate">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e('Generate Variants', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Loading State -->
			<div class="aips-multi-draft-loading" id="aips-multi-draft-loading" style="display: none;">
				<div class="spinner is-active" aria-hidden="true"></div>
				<p id="aips-multi-draft-loading-text"><?php esc_html_e('Generating draft variants — this may take a moment…', 'ai-post-scheduler'); ?></p>
			</div>

			<!-- Results Panel (shown after generation) -->
			<div class="aips-multi-draft-results" id="aips-multi-draft-results" style="display: none;">

				<!-- Toolbar -->
				<div class="aips-multi-draft-toolbar">
					<div class="aips-multi-draft-toolbar-left">
						<span class="aips-multi-draft-summary" id="aips-multi-draft-summary"></span>
					</div>
					<div class="aips-multi-draft-toolbar-right">
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-multi-draft-regenerate">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>

				<!-- Error list (partial failures) -->
				<div class="aips-multi-draft-errors" id="aips-multi-draft-errors" style="display: none;">
					<p class="aips-multi-draft-error-heading"><?php esc_html_e('Some variants could not be generated:', 'ai-post-scheduler'); ?></p>
					<ul id="aips-multi-draft-error-list"></ul>
				</div>

				<!-- Side-by-side compare table -->
				<div class="aips-multi-draft-compare" id="aips-multi-draft-compare" role="region" aria-label="<?php esc_attr_e('Draft comparison', 'ai-post-scheduler'); ?>">
					<!-- Populated dynamically by JS -->
				</div>

				<!-- Selection summary -->
				<div class="aips-multi-draft-selections" id="aips-multi-draft-selections">
					<h4><?php esc_html_e('Your Selections', 'ai-post-scheduler'); ?></h4>
					<ul id="aips-multi-draft-selection-list">
						<li class="aips-multi-draft-no-selection"><?php esc_html_e('No components selected yet. Use the "Use this" buttons above to select versions.', 'ai-post-scheduler'); ?></li>
					</ul>
				</div>
			</div>
		</div>

		<div class="aips-modal-footer" id="aips-multi-draft-footer" style="display: none;">
			<div class="aips-modal-footer-left">
				<p class="description"><?php esc_html_e('Selected components will be applied to the AI Edit modal.', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-modal-footer-right">
				<button type="button" class="aips-btn aips-btn-secondary" id="aips-multi-draft-discard">
					<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-primary" id="aips-multi-draft-apply">
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					<?php esc_html_e('Apply to Post', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>
</div>
