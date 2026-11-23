<?php
/**
 * Email Design Theme Tokens
 *
 * Single source of truth for every colour, font, radius, and spacing value used
 * by the notification email templates.  Partials read tokens from here and must
 * never hardcode a literal hex value, so the whole email system can be re-skinned
 * from one place (or by a site, through the `aips_email_theme` filter).
 *
 * Token values come from the `Email/Emails.dc.html` design handoff.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Email_Theme
 *
 * Usage:
 *
 *   $theme = AIPS_Email_Theme::tokens();
 *   echo esc_attr($theme['color']['brand']);
 *
 *   // Or for a single token, using dot notation:
 *   echo esc_attr(AIPS_Email_Theme::token('color.brand'));
 *
 *   // Tone-aware lookups (brand / danger / warning / success / neutral):
 *   $tone = AIPS_Email_Theme::tone('danger');
 *   echo esc_attr($tone['fg']);   // #b32d2e
 *   echo esc_attr($tone['bg']);   // #fcecec
 */
class AIPS_Email_Theme {

	/**
	 * Memoised token map (post-filter).
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Return the full token map.
	 *
	 * @return array<string, mixed>
	 */
	public static function tokens() {
		if (null !== self::$cache) {
			return self::$cache;
		}

		$tokens = self::defaults();

		/**
		 * Filter: aips_email_theme
		 *
		 * Allows a site to re-skin notification emails by overriding design tokens.
		 * The returned value must keep the same array shape as the default map;
		 * anything missing falls back to the default via array merge.
		 *
		 * @since 3.8.0
		 * @param array<string, mixed> $tokens Default token map.
		 */
		$filtered = apply_filters('aips_email_theme', $tokens);

		if (is_array($filtered)) {
			// Merge one level deep so a partial override (e.g. only 'color') keeps
			// the remaining default groups intact.
			foreach ($filtered as $group => $values) {
				if (is_array($values) && isset($tokens[$group]) && is_array($tokens[$group])) {
					$tokens[$group] = array_merge($tokens[$group], $values);
					continue;
				}
				$tokens[$group] = $values;
			}
		}

		self::$cache = $tokens;

		return self::$cache;
	}

	/**
	 * Look up a single token using dot notation (e.g. 'color.brand').
	 *
	 * @param string $path    Dot-delimited token path.
	 * @param mixed  $default Value returned when the path is not set.
	 * @return mixed
	 */
	public static function token($path, $default = '') {
		$node = self::tokens();

		foreach (explode('.', (string) $path) as $segment) {
			if (!is_array($node) || !isset($node[$segment])) {
				return $default;
			}
			$node = $node[$segment];
		}

		return $node;
	}

	/**
	 * Return the colour triplet for a semantic tone.
	 *
	 * Each tone resolves to a foreground colour, a tinted background, and a border
	 * colour, which is everything the callout / pill / tile partials need.
	 *
	 * @param string $tone One of brand, danger, warning, success, neutral.
	 * @return array{fg: string, bg: string, border: string}
	 */
	public static function tone($tone) {
		$tones = self::token('tone', array());
		$tone  = (string) $tone;

		if (isset($tones[$tone])) {
			return $tones[$tone];
		}

		return isset($tones['neutral']) ? $tones['neutral'] : array(
			'fg'     => '#50575e',
			'bg'     => '#f0f0f0',
			'border' => '#e0e0e0',
		);
	}

	/**
	 * Reset the memoised token map.
	 *
	 * Used by tests that register an `aips_email_theme` filter after the map has
	 * already been built.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * The built-in token map, transcribed from the design handoff.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults() {
		return array(
			'color' => array(
				// Surfaces.
				'page'           => '#f0f0f1',
				'surface'        => '#ffffff',
				'surface_subtle' => '#fafafa',
				'border'         => '#e0e0e0',
				'border_subtle'  => '#eeeeee',
				'border_strong'  => '#c3c4c7',

				// Brand.
				'brand'          => '#3858e9',
				'brand_hover'    => '#2145d4',
				'brand_tint'     => '#eef1fd',
				'brand_tint_border' => '#dde3fb',
				'on_brand'       => '#ffffff',

				// Text.
				'text'           => '#1e1e1e',
				'text_subtle'    => '#50575e',
				'text_dim'       => '#757575',
				'text_body'      => '#3c434a',
			),

			// Semantic tones used by callouts, status pills, and stat tiles.
			'tone' => array(
				'brand' => array(
					'fg'     => '#3858e9',
					'bg'     => '#eef1fd',
					'border' => '#dde3fb',
				),
				'danger' => array(
					'fg'     => '#b32d2e',
					'bg'     => '#fcecec',
					'border' => '#f3c6c6',
				),
				'warning' => array(
					'fg'     => '#996300',
					'bg'     => '#fdf3df',
					'border' => '#fbe3b0',
				),
				'success' => array(
					'fg'     => '#1a7f37',
					'bg'     => '#e7f5ea',
					'border' => '#cbe8d4',
				),
				'neutral' => array(
					'fg'     => '#50575e',
					'bg'     => '#f0f0f0',
					'border' => '#e0e0e0',
				),
			),

			'font' => array(
				// Bricolage Grotesque is linked for clients that honour webfonts and
				// degrades to Arial everywhere else (Gmail, Outlook). Never used for
				// text baked into an image.
				'heading' => "'Bricolage Grotesque', 'Helvetica Neue', Arial, sans-serif",
				'body'    => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, Helvetica, sans-serif",
				'mono'    => "ui-monospace, 'SF Mono', Menlo, Consolas, monospace",
				'webfont_url' => 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;600&display=swap',
			),

			'size' => array(
				// Container is 600px wide; 32px of horizontal padding leaves 536px
				// of usable content width (used for max-width on images).
				'container'     => 600,
				'content_width' => 536,
				'gutter'        => 32,
				'cell_gap'      => 8,
			),

			'radius' => array(
				'container' => 8,
				'cell'      => 6,
				'button'    => 4,
				'pill'      => 10,
			),
		);
	}
}
