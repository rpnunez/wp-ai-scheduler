<?php
/**
 * Callout Partial
 *
 * The tone-tinted explanation box from the design (mockups 2c, 3f): a bold
 * tone-coloured reason line over a muted detail line.  Carries the single most
 * important sentence of an alert email.
 *
 * Expected variables:
 *
 *   @var AIPS_Email_View $view
 *   @var array           $theme
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$callout = $view->get('callout', array());

if (empty($callout['title']) && empty($callout['body'])) {
	return;
}

$color = $theme['color'];
$font  = $theme['font'];
$tone  = AIPS_Email_Theme::tone(isset($callout['tone']) ? $callout['tone'] : AIPS_Email_View::TONE_DANGER);
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:<?php echo esc_attr($tone['bg']); ?>; border:1px solid <?php echo esc_attr($tone['border']); ?>; border-radius:<?php echo esc_attr($theme['radius']['cell']); ?>px;">
	<tr>
		<td style="padding:14px 16px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; line-height:1.5;">
			<?php if (!empty($callout['title'])) : ?>
			<div style="font-weight:700; color:<?php echo esc_attr($tone['fg']); ?>;">
				<?php echo esc_html($callout['title']); ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($callout['body'])) : ?>
			<div style="<?php echo empty($callout['title']) ? '' : 'margin-top:2px;'; ?> color:<?php echo esc_attr($color['text_subtle']); ?>;">
				<?php echo esc_html($callout['body']); ?>
			</div>
			<?php endif; ?>
		</td>
	</tr>
</table>
