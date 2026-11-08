<?php
/**
 * Classic Editor AI Edit Component Meta Box
 *
 * Dedicated meta box template for individual post components (Title, Excerpt, Content, Featured Image).
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Variables provided by AIPS_Editor_Assets:
 * @var WP_Post $post Current post object.
 * @var string  $component Component key ('title', 'excerpt', 'content', 'featured_image').
 * @var string  $component_label Human-readable component label.
 */
$component       = !empty($component) ? $component : 'title';
$component_label = !empty($component_label) ? $component_label : ucfirst($component);
$post_id         = isset($post->ID) ? (int) $post->ID : 0;
?>
<div class="aips-classic-component-widget aips-component-section" data-component="<?php echo esc_attr($component); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
	<div class="aips-component-header">
		<div class="aips-component-header-left">
			<span class="aips-component-status" style="display: none;"></span>
		</div>
		<div class="aips-component-actions">
			<button type="button" class="aips-regenerate-btn button button-primary aips-btn-regenerate" data-component="<?php echo esc_attr($component); ?>">
				<span class="dashicons dashicons-update"></span>
				<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
			</button>
			<?php if (in_array($component, array('title', 'excerpt'), true)): ?>
				<button type="button" class="button button-secondary aips-gen-variants-btn" data-component="<?php echo esc_attr($component); ?>" title="<?php esc_attr_e('Generate 3 Candidate Variations', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e('3 Candidates', 'ai-post-scheduler'); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Candidate Variations Container (collapsible/visible when requested) -->
	<div class="aips-classic-variants-container" style="display: none;">
		<div class="aips-variant-carousel"></div>
	</div>

	<!-- Side-by-Side Diff Comparison Slot -->
	<div class="aips-diff-wrapper" data-component="<?php echo esc_attr($component); ?>" style="display: none;">
		<div class="aips-diff-header">
			<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
			<strong><?php esc_html_e('Side-by-Side Comparison', 'ai-post-scheduler'); ?></strong>
		</div>
		<div class="aips-diff-grid">
			<div class="aips-diff-col aips-diff-col-current">
				<div class="aips-diff-col-header"><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></div>
				<div class="aips-diff-col-body aips-diff-current-val"></div>
			</div>
			<div class="aips-diff-col aips-diff-col-regenerated">
				<div class="aips-diff-col-header"><?php esc_html_e('Newly Regenerated Draft', 'ai-post-scheduler'); ?></div>
				<div class="aips-diff-col-body aips-diff-regenerated-val"></div>
			</div>
		</div>
		<div class="aips-diff-footer">
			<button type="button" class="button button-primary aips-accept-diff-btn" data-component="<?php echo esc_attr($component); ?>">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e('Accept & Replace', 'ai-post-scheduler'); ?>
			</button>
			<button type="button" class="button button-secondary aips-discard-diff-btn" data-component="<?php echo esc_attr($component); ?>">
				<span class="dashicons dashicons-no-alt"></span>
				<?php esc_html_e('Discard', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>

	<!-- Component Body Preview / Field -->
	<div class="aips-component-body">
		<?php if ('title' === $component): ?>
			<label class="aips-field-label" for="aips-editor-input-title"><?php esc_html_e('Editable Title:', 'ai-post-scheduler'); ?></label>
			<input type="text" id="aips-editor-input-title" class="aips-component-input widefat" value="<?php echo esc_attr($post->post_title); ?>" />
			<div class="aips-component-meta">
				<span class="aips-char-count"></span>
			</div>
		<?php elseif ('excerpt' === $component): ?>
			<label class="aips-field-label" for="aips-editor-input-excerpt"><?php esc_html_e('Editable Excerpt:', 'ai-post-scheduler'); ?></label>
			<textarea id="aips-editor-input-excerpt" class="aips-component-textarea widefat" rows="4"><?php echo esc_textarea($post->post_excerpt); ?></textarea>
			<div class="aips-component-meta">
				<span class="aips-char-count"></span>
			</div>
		<?php elseif ('content' === $component): ?>
			<label class="aips-field-label"><?php esc_html_e('Article Body Preview:', 'ai-post-scheduler'); ?></label>
			<div class="aips-diff-col-body aips-content-preview"><?php echo wp_kses_post(wp_trim_words($post->post_content, 60)); ?></div>
		<?php elseif ('featured_image' === $component): ?>
			<?php $thumb_id = get_post_thumbnail_id($post_id); ?>
			<div class="aips-featured-image-preview">
				<?php if ($thumb_id): ?>
					<img src="<?php echo esc_url(wp_get_attachment_url($thumb_id)); ?>" alt="" />
				<?php else: ?>
					<div class="aips-featured-image-none">
						<p><?php esc_html_e('No featured image set.', 'ai-post-scheduler'); ?></p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Collapsible Revisions Wrapper -->
	<div class="aips-component-revisions-wrapper">
		<button type="button" class="button button-secondary aips-view-revisions-btn" data-component="<?php echo esc_attr($component); ?>">
			<span class="dashicons dashicons-backup"></span>
			<span class="button-text"><?php esc_html_e('View Revisions', 'ai-post-scheduler'); ?></span>
			<span class="revision-count" style="display: none;"></span>
		</button>
		<div class="aips-component-revisions" style="display: none;">
			<div class="aips-revisions-loading" style="display: none;">
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
