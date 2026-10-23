<?php
/**
 * Settings Password Input Field Partial
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-password-field-wrapper">
	<input type="password" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" class="<?php echo esc_attr(trim($class_str)); ?>" autocomplete="new-password" />
	<button type="button" class="button button-secondary aips-toggle-password" data-target="<?php echo esc_attr($id); ?>">
		<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
		<span class="screen-reader-text"><?php esc_html_e('Toggle visibility', 'ai-post-scheduler'); ?></span>
	</button>
</div>
