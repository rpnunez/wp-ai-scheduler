<?php
/**
 * Item Rows Partial
 *
 * The digest list from the design (mockup 2b): an optional small-caps caption
 * (`GENERATED TODAY`) followed by title/meta rows separated by hairline rules.
 *
 * Handles both shapes the plugin needs:
 *
 *   - `item_rows`   — one flat list (daily digest, weekly summary).
 *   - `item_groups` — lists grouped under a type label (summarized digest, which
 *                     batches several notification types into one email).
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

$flat_rows = $view->get('item_rows', array());
$groups    = $view->get('item_groups', array());

if (empty($flat_rows) && empty($groups)) {
	return;
}

$color = $theme['color'];
$font  = $theme['font'];

/**
 * Render a caption + row list.
 *
 * @param string $caption Small-caps caption, or '' for none.
 * @param array  $rows    Row list.
 * @param array  $color   Colour tokens.
 * @param array  $font    Font tokens.
 * @return void
 */
$aips_render_rows = static function ($caption, $rows, $color, $font) {
	?>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
		<?php if ('' !== (string) $caption) : ?>
		<tr>
			<td colspan="2" class="aips-text-dim" style="padding:0 0 6px 0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:11px; font-weight:600; line-height:1.4; letter-spacing:1.3px; text-transform:uppercase; color:<?php echo esc_attr($color['text_dim']); ?>;">
				<?php echo esc_html($caption); ?>
			</td>
		</tr>
		<?php endif; ?>

		<?php foreach ($rows as $row) : ?>
		<tr>
			<td valign="top" style="padding:10px 12px 10px 0; border-top:1px solid <?php echo esc_attr($color['border_subtle']); ?>; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; line-height:1.5; color:<?php echo esc_attr($color['text']); ?>;">
				<?php if ('' !== $row['url']) : ?>
				<a href="<?php echo esc_url($row['url']); ?>" style="color:<?php echo esc_attr($color['text']); ?>; text-decoration:none;"><?php echo esc_html($row['title']); ?></a>
				<?php else : ?>
				<?php echo esc_html($row['title']); ?>
				<?php endif; ?>
			</td>
			<td valign="top" align="right" class="aips-text-dim" style="padding:10px 0; border-top:1px solid <?php echo esc_attr($color['border_subtle']); ?>; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; line-height:1.5; color:<?php echo esc_attr($color['text_dim']); ?>; white-space:nowrap;">
				<?php echo esc_html($row['meta']); ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php
};
?>
<?php if (!empty($flat_rows)) : ?>
<?php $aips_render_rows($view->get('item_caption', ''), $flat_rows, $color, $font); ?>
<?php endif; ?>

<?php foreach ($groups as $group_index => $group) : ?>
<?php if ($group_index > 0 || !empty($flat_rows)) : ?>
<div style="height:20px; font-size:20px; line-height:20px;">&nbsp;</div>
<?php endif; ?>
<?php $aips_render_rows($group['label'], $group['rows'], $color, $font); ?>
<?php endforeach; ?>
