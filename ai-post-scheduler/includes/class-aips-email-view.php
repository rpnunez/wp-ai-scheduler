<?php
/**
 * Email View Model
 *
 * Immutable value object describing *what* a notification email contains, with no
 * opinion about how it is rendered.  `AIPS_Email_View_Factory` builds instances
 * from sender payloads; `AIPS_Email_Renderer` turns them into HTML by dispatching
 * each populated region to a partial.
 *
 * Every string field holds PLAIN TEXT, never markup — templates are responsible
 * for escaping.  The one deliberate exception is that no field accepts raw HTML
 * at all: post content arrives as a list of typed preview blocks, so a malicious
 * generated post cannot inject markup into an email.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Email_View
 *
 * Usage:
 *
 *   $view = AIPS_Email_View::create(array(
 *       'header_mode'  => AIPS_Email_View::HEADER_BANNER,
 *       'header_title' => 'Post ready for review',
 *       'headline'     => 'Continuous Pentesting in Production',
 *       'meta_cells'   => array(
 *           array('label' => 'Category', 'value' => 'Security'),
 *       ),
 *   ));
 *
 *   $view = $view->with(array('preheader' => 'A generated post is awaiting review.'));
 */
class AIPS_Email_View {

	// -----------------------------------------------------------------------
	// Header modes
	// -----------------------------------------------------------------------

	/** Tinted status band with icon, title, subtitle, and optional status pill. */
	const HEADER_BANNER = 'banner';

	/** Quiet one-line brand bar: product name on the left, site name on the right. */
	const HEADER_BRAND = 'brand';

	/** No header chrome; the body opens on the eyebrow + headline block. */
	const HEADER_NONE = 'none';

	// -----------------------------------------------------------------------
	// Tones (mirrors AIPS_Email_Theme::tone() keys)
	// -----------------------------------------------------------------------

	const TONE_BRAND   = 'brand';
	const TONE_DANGER  = 'danger';
	const TONE_WARNING = 'warning';
	const TONE_SUCCESS = 'success';
	const TONE_NEUTRAL = 'neutral';

	// -----------------------------------------------------------------------
	// Preview block types
	// -----------------------------------------------------------------------

	const BLOCK_PARAGRAPH = 'paragraph';
	const BLOCK_HEADING   = 'heading';

	/**
	 * View data, normalised against self::schema().
	 *
	 * @var array<string, mixed>
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * Private-by-convention entry point is create(); the constructor stays public
	 * so the class remains trivially instantiable in tests.
	 *
	 * @param array<string, mixed> $data Raw view data.
	 */
	public function __construct(array $data = array()) {
		$this->data = array_merge(self::schema(), $this->normalise($data));
	}

	/**
	 * Named constructor.
	 *
	 * @param array<string, mixed> $data Raw view data.
	 * @return self
	 */
	public static function create(array $data = array()) {
		return new self($data);
	}

	/**
	 * Return a new instance with the given fields replaced.
	 *
	 * The receiver is never mutated.
	 *
	 * @param array<string, mixed> $patch Fields to override.
	 * @return self
	 */
	public function with(array $patch) {
		return new self(array_merge($this->data, $this->normalise($patch)));
	}

	/**
	 * Read a single field.
	 *
	 * @param string $key     Field name.
	 * @param mixed  $default Value returned when the field is unset or empty.
	 * @return mixed
	 */
	public function get($key, $default = null) {
		if (!array_key_exists($key, $this->data)) {
			return $default;
		}

		$value = $this->data[$key];

		if (null === $default) {
			return $value;
		}

		return ('' === $value || array() === $value) ? $default : $value;
	}

	/**
	 * Whether a region has content worth rendering.
	 *
	 * Templates use this instead of truthiness checks so "0" (a legitimate stat
	 * tile value) is never treated as empty.
	 *
	 * @param string $key Field name.
	 * @return bool
	 */
	public function has($key) {
		if (!array_key_exists($key, $this->data)) {
			return false;
		}

		$value = $this->data[$key];

		if (is_array($value)) {
			return !empty($value);
		}

		return '' !== (string) $value;
	}

	/**
	 * Return the full normalised data array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->data;
	}

	// -----------------------------------------------------------------------
	// Convenience accessors for the regions templates touch most
	// -----------------------------------------------------------------------

	/**
	 * Header mode: banner, brand, or none.
	 *
	 * @return string
	 */
	public function header_mode() {
		return (string) $this->data['header_mode'];
	}

	/**
	 * Curated metadata cells.
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	public function meta_cells() {
		return $this->data['meta_cells'];
	}

	/**
	 * Call-to-action buttons, primary first.
	 *
	 * @return array<int, array{label: string, url: string, variant: string}>
	 */
	public function actions() {
		return $this->data['actions'];
	}

	// -----------------------------------------------------------------------
	// Normalisation
	// -----------------------------------------------------------------------

	/**
	 * Coerce caller-supplied data into the documented shape.
	 *
	 * Repeating regions are filtered so an entry missing its required keys drops
	 * out rather than rendering an empty cell/row/tile — the design's metadata
	 * grid re-flows to whatever cells actually have values.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function normalise(array $data) {
		if (isset($data['header_mode'])) {
			$allowed = array(self::HEADER_BANNER, self::HEADER_BRAND, self::HEADER_NONE);
			if (!in_array($data['header_mode'], $allowed, true)) {
				$data['header_mode'] = self::HEADER_NONE;
			}
		}

		if (isset($data['meta_cells'])) {
			$data['meta_cells'] = self::filter_rows($data['meta_cells'], array('label', 'value'));
		}

		if (isset($data['stat_tiles'])) {
			$data['stat_tiles'] = self::filter_rows($data['stat_tiles'], array('label', 'value'), array('tone' => self::TONE_NEUTRAL));
		}

		if (isset($data['item_rows'])) {
			$data['item_rows'] = self::filter_rows($data['item_rows'], array('title'), array('meta' => '', 'url' => ''));
		}

		if (isset($data['item_groups']) && is_array($data['item_groups'])) {
			$groups = array();
			foreach ($data['item_groups'] as $group) {
				if (!is_array($group) || empty($group['label'])) {
					continue;
				}
				$rows = isset($group['rows']) ? self::filter_rows($group['rows'], array('title'), array('meta' => '', 'url' => '')) : array();
				if (empty($rows)) {
					continue;
				}
				$groups[] = array(
					'label' => (string) $group['label'],
					'rows'  => $rows,
				);
			}
			$data['item_groups'] = $groups;
		}

		if (isset($data['preview_blocks']) && is_array($data['preview_blocks'])) {
			$blocks = array();
			foreach ($data['preview_blocks'] as $block) {
				if (!is_array($block) || empty($block['text'])) {
					continue;
				}
				$type = isset($block['type']) && self::BLOCK_HEADING === $block['type']
					? self::BLOCK_HEADING
					: self::BLOCK_PARAGRAPH;
				$blocks[] = array(
					'type' => $type,
					'text' => (string) $block['text'],
				);
			}
			$data['preview_blocks'] = $blocks;
		}

		if (isset($data['actions']) && is_array($data['actions'])) {
			$actions = array();
			foreach ($data['actions'] as $index => $action) {
				if (!is_array($action) || empty($action['label']) || empty($action['url'])) {
					continue;
				}
				$variant = isset($action['variant']) && 'secondary' === $action['variant'] ? 'secondary' : 'primary';
				// Only the first button is primary, regardless of what the caller
				// declared, so an email never shows two filled buttons.
				if (!empty($actions)) {
					$variant = 'secondary';
				}
				$actions[] = array(
					'label'   => (string) $action['label'],
					'url'     => (string) $action['url'],
					'variant' => $variant,
				);
				unset($index);
			}
			$data['actions'] = $actions;
		}

		if (isset($data['callout']) && is_array($data['callout'])) {
			if (empty($data['callout']['title']) && empty($data['callout']['body'])) {
				$data['callout'] = array();
			} else {
				$data['callout'] = array(
					'title' => isset($data['callout']['title']) ? (string) $data['callout']['title'] : '',
					'body'  => isset($data['callout']['body']) ? (string) $data['callout']['body'] : '',
					'tone'  => isset($data['callout']['tone']) ? (string) $data['callout']['tone'] : self::TONE_DANGER,
				);
			}
		}

		if (isset($data['status_pill']) && is_array($data['status_pill'])) {
			if (empty($data['status_pill']['label'])) {
				$data['status_pill'] = array();
			} else {
				$data['status_pill'] = array(
					'label' => (string) $data['status_pill']['label'],
					'tone'  => isset($data['status_pill']['tone']) ? (string) $data['status_pill']['tone'] : self::TONE_NEUTRAL,
				);
			}
		}

		if (isset($data['featured_image']) && is_array($data['featured_image'])) {
			if (empty($data['featured_image']['url'])) {
				$data['featured_image'] = array();
			} else {
				$data['featured_image'] = array(
					'url' => (string) $data['featured_image']['url'],
					'alt' => isset($data['featured_image']['alt']) ? (string) $data['featured_image']['alt'] : '',
				);
			}
		}

		if (isset($data['preview_link']) && is_array($data['preview_link'])) {
			if (empty($data['preview_link']['url']) || empty($data['preview_link']['label'])) {
				$data['preview_link'] = array();
			} else {
				$data['preview_link'] = array(
					'url'   => (string) $data['preview_link']['url'],
					'label' => (string) $data['preview_link']['label'],
				);
			}
		}

		if (isset($data['footer_link']) && is_array($data['footer_link'])) {
			if (empty($data['footer_link']['url']) || empty($data['footer_link']['label'])) {
				$data['footer_link'] = array();
			} else {
				$data['footer_link'] = array(
					'url'   => (string) $data['footer_link']['url'],
					'label' => (string) $data['footer_link']['label'],
				);
			}
		}

		return $data;
	}

	/**
	 * Keep only list entries that carry every required key, applying defaults.
	 *
	 * @param mixed                $rows     Candidate list.
	 * @param array<int, string>   $required Keys that must be present and non-empty.
	 * @param array<string, mixed> $defaults Optional keys with fallback values.
	 * @return array<int, array<string, string>>
	 */
	private static function filter_rows($rows, array $required, array $defaults = array()) {
		if (!is_array($rows)) {
			return array();
		}

		$clean = array();

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$skip = false;
			foreach ($required as $key) {
				// A literal "0" is a valid stat-tile value, so test for '' rather
				// than falsiness.
				if (!isset($row[$key]) || '' === (string) $row[$key]) {
					$skip = true;
					break;
				}
			}

			if ($skip) {
				continue;
			}

			$entry = array();
			foreach ($required as $key) {
				$entry[$key] = (string) $row[$key];
			}
			foreach ($defaults as $key => $fallback) {
				$entry[$key] = isset($row[$key]) && '' !== (string) $row[$key] ? (string) $row[$key] : $fallback;
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * Default value for every supported field.
	 *
	 * @return array<string, mixed>
	 */
	private static function schema() {
		return array(
			// Document-level.
			'notification_type' => '',
			'subject'           => '',
			'preheader'         => '',
			'site_name'         => '',

			// Header.
			'header_mode'     => self::HEADER_NONE,
			'header_tone'     => self::TONE_BRAND,
			'header_icon'     => '',
			'header_title'    => '',
			'header_subtitle' => '',
			'status_pill'     => array(),

			// Lede.
			'eyebrow'       => '',
			'eyebrow_tone'  => self::TONE_BRAND,
			'headline'      => '',
			'headline_size' => 'lg',

			// Body regions, rendered in this order when populated.
			'callout'        => array(),
			'featured_image' => array(),
			'meta_cells'     => array(),
			'stat_tiles'     => array(),
			'excerpt'        => '',
			'preview_blocks' => array(),
			'preview_link'   => array(),
			'item_caption'   => '',
			'item_rows'      => array(),
			'item_groups'    => array(),
			'body_text'      => '',

			// Footer.
			'actions'     => array(),
			'footer_link' => array(),
		);
	}
}
