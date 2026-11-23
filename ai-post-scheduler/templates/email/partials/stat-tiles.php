<?php
/**
 * Stat Tiles Partial
 *
 * The three-up counter row from the design (mockups 2b, 3e): a large display
 * number over a small label, in a bordered tile.  Tiles carry a tone so the
 * "Ready for review" count can read amber and an error count red, while neutral
 * counts stay white.
 *
 * Tiles are laid out as one table row with 12px spacer columns, and stack to full
 * width on narrow screens via `aips-stack`.
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

$tiles = $view->get('stat_tiles', array());

if (empty($tiles)) {
	return;
}

$color = $theme['color'];
$font  = $theme['font'];

// Even widths for however many tiles there are (the design shows three).
$tile_width = (int) floor(100 / max(1, count($tiles)));
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<?php foreach ($tiles as $index => $tile) : ?>
		<?php
		$is_neutral = AIPS_Email_View::TONE_NEUTRAL === (isset($tile['tone']) ? $tile['tone'] : AIPS_Email_View::TONE_NEUTRAL);
		$tone       = AIPS_Email_Theme::tone(isset($tile['tone']) ? $tile['tone'] : AIPS_Email_View::TONE_NEUTRAL);

		// A neutral tile stays on the white surface with the standard border; a
		// toned tile takes its tint, border, and text colour from the tone.
		$tile_bg     = $is_neutral ? $color['surface'] : $tone['bg'];
		$tile_border = $is_neutral ? $color['border'] : $tone['border'];
		$value_color = $is_neutral ? $color['text'] : $tone['fg'];
		$label_color = $is_neutral ? $color['text_dim'] : $tone['fg'];
		?>
		<?php if ($index > 0) : ?>
		<td class="aips-stack-gap" width="12" style="width:12px; font-size:12px; line-height:12px;">&nbsp;</td>
		<?php endif; ?>
		<td class="aips-stack aips-cell" width="<?php echo esc_attr($tile_width); ?>%" valign="top" style="width:<?php echo esc_attr($tile_width); ?>%; padding:14px 16px; background-color:<?php echo esc_attr($tile_bg); ?>; border:1px solid <?php echo esc_attr($tile_border); ?>; border-radius:<?php echo esc_attr($theme['radius']['cell']); ?>px;">
			<div style="font-family:<?php echo esc_attr($font['heading']); ?>; font-size:28px; font-weight:600; line-height:1.15; color:<?php echo esc_attr($value_color); ?>;">
				<?php echo esc_html($tile['value']); ?>
			</div>
			<div style="margin-top:2px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:12px; line-height:1.4; color:<?php echo esc_attr($label_color); ?>;">
				<?php echo esc_html($tile['label']); ?>
			</div>
		</td>
		<?php endforeach; ?>
	</tr>
</table>
