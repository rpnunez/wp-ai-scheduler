<?php
/**
 * Status Pill Partial
 *
 * The small rounded status chip from the design (`Published`, `Draft`). Rendered as
 * a table cell rather than a styled span so the padding survives Outlook.
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

$pill = $view->get('status_pill', array());

if (empty($pill['label'])) {
	return;
}

$tone = AIPS_Email_Theme::tone(isset($pill['tone']) ? $pill['tone'] : AIPS_Email_View::TONE_NEUTRAL);
$font = $theme['font'];
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="right" style="display:inline-table;">
	<tr>
		<td style="padding:3px 10px; background-color:<?php echo esc_attr($tone['bg']); ?>; border-radius:<?php echo esc_attr($theme['radius']['pill']); ?>px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:12px; font-weight:600; line-height:1.5; color:<?php echo esc_attr($tone['fg']); ?>; white-space:nowrap;">
			<?php echo esc_html($pill['label']); ?>
		</td>
	</tr>
</table>
