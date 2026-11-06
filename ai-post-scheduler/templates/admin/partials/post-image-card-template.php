<?php
/**
 * Post Image Repeater Card Template
 *
 * Client-side template for rendering dynamic post content image cards in
 * Templates and Authors admin modals.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.21
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<script type="text/html" id="aips-tmpl-post-image-card">
	<div class="aips-post-image-card" data-index="{{index}}" data-id="{{id}}">
		<div class="aips-post-image-card-header">
			<span class="aips-post-image-card-title">
				<span class="dashicons dashicons-format-image"></span>
				<?php esc_html_e('Post Image', 'ai-post-scheduler'); ?> #<span class="aips-post-image-index-display">{{displayIndex}}</span>
			</span>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-remove-post-image-btn" title="<?php esc_attr_e('Remove Image', 'ai-post-scheduler'); ?>" style="color: var(--aips-danger, #d63638);">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e('Remove', 'ai-post-scheduler'); ?>
			</button>
		</div>
		<div class="aips-post-image-card-body">
			<input type="hidden" name="{{prefix}}[{{index}}][id]" value="{{id}}" class="aips-post-image-id-input">
			<input type="hidden" name="{{prefix}}[{{index}}][image_order]" value="{{displayIndex}}" class="aips-post-image-order-input">

			<div class="aips-form-row">
				<label><?php esc_html_e('Placement in Post Content', 'ai-post-scheduler'); ?></label>
				<select name="{{prefix}}[{{index}}][placement]" class="aips-image-placement-select">
					<option value="auto" {{selectedPlacementAuto}}><?php esc_html_e('Auto-distribute (AI natural placement)', 'ai-post-scheduler'); ?></option>
					<option value="after_section_1" {{selectedPlacement1}}><?php esc_html_e('After Section 1 (Heading 1)', 'ai-post-scheduler'); ?></option>
					<option value="after_section_2" {{selectedPlacement2}}><?php esc_html_e('After Section 2 (Heading 2)', 'ai-post-scheduler'); ?></option>
					<option value="after_section_3" {{selectedPlacement3}}><?php esc_html_e('After Section 3 (Heading 3)', 'ai-post-scheduler'); ?></option>
					<option value="after_section_4" {{selectedPlacement4}}><?php esc_html_e('After Section 4 (Heading 4)', 'ai-post-scheduler'); ?></option>
					<option value="after_section_5" {{selectedPlacement5}}><?php esc_html_e('After Section 5 (Heading 5)', 'ai-post-scheduler'); ?></option>
				</select>
				<p class="description"><?php esc_html_e('Where the AI should place this image tag within the generated content.', 'ai-post-scheduler'); ?></p>
			</div>

			<div class="aips-image-settings-group" data-id-prefix="{{idPrefix}}">
				<div class="aips-form-row">
					<label><?php esc_html_e('Image Source', 'ai-post-scheduler'); ?></label>
					<select name="{{prefix}}[{{index}}][source]" class="aips-image-source-select">
						<option value="ai_prompt" {{selectedSourceAi}}><?php esc_html_e('Generate with AI Prompt', 'ai-post-scheduler'); ?></option>
						<option value="unsplash" {{selectedSourceUnsplash}}><?php esc_html_e('Unsplash (keywords)', 'ai-post-scheduler'); ?></option>
						<option value="media_library" {{selectedSourceMedia}}><?php esc_html_e('Media Library (select images)', 'ai-post-scheduler'); ?></option>
					</select>
				</div>

				<div class="aips-form-row aips-image-source-panel aips-image-source-panel-ai" style="{{aiDisplay}}">
					<label><?php esc_html_e('Image Prompt', 'ai-post-scheduler'); ?></label>
					<textarea name="{{prefix}}[{{index}}][image_prompt]" rows="3" class="large-text aips-image-prompt-textarea" placeholder="<?php esc_attr_e('Describe the image or leave blank for AI to contextualize from section content...', 'ai-post-scheduler'); ?>">{{prompt}}</textarea>
					<p class="description"><?php esc_html_e('Optional: Custom instructions or template variables ({title}, {topic}). If left empty, AI generates prompt based on section content.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row aips-image-source-panel aips-image-source-panel-unsplash" style="{{unsplashDisplay}}">
					<label><?php esc_html_e('Unsplash Keywords', 'ai-post-scheduler'); ?></label>
					<input type="text" name="{{prefix}}[{{index}}][unsplash_keywords]" value="{{unsplashKeywords}}" class="regular-text aips-unsplash-keywords-input" placeholder="<?php esc_attr_e('e.g. sunrise, mountains, technology', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('Unsplash will return a random image that matches these keywords.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row aips-image-source-panel aips-image-source-panel-media" style="{{mediaDisplay}}">
					<label><?php esc_html_e('Media Library Images', 'ai-post-scheduler'); ?></label>
					<div class="aips-media-library-picker">
						<input type="hidden" name="{{prefix}}[{{index}}][media_ids]" value="{{mediaIds}}" class="aips-media-ids-input">
						<button type="button" class="aips-btn aips-btn-secondary aips-media-select-btn"><?php esc_html_e('Select Images', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-btn aips-btn-ghost aips-media-clear-btn"><?php esc_html_e('Clear Selection', 'ai-post-scheduler'); ?></button>
						<div class="aips-media-preview-container description" style="margin-top: 6px;">{{mediaPreviewText}}</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</script>
