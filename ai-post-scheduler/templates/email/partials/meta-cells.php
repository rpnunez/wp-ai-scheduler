<?php
/**
 * Metadata Cells Partial
 *
 * The two-column metadata grid from the design: each cell is a tinted, bordered
 * box with a small dim label over a semibold value.
 *
 * The prototype used CSS grid, which Outlook does not support, so this is a table
 * of two-cell rows separated by an 8px spacer column.  Cells stack to full width
 * below 600px via the `aips-stack` class in the layout's media query.
 *
 * A cell with no value never reaches this partial — AIPS_Email_View drops it — so
 * the grid re-flows to whatever metadata actually exists.  An odd final cell gets
 * an empty partner so the row keeps its 50/50 geometry.
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

$cells = $view->meta_cells();

if (empty($cells)) {
	return;
}

$color = $theme['color'];
$font  = $theme['font'];
$gap   = $theme['size']['cell_gap'];
$rows  = array_chunk($cells, 2);

/**
 * Render one metadata cell, or an empty spacer when $cell is null.
 *
 * @param array|null $cell  Cell data.
 * @param array      $color Colour tokens.
 * @param array      $font  Font tokens.
 * @param array      $theme Full token map.
 * @return void
 */
$aips_render_cell = static function ($cell, $color, $font, $theme) {
	if (null === $cell) {
		echo '<td class="aips-stack" width="50%" style="width:50%; padding:0;">&nbsp;</td>';
		return;
	}
	?>
	<td class="aips-stack aips-cell" width="50%" valign="top" style="width:50%; padding:12px 16px; background-color:<?php echo esc_attr($color['surface_subtle']); ?>; border:1px solid <?php echo esc_attr($color['border']); ?>; border-radius:<?php echo esc_attr($theme['radius']['cell']); ?>px; font-family:<?php echo esc_attr($font['body']); ?>;">
		<div class="aips-text-dim" style="font-size:12px; line-height:1.4; color:<?php echo esc_attr($color['text_dim']); ?>;">
			<?php echo esc_html($cell['label']); ?>
		</div>
		<div class="aips-text" style="font-size:13px; font-weight:600; line-height:1.4; color:<?php echo esc_attr($color['text']); ?>; word-break:break-word; overflow-wrap:anywhere;">
			<?php echo esc_html($cell['value']); ?>
		</div>
	</td>
	<?php
};
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<?php foreach ($rows as $row_index => $row) : ?>
	<?php if ($row_index > 0) : ?>
	<tr><td colspan="3" class="aips-stack-gap" style="height:<?php echo esc_attr($gap); ?>px; font-size:<?php echo esc_attr($gap); ?>px; line-height:<?php echo esc_attr($gap); ?>px;">&nbsp;</td></tr>
	<?php endif; ?>
	<tr>
		<?php $aips_render_cell($row[0], $color, $font, $theme); ?>
		<td class="aips-stack-gap" width="<?php echo esc_attr($gap); ?>" style="width:<?php echo esc_attr($gap); ?>px; font-size:<?php echo esc_attr($gap); ?>px; line-height:<?php echo esc_attr($gap); ?>px;">&nbsp;</td>
		<?php $aips_render_cell(isset($row[1]) ? $row[1] : null, $color, $font, $theme); ?>
	</tr>
	<?php endforeach; ?>
</table>
