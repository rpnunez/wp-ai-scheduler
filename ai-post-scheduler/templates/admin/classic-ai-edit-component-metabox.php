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

$directive_service = new AIPS_Editor_Directive_Service();
$standard_presets  = $directive_service->get_standard_presets();
$all_overrides     = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
$comp_override     = is_array($all_overrides) && isset($all_overrides[$component]) && is_array($all_overrides[$component]) ? $all_overrides[$component] : array();
$saved_model       = !empty($comp_override['model']) ? $comp_override['model'] : '';
$saved_temp        = isset($comp_override['temperature']) ? floatval($comp_override['temperature']) : 0.7;
$available_models  = array(
	''                           => __('Template Default', 'ai-post-scheduler'),
	'gpt-4o'                     => 'GPT-4o (OpenAI)',
	'gpt-4o-mini'                => 'GPT-4o Mini (OpenAI)',
	'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Anthropic)',
	'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Anthropic)',
	'gemini-1.5-pro'             => 'Gemini 1.5 Pro (Google)',
	'gemini-1.5-flash'           => 'Gemini 1.5 Flash (Google)',
);
?>
<div class="aips-classic-component-widget aips-component-section" data-component="<?php echo esc_attr($component); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
	<div class="aips-component-header">
		<div class="aips-component-header-left">
			<span class="aips-component-status" style="display: none;"></span>
		</div>
		<div class="aips-component-actions">
			<div class="aips-split-btn-group">
				<button type="button" class="aips-regenerate-btn button button-primary aips-btn-regenerate" data-component="<?php echo esc_attr($component); ?>">
					<span class="dashicons dashicons-update"></span>
					<span class="button-text"><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
				</button>
				<button type="button" class="button button-primary aips-split-toggle-btn" data-component="<?php echo esc_attr($component); ?>" title="<?php esc_attr_e('Steering & Directives', 'ai-post-scheduler'); ?>" aria-expanded="false">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
			</div>
			<?php if (in_array($component, array('title', 'excerpt', 'slug'), true)): ?>
				<button type="button" class="button button-secondary aips-gen-variants-btn" data-component="<?php echo esc_attr($component); ?>" title="<?php esc_attr_e('Generate 3 Candidate Variations', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e('3 Candidates', 'ai-post-scheduler'); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Quick Prompt Steering Popover -->
	<div class="aips-directive-popover" data-component="<?php echo esc_attr($component); ?>" style="display: none;">
		<div class="aips-popover-content">
			<div class="aips-popover-header">
				<strong><?php esc_html_e('Prompt Steering Presets', 'ai-post-scheduler'); ?></strong>
				<button type="button" class="aips-popover-close-btn button-link" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-preset-chips">
				<?php foreach ($standard_presets as $preset_key => $preset_data): ?>
					<button type="button" class="aips-preset-chip button button-small" data-preset="<?php echo esc_attr($preset_key); ?>" title="<?php echo esc_attr($preset_data['description']); ?>">
						<?php echo esc_html($preset_data['label']); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="aips-custom-directive-wrap">
				<input type="text" class="aips-custom-directive-input widefat" placeholder="<?php esc_attr_e('Custom directive (e.g. Make it more engaging for technical leads)...', 'ai-post-scheduler'); ?>" />
			</div>
			<div class="aips-popover-footer">
				<button type="button" class="button button-small aips-clear-directive-btn"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button button-small button-primary aips-apply-regenerate-btn" data-component="<?php echo esc_attr($component); ?>">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e('Apply & Re-generate', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Collapsible Advanced Directives & Model Accordion -->
	<div class="aips-advanced-accordion" data-component="<?php echo esc_attr($component); ?>">
		<button type="button" class="aips-advanced-accordion-toggle" aria-expanded="false">
			<span>
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e('Advanced Directives & Model', 'ai-post-scheduler'); ?>
				<span class="aips-active-override-badge" style="<?php echo ($saved_model || $saved_temp != 0.7) ? '' : 'display: none;'; ?>"><?php esc_html_e('Customized', 'ai-post-scheduler'); ?></span>
			</span>
			<span class="dashicons dashicons-arrow-down-alt2 aips-accordion-arrow"></span>
		</button>
		<div class="aips-advanced-drawer" style="display: none;">
			<div class="aips-drawer-row">
				<label><?php esc_html_e('AI Model Override:', 'ai-post-scheduler'); ?></label>
				<select class="aips-model-select widefat" data-component="<?php echo esc_attr($component); ?>">
					<?php foreach ($available_models as $model_val => $model_label): ?>
						<option value="<?php echo esc_attr($model_val); ?>" <?php selected($saved_model, $model_val); ?>><?php echo esc_html($model_label); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="aips-drawer-row">
				<div class="aips-temp-header">
					<label><?php esc_html_e('Creativity / Temperature:', 'ai-post-scheduler'); ?></label>
					<span class="aips-temp-val-badge"><?php echo esc_html(number_format($saved_temp, 2)); ?></span>
				</div>
				<input type="range" class="aips-temperature-slider widefat" min="0" max="1" step="0.05" value="<?php echo esc_attr($saved_temp); ?>" data-component="<?php echo esc_attr($component); ?>" />
				<div class="aips-temp-scale-labels">
					<span><?php esc_html_e('0.0 (Precise)', 'ai-post-scheduler'); ?></span>
					<span><?php esc_html_e('0.7 (Balanced)', 'ai-post-scheduler'); ?></span>
					<span><?php esc_html_e('1.0 (Creative)', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-drawer-footer">
				<button type="button" class="button-link aips-reset-overrides-btn" data-component="<?php echo esc_attr($component); ?>">
					<?php esc_html_e('Reset to Template Default', 'ai-post-scheduler'); ?>
				</button>
			</div>
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
			<?php if ('title' === $component): ?>
				<label class="aips-sync-downstream-label">
					<input type="checkbox" class="aips-sync-downstream-cb" data-source="title" data-downstream="excerpt" checked="checked" />
					<span><?php esc_html_e('Sync Downstream (Auto-realign Excerpt)', 'ai-post-scheduler'); ?></span>
				</label>
			<?php elseif ('content' === $component): ?>
				<label class="aips-sync-downstream-label">
					<input type="checkbox" class="aips-sync-downstream-cb" data-source="content" data-downstream="excerpt" checked="checked" />
					<span><?php esc_html_e('Sync Downstream (Auto-realign Excerpt)', 'ai-post-scheduler'); ?></span>
				</label>
			<?php endif; ?>
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
		<?php elseif ('slug' === $component): ?>
			<label class="aips-field-label" for="aips-editor-input-slug"><?php esc_html_e('Editable Post Slug:', 'ai-post-scheduler'); ?></label>
			<input type="text" id="aips-editor-input-slug" class="aips-component-input widefat" value="<?php echo esc_attr($post->post_name); ?>" />
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
