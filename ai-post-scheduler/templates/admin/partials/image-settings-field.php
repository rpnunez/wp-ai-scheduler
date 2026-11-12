<?php
/**
 * Reusable Image Settings Field Partial
 *
 * Renders source dropdown (AI Prompt, Unsplash, Media Library) and their
 * respective configuration fields for Featured Image or Post Content Images.
 *
 * Expected variables (scoped or defaults applied):
 * - $image_field_name_prefix (string) e.g. 'featured_image' or 'post_images[0]'
 * - $image_field_id_prefix   (string) e.g. 'featured_image' or 'post_images_0'
 * - $image_values            (array|object) Current field values
 * - $is_post_image           (bool) True if this is a post content image repeater row
 * - $allowed_sources         (array) Allowed image source keys
 * - $image_prompt_field_id   (string) Optional ID for the prompt textarea; existing
 *                            scripts address the Template modal's field as #image_prompt
 *
 * @package AI_Post_Scheduler
 * @since 3.7.21
 */

if (!defined('ABSPATH')) {
	exit;
}

$image_field_name_prefix = isset($image_field_name_prefix) ? $image_field_name_prefix : 'featured_image';
$image_field_id_prefix   = isset($image_field_id_prefix) ? $image_field_id_prefix : 'featured_image';
$is_post_image           = !empty($is_post_image);
$image_prompt_field_id   = !empty($image_prompt_field_id) ? $image_prompt_field_id : $image_field_id_prefix . '_image_prompt';

// Normalize values
$vals = is_object($image_values) ? get_object_vars($image_values) : (is_array($image_values) ? $image_values : array());
$source            = isset($vals['source']) ? $vals['source'] : (isset($vals['featured_image_source']) ? $vals['featured_image_source'] : 'ai_prompt');
$prompt            = isset($vals['image_prompt']) ? $vals['image_prompt'] : '';
$unsplash_keywords = isset($vals['unsplash_keywords']) ? $vals['unsplash_keywords'] : (isset($vals['featured_image_unsplash_keywords']) ? $vals['featured_image_unsplash_keywords'] : '');
$media_ids         = isset($vals['media_ids']) ? $vals['media_ids'] : (isset($vals['featured_image_media_ids']) ? $vals['featured_image_media_ids'] : '');
$placement         = isset($vals['placement']) ? $vals['placement'] : 'auto';

if (!isset($allowed_sources) || !is_array($allowed_sources)) {
	$allowed_sources = AIPS_Config::get_instance()->get_allowed_image_sources();
}
$unsplash_configured = (bool) AIPS_Config::get_instance()->get_option('aips_unsplash_access_key');

$source_field_name = $is_post_image ? "{$image_field_name_prefix}[source]" : "featured_image_source";
$prompt_field_name = $is_post_image ? "{$image_field_name_prefix}[image_prompt]" : "image_prompt";
$unsplash_field_name = $is_post_image ? "{$image_field_name_prefix}[unsplash_keywords]" : "featured_image_unsplash_keywords";
$media_field_name  = $is_post_image ? "{$image_field_name_prefix}[media_ids]" : "featured_image_media_ids";
$placement_field_name = "{$image_field_name_prefix}[placement]";
?>
<div class="aips-image-settings-group" data-id-prefix="<?php echo esc_attr($image_field_id_prefix); ?>">
	<?php if ($is_post_image): ?>
		<div class="aips-form-row">
			<label for="<?php echo esc_attr($image_field_id_prefix); ?>_placement"><?php esc_html_e('Placement in Post Content', 'ai-post-scheduler'); ?></label>
			<select id="<?php echo esc_attr($image_field_id_prefix); ?>_placement" name="<?php echo esc_attr($placement_field_name); ?>" class="aips-image-placement-select">
				<option value="auto" <?php selected($placement, 'auto'); ?>><?php esc_html_e('Auto-distribute (AI natural placement)', 'ai-post-scheduler'); ?></option>
				<option value="after_section_1" <?php selected($placement, 'after_section_1'); ?>><?php esc_html_e('After Section 1 (Heading 1)', 'ai-post-scheduler'); ?></option>
				<option value="after_section_2" <?php selected($placement, 'after_section_2'); ?>><?php esc_html_e('After Section 2 (Heading 2)', 'ai-post-scheduler'); ?></option>
				<option value="after_section_3" <?php selected($placement, 'after_section_3'); ?>><?php esc_html_e('After Section 3 (Heading 3)', 'ai-post-scheduler'); ?></option>
				<option value="after_section_4" <?php selected($placement, 'after_section_4'); ?>><?php esc_html_e('After Section 4 (Heading 4)', 'ai-post-scheduler'); ?></option>
				<option value="after_section_5" <?php selected($placement, 'after_section_5'); ?>><?php esc_html_e('After Section 5 (Heading 5)', 'ai-post-scheduler'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Where the AI should place this image tag within the generated content.', 'ai-post-scheduler'); ?></p>
		</div>
	<?php endif; ?>

	<div class="aips-form-row">
		<label for="<?php echo esc_attr($image_field_id_prefix); ?>_source"><?php esc_html_e('Image Source', 'ai-post-scheduler'); ?></label>
		<select id="<?php echo esc_attr($image_field_id_prefix); ?>_source" name="<?php echo esc_attr($source_field_name); ?>" class="aips-image-source-select">
			<?php if (in_array('ai_prompt', $allowed_sources, true)): ?>
				<option value="ai_prompt" <?php selected($source, 'ai_prompt'); ?>><?php esc_html_e('Generate with AI Prompt', 'ai-post-scheduler'); ?></option>
			<?php endif; ?>
			<?php if (in_array('unsplash', $allowed_sources, true)): ?>
				<option value="unsplash" <?php selected($source, 'unsplash'); ?>><?php esc_html_e('Unsplash (keywords)', 'ai-post-scheduler'); ?></option>
			<?php endif; ?>
			<?php if (in_array('media_library', $allowed_sources, true)): ?>
				<option value="media_library" <?php selected($source, 'media_library'); ?>><?php esc_html_e('Media Library (select images)', 'ai-post-scheduler'); ?></option>
			<?php endif; ?>
		</select>
		<p class="description"><?php echo $is_post_image ? esc_html__('Choose how this post content image should be sourced.', 'ai-post-scheduler') : esc_html__('Choose how the featured image should be sourced.', 'ai-post-scheduler'); ?></p>
	</div>

	<!-- AI Prompt Source Panel -->
	<div class="aips-form-row aips-image-source-panel aips-image-source-panel-ai" style="<?php echo $source === 'ai_prompt' ? '' : 'display: none;'; ?>">
		<label for="<?php echo esc_attr($image_prompt_field_id); ?>"><?php esc_html_e('Image Prompt', 'ai-post-scheduler'); ?></label>
		<textarea id="<?php echo esc_attr($image_prompt_field_id); ?>" name="<?php echo esc_attr($prompt_field_name); ?>" rows="3" class="large-text aips-image-prompt-textarea" placeholder="<?php echo $is_post_image ? esc_attr__('Describe the image or leave blank for AI to contextualize from section content...', 'ai-post-scheduler') : esc_attr__('Describe the featured image you want generated...', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($prompt); ?></textarea>
		<p class="description">
			<?php if ($is_post_image): ?>
				<?php esc_html_e('Optional: Enter custom image instructions or template variables ({title}, {topic}). If left empty, AI will generate a prompt tailored to the surrounding section.', 'ai-post-scheduler'); ?>
			<?php else: ?>
				<?php esc_html_e('Used when generating the featured image with AI. Supports template variables ({title}, {topic}).', 'ai-post-scheduler'); ?>
			<?php endif; ?>
		</p>
	</div>

	<!-- Unsplash Source Panel -->
	<div class="aips-form-row aips-image-source-panel aips-image-source-panel-unsplash" style="<?php echo $source === 'unsplash' ? '' : 'display: none;'; ?>">
		<label for="<?php echo esc_attr($image_field_id_prefix); ?>_unsplash_keywords"><?php esc_html_e('Unsplash Keywords', 'ai-post-scheduler'); ?></label>
		<input type="text" id="<?php echo esc_attr($image_field_id_prefix); ?>_unsplash_keywords" name="<?php echo esc_attr($unsplash_field_name); ?>" value="<?php echo esc_attr($unsplash_keywords); ?>" class="regular-text aips-unsplash-keywords-input" placeholder="<?php esc_attr_e('e.g. sunrise, mountains, technology', 'ai-post-scheduler'); ?>">
		<p class="description"><?php esc_html_e('Unsplash will return a random image that matches these keywords.', 'ai-post-scheduler'); ?></p>
		<?php if (!$unsplash_configured): ?>
			<div class="notice notice-warning inline aips-unsplash-missing-key-notice">
				<p>
					<?php esc_html_e('Unsplash Access Key is not configured. Generations will skip image fetching until an access key is provided in', 'ai-post-scheduler'); ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#aips_api_keys_section')); ?>" target="_blank">
						<?php esc_html_e('Settings &rarr; API Keys', 'ai-post-scheduler'); ?>
					</a>.
				</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Media Library Source Panel -->
	<div class="aips-form-row aips-image-source-panel aips-image-source-panel-media" style="<?php echo $source === 'media_library' ? '' : 'display: none;'; ?>">
		<label><?php esc_html_e('Media Library Images', 'ai-post-scheduler'); ?></label>
		<div class="aips-media-library-picker">
			<input type="hidden" id="<?php echo esc_attr($image_field_id_prefix); ?>_media_ids" name="<?php echo esc_attr($media_field_name); ?>" value="<?php echo esc_attr($media_ids); ?>" class="aips-media-ids-input">
			<button type="button" class="aips-btn aips-btn-secondary aips-media-select-btn" id="<?php echo esc_attr($image_field_id_prefix); ?>_media_select"><?php esc_html_e('Select Images', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-ghost aips-media-clear-btn" id="<?php echo esc_attr($image_field_id_prefix); ?>_media_clear"><?php esc_html_e('Clear Selection', 'ai-post-scheduler'); ?></button>
			<div class="aips-media-preview-container description" style="margin-top: 6px;">
				<?php
				if (!empty($media_ids)) {
					$id_array = array_filter(array_map('absint', explode(',', $media_ids)));
					$count = count($id_array);
					printf(
						/* translators: %d: number of images selected */
						esc_html(_n('%d image selected', '%d images selected', $count, 'ai-post-scheduler')),
						$count
					);
				} else {
					esc_html_e('No images selected.', 'ai-post-scheduler');
				}
				?>
			</div>
		</div>
		<p class="description"><?php echo $is_post_image ? esc_html__('One image will be chosen at random from the selected media library items for this post image.', 'ai-post-scheduler') : esc_html__('One image will be chosen at random from the selected media library items.', 'ai-post-scheduler'); ?></p>
	</div>
</div>
