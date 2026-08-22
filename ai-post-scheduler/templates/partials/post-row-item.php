<?php
/**
 * Partial: Post Row Item
 *
 * Used in Generated Posts Grouped View and List View.
 * Adapts contextually for Published, Scheduled, Pending Review, and Incomplete items.
 *
 * @var array $post_item
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-post-row-item <?php echo !empty($post_item['is_incomplete']) ? 'aips-row-incomplete' : ''; ?>" data-post-id="<?php echo esc_attr($post_item['post_id']); ?>" data-history-id="<?php echo esc_attr($post_item['history_id']); ?>">
	<!-- Selection Checkbox -->
	<div class="aips-post-select">
		<?php if (!empty($post_item['post_id'])): ?>
		<input type="checkbox" class="aips-post-checkbox" value="<?php echo esc_attr($post_item['post_id']); ?>">
		<?php endif; ?>
	</div>

	<!-- Thumbnail -->
	<div class="aips-post-thumb-box">
		<?php if (!empty($post_item['thumb_url'])): ?>
		<img src="<?php echo esc_url($post_item['thumb_url']); ?>" alt="" class="aips-post-thumb-img">
		<?php elseif (!empty($post_item['is_incomplete'])): ?>
		<div class="aips-post-thumb-fallback aips-thumb-warning">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		</div>
		<?php else: ?>
		<div class="aips-post-thumb-fallback">
			<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
		</div>
		<?php endif; ?>
	</div>

	<!-- Details: Title & Meta Pills -->
	<div class="aips-post-details">
		<?php if (!empty($post_item['edit_link'])): ?>
		<a href="<?php echo esc_url($post_item['edit_link']); ?>" class="aips-post-title-link">
			<?php echo esc_html($post_item['title']); ?>
		</a>
		<?php else: ?>
		<span class="aips-post-title-link"><?php echo esc_html($post_item['title']); ?></span>
		<?php endif; ?>

		<div class="aips-post-meta-pills">
			<?php if (!empty($post_item['is_incomplete']) && !empty($post_item['missing_components'])): ?>
				<?php foreach ($post_item['missing_components'] as $missing_lbl): ?>
				<span class="aips-pill aips-pill-danger" title="<?php esc_attr_e('Missing Component', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<?php printf(esc_html__('Missing %s', 'ai-post-scheduler'), esc_html($missing_lbl)); ?>
				</span>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if (!empty($post_item['campaign_name'])): ?>
			<span class="aips-pill aips-pill-campaign" title="<?php esc_attr_e('Campaign', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
				<?php echo esc_html($post_item['campaign_name']); ?>
			</span>
			<?php endif; ?>

			<?php if (!empty($post_item['template_name'])): ?>
			<span class="aips-pill aips-pill-template" title="<?php esc_attr_e('Template', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-layout" aria-hidden="true"></span>
				<?php echo esc_html($post_item['template_name']); ?>
			</span>
			<?php endif; ?>

			<?php if (!empty($post_item['topic_title'])): ?>
			<span class="aips-pill aips-pill-topic" title="<?php esc_attr_e('Topic', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-tag" aria-hidden="true"></span>
				<?php echo esc_html($post_item['topic_title']); ?>
			</span>
			<?php endif; ?>

			<?php if (!empty($post_item['author_name'])): ?>
			<span class="aips-pill aips-pill-author" title="<?php esc_attr_e('Author', 'ai-post-scheduler'); ?>">
				<?php if (!empty($post_item['author_avatar'])): ?>
				<img src="<?php echo esc_url($post_item['author_avatar']); ?>" class="aips-author-avatar-img" alt="">
				<?php endif; ?>
				<span><?php echo esc_html($post_item['author_name']); ?></span>
			</span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Status & Quick Status Switcher -->
	<div class="aips-status-box">
		<?php if (!empty($post_item['is_incomplete'])): ?>
		<span class="aips-status-pill status-incomplete">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php esc_html_e('Incomplete', 'ai-post-scheduler'); ?>
		</span>
		<?php else: ?>
		<span class="aips-status-pill status-<?php echo esc_attr($post_item['post_status']); ?>">
			<?php echo esc_html($post_item['post_status_label']); ?>
		</span>
		<?php if (!empty($post_item['post_id'])): ?>
		<select class="aips-quick-status-select" data-post-id="<?php echo esc_attr($post_item['post_id']); ?>" title="<?php esc_attr_e('Change post status', 'ai-post-scheduler'); ?>">
			<option value="publish" <?php selected($post_item['post_status'], 'publish'); ?>><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
			<option value="draft" <?php selected($post_item['post_status'], 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
			<option value="trash" <?php selected($post_item['post_status'], 'trash'); ?>><?php esc_html_e('Trash', 'ai-post-scheduler'); ?></option>
		</select>
		<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Telemetry Hover Tooltips -->
	<div class="aips-telemetry-box">
		<?php if (!empty($post_item['word_count'])): ?>
		<span class="aips-telemetry-chip">
			<span class="dashicons dashicons-text-page" aria-hidden="true"></span>
			<span><?php echo esc_html(number_format_i18n($post_item['word_count'])); ?> <?php esc_html_e('words', 'ai-post-scheduler'); ?></span>
			<span class="aips-telemetry-tooltip"><?php printf(esc_html__('%1$d words • ~%2$d min read', 'ai-post-scheduler'), (int) $post_item['word_count'], (int) $post_item['reading_time']); ?></span>
		</span>
		<?php endif; ?>

		<?php if ($post_item['duration_seconds'] !== null): ?>
		<span class="aips-telemetry-chip">
			<span class="dashicons dashicons-performance" aria-hidden="true"></span>
			<span><?php echo esc_html($post_item['duration_seconds']); ?>s</span>
			<span class="aips-telemetry-tooltip"><?php printf(esc_html__('Generated in %ss', 'ai-post-scheduler'), esc_html($post_item['duration_seconds'])); ?></span>
		</span>
		<?php endif; ?>
	</div>

	<!-- Actions Group -->
	<div class="cell-actions aips-post-row-actions">
		<div class="aips-row-action-group">
			<?php if (!empty($post_item['is_incomplete'])): ?>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-warning aips-view-session"
				data-history-id="<?php echo esc_attr($post_item['history_id']); ?>"
				title="<?php esc_attr_e('View error and session logs', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php esc_html_e('View Error', 'ai-post-scheduler'); ?>
			</button>
			<?php elseif (!empty($post_item['is_pending_review'])): ?>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-preview-post"
				data-post-id="<?php echo esc_attr($post_item['post_id']); ?>"
				title="<?php esc_attr_e('Review and publish draft', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e('Review', 'ai-post-scheduler'); ?>
			</button>
			<?php else: ?>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-post"
				data-edit-url="<?php echo esc_url($post_item['edit_link']); ?>"
				title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
			</button>
			<?php endif; ?>

			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
				aria-haspopup="true"
				aria-expanded="false"
				aria-controls="aips-row-actions-<?php echo esc_attr($post_item['history_id']); ?>"
				title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
		</div>
		<div id="aips-row-actions-<?php echo esc_attr($post_item['history_id']); ?>" class="aips-row-action-menu" hidden>
			<?php if (!empty($post_item['post_id'])): ?>
			<button type="button" class="aips-row-action-item aips-preview-post"
				data-post-id="<?php echo esc_attr($post_item['post_id']); ?>"
				title="<?php esc_attr_e('Preview this post', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<span><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></span>
			</button>
			<button type="button" class="aips-row-action-item aips-ai-edit-btn"
				data-post-id="<?php echo esc_attr($post_item['post_id']); ?>"
				data-history-id="<?php echo esc_attr($post_item['history_id']); ?>"
				title="<?php esc_attr_e('AI Edit', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
				<span><?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?></span>
			</button>
			<?php endif; ?>

			<button type="button" class="aips-row-action-item aips-view-session"
				data-history-id="<?php echo esc_attr($post_item['history_id']); ?>"
				title="<?php esc_attr_e('View Session', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
				<span><?php esc_html_e('View Session', 'ai-post-scheduler'); ?></span>
			</button>
			<?php if ($post_item['post_status'] === 'publish' && !empty($post_item['permalink'])): ?>
			<a class="aips-row-action-item" href="<?php echo esc_url($post_item['permalink']); ?>" target="_blank" rel="noopener">
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
				<span><?php esc_html_e('View Live Post', 'ai-post-scheduler'); ?></span>
			</a>
			<?php endif; ?>
		</div>
	</div>
</div>
