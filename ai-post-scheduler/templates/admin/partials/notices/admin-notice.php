<?php
/**
 * Admin Notice Partial
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="<?php echo esc_attr($classes); ?>"<?php echo $attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><p><?php echo wp_kses_post($message); ?></p></div>
