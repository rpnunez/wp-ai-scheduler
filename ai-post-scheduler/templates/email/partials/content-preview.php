<?php
/**
 * Content Preview Partial
 *
 * The excerpt and content-preview block from the design (mockups 3b–3d): an
 * `EXCERPT` caption with the post excerpt, then a `CONTENT PREVIEW` caption with
 * the opening paragraphs and subheadings, then a read-more link.
 *
 * The prototype clipped the preview at `max-height:190px` under a white gradient
 * fade.  Neither overflow clipping nor gradients are reliable in email, so the
 * preview is truncated server-side (see AIPS_Email_View_Factory) and the link
 * carries the "there is more" signal instead.
 *
 * Preview blocks are typed plain text, never post HTML, so generated content
 * cannot inject markup into an email.
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

$blocks = $view->get('preview_blocks', array());
$link   = $view->get('preview_link', array());

if (!$view->has('excerpt') && empty($blocks) && empty($link)) {
	return;
}

$color = $theme['color'];
$font  = $theme['font'];

/**
 * Small-caps section caption.
 *
 * @param string $label Caption text.
 * @param array  $color Colour tokens.
 * @param array  $font  Font tokens.
 * @return void
 */
$aips_caption = static function ($label, $color, $font) {
	?>
	<div class="aips-text-dim" style="margin-bottom:6px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:11px; font-weight:600; line-height:1.4; letter-spacing:1.3px; text-transform:uppercase; color:<?php echo esc_attr($color['text_dim']); ?>;">
		<?php echo esc_html($label); ?>
	</div>
	<?php
};
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<?php if ($view->has('excerpt')) : ?>
	<tr>
		<td style="padding:0;">
			<?php $aips_caption(__('Excerpt', 'ai-post-scheduler'), $color, $font); ?>
			<div style="font-family:<?php echo esc_attr($font['body']); ?>; font-size:14px; line-height:1.5; color:<?php echo esc_attr($color['text_body']); ?>;">
				<?php echo esc_html($view->get('excerpt', '')); ?>
			</div>
		</td>
	</tr>
	<?php endif; ?>

	<?php if (!empty($blocks)) : ?>
	<tr>
		<td style="padding:<?php echo $view->has('excerpt') ? '16px' : '0'; ?> 0 0 0; <?php echo $view->has('excerpt') ? 'border-top:1px solid ' . esc_attr($color['border_subtle']) . ';' : ''; ?>">
			<?php if ($view->has('excerpt')) : ?>
			<div style="height:16px; font-size:16px; line-height:16px;">&nbsp;</div>
			<?php endif; ?>
			<?php $aips_caption(__('Content preview', 'ai-post-scheduler'), $color, $font); ?>

			<?php foreach ($blocks as $block_index => $block) : ?>
			<?php if (AIPS_Email_View::BLOCK_HEADING === $block['type']) : ?>
			<div class="aips-text" style="<?php echo $block_index > 0 ? 'margin-top:12px;' : ''; ?> font-family:<?php echo esc_attr($font['heading']); ?>; font-size:16px; font-weight:600; line-height:1.35; color:<?php echo esc_attr($color['text']); ?>;">
				<?php echo esc_html($block['text']); ?>
			</div>
			<?php else : ?>
			<div style="<?php echo $block_index > 0 ? 'margin-top:12px;' : ''; ?> font-family:<?php echo esc_attr($font['body']); ?>; font-size:14px; line-height:1.5; color:<?php echo esc_attr($color['text_body']); ?>;">
				<?php echo esc_html($block['text']); ?>
			</div>
			<?php endif; ?>
			<?php endforeach; ?>
		</td>
	</tr>
	<?php endif; ?>

	<?php if (!empty($link)) : ?>
	<tr>
		<td style="padding:12px 0 0 0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; font-weight:600; line-height:1.5;">
			<a href="<?php echo esc_url($link['url']); ?>" style="color:<?php echo esc_attr($color['brand']); ?>; text-decoration:none;">
				<?php echo esc_html($link['label']); ?> &rarr;
			</a>
		</td>
	</tr>
	<?php endif; ?>
</table>
