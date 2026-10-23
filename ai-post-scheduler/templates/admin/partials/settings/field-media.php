<?php
/**
 * Settings Media Upload Field Partial
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-media-field" id="<?php echo esc_attr($id); ?>_wrap">
	<input type="hidden" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" />
	<div class="aips-media-preview<?php echo empty($image_url) ? ' aips-hidden' : ''; ?>">
		<img src="<?php echo esc_url($image_url); ?>" alt="" class="aips-media-preview-img" />
	</div>
	<button type="button" class="button button-secondary aips-choose-media" data-target="<?php echo esc_attr($id); ?>">
		<?php esc_html_e('Select Image', 'ai-post-scheduler'); ?>
	</button>
	<button type="button" class="button button-link-delete aips-remove-media<?php echo empty($image_url) ? ' aips-hidden' : ''; ?>" data-target="<?php echo esc_attr($id); ?>">
		<?php esc_html_e('Remove', 'ai-post-scheduler'); ?>
	</button>
</div>
