<?php
/**
 * Featured Image Partial
 *
 * The post's featured image, shown under the headline (mockup 3d).
 *
 * The prototype used `aspect-ratio:16/9`, which Outlook ignores, so the image is
 * sized by `width:100%` against the container's usable content width and lets its
 * own aspect ratio decide the height.
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

$image = $view->get('featured_image', array());

if (empty($image['url'])) {
	return;
}

$color = $theme['color'];
$width = $theme['size']['content_width'];
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td style="padding:0; font-size:0; line-height:0;">
			<img src="<?php echo esc_url($image['url']); ?>"
				alt="<?php echo esc_attr($image['alt']); ?>"
				width="<?php echo esc_attr($width); ?>"
				style="display:block; width:100%; max-width:<?php echo esc_attr($width); ?>px; height:auto; border:1px solid <?php echo esc_attr($color['border']); ?>; border-radius:<?php echo esc_attr($theme['radius']['cell']); ?>px;" />
		</td>
	</tr>
</table>
