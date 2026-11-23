<?php
/**
 * Shared Email Layout
 *
 * The document shell for every AIPS notification email: doctype, hidden preheader,
 * page background, the 600px container, and the footer.  Body regions are rendered
 * by partials, in the order declared below, and any region the view leaves empty
 * is skipped entirely.
 *
 * Expected variables:
 *
 *   @var AIPS_Email_View      $view     The view being rendered.
 *   @var array                $theme    Design tokens from AIPS_Email_Theme::tokens().
 *   @var AIPS_Email_Renderer  $renderer Renderer, used to pull in partials.
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
$radius = $theme['radius'];

/**
 * Body regions, in render order.  Each entry maps a partial name to the view
 * field that must be populated for it to render.
 */
$regions = array(
	'eyebrow-headline' => array('eyebrow', 'headline'),
	'callout'          => array('callout'),
	'featured-image'   => array('featured_image'),
	'body-text'        => array('body_text'),
	'stat-tiles'       => array('stat_tiles'),
	'meta-cells'       => array('meta_cells'),
	'content-preview'  => array('excerpt', 'preview_blocks', 'preview_link'),
	'item-rows'        => array('item_rows', 'item_groups'),
	'actions'          => array('actions'),
);

$body_blocks = array();

foreach ($regions as $partial => $fields) {
	$populated = false;

	foreach ($fields as $field) {
		if ($view->has($field)) {
			$populated = true;
			break;
		}
	}

	if (!$populated) {
		continue;
	}

	$rendered = trim($renderer->partial($partial, $view));

	if ('' !== $rendered) {
		$body_blocks[] = $rendered;
	}
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo esc_attr(str_replace('_', '-', get_locale())); ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="x-apple-disable-message-reformatting" />
	<meta name="color-scheme" content="light dark" />
	<meta name="supported-color-schemes" content="light dark" />
	<title><?php echo esc_html($view->get('subject', $view->get('headline', ''))); ?></title>
	<!--[if mso]>
	<xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
	<![endif]-->
	<link href="<?php echo esc_url($font['webfont_url']); ?>" rel="stylesheet" />
	<style type="text/css">
		/*
		 * Progressive enhancement ONLY. Gmail strips this block, so nothing here
		 * may be the sole source of a style — every element is styled inline too.
		 */
		body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
		img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
		table { border-collapse: collapse !important; }
		a { color: <?php echo esc_html($color['brand']); ?>; }
		a:hover { color: <?php echo esc_html($color['brand_hover']); ?>; }

		@media only screen and (max-width: 600px) {
			.aips-container { width: 100% !important; }
			.aips-gutter { padding-left: 20px !important; padding-right: 20px !important; }
			/* Metadata cells and stat tiles go full width rather than squeezing. */
			.aips-stack { display: block !important; width: 100% !important; }
			.aips-stack-gap { height: 8px !important; width: auto !important; font-size: 8px !important; line-height: 8px !important; }
			.aips-btn { display: block !important; width: 100% !important; text-align: center !important; }
		}

		@media (prefers-color-scheme: dark) {
			.aips-page { background: #1d2327 !important; }
			.aips-surface { background: #23282d !important; }
			.aips-cell { background: #2c3338 !important; }
			.aips-text { color: #f0f0f1 !important; }
			.aips-text-dim { color: #a7aaad !important; }
		}
	</style>
</head>
<body class="aips-page" style="margin:0; padding:0; background-color:<?php echo esc_attr($color['page']); ?>; -webkit-font-smoothing:antialiased;">

	<?php /* Preheader: the snippet clients show next to the subject line. */ ?>
	<div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
		<?php echo esc_html($view->get('preheader', '')); ?>
		<?php // Pad so the client does not pull body copy into the preview snippet. ?>
		<?php echo str_repeat('&#847;&zwnj;&nbsp;', 40); // phpcs:ignore WordPress.Security.EscapeOutput -- static entity padding. ?>
	</div>

	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="aips-page" style="background-color:<?php echo esc_attr($color['page']); ?>;">
		<tr>
			<td align="center" style="padding:24px 12px;">

				<table role="presentation" width="<?php echo esc_attr($size['container']); ?>" cellpadding="0" cellspacing="0" border="0" class="aips-container aips-surface" style="width:<?php echo esc_attr($size['container']); ?>px; max-width:<?php echo esc_attr($size['container']); ?>px; background-color:<?php echo esc_attr($color['surface']); ?>; border:1px solid <?php echo esc_attr($color['border']); ?>; border-radius:<?php echo esc_attr($radius['container']); ?>px; font-family:<?php echo esc_attr($font['body']); ?>; font-size:14px; line-height:1.5; color:<?php echo esc_attr($color['text']); ?>;">

					<?php
					// Header chrome: tinted status banner, quiet brand bar, or nothing.
					if (AIPS_Email_View::HEADER_BANNER === $view->header_mode()) {
						echo $renderer->partial('header-banner', $view); // phpcs:ignore WordPress.Security.EscapeOutput -- partial escapes its own output.
					} elseif (AIPS_Email_View::HEADER_BRAND === $view->header_mode()) {
						echo $renderer->partial('header-brand', $view); // phpcs:ignore WordPress.Security.EscapeOutput -- partial escapes its own output.
					}
					?>

					<?php if (!empty($body_blocks)) : ?>
					<tr>
						<td class="aips-gutter" style="padding:<?php echo esc_attr($size['gutter']); ?>px;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
								<?php foreach ($body_blocks as $index => $block) : ?>
								<?php if ($index > 0) : ?>
								<tr><td style="height:20px; font-size:20px; line-height:20px;">&nbsp;</td></tr>
								<?php endif; ?>
								<tr>
									<td style="padding:0;">
										<?php echo $block; // phpcs:ignore WordPress.Security.EscapeOutput -- partials escape their own output. ?>
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<?php endif; ?>

					<?php echo $renderer->partial('footer', $view); // phpcs:ignore WordPress.Security.EscapeOutput -- partial escapes its own output. ?>

				</table>

			</td>
		</tr>
	</table>
</body>
</html>
