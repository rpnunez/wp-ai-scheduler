<?php
/**
 * Quiet Brand Bar Header Partial
 *
 * The understated header from the design (mockups 1a, 2b, 2c): a small brand mark
 * and the product name on the left, the site name on the right.  Used by digests
 * and summaries, where a large status banner would overstate the message.
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

$color = $theme['color'];
$font  = $theme['font'];
$size  = $theme['size'];
?>
<tr>
	<td class="aips-gutter" style="padding:18px <?php echo esc_attr($size['gutter']); ?>px; border-bottom:1px solid <?php echo esc_attr($color['border_subtle']); ?>;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
			<tr>
				<td valign="middle" style="padding:0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; font-weight:600; color:<?php echo esc_attr($color['text']); ?>;">
					<table role="presentation" cellpadding="0" cellspacing="0" border="0">
						<tr>
							<td valign="middle" style="padding:0 8px 0 0;">
								<?php /* 10px brand square. */ ?>
								<table role="presentation" width="10" cellpadding="0" cellspacing="0" border="0" style="width:10px;">
									<tr>
										<td height="10" style="width:10px; height:10px; background-color:<?php echo esc_attr($color['brand']); ?>; border-radius:3px; font-size:0; line-height:0;">&nbsp;</td>
									</tr>
								</table>
							</td>
							<td valign="middle" class="aips-text" style="padding:0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; font-weight:600; color:<?php echo esc_attr($color['text']); ?>;">
								<?php echo esc_html__('AI Post Scheduler', 'ai-post-scheduler'); ?>
							</td>
						</tr>
					</table>
				</td>
				<td valign="middle" align="right" class="aips-text-dim" style="padding:0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:13px; color:<?php echo esc_attr($color['text_dim']); ?>;">
					<?php echo esc_html($view->get('site_name', '')); ?>
				</td>
			</tr>
		</table>
	</td>
</tr>
