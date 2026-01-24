<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('Activity', 'ai-post-scheduler'); ?></h1>
	
	<div class="aips-activity-container">
		<div class="aips-activity-filters">
			<div class="aips-filter-group">
				<button class="button aips-filter-btn active" data-filter="all">
					<?php esc_html_e('All Activity', 'ai-post-scheduler'); ?>
				</button>
				<button class="button aips-filter-btn" data-filter="published">
					<?php esc_html_e('Published', 'ai-post-scheduler'); ?>
				</button>
				<button class="button aips-filter-btn" data-filter="drafts">
					<?php esc_html_e('Drafts', 'ai-post-scheduler'); ?>
				</button>
				<button class="button aips-filter-btn" data-filter="failed">
					<?php esc_html_e('Failed', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<div class="aips-search-box" style="margin-left: auto;">
				<label class="screen-reader-text" for="aips-activity-search"><?php esc_html_e('Search Activity:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-activity-search" class="regular-text" placeholder="<?php esc_attr_e('Search activity...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-activity-search-btn" class="button"><?php esc_html_e('Search', 'ai-post-scheduler'); ?></button>
				<button type="button" id="aips-activity-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
		
		<div class="aips-activity-feed">
			<div class="aips-activity-loading">
				<span class="spinner is-active"></span>
				<p><?php esc_html_e('Loading activity...', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-activity-list"></div>
			<div class="aips-activity-empty" style="display: none;">
				<span class="dashicons dashicons-info"></span>
				<h3><?php esc_html_e('No Activity Found', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('No activity matches the selected filter.', 'ai-post-scheduler'); ?></p>
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
