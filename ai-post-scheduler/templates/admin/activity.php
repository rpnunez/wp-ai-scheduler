<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap aips-redesign">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Activity', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Monitor recent post generation activity including published posts, drafts, and failed attempts.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>
		
		<div class="aips-content-panel">
			<!-- Filter Bar -->
			<div class="aips-filter-bar">
				<div class="aips-filter-group" style="display: flex; gap: 8px;">
					<button class="aips-btn aips-btn-secondary aips-filter-btn active" data-filter="all">
						<?php esc_html_e('All Activity', 'ai-post-scheduler'); ?>
					</button>
					<button class="aips-btn aips-btn-secondary aips-filter-btn" data-filter="published">
						<?php esc_html_e('Published', 'ai-post-scheduler'); ?>
					</button>
					<button class="aips-btn aips-btn-secondary aips-filter-btn" data-filter="drafts">
						<?php esc_html_e('Drafts', 'ai-post-scheduler'); ?>
					</button>
					<button class="aips-btn aips-btn-secondary aips-filter-btn" data-filter="failed">
						<?php esc_html_e('Failed', 'ai-post-scheduler'); ?>
					</button>
				</div>

				<div style="display: flex; gap: 8px; margin-left: auto;">
					<label class="screen-reader-text" for="aips-activity-search"><?php esc_html_e('Search Activity:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-activity-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search activity...', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-activity-search-btn" class="aips-btn aips-btn-secondary"><?php esc_html_e('Search', 'ai-post-scheduler'); ?></button>
					<button type="button" id="aips-activity-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>
			
			<!-- Activity Feed -->
			<div class="aips-panel-body">
				<div class="aips-activity-feed">
					<div class="aips-activity-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e('Loading activity...', 'ai-post-scheduler'); ?></p>
					</div>
					<div class="aips-activity-list"></div>
					<div class="aips-activity-empty aips-empty-state" style="display: none;">
						<div class="dashicons dashicons-info aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Activity Found', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('No activity matches the selected filter.', 'ai-post-scheduler'); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Activity Detail Modal -->
<div id="aips-activity-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-activity-modal-title"><?php esc_html_e('Post Details', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<div class="aips-activity-detail-loading">
				<span class="spinner is-active"></span>
				<p><?php esc_html_e('Loading post details...', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-activity-detail-content" style="display: none;">
				<div class="aips-post-meta">
					<div class="aips-post-status"></div>
					<div class="aips-post-date"></div>
					<div class="aips-post-author"></div>
				</div>
				
				<div class="aips-post-featured-image"></div>
				
				<div class="aips-post-title"></div>
				
				<div class="aips-post-excerpt"></div>
				
				<div class="aips-post-content"></div>
				
				<div class="aips-post-taxonomy">
					<div class="aips-post-categories"></div>
					<div class="aips-post-tags"></div>
				</div>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button button-secondary aips-modal-close">
				<?php esc_html_e('Close', 'ai-post-scheduler'); ?>
			</button>
			<a href="#" id="aips-post-edit-link" class="button button-secondary" target="_blank" style="display: none;">
				<?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?>
			</a>
			<button type="button" id="aips-post-publish-btn" class="button button-primary" style="display: none;">
				<?php esc_html_e('Publish Post', 'ai-post-scheduler'); ?>
			</button>
			<a href="#" id="aips-post-view-link" class="button button-primary" target="_blank" style="display: none;">
				<?php esc_html_e('View Post', 'ai-post-scheduler'); ?>
			</a>
		</div>
	</div>
</div>
