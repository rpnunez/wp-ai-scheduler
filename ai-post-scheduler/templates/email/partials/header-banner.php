<?php
/**
 * Status Banner Header Partial
 *
 * The tinted header band from the design (mockups 1b, 3a–3d): a round glyph badge,
 * the event title with a `Site · Time` subtitle, and an optional status pill on the
 * right.  Used by per-post event emails.
 *
 * Expected variables:
 *
 *   @var AIPS_Email_View     $view
 *   @var array               $theme
 *   @var AIPS_Email_Renderer $renderer
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$color  = $theme['color'];
$font   = $theme['font'];
$size   = $theme['size'];

$tone      = AIPS_Email_Theme::tone($view->get('header_tone', AIPS_Email_View::TONE_BRAND));
$icon      = $view->get('header_icon', '');
$pill_html = $view->has('status_pill') ? trim($renderer->partial('status-pill', $view)) : '';
?>
<tr>
	<td class="aips-gutter" style="padding:28px <?php echo esc_attr($size['gutter']); ?>px; background-color:<?php echo esc_attr($tone['bg']); ?>; border-bottom:1px solid <?php echo esc_attr($tone['border']); ?>; border-radius:<?php echo esc_attr($theme['radius']['container']); ?>px <?php echo esc_attr($theme['radius']['container']); ?>px 0 0;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
			<tr>
				<?php if ('' !== $icon) : ?>
				<td width="44" valign="middle" style="width:44px; padding:0 16px 0 0;">
					<?php /* Round badge: a table cell, since Outlook ignores border-radius on spans. */ ?>
					<table role="presentation" width="44" cellpadding="0" cellspacing="0" border="0" style="width:44px;">
						<tr>
							<td align="center" valign="middle" height="44" style="width:44px; height:44px; background-color:<?php echo esc_attr($tone['fg']); ?>; border-radius:22px; color:<?php echo esc_attr($color['on_brand']); ?>; font-family:<?php echo esc_attr($font['body']); ?>; font-size:20px; font-weight:600; line-height:44px; text-align:center;">
								<?php echo esc_html($icon); ?>
							</td>
						</tr>
					</table>
				</td>
				<?php endif; ?>

				<td valign="middle" style="padding:0;">
					<?php if ($view->has('header_title')) : ?>
					<div class="aips-text" style="font-family:<?php echo esc_attr($font['heading']); ?>; font-size:20px; font-weight:600; line-height:1.3; color:<?php echo esc_attr($color['text']); ?>;">
						<?php echo esc_html($view->get('header_title', '')); ?>
					</div>
					<?php endif; ?>

					<?php if ($view->has('header_subtitle')) : ?>
					<div class="aips-text-dim" style="margin-top:2px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; line-height:1.4; color:<?php echo esc_attr($color['text_subtle']); ?>;">
						<?php echo esc_html($view->get('header_subtitle', '')); ?>
					</div>
					<?php endif; ?>
				</td>

				<?php if ('' !== $pill_html) : ?>
				<td valign="middle" align="right" style="padding:0 0 0 12px;">
					<?php echo $pill_html; // phpcs:ignore WordPress.Security.EscapeOutput -- partial escapes its own output. ?>
				</td>
				<?php endif; ?>
			</tr>
		</table>
	</td>
</tr>
