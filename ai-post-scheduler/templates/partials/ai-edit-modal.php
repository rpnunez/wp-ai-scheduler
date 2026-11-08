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

static $aips_ai_edit_modal_rendered = false;
if ($aips_ai_edit_modal_rendered) {
	return;
}
$aips_ai_edit_modal_rendered = true;
?>

<!-- AI Edit Modal -->
<div id="aips-ai-edit-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-ai-edit-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large" style="max-width: 960px; width: 92%;">
		<div class="aips-modal-header">
			<div class="aips-ai-edit-header-title-wrap">
				<h2 id="aips-ai-edit-modal-title"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e('AI Edit — Regenerate Components', 'ai-post-scheduler'); ?></h2>
				<div class="aips-ai-edit-modal-subtitle" id="aips-ai-edit-post-subtitle"></div>
			</div>
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
				<!-- Tab Navigation -->
				<nav class="aips-ai-edit-tabs" role="tablist" aria-label="<?php esc_attr_e('AI Edit Components', 'ai-post-scheduler'); ?>">
					<button type="button" class="aips-ai-edit-tab is-active" role="tab" aria-selected="true" data-tab="overview">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<span><?php esc_html_e('Overview', 'ai-post-scheduler'); ?></span>
					</button>
					<button type="button" class="aips-ai-edit-tab" role="tab" aria-selected="false" data-tab="title">
						<span><?php esc_html_e('Title', 'ai-post-scheduler'); ?></span>
						<span class="aips-tab-badge" data-badge="title" style="display: none;"></span>
					</button>
					<button type="button" class="aips-ai-edit-tab" role="tab" aria-selected="false" data-tab="slug">
						<span><?php esc_html_e('Slug', 'ai-post-scheduler'); ?></span>
						<span class="aips-tab-badge" data-badge="slug" style="display: none;"></span>
					</button>
					<button type="button" class="aips-ai-edit-tab" role="tab" aria-selected="false" data-tab="excerpt">
						<span><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></span>
						<span class="aips-tab-badge" data-badge="excerpt" style="display: none;"></span>
					</button>
					<button type="button" class="aips-ai-edit-tab" role="tab" aria-selected="false" data-tab="content">
						<span><?php esc_html_e('Content', 'ai-post-scheduler'); ?></span>
						<span class="aips-tab-badge" data-badge="content" style="display: none;"></span>
					</button>
					<button type="button" class="aips-ai-edit-tab" role="tab" aria-selected="false" data-tab="featured_image">
						<span><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></span>
						<span class="aips-tab-badge" data-badge="featured_image" style="display: none;"></span>
					</button>
				</nav>

				<!-- Tab Panel: Overview & Context -->
				<div class="aips-ai-edit-tab-panel is-active" data-panel="overview" role="tabpanel">
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

					<div class="aips-ai-edit-overview-summary">
						<h4><?php esc_html_e('Component Status Summary', 'ai-post-scheduler'); ?></h4>
						<div class="aips-ai-edit-summary-grid">
							<div class="aips-ai-edit-summary-card" data-component-card="title">
								<span class="aips-summary-card-title"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></span>
								<span class="aips-summary-card-status" id="aips-summary-status-title"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
								<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-nav-to-tab" data-target-tab="title"><?php esc_html_e('View', 'ai-post-scheduler'); ?></button>
							</div>
							<div class="aips-ai-edit-summary-card" data-component-card="slug">
								<span class="aips-summary-card-title"><?php esc_html_e('Slug', 'ai-post-scheduler'); ?></span>
								<span class="aips-summary-card-status" id="aips-summary-status-slug"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
								<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-nav-to-tab" data-target-tab="slug"><?php esc_html_e('View', 'ai-post-scheduler'); ?></button>
							</div>
							<div class="aips-ai-edit-summary-card" data-component-card="excerpt">
								<span class="aips-summary-card-title"><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></span>
								<span class="aips-summary-card-status" id="aips-summary-status-excerpt"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
								<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-nav-to-tab" data-target-tab="excerpt"><?php esc_html_e('View', 'ai-post-scheduler'); ?></button>
							</div>
							<div class="aips-ai-edit-summary-card" data-component-card="content">
								<span class="aips-summary-card-title"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></span>
								<span class="aips-summary-card-status" id="aips-summary-status-content"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
								<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-nav-to-tab" data-target-tab="content"><?php esc_html_e('View', 'ai-post-scheduler'); ?></button>
							</div>
							<div class="aips-ai-edit-summary-card" data-component-card="featured_image">
								<span class="aips-summary-card-title"><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></span>
								<span class="aips-summary-card-status" id="aips-summary-status-featured_image"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
								<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-nav-to-tab" data-target-tab="featured_image"><?php esc_html_e('View', 'ai-post-scheduler'); ?></button>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Tab Panel: Title Component -->
				<div class="aips-ai-edit-tab-panel" data-panel="title" role="tabpanel">
					<div class="aips-component-section" data-component="title">
						<div class="aips-component-header">
							<div class="aips-component-header-left">
								<h4 class="aips-component-title"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></h4>
								<span class="aips-component-status"></span>
							</div>
							<div class="aips-component-actions">
								<button type="button" class="aips-regenerate-btn" data-component="title">
									<span class="dashicons dashicons-update"></span>
									<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
								</button>
							</div>
						</div>

						<!-- Side-by-Side Diff Comparison Slot -->
						<div class="aips-diff-wrapper" data-component="title" style="display: none;">
							<div class="aips-diff-header">
								<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
								<strong><?php esc_html_e('Side-by-Side Comparison', 'ai-post-scheduler'); ?></strong>
							</div>
							<div class="aips-diff-grid">
								<div class="aips-diff-col aips-diff-col-current">
									<div class="aips-diff-col-header"><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-current-title"></div>
								</div>
								<div class="aips-diff-col aips-diff-col-regenerated">
									<div class="aips-diff-col-header"><?php esc_html_e('Newly Regenerated Draft', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-regenerated-title"></div>
								</div>
							</div>
							<div class="aips-diff-footer">
								<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-accept-diff-btn" data-component="title">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e('Accept & Replace', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-discard-diff-btn" data-component="title">
									<span class="dashicons dashicons-no-alt"></span>
									<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>
						
						<div class="aips-component-body">
							<label class="aips-field-label" for="aips-component-title"><?php esc_html_e('Editable Title:', 'ai-post-scheduler'); ?></label>
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
				</div>

				<!-- Tab Panel: Slug Component -->
				<div class="aips-ai-edit-tab-panel" data-panel="slug" role="tabpanel">
					<div class="aips-component-section" data-component="slug">
						<div class="aips-component-header">
							<div class="aips-component-header-left">
								<h4 class="aips-component-title"><?php esc_html_e('Slug', 'ai-post-scheduler'); ?></h4>
								<span class="aips-component-status"></span>
							</div>
							<div class="aips-component-actions">
								<button type="button" class="aips-regenerate-btn" data-component="slug">
									<span class="dashicons dashicons-update"></span>
									<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
								</button>
							</div>
						</div>

						<!-- Side-by-Side Diff Comparison Slot -->
						<div class="aips-diff-wrapper" data-component="slug" style="display: none;">
							<div class="aips-diff-header">
								<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
								<strong><?php esc_html_e('Side-by-Side Comparison', 'ai-post-scheduler'); ?></strong>
							</div>
							<div class="aips-diff-grid">
								<div class="aips-diff-col aips-diff-col-current">
									<div class="aips-diff-col-header"><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-current-slug"></div>
								</div>
								<div class="aips-diff-col aips-diff-col-regenerated">
									<div class="aips-diff-col-header"><?php esc_html_e('Newly Regenerated Draft', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-regenerated-slug"></div>
								</div>
							</div>
							<div class="aips-diff-footer">
								<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-accept-diff-btn" data-component="slug">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e('Accept & Replace', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-discard-diff-btn" data-component="slug">
									<span class="dashicons dashicons-no-alt"></span>
									<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>

						<div class="aips-component-body">
							<label class="aips-field-label" for="aips-component-slug"><?php esc_html_e('Editable Slug:', 'ai-post-scheduler'); ?></label>
							<input type="text" id="aips-component-slug" class="aips-component-input" />
							<div class="aips-component-meta">
								<span class="aips-char-count"></span>
							</div>
						</div>
						<div class="aips-component-revisions-wrapper">
							<button type="button" class="aips-view-revisions-btn" data-component="slug">
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
				</div>
				
				<!-- Tab Panel: Excerpt Component -->
				<div class="aips-ai-edit-tab-panel" data-panel="excerpt" role="tabpanel">
					<div class="aips-component-section" data-component="excerpt">
						<div class="aips-component-header">
							<div class="aips-component-header-left">
								<h4 class="aips-component-title"><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></h4>
								<span class="aips-component-status"></span>
							</div>
							<div class="aips-component-actions">
								<button type="button" class="aips-regenerate-btn" data-component="excerpt">
									<span class="dashicons dashicons-update"></span>
									<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
								</button>
							</div>
						</div>

						<!-- Side-by-Side Diff Comparison Slot -->
						<div class="aips-diff-wrapper" data-component="excerpt" style="display: none;">
							<div class="aips-diff-header">
								<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
								<strong><?php esc_html_e('Side-by-Side Comparison', 'ai-post-scheduler'); ?></strong>
							</div>
							<div class="aips-diff-grid">
								<div class="aips-diff-col aips-diff-col-current">
									<div class="aips-diff-col-header"><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-current-excerpt"></div>
								</div>
								<div class="aips-diff-col aips-diff-col-regenerated">
									<div class="aips-diff-col-header"><?php esc_html_e('Newly Regenerated Draft', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-regenerated-excerpt"></div>
								</div>
							</div>
							<div class="aips-diff-footer">
								<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-accept-diff-btn" data-component="excerpt">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e('Accept & Replace', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-discard-diff-btn" data-component="excerpt">
									<span class="dashicons dashicons-no-alt"></span>
									<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>

						<div class="aips-component-body">
							<label class="aips-field-label" for="aips-component-excerpt"><?php esc_html_e('Editable Excerpt:', 'ai-post-scheduler'); ?></label>
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
				</div>
				
				<!-- Tab Panel: Content Component -->
				<div class="aips-ai-edit-tab-panel" data-panel="content" role="tabpanel">
					<div class="aips-component-section" data-component="content">
						<div class="aips-component-header">
							<div class="aips-component-header-left">
								<h4 class="aips-component-title"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></h4>
								<span class="aips-component-status"></span>
							</div>
							<div class="aips-component-actions">
								<button type="button" class="aips-regenerate-btn" data-component="content">
									<span class="dashicons dashicons-update"></span>
									<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
								</button>
							</div>
						</div>

						<!-- Side-by-Side Diff Comparison Slot -->
						<div class="aips-diff-wrapper" data-component="content" style="display: none;">
							<div class="aips-diff-header">
								<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
								<strong><?php esc_html_e('Side-by-Side Comparison', 'ai-post-scheduler'); ?></strong>
							</div>
							<div class="aips-diff-grid">
								<div class="aips-diff-col aips-diff-col-current">
									<div class="aips-diff-col-header"><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body aips-content-preview" id="aips-diff-current-content"></div>
								</div>
								<div class="aips-diff-col aips-diff-col-regenerated">
									<div class="aips-diff-col-header"><?php esc_html_e('Newly Regenerated Draft', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body aips-content-preview" id="aips-diff-regenerated-content"></div>
								</div>
							</div>
							<div class="aips-diff-footer">
								<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-accept-diff-btn" data-component="content">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e('Accept & Replace', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-discard-diff-btn" data-component="content">
									<span class="dashicons dashicons-no-alt"></span>
									<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>

						<div class="aips-component-body">
							<label class="aips-field-label" for="aips-component-content"><?php esc_html_e('Editable Content:', 'ai-post-scheduler'); ?></label>
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
				</div>
				
				<!-- Tab Panel: Featured Image Component -->
				<div class="aips-ai-edit-tab-panel" data-panel="featured_image" role="tabpanel">
					<div class="aips-component-section" data-component="featured_image">
						<div class="aips-component-header">
							<div class="aips-component-header-left">
								<h4 class="aips-component-title"><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></h4>
								<span class="aips-component-status"></span>
							</div>
							<div class="aips-component-actions">
								<button type="button" class="aips-regenerate-btn" data-component="featured_image">
									<span class="dashicons dashicons-update"></span>
									<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
								</button>
							</div>
						</div>

						<!-- Side-by-Side Diff Comparison Slot -->
						<div class="aips-diff-wrapper" data-component="featured_image" style="display: none;">
							<div class="aips-diff-header">
								<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
								<strong><?php esc_html_e('Side-by-Side Comparison', 'ai-post-scheduler'); ?></strong>
							</div>
							<div class="aips-diff-grid">
								<div class="aips-diff-col aips-diff-col-current">
									<div class="aips-diff-col-header"><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-current-featured_image"></div>
								</div>
								<div class="aips-diff-col aips-diff-col-regenerated">
									<div class="aips-diff-col-header"><?php esc_html_e('Newly Regenerated Draft', 'ai-post-scheduler'); ?></div>
									<div class="aips-diff-col-body" id="aips-diff-regenerated-featured_image"></div>
								</div>
							</div>
							<div class="aips-diff-footer">
								<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-accept-diff-btn" data-component="featured_image">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e('Accept & Replace', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-discard-diff-btn" data-component="featured_image">
									<span class="dashicons dashicons-no-alt"></span>
									<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
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
		</div>
		
		<div class="aips-modal-footer">
			<div class="aips-modal-footer-left">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-success" id="aips-ai-edit-accept-all" style="display: none;">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e('Accept All Regenerations', 'ai-post-scheduler'); ?>
				</button>
				<p class="aips-save-notice-text"><?php esc_html_e('Changes marked with * will be saved', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-modal-footer-right">
				<button type="button" class="button" id="aips-ai-edit-regenerate-all">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e('Regenerate All', 'ai-post-scheduler'); ?>
				</button>
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
