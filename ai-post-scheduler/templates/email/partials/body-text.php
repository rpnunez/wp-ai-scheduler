<?php
/**
 * Body Text Partial
 *
 * A single paragraph of plain prose.  Used as the fallback body for notification
 * types that have no curated view yet (including third-party types registered
 * through the `aips_notification_templates` hook), so every email still renders in
 * the redesigned layout rather than falling back to bare HTML.
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

if (!$view->has('body_text')) {
	return;
}

$color = $theme['color'];
$font  = $theme['font'];
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td style="padding:0; font-family:<?php echo esc_attr($font['body']); ?>; font-size:14px; line-height:1.6; color:<?php echo esc_attr($color['text_body']); ?>;">
			<?php echo esc_html($view->get('body_text', '')); ?>
		</td>
	</tr>
</table>
