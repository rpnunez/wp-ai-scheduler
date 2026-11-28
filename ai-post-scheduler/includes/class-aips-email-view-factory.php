<?php
/**
 * Email View Factory
 *
 * Turns a dispatched notification (type + payload + legacy token vars) into an
 * AIPS_Email_View describing the redesigned email.
 *
 * This is the layer the redesign needed most.  The previous email system passed
 * `build_details_html()` output — a raw dump of whatever keys the sender happened
 * to include — straight into the template, which cannot produce the design's
 * curated, ordered metadata grid.  Here each notification family declares exactly
 * which cells it wants, in order, with proper labels; any cell whose value is
 * missing drops out and the grid re-flows.
 *
 * Every notification type resolves to a composition:
 *
 *   - per-post events   → status banner, status pill, post metadata, content preview
 *   - alerts            → danger eyebrow, callout, diagnostic metadata
 *   - digests/summaries → brand bar or bare eyebrow, stat tiles, item rows
 *   - anything else     → the lede + body text + action fallback, so third-party
 *                         notification types still render in the new layout
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Email_View_Factory
 *
 * Usage:
 *
 *   $factory = new AIPS_Email_View_Factory();
 *   $view    = $factory->build('post_generated', array(
 *       'title'   => 'My Post',
 *       'message' => 'Template: News — Draft',
 *       'url'     => 'https://example.test/wp-admin/post.php?post=1&action=edit',
 *       'meta'    => array('post_id' => 1, 'post_status' => 'draft'),
 *       'vars'    => array('{{action_label}}' => 'Edit post'),
 *   ));
 */
class AIPS_Email_View_Factory {

	/**
	 * Average adult reading speed, in words per minute, used for the "N min" half
	 * of the design's `1,842 words · 7 min` length cell.
	 *
	 * @var int
	 */
	const WORDS_PER_MINUTE = 225;

	/**
	 * Maximum number of blocks shown in the content preview.
	 *
	 * The design clipped the preview with a gradient fade, which email clients
	 * cannot render; truncating to a few blocks conveys the same "there is more"
	 * signal and keeps the message small.
	 *
	 * @var int
	 */
	const PREVIEW_BLOCK_LIMIT = 3;

	/**
	 * Maximum characters per preview block.
	 *
	 * @var int
	 */
	const PREVIEW_BLOCK_CHARS = 220;

	/**
	 * Notification types that render as alerts (danger eyebrow + callout).
	 *
	 * @var array<int, string>
	 */
	private const ALERT_TYPES = array(
		'generation_failed',
		'quota_alert',
		'integration_error',
		'scheduler_error',
		'system_error',
		'circuit_breaker_opened',
		'rate_limit_reached',
	);

	/**
	 * Notification types that render as a rollup (stat tiles + item rows).
	 *
	 * @var array<int, string>
	 */
	private const ROLLUP_TYPES = array(
		'daily_digest',
		'weekly_summary',
		'monthly_report',
	);

	/**
	 * Notification types that render with the per-post status banner.
	 *
	 * @var array<int, string>
	 */
	private const POST_EVENT_TYPES = array(
		'post_generated',
		'post_ready_for_review',
		'manual_generation_completed',
		'partial_generation_completed',
		'post_rejected',
		'template_generated',
	);

	// -----------------------------------------------------------------------
	// Entry point
	// -----------------------------------------------------------------------

	/**
	 * Build the view for a dispatched notification.
	 *
	 * @param string $type    Notification type slug.
	 * @param array  $context Dispatch context: title, message, url, level, meta, vars.
	 * @return AIPS_Email_View
	 */
	public function build($type, array $context = array()) {
		$type    = (string) $type;
		$payload = isset($context['meta']) && is_array($context['meta']) ? $context['meta'] : array();
		$vars    = isset($context['vars']) && is_array($context['vars']) ? $context['vars'] : array();

		$base = AIPS_Email_View::create(array(
			'notification_type' => $type,
			'site_name'         => $this->site_name(),
			'footer_link'       => array(
				'url'   => AIPS_Admin_Menu_Helper::get_page_url('settings', array('tab' => 'notifications')),
				'label' => __('Notification settings', 'ai-post-scheduler'),
			),
			'actions'           => $this->build_actions($type, $context, $payload, $vars),
		));

		if (in_array($type, self::POST_EVENT_TYPES, true)) {
			$view = $this->compose_post_event($base, $type, $context, $payload);
		} elseif (in_array($type, self::ALERT_TYPES, true)) {
			$view = $this->compose_alert($base, $type, $context, $payload);
		} elseif (in_array($type, self::ROLLUP_TYPES, true)) {
			$view = $this->compose_rollup($base, $type, $context, $payload);
		} elseif ('summarized_digest' === $type) {
			$view = $this->compose_summarized_digest($base, $context, $payload);
		} else {
			$view = $this->compose_generic($base, $type, $context);
		}

		// Subject and preheader are derived last so a composition can influence them.
		$view = $view->with(array(
			'subject'   => $this->build_subject($type, $context, $view),
			'preheader' => $view->has('preheader') ? $view->get('preheader', '') : $this->text($this->value($context, 'message')),
		));

		/**
		 * Filter: aips_email_view
		 *
		 * Adjust the composed view before it is rendered — to add a metadata cell,
		 * change an action label, or re-tone a notification type.
		 *
		 * @since 3.8.0
		 * @param AIPS_Email_View $view    Composed view.
		 * @param string          $type    Notification type slug.
		 * @param array           $context Dispatch context.
		 */
		$filtered = apply_filters('aips_email_view', $view, $type, $context);

		return ($filtered instanceof AIPS_Email_View) ? $filtered : $view;
	}

	// -----------------------------------------------------------------------
	// Compositions
	// -----------------------------------------------------------------------

	/**
	 * Per-post events: status banner, pill, curated post metadata, content preview.
	 *
	 * Mirrors mockups 3a–3d.
	 *
	 * @param AIPS_Email_View $view    Base view.
	 * @param string          $type    Notification type.
	 * @param array           $context Dispatch context.
	 * @param array           $payload Sender payload.
	 * @return AIPS_Email_View
	 */
	private function compose_post_event(AIPS_Email_View $view, $type, array $context, array $payload) {
		$post_id = isset($payload['post_id']) ? absint($payload['post_id']) : 0;
		$post    = $post_id ? get_post($post_id) : null;

		$post_title = ($post && '' !== (string) $post->post_title)
			? $post->post_title
			: $this->text($this->value($context, 'title'));

		$post_status = isset($payload['post_status']) ? (string) $payload['post_status'] : (($post) ? (string) $post->post_status : '');

		$data = array(
			'header_mode'     => AIPS_Email_View::HEADER_BANNER,
			'header_tone'     => $this->header_tone_for($type),
			'header_icon'     => $this->header_icon_for($type),
			'header_title'    => $this->event_label($type),
			'header_subtitle' => $this->header_subtitle($post),
			'headline'        => $post_title,
			'headline_size'   => 'md',
			'meta_cells'      => $this->post_meta_cells($post, $payload),
		);

		if ('' !== $post_status) {
			$data['status_pill'] = array(
				'label' => $this->status_label($post_status, $payload),
				'tone'  => $this->status_tone($post_status),
			);
		}

		// Only post_generated carries the full post presentation; the other event
		// types are short "here is what happened" notices.
		if ('post_generated' === $type) {
			$data['featured_image'] = $this->featured_image($post_id, $post_title);
			$data['excerpt']        = $this->excerpt($post);
			$data['preview_blocks'] = $this->preview_blocks($post);

			$edit_url = $post_id ? (string) get_edit_post_link($post_id, 'raw') : '';

			if ('' !== $edit_url && !empty($data['preview_blocks'])) {
				$data['preview_link'] = array(
					'url'   => $edit_url,
					'label' => __('Read the full post in the editor', 'ai-post-scheduler'),
				);
			}
		}

		$data['preheader'] = $this->preheader_for_post_event($type, $post_title);

		return $view->with($data);
	}

	/**
	 * Alerts: danger eyebrow, callout carrying the reason, diagnostic metadata.
	 *
	 * Mirrors mockups 2c and 3f.
	 *
	 * @param AIPS_Email_View $view    Base view.
	 * @param string          $type    Notification type.
	 * @param array           $context Dispatch context.
	 * @param array           $payload Sender payload.
	 * @return AIPS_Email_View
	 */
	private function compose_alert(AIPS_Email_View $view, $type, array $context, array $payload) {
		$eyebrow = $this->event_label($type);

		// The design shows the subject of the alert appended to the eyebrow, e.g.
		// "QUOTA ALERT · EMBEDDING".
		$qualifier = '';
		foreach (array('request_type', 'schedule_name', 'component') as $key) {
			if (!empty($payload[$key]) && is_scalar($payload[$key])) {
				$qualifier = (string) $payload[$key];
				break;
			}
		}

		if ('' !== $qualifier) {
			$eyebrow .= ' · ' . $qualifier;
		}

		$error_message = isset($payload['error_message']) ? (string) $payload['error_message'] : '';

		return $view->with(array(
			'header_mode'   => AIPS_Email_View::HEADER_NONE,
			'eyebrow'       => $eyebrow,
			'eyebrow_tone'  => AIPS_Email_View::TONE_DANGER,
			'headline'      => $this->alert_headline($type, $context, $payload),
			'headline_size' => 'lg',
			'callout'       => array(
				'title' => '' !== $error_message ? $error_message : $this->event_label($type),
				'body'  => $this->alert_callout_body($payload),
				'tone'  => AIPS_Email_View::TONE_DANGER,
			),
			'meta_cells'    => $this->alert_meta_cells($type, $payload),
			'preheader'     => $this->text($this->value($context, 'message')),
		));
	}

	/**
	 * Rollups: eyebrow with the period, headline count, stat tiles, item rows.
	 *
	 * Mirrors mockups 2b and 3e.
	 *
	 * @param AIPS_Email_View $view    Base view.
	 * @param string          $type    Notification type.
	 * @param array           $context Dispatch context.
	 * @param array           $payload Sender payload.
	 * @return AIPS_Email_View
	 */
	private function compose_rollup(AIPS_Email_View $view, $type, array $context, array $payload) {
		$generated    = isset($payload['generated']) ? (int) $payload['generated'] : 0;
		$review_ready = isset($payload['review_ready']) ? (int) $payload['review_ready'] : 0;
		$errors       = isset($payload['errors']) ? (int) $payload['errors'] : 0;

		$tiles = array(
			array(
				'value' => number_format_i18n($generated),
				'label' => __('Generated', 'ai-post-scheduler'),
				'tone'  => AIPS_Email_View::TONE_NEUTRAL,
			),
			array(
				'value' => number_format_i18n($review_ready),
				'label' => __('Ready for review', 'ai-post-scheduler'),
				// The design tints this tile amber only when there is something
				// waiting; an empty queue is not a thing to flag.
				'tone'  => $review_ready > 0 ? AIPS_Email_View::TONE_WARNING : AIPS_Email_View::TONE_NEUTRAL,
			),
			array(
				'value' => number_format_i18n($errors),
				'label' => __('Errors', 'ai-post-scheduler'),
				'tone'  => $errors > 0 ? AIPS_Email_View::TONE_DANGER : AIPS_Email_View::TONE_NEUTRAL,
			),
		);

		$headline = sprintf(
			/* translators: 1: number of generated posts, 2: number of posts ready for review */
			_n(
				'%1$s post generated, %2$s ready for review',
				'%1$s posts generated, %2$s ready for review',
				$generated,
				'ai-post-scheduler'
			),
			number_format_i18n($generated),
			number_format_i18n($review_ready)
		);

		// The daily digest keeps the quiet brand bar (mockup 2b); the longer-period
		// summaries open straight on the eyebrow (mockup 3e).
		$header_mode = ('daily_digest' === $type)
			? AIPS_Email_View::HEADER_BRAND
			: AIPS_Email_View::HEADER_NONE;

		return $view->with(array(
			'header_mode'   => $header_mode,
			'eyebrow'       => $this->event_label($type) . ' · ' . $this->period_label($type, $payload),
			'eyebrow_tone'  => AIPS_Email_View::TONE_BRAND,
			'headline'      => $headline,
			'headline_size' => 'lg',
			'stat_tiles'    => $tiles,
			'item_caption'  => __('Generated', 'ai-post-scheduler'),
			'item_rows'     => $this->rollup_item_rows($payload),
			'preheader'     => $this->text($this->value($context, 'message')),
		));
	}

	/**
	 * Summarized digest: brand bar, count headline, items grouped by type.
	 *
	 * @param AIPS_Email_View $view    Base view.
	 * @param array           $context Dispatch context.
	 * @param array           $payload Sender payload (carries the queued items).
	 * @return AIPS_Email_View
	 */
	private function compose_summarized_digest(AIPS_Email_View $view, array $context, array $payload) {
		$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
		$count = isset($payload['count']) ? absint($payload['count']) : count($items);

		$type_registry = AIPS_Notification_Registry::get_type_registry();
		$grouped       = array();

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$item_type = isset($item['type']) ? (string) $item['type'] : 'unknown';
			$label     = isset($type_registry[$item_type]['label'])
				? $type_registry[$item_type]['label']
				: ucwords(str_replace('_', ' ', $item_type));

			if (!isset($grouped[$item_type])) {
				$grouped[$item_type] = array(
					'label' => $label,
					'rows'  => array(),
				);
			}

			$item_meta = isset($item['meta']) && is_array($item['meta']) ? $item['meta'] : array();

			$grouped[$item_type]['rows'][] = array(
				'title' => isset($item['title']) && '' !== (string) $item['title']
					? (string) $item['title']
					: __('Untitled', 'ai-post-scheduler'),
				'meta'  => isset($item_meta['post_status_label']) ? (string) $item_meta['post_status_label'] : '',
				'url'   => isset($item['url']) ? (string) $item['url'] : '',
			);
		}

		return $view->with(array(
			'header_mode'   => AIPS_Email_View::HEADER_BRAND,
			'eyebrow'       => __('Activity digest', 'ai-post-scheduler'),
			'eyebrow_tone'  => AIPS_Email_View::TONE_BRAND,
			'headline'      => sprintf(
				/* translators: %s: number of batched notifications */
				_n('%s update batched for your review', '%s updates batched for your review', $count, 'ai-post-scheduler'),
				number_format_i18n($count)
			),
			'headline_size' => 'lg',
			'item_groups'   => array_values($grouped),
			'preheader'     => sprintf(
				/* translators: %s: number of batched notifications */
				_n('%s notification from AI Post Scheduler.', '%s notifications from AI Post Scheduler.', $count, 'ai-post-scheduler'),
				number_format_i18n($count)
			),
		));
	}

	/**
	 * Fallback composition for types without a bespoke one.
	 *
	 * Keeps third-party notification types (and any built-in type added later)
	 * rendering in the redesigned layout instead of dropping to bare HTML.
	 *
	 * @param AIPS_Email_View $view    Base view.
	 * @param string          $type    Notification type.
	 * @param array           $context Dispatch context.
	 * @return AIPS_Email_View
	 */
	private function compose_generic(AIPS_Email_View $view, $type, array $context) {
		$level = (string) $this->value($context, 'level', 'info');

		$tone = AIPS_Email_View::TONE_BRAND;
		if ('error' === $level) {
			$tone = AIPS_Email_View::TONE_DANGER;
		} elseif ('warning' === $level) {
			$tone = AIPS_Email_View::TONE_WARNING;
		}

		return $view->with(array(
			'header_mode'   => AIPS_Email_View::HEADER_BRAND,
			'eyebrow'       => $this->event_label($type),
			'eyebrow_tone'  => $tone,
			'headline'      => $this->text($this->value($context, 'title')),
			'headline_size' => 'lg',
			'body_text'     => $this->text($this->value($context, 'message')),
		));
	}

	// -----------------------------------------------------------------------
	// Metadata cells
	// -----------------------------------------------------------------------

	/**
	 * Curated metadata cells for a post event.
	 *
	 * Order follows the design: Category, Generated, source (Template / Schedule /
	 * Author topic), Length.  Cells with no value are dropped by AIPS_Email_View.
	 *
	 * @param WP_Post|null $post    Post object, when resolvable.
	 * @param array        $payload Sender payload.
	 * @return array<int, array{label: string, value: string}>
	 */
	private function post_meta_cells($post, array $payload) {
		$cells = array();

		$cells[] = array(
			'label' => __('Category', 'ai-post-scheduler'),
			'value' => $post ? $this->category_label($post->ID) : '',
		);

		$cells[] = array(
			'label' => __('Generated', 'ai-post-scheduler'),
			'value' => $this->generated_value($post, $payload),
		);

		// The third cell names whichever source actually drove this generation.
		$source_cell = $this->source_cell($payload);
		if (null !== $source_cell) {
			$cells[] = $source_cell;
		}

		$cells[] = array(
			'label' => __('Length', 'ai-post-scheduler'),
			'value' => $post ? $this->length_value($post->post_content) : '',
		);

		return $cells;
	}

	/**
	 * The source cell: Template, Schedule, or Author topic, whichever applies.
	 *
	 * @param array $payload Sender payload.
	 * @return array{label: string, value: string}|null
	 */
	private function source_cell(array $payload) {
		$candidates = array(
			'template_name' => __('Template', 'ai-post-scheduler'),
			'schedule_name' => __('Schedule', 'ai-post-scheduler'),
			'topic'         => __('Author topic', 'ai-post-scheduler'),
			'topic_title'   => __('Author topic', 'ai-post-scheduler'),
		);

		foreach ($candidates as $key => $label) {
			if (!empty($payload[$key]) && is_scalar($payload[$key])) {
				return array(
					'label' => $label,
					'value' => (string) $payload[$key],
				);
			}
		}

		// `source_label` arrives pre-formatted as "Template: News" or
		// "Author Topic: ...". Split it so the label sits in the cell's label slot,
		// matching the design rather than repeating the prefix in the value.
		if (!empty($payload['source_label']) && is_scalar($payload['source_label'])) {
			$source_label = (string) $payload['source_label'];
			$parts        = explode(':', $source_label, 2);

			if (2 === count($parts) && '' !== trim($parts[1])) {
				return array(
					'label' => trim($parts[0]),
					'value' => trim($parts[1]),
				);
			}

			return array(
				'label' => __('Source', 'ai-post-scheduler'),
				'value' => $source_label,
			);
		}

		return null;
	}

	/**
	 * Diagnostic metadata cells for an alert.
	 *
	 * Each alert family declares the cells the design shows for it; anything the
	 * payload does not carry simply does not appear.
	 *
	 * @param string $type    Notification type.
	 * @param array  $payload Sender payload.
	 * @return array<int, array{label: string, value: string}>
	 */
	private function alert_meta_cells($type, array $payload) {
		if ('quota_alert' === $type) {
			// `ai_model` is the key the live emitter (AIPS_AI_Service) sends;
			// `model` is accepted too, since other callers and the preview use it.
			$map = array(
				'request_type' => __('Request type', 'ai-post-scheduler'),
				'ai_model'     => __('AI model', 'ai-post-scheduler'),
				'model'        => __('AI model', 'ai-post-scheduler'),
				'error_code'   => __('Error code', 'ai-post-scheduler'),
				'usage'        => __('Usage', 'ai-post-scheduler'),
			);
		} else {
			$map = array(
				'schedule_name'  => __('Schedule', 'ai-post-scheduler'),
				'resource_label' => __('Resource', 'ai-post-scheduler'),
				'topic'          => __('Topic', 'ai-post-scheduler'),
				'attempts'       => __('Attempts', 'ai-post-scheduler'),
				'next_retry'     => __('Next retry', 'ai-post-scheduler'),
				'error_code'     => __('Error code', 'ai-post-scheduler'),
			);
		}

		$cells = array();
		$seen  = array();

		foreach ($map as $key => $label) {
			if (!isset($payload[$key]) || !is_scalar($payload[$key]) || '' === (string) $payload[$key]) {
				continue;
			}

			// Two keys can share one label (e.g. ai_model / model); the first
			// populated one wins, so the grid never shows the same cell twice.
			if (isset($seen[$label])) {
				continue;
			}
			$seen[$label] = true;

			$cells[] = array(
				'label' => $label,
				'value' => $this->format_cell_value($key, $payload[$key]),
			);
		}

		return $cells;
	}

	/**
	 * Format a payload value for display in a metadata cell.
	 *
	 * @param string $key   Payload key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	private function format_cell_value($key, $value) {
		// Retry timestamps come through as epoch seconds; show the local clock time.
		if ('next_retry' === $key && is_numeric($value) && (int) $value > 0) {
			return AIPS_DateTime::fromTimestamp((int) $value)->toTimeDisplay();
		}

		return (string) $value;
	}

	// -----------------------------------------------------------------------
	// Actions
	// -----------------------------------------------------------------------

	/**
	 * Build the call-to-action buttons.
	 *
	 * The primary action reuses the URL and label the sender already resolved, so
	 * every existing link keeps working.  The secondary action is the design's
	 * per-type companion link.
	 *
	 * No button performs a state change: a URL sitting in an inbox (and in every
	 * forward of it) is not a safe place for an action token, so the design's
	 * "Retry now" resolves to the history entry, where the real retry control is.
	 *
	 * @param string $type    Notification type.
	 * @param array  $context Dispatch context.
	 * @param array  $payload Sender payload.
	 * @param array  $vars    Legacy token vars (source of the primary label).
	 * @return array<int, array{label: string, url: string, variant: string}>
	 */
	private function build_actions($type, array $context, array $payload, array $vars) {
		$actions = array();

		$primary_url   = (string) $this->value($context, 'url');
		$primary_label = isset($vars['{{action_label}}']) ? (string) $vars['{{action_label}}'] : '';

		if ('' === $primary_label) {
			$primary_label = $this->default_primary_label($type);
		}

		if ('' !== $primary_url) {
			$actions[] = array(
				'label'   => $primary_label,
				'url'     => $primary_url,
				'variant' => 'primary',
			);
		}

		$secondary = $this->secondary_action($type, $payload);

		if (null !== $secondary && $secondary['url'] !== $primary_url) {
			$secondary['variant'] = 'secondary';
			$actions[]            = $secondary;
		}

		return $actions;
	}

	/**
	 * Primary button label for types whose sender does not supply one.
	 *
	 * @param string $type Notification type.
	 * @return string
	 */
	private function default_primary_label($type) {
		switch ($type) {
			case 'post_generated':
				return __('Edit post', 'ai-post-scheduler');

			case 'post_ready_for_review':
				return __('Review post', 'ai-post-scheduler');

			case 'summarized_digest':
				return __('Open generated posts', 'ai-post-scheduler');

			case 'daily_digest':
			case 'weekly_summary':
			case 'monthly_report':
				return __('Open history', 'ai-post-scheduler');
		}

		return __('Open AI Post Scheduler', 'ai-post-scheduler');
	}

	/**
	 * The companion action for a notification type.
	 *
	 * @param string $type    Notification type.
	 * @param array  $payload Sender payload.
	 * @return array{label: string, url: string}|null
	 */
	private function secondary_action($type, array $payload) {
		switch ($type) {
			case 'post_generated':
				// "View post" for something published, otherwise the review queue:
				// a preview URL's nonce is tied to whoever created it, not to the
				// person opening the email later.
				$post_id = isset($payload['post_id']) ? absint($payload['post_id']) : 0;
				$status  = isset($payload['post_status']) ? (string) $payload['post_status'] : '';

				if ($post_id && 'publish' === $status) {
					$permalink = get_permalink($post_id);
					if ($permalink) {
						return array(
							'label' => __('View post', 'ai-post-scheduler'),
							'url'   => (string) $permalink,
						);
					}
				}

				return array(
					'label' => __('Open generated posts', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
				);

			case 'post_ready_for_review':
			case 'partial_generation_completed':
				return array(
					'label' => __('Open review queue', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
				);

			case 'quota_alert':
			case 'rate_limit_reached':
			case 'integration_error':
			case 'circuit_breaker_opened':
				return array(
					'label' => __('View history', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('history'),
				);

			case 'generation_failed':
			case 'system_error':
				return array(
					'label' => __('View history', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('history'),
				);

			case 'scheduler_error':
				return array(
					'label' => __('Open schedules', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
				);

			case 'daily_digest':
			case 'weekly_summary':
			case 'monthly_report':
				return array(
					'label' => __('Open generated posts', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
				);
		}

		return null;
	}

	// -----------------------------------------------------------------------
	// Derivations
	// -----------------------------------------------------------------------

	/**
	 * `1,842 words · 7 min` for a post body.
	 *
	 * @param string $content Raw post content.
	 * @return string Empty when the content has no words.
	 */
	private function length_value($content) {
		$plain = wp_strip_all_tags((string) $content);
		$words = str_word_count($plain);

		if ($words < 1) {
			return '';
		}

		$minutes = max(1, (int) ceil($words / self::WORDS_PER_MINUTE));

		return sprintf(
			/* translators: 1: formatted word count, 2: reading time in minutes */
			__('%1$s words · %2$d min', 'ai-post-scheduler'),
			number_format_i18n($words),
			$minutes
		);
	}

	/**
	 * The post's primary category (or first non-category term).
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when the post is uncategorised.
	 */
	private function category_label($post_id) {
		$terms = get_the_terms($post_id, 'category');

		if (is_wp_error($terms) || empty($terms)) {
			return '';
		}

		$names = array();

		foreach ($terms as $term) {
			if (isset($term->name)) {
				$names[] = $term->name;
			}
		}

		if (empty($names)) {
			return '';
		}

		// Two names fit the cell comfortably; beyond that, count the remainder.
		if (count($names) <= 2) {
			return implode(', ', $names);
		}

		return sprintf(
			/* translators: 1: first two category names, 2: number of remaining categories */
			__('%1$s +%2$d more', 'ai-post-scheduler'),
			implode(', ', array_slice($names, 0, 2)),
			count($names) - 2
		);
	}

	/**
	 * The `Generated` cell: how the post was created, and when.
	 *
	 * @param WP_Post|null $post    Post object.
	 * @param array        $payload Sender payload.
	 * @return string
	 */
	private function generated_value($post, array $payload) {
		$method = isset($payload['creation_method']) ? (string) $payload['creation_method'] : '';

		// An unknown method stays unlabelled rather than being reported as
		// "Scheduled": a manually generated post must never be described as a
		// scheduled one, and the timestamp alone is still useful.
		if ('manual' === $method) {
			$mode = __('Manual', 'ai-post-scheduler');
		} elseif ('scheduled' === $method) {
			$mode = __('Scheduled', 'ai-post-scheduler');
		} elseif ('' !== $method) {
			$mode = ucfirst(str_replace('_', ' ', $method));
		} else {
			$mode = '';
		}

		$time = $post ? $this->post_time($post) : '';

		if ('' === $mode) {
			return $time;
		}

		if ('' === $time) {
			return $mode;
		}

		return $mode . ' · ' . $time;
	}

	/**
	 * Local clock time for a post, in the site timezone.
	 *
	 * @param WP_Post|null $post Post object.
	 * @return string
	 */
	private function post_time($post) {
		if (!$post || empty($post->post_date_gmt) || '0000-00-00 00:00:00' === $post->post_date_gmt) {
			return '';
		}

		$timestamp = strtotime($post->post_date_gmt . ' UTC');

		if (!$timestamp) {
			return '';
		}

		return AIPS_DateTime::fromTimestamp((int) $timestamp)->toTimeDisplay();
	}

	/**
	 * The banner subtitle: `Site · Today, 2:42 PM`.
	 *
	 * Relative-day wording matches the design, which reads "Today, 2:42 PM" for a
	 * same-day event and "Tue, Nov 3, 3:03 PM" for anything older.
	 *
	 * @param WP_Post|null $post Post object.
	 * @return string
	 */
	private function header_subtitle($post) {
		$site = $this->site_name();

		$timestamp = 0;

		if ($post && !empty($post->post_date_gmt) && '0000-00-00 00:00:00' !== $post->post_date_gmt) {
			$timestamp = (int) strtotime($post->post_date_gmt . ' UTC');
		}

		if ($timestamp < 1) {
			$timestamp = AIPS_DateTime::now()->timestamp();
		}

		$moment = AIPS_DateTime::fromTimestamp($timestamp);

		if ($moment->isToday()) {
			$when = sprintf(
				/* translators: %s: clock time, e.g. "2:42 PM" */
				__('Today, %s', 'ai-post-scheduler'),
				$moment->toTimeDisplay()
			);
		} else {
			$when = $moment->toDisplay('D, M j') . ', ' . $moment->toTimeDisplay();
		}

		return '' !== $site ? $site . ' · ' . $when : $when;
	}

	/**
	 * Featured image descriptor for a post.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $post_title Used as the alt text.
	 * @return array{url: string, alt: string}
	 */
	private function featured_image($post_id, $post_title) {
		if (!$post_id || !has_post_thumbnail($post_id)) {
			return array();
		}

		$url = get_the_post_thumbnail_url($post_id, 'large');

		if (!$url) {
			return array();
		}

		return array(
			'url' => (string) $url,
			'alt' => (string) $post_title,
		);
	}

	/**
	 * The post excerpt, or a trimmed fallback from its content.
	 *
	 * @param WP_Post|null $post Post object.
	 * @return string
	 */
	private function excerpt($post) {
		if (!$post) {
			return '';
		}

		if ('' !== (string) $post->post_excerpt) {
			return $this->text($post->post_excerpt);
		}

		return $this->text(wp_trim_words(wp_strip_all_tags($post->post_content), 45));
	}

	/**
	 * Typed preview blocks from the opening of a post.
	 *
	 * Returns plain text with a block type, never markup, so generated content
	 * cannot inject HTML into an email.  The design's gradient fade is replaced by
	 * this truncation.
	 *
	 * @param WP_Post|null $post Post object.
	 * @return array<int, array{type: string, text: string}>
	 */
	private function preview_blocks($post) {
		if (!$post || '' === (string) $post->post_content) {
			return array();
		}

		// Normalise block/classic markup into one tag per line so headings can be
		// told apart from paragraphs without parsing the whole document.
		$content = (string) $post->post_content;
		$content = preg_replace('/<!--\s*\/?wp:[^>]*-->/', '', $content);

		if (!preg_match_all('/<(h[1-6]|p)\b[^>]*>(.*?)<\/\1>/is', $content, $matches, PREG_SET_ORDER)) {
			// No structural markup: fall back to the first paragraph of plain text.
			$plain = $this->text($content);

			if ('' === $plain) {
				return array();
			}

			return array(
				array(
					'type' => AIPS_Email_View::BLOCK_PARAGRAPH,
					'text' => $this->truncate($plain, self::PREVIEW_BLOCK_CHARS),
				),
			);
		}

		$blocks = array();

		foreach ($matches as $match) {
			if (count($blocks) >= self::PREVIEW_BLOCK_LIMIT) {
				break;
			}

			$text = $this->text($match[2]);

			if ('' === $text) {
				continue;
			}

			$is_heading = 'p' !== strtolower($match[1]);

			$blocks[] = array(
				'type' => $is_heading ? AIPS_Email_View::BLOCK_HEADING : AIPS_Email_View::BLOCK_PARAGRAPH,
				// Headings are short by nature; only body copy needs clamping.
				'text' => $is_heading ? $text : $this->truncate($text, self::PREVIEW_BLOCK_CHARS),
			);
		}

		return $blocks;
	}

	/**
	 * Item rows for a rollup email.
	 *
	 * @param array $payload Sender payload.
	 * @return array<int, array{title: string, meta: string, url: string}>
	 */
	private function rollup_item_rows(array $payload) {
		$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
		$rows  = array();

		foreach ($items as $item) {
			if (!is_array($item) || empty($item['title'])) {
				continue;
			}

			$meta = '';

			if (!empty($item['created_at'])) {
				$meta = AIPS_DateTime::fromTimestamp((int) $item['created_at'])->toTimeDisplay();
			}

			$rows[] = array(
				'title' => $this->text($item['title']),
				'meta'  => $meta,
				'url'   => isset($item['url']) ? (string) $item['url'] : '',
			);
		}

		return $rows;
	}

	// -----------------------------------------------------------------------
	// Labels
	// -----------------------------------------------------------------------

	/**
	 * Human-readable label for a notification type, from the central registry.
	 *
	 * @param string $type Notification type slug.
	 * @return string
	 */
	private function event_label($type) {
		$registry = AIPS_Notification_Registry::get_type_registry();

		if (isset($registry[$type]['label'])) {
			return (string) $registry[$type]['label'];
		}

		return ucwords(str_replace('_', ' ', (string) $type));
	}

	/**
	 * Glyph for the banner badge.
	 *
	 * @param string $type Notification type slug.
	 * @return string
	 */
	private function header_icon_for($type) {
		if ('post_rejected' === $type) {
			return '×';
		}

		if ('partial_generation_completed' === $type) {
			return '!';
		}

		return '✓';
	}

	/**
	 * Tone for the banner band.
	 *
	 * @param string $type Notification type slug.
	 * @return string
	 */
	private function header_tone_for($type) {
		if (in_array($type, array('post_rejected', 'partial_generation_completed'), true)) {
			return AIPS_Email_View::TONE_WARNING;
		}

		return AIPS_Email_View::TONE_BRAND;
	}

	/**
	 * Status pill label for a post status.
	 *
	 * @param string $status  Post status slug.
	 * @param array  $payload Sender payload (may carry a pre-formatted label).
	 * @return string
	 */
	private function status_label($status, array $payload) {
		if (!empty($payload['post_status_label']) && is_scalar($payload['post_status_label'])) {
			return (string) $payload['post_status_label'];
		}

		$object = get_post_status_object($status);

		if ($object && !empty($object->label)) {
			return (string) $object->label;
		}

		return ucfirst(str_replace('_', ' ', (string) $status));
	}

	/**
	 * Tone for a post status pill.
	 *
	 * @param string $status Post status slug.
	 * @return string
	 */
	private function status_tone($status) {
		switch ($status) {
			case 'publish':
				return AIPS_Email_View::TONE_SUCCESS;

			case 'draft':
			case 'pending':
				return AIPS_Email_View::TONE_WARNING;

			case 'trash':
				return AIPS_Email_View::TONE_DANGER;
		}

		return AIPS_Email_View::TONE_NEUTRAL;
	}

	/**
	 * Headline for an alert email.
	 *
	 * @param string $type    Notification type.
	 * @param array  $context Dispatch context.
	 * @param array  $payload Sender payload.
	 * @return string
	 */
	private function alert_headline($type, array $context, array $payload) {
		if ('quota_alert' === $type) {
			$request_type = !empty($payload['request_type']) ? (string) $payload['request_type'] : '';

			if ('' !== $request_type) {
				return sprintf(
					/* translators: %s: AI request type, e.g. "embedding" */
					__('AI requests are blocked for %s operations', 'ai-post-scheduler'),
					$request_type
				);
			}
		}

		if ('generation_failed' === $type && !empty($payload['resource_label'])) {
			return sprintf(
				/* translators: %s: name of the schedule, template, or topic that failed */
				__('%s could not generate a post', 'ai-post-scheduler'),
				(string) $payload['resource_label']
			);
		}

		if ('scheduler_error' === $type && !empty($payload['schedule_name'])) {
			return sprintf(
				/* translators: %s: schedule name */
				__('%s did not complete', 'ai-post-scheduler'),
				(string) $payload['schedule_name']
			);
		}

		return $this->text($this->value($context, 'title'));
	}

	/**
	 * Supporting detail line for an alert callout.
	 *
	 * @param array $payload Sender payload.
	 * @return string
	 */
	private function alert_callout_body(array $payload) {
		if (!empty($payload['error_detail']) && is_scalar($payload['error_detail'])) {
			return (string) $payload['error_detail'];
		}

		if (!empty($payload['next_retry'])) {
			$next = $this->format_cell_value('next_retry', $payload['next_retry']);

			if ('' !== $next) {
				return sprintf(
					/* translators: %s: clock time of the next retry */
					__('The next attempt is scheduled for %s.', 'ai-post-scheduler'),
					$next
				);
			}
		}

		return '';
	}

	/**
	 * Period label for a rollup eyebrow, e.g. `Nov 2–8, 2026`.
	 *
	 * @param string $type    Notification type.
	 * @param array  $payload Sender payload.
	 * @return string
	 */
	private function period_label($type, array $payload) {
		$now = AIPS_DateTime::now();

		if ('daily_digest' === $type) {
			return $now->toDisplay('M j, Y');
		}

		$window = isset($payload['window']) ? absint($payload['window']) : 0;

		if ($window < 1) {
			$window = ('monthly_report' === $type) ? 30 * DAY_IN_SECONDS : 7 * DAY_IN_SECONDS;
		}

		$start = AIPS_DateTime::fromTimestamp($now->timestamp() - $window);

		// Collapse the month name when both ends fall in the same month, matching
		// the design's "NOV 2–8, 2026".
		if ($start->toDisplay('Y-m') === $now->toDisplay('Y-m')) {
			return $start->toDisplay('M j') . '–' . $now->toDisplay('j, Y');
		}

		return $start->toDisplay('M j') . ' – ' . $now->toDisplay('M j, Y');
	}

	/**
	 * Preheader text for a post event.
	 *
	 * @param string $type       Notification type.
	 * @param string $post_title Post title.
	 * @return string
	 */
	private function preheader_for_post_event($type, $post_title) {
		switch ($type) {
			case 'post_ready_for_review':
				return sprintf(
					/* translators: %s: post title */
					__('"%s" is waiting for your review.', 'ai-post-scheduler'),
					$post_title
				);

			case 'post_generated':
				return sprintf(
					/* translators: %s: post title */
					__('AI Post Scheduler generated "%s".', 'ai-post-scheduler'),
					$post_title
				);
		}

		return $post_title;
	}

	/**
	 * Email subject line.
	 *
	 * Keeps the previous `[Site] Title` shape so existing filters and inbox rules
	 * continue to match.
	 *
	 * @param string          $type    Notification type.
	 * @param array           $context Dispatch context.
	 * @param AIPS_Email_View $view    Composed view.
	 * @return string
	 */
	private function build_subject($type, array $context, AIPS_Email_View $view) {
		$title = $this->text($this->value($context, 'title'));

		if ('' === $title) {
			$title = $view->get('headline', $this->event_label($type));
		}

		$site = $this->site_name();

		if ('' === $site) {
			return $title;
		}

		return sprintf('[%1$s] %2$s', $site, $title);
	}

	// -----------------------------------------------------------------------
	// Small helpers
	// -----------------------------------------------------------------------

	/**
	 * Read a dispatch-context value.
	 *
	 * @param array  $context Dispatch context.
	 * @param string $key     Key to read.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	private function value(array $context, $key, $default = '') {
		return isset($context[$key]) ? $context[$key] : $default;
	}

	/**
	 * Flatten a value to single-line plain text.
	 *
	 * Senders historically passed strings through `esc_html()` before they reached
	 * the template, so entities are decoded here — the templates escape on output,
	 * and double-escaping would show `&amp;#039;` to the reader.
	 *
	 * @param mixed $value Source value.
	 * @return string
	 */
	private function text($value) {
		if (!is_scalar($value)) {
			return '';
		}

		$text = wp_strip_all_tags((string) $value);
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

		return trim(preg_replace('/\s+/', ' ', $text));
	}

	/**
	 * Truncate to a character budget on a word boundary.
	 *
	 * @param string $text  Source text.
	 * @param int    $limit Maximum characters.
	 * @return string
	 */
	private function truncate($text, $limit) {
		$text  = (string) $text;
		$limit = absint($limit);

		if ($limit < 1 || mb_strlen($text) <= $limit) {
			return $text;
		}

		$clipped = mb_substr($text, 0, $limit);
		$space   = mb_strrpos($clipped, ' ');

		if (false !== $space && $space > ($limit / 2)) {
			$clipped = mb_substr($clipped, 0, $space);
		}

		return rtrim($clipped, " \t\n\r\0\x0B.,;:") . '…';
	}

	/**
	 * The site name, as shown in headers and the footer.
	 *
	 * @return string
	 */
	private function site_name() {
		return $this->text(get_bloginfo('name'));
	}
}
