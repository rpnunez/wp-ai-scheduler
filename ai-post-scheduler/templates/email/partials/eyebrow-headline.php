<?php
/**
 * Eyebrow + Headline Partial
 *
 * The lede block from the design: a small uppercase, letter-spaced, tone-coloured
 * eyebrow (`POST READY FOR REVIEW`, `QUOTA ALERT · EMBEDDING`, `WEEKLY SUMMARY ·
 * NOV 2–8, 2026`) above the headline.
 *
 * Either half may be omitted: per-post emails carry the status in the banner header
 * and show the post title alone, at the smaller `md` size.
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

$color = $theme['color'];
$font  = $theme['font'];

$eyebrow_tone = AIPS_Email_Theme::tone($view->get('eyebrow_tone', AIPS_Email_View::TONE_BRAND));

// 'lg' is the 24px standalone headline; 'md' is the 20px post title that sits
// under a status banner.
$is_large       = 'md' !== $view->get('headline_size', 'lg');
$headline_size  = $is_large ? '24px' : '20px';
$headline_lead  = $is_large ? '1.25' : '1.3';
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<?php if ($view->has('eyebrow')) : ?>
	<tr>
		<td style="padding:0 0 8px 0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:11px; font-weight:600; line-height:1.4; letter-spacing:1.3px; text-transform:uppercase; color:<?php echo esc_attr($eyebrow_tone['fg']); ?>;">
			<?php echo esc_html($view->get('eyebrow', '')); ?>
		</td>
	</tr>
	<?php endif; ?>

	<?php if ($view->has('headline')) : ?>
	<tr>
		<td class="aips-text" style="padding:0; font-family:<?php echo esc_attr($font['heading']); ?>; font-size:<?php echo esc_attr($headline_size); ?>; font-weight:600; line-height:<?php echo esc_attr($headline_lead); ?>; color:<?php echo esc_attr($color['text']); ?>;">
			<?php echo esc_html($view->get('headline', '')); ?>
		</td>
	</tr>
	<?php endif; ?>
</table>
