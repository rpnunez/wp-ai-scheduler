<?php
/**
 * Email Footer Partial
 *
 * The footer bar from the design: `Sent by AI Post Scheduler on {Site} ·
 * Notification settings`, on the subtle surface with a hairline top border.
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

$color  = $theme['color'];
$font   = $theme['font'];
$size   = $theme['size'];
$radius = $theme['radius']['container'];

$footer_link = $view->get('footer_link', array());
?>
<tr>
	<td class="aips-gutter aips-cell" style="padding:18px <?php echo esc_attr($size['gutter']); ?>px; background-color:<?php echo esc_attr($color['surface_subtle']); ?>; border-top:1px solid <?php echo esc_attr($color['border_subtle']); ?>; border-radius:0 0 <?php echo esc_attr($radius); ?>px <?php echo esc_attr($radius); ?>px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:12px; line-height:1.5; color:<?php echo esc_attr($color['text_dim']); ?>;">
		<span class="aips-text-dim" style="color:<?php echo esc_attr($color['text_dim']); ?>;">
			<?php
			echo esc_html(sprintf(
				/* translators: %s: site name */
				__('Sent by AI Post Scheduler on %s', 'ai-post-scheduler'),
				$view->get('site_name', '')
			));
			?>
		</span>
		<?php if (!empty($footer_link['url']) && !empty($footer_link['label'])) : ?>
		<span class="aips-text-dim" style="color:<?php echo esc_attr($color['text_dim']); ?>;"> &middot; </span>
		<a href="<?php echo esc_url($footer_link['url']); ?>" style="color:<?php echo esc_attr($color['brand']); ?>; text-decoration:none;">
			<?php echo esc_html($footer_link['label']); ?>
		</a>
		<?php endif; ?>
	</td>
</tr>
