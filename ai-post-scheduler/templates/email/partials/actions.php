<?php
/**
 * Action Buttons Partial
 *
 * The call-to-action row from the design: a filled primary button followed by
 * outlined secondary buttons.
 *
 * Buttons are padded table cells, not styled anchors, because Outlook drops
 * padding on inline elements.  `mso-padding-alt` restores the inner spacing there.
 * On narrow screens `aips-btn` makes each button full width.
 *
 * AIPS_Email_View guarantees only the first action is primary, so an email never
 * shows two filled buttons competing for the same click.
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

$actions = $view->actions();

if (empty($actions)) {
	return;
}

$color  = $theme['color'];
$font   = $theme['font'];
$radius = $theme['radius']['button'];
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<?php foreach ($actions as $index => $action) : ?>
		<?php
		$is_primary = 'primary' === $action['variant'];

		if ($is_primary) {
			$bg         = $color['brand'];
			$fg         = $color['on_brand'];
			$border     = $color['brand'];
			$padding    = '10px 18px';
			$weight     = '600';
		} else {
			$bg         = $color['surface'];
			$fg         = $color['text'];
			$border     = $color['border_strong'];
			$padding    = '9px 16px';
			$weight     = '500';
		}
		?>
		<?php if ($index > 0) : ?>
		<td width="10" style="width:10px; font-size:10px; line-height:10px;">&nbsp;</td>
		<?php endif; ?>
		<td style="padding:0;">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="aips-btn">
				<tr>
					<td align="center" style="background-color:<?php echo esc_attr($bg); ?>; border:1px solid <?php echo esc_attr($border); ?>; border-radius:<?php echo esc_attr($radius); ?>px; mso-padding-alt:<?php echo esc_attr($padding); ?>;">
						<a href="<?php echo esc_url($action['url']); ?>" class="aips-btn" style="display:inline-block; padding:<?php echo esc_attr($padding); ?>; font-family:<?php echo esc_attr($font['body']); ?>; font-size:14px; font-weight:<?php echo esc_attr($weight); ?>; line-height:1.2; color:<?php echo esc_attr($fg); ?>; text-decoration:none; white-space:nowrap;">
							<?php echo esc_html($action['label']); ?>
						</a>
					</td>
				</tr>
			</table>
		</td>
		<?php endforeach; ?>
	</tr>
</table>
