<?php
/**
 * AI Edit Modal Partial Template
 *
 * Reusable modal for regenerating individual post components via AI Edit.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<!-- AI Edit Modal -->
<div id="aips-ai-edit-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large" style="max-width: 900px; width: 90%;">
		<div class="aips-modal-header">
			<h2><?php esc_html_e('AI Edit - Regenerate Components', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" id="aips-ai-edit-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		
		<div class="aips-modal-body">
			<!-- Loading State -->
			<div class="aips-ai-edit-loading">
				<div class="spinner is-active"></div>
				<p><?php esc_html_e('Loading post components...', 'ai-post-scheduler'); ?></p>
			</div>
			
			<!-- Content (hidden until loaded) -->
			<div class="aips-ai-edit-content" style="display: none;">
				<!-- Generation Context -->
				<div class="aips-ai-edit-context">
					<h3><?php esc_html_e('Original Generation Context', 'ai-post-scheduler'); ?></h3>
					<div class="aips-ai-edit-context-grid">
						<div class="aips-ai-edit-context-item">
							<span class="aips-ai-edit-context-label"><?php esc_html_e('Template:', 'ai-post-scheduler'); ?></span>
							<span class="aips-ai-edit-context-value" id="aips-context-template">—</span>
						</div>
						<div class="aips-ai-edit-context-item">
							<span class="aips-ai-edit-context-label"><?php esc_html_e('Author:', 'ai-post-scheduler'); ?></span>
							<span class="aips-ai-edit-context-value" id="aips-context-author">—</span>
						</div>
						<div class="aips-ai-edit-context-item">
							<span class="aips-ai-edit-context-label"><?php esc_html_e('Topic:', 'ai-post-scheduler'); ?></span>
							<span class="aips-ai-edit-context-value" id="aips-context-topic">—</span>
						</div>
					</div>
				</div>
				
				<!-- Title Component -->
				<div class="aips-component-section" data-component="title">
					<div class="aips-component-header">
						<h4 class="aips-component-title"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></h4>
						<div class="aips-component-actions">
							<span class="aips-component-status"></span>
							<button type="button" class="aips-regenerate-btn" data-component="title">
								<span class="dashicons dashicons-update"></span>
								<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
							</button>
						</div>
					</div>
					<div class="aips-component-body">
						<input type="text" id="aips-component-title" class="aips-component-input" />
						<div class="aips-component-meta">
							<span class="aips-char-count"></span>
						</div>
					</div>
					<div class="aips-component-revisions-wrapper">
						<button type="button" class="aips-view-revisions-btn" data-component="title">
							<span class="dashicons dashicons-backup"></span>
							<span class="button-text"><?php esc_html_e('View Revisions', 'ai-post-scheduler'); ?></span>
							<span class="revision-count"></span>
						</button>
						<div class="aips-component-revisions" style="display: none;">
							<div class="aips-revisions-loading">
								<span class="spinner is-active"></span>
								<span><?php esc_html_e('Loading revisions...', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-revisions-list"></div>
							<div class="aips-revisions-empty" style="display: none;">
								<?php esc_html_e('No previous revisions found.', 'ai-post-scheduler'); ?>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Excerpt Component -->
				<div class="aips-component-section" data-component="excerpt">
					<div class="aips-component-header">
						<h4 class="aips-component-title"><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></h4>
						<div class="aips-component-actions">
							<span class="aips-component-status"></span>
							<button type="button" class="aips-regenerate-btn" data-component="excerpt">
								<span class="dashicons dashicons-update"></span>
								<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
							</button>
						</div>
					</div>
					<div class="aips-component-body">
						<textarea id="aips-component-excerpt" class="aips-component-textarea" rows="4"></textarea>
						<div class="aips-component-meta">
							<span class="aips-char-count"></span>
						</div>
					</div>
					<div class="aips-component-revisions-wrapper">
						<button type="button" class="aips-view-revisions-btn" data-component="excerpt">
							<span class="dashicons dashicons-backup"></span>
							<span class="button-text"><?php esc_html_e('View Revisions', 'ai-post-scheduler'); ?></span>
							<span class="revision-count"></span>
						</button>
						<div class="aips-component-revisions" style="display: none;">
							<div class="aips-revisions-loading">
								<span class="spinner is-active"></span>
								<span><?php esc_html_e('Loading revisions...', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-revisions-list"></div>
							<div class="aips-revisions-empty" style="display: none;">
								<?php esc_html_e('No previous revisions found.', 'ai-post-scheduler'); ?>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Content Component -->
				<div class="aips-component-section" data-component="content">
					<div class="aips-component-header">
						<h4 class="aips-component-title"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></h4>
						<div class="aips-component-actions">
							<span class="aips-component-status"></span>
							<button type="button" class="aips-regenerate-btn" data-component="content">
								<span class="dashicons dashicons-update"></span>
								<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
							</button>
						</div>
					</div>
					<div class="aips-component-body">
						<textarea id="aips-component-content" class="aips-component-textarea large" rows="15"></textarea>
						<div class="aips-component-meta">
							<span class="aips-char-count"></span>
						</div>
					</div>
					<div class="aips-component-revisions-wrapper">
						<button type="button" class="aips-view-revisions-btn" data-component="content">
							<span class="dashicons dashicons-backup"></span>
							<span class="button-text"><?php esc_html_e('View Revisions', 'ai-post-scheduler'); ?></span>
							<span class="revision-count"></span>
						</button>
						<div class="aips-component-revisions" style="display: none;">
							<div class="aips-revisions-loading">
								<span class="spinner is-active"></span>
								<span><?php esc_html_e('Loading revisions...', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-revisions-list"></div>
							<div class="aips-revisions-empty" style="display: none;">
								<?php esc_html_e('No previous revisions found.', 'ai-post-scheduler'); ?>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Featured Image Component -->
				<div class="aips-component-section" data-component="featured_image">
					<div class="aips-component-header">
						<h4 class="aips-component-title"><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></h4>
						<div class="aips-component-actions">
							<span class="aips-component-status"></span>
							<button type="button" class="aips-regenerate-btn" data-component="featured_image">
								<span class="dashicons dashicons-update"></span>
								<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
							</button>
						</div>
					</div>
					<div class="aips-component-body">
						<div class="aips-featured-image-preview">
							<img id="aips-component-image" src="" alt="<?php esc_attr_e('Featured Image', 'ai-post-scheduler'); ?>" style="display: none;" />
							<div id="aips-component-image-none" class="aips-featured-image-none">
								<?php esc_html_e('No featured image', 'ai-post-scheduler'); ?>
							</div>
						</div>
					</div>
					<div class="aips-component-revisions-wrapper">
						<button type="button" class="aips-view-revisions-btn" data-component="featured_image">
							<span class="dashicons dashicons-backup"></span>
							<span class="button-text"><?php esc_html_e('View Revisions', 'ai-post-scheduler'); ?></span>
							<span class="revision-count"></span>
						</button>
						<div class="aips-component-revisions" style="display: none;">
							<div class="aips-revisions-loading">
								<span class="spinner is-active"></span>
								<span><?php esc_html_e('Loading revisions...', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-revisions-list"></div>
							<div class="aips-revisions-empty" style="display: none;">
								<?php esc_html_e('No previous revisions found for this image.', 'ai-post-scheduler'); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div class="aips-modal-footer">
			<div class="aips-modal-footer-left">
				<p><?php esc_html_e('Changes marked with * will be saved', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-modal-footer-right">
				<button type="button" class="button" id="aips-ai-edit-cancel">
					<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="button button-primary" id="aips-ai-edit-save">
					<?php esc_html_e('Save Changes', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>
</div>
