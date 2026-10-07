<?php
/**
 * History Event read model / formatter.
 *
 * Gives schedule, dashboard, diagnostics, and activity-feed consumers a single
 * place to turn a raw aips_history_log row into a normalized, escaped array —
 * instead of each consumer independently decoding the serialized `details`
 * blob and guessing where event_type/event_status live.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Event_View
 */
final class AIPS_History_Event_View {

	/**
	 * Normalize a single raw log row.
	 *
	 * Reads event_type/event_status from the indexed columns when present and
	 * falls back to the serialized `details.input` block for rows written (or
	 * backfilled) before the columns existed. Values are canonicalized so both
	 * new and legacy rows present a single vocabulary, while the raw stored
	 * value is preserved under *_raw for callers that need it.
	 *
	 * @param object|array $log Raw DB row from aips_history_log.
	 * @return array Normalized, escaped entry.
	 */
	public static function from_log($log) {
		$log = (object) $log;

		$details = array();
		if (!empty($log->details)) {
			$decoded = json_decode($log->details, true);
			if (is_array($decoded)) {
				$details = $decoded;
			}
		}

		$input   = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();
		$context = isset($details['context']) && is_array($details['context']) ? $details['context'] : array();

		// Prefer indexed columns; fall back to the serialized input block.
		$raw_type = '';
		if (isset($log->event_type) && $log->event_type !== '') {
			$raw_type = (string) $log->event_type;
		} elseif (isset($input['event_type'])) {
			$raw_type = (string) $input['event_type'];
		}

		$raw_status = '';
		if (isset($log->event_status) && $log->event_status !== '') {
			$raw_status = (string) $log->event_status;
		} elseif (isset($input['event_status'])) {
			$raw_status = (string) $input['event_status'];
		}

		$canonical_type   = $raw_type !== '' ? AIPS_History_Event_Type::canonicalize($raw_type) : '';
		$canonical_status = $raw_status !== '' ? AIPS_History_Event_Status::canonicalize($raw_status) : '';

		$timestamp = isset($log->timestamp) ? absint($log->timestamp) : 0;

		return array(
			'id'                => isset($log->id) ? absint($log->id) : 0,
			// Raw Unix timestamp, kept for consumers that need to sort/compare.
			'timestamp'         => $timestamp,
			// Human-readable ("2 hours ago" / absolute) and ISO-8601 (for <time datetime>) variants.
			'timestamp_display' => $timestamp ? esc_html(AIPS_DateTime::formatRelativeOrAbsolute($timestamp)) : '',
			'timestamp_iso'     => $timestamp ? esc_attr(AIPS_DateTime::fromTimestamp($timestamp)->toIso8601()) : '',
			'log_type'          => isset($details['log_subtype']) ? esc_html($details['log_subtype']) : '',
			'history_type_id'   => isset($log->history_type_id) ? absint($log->history_type_id) : 0,
			'message'           => isset($details['message']) ? esc_html($details['message']) : '',
			// Canonical values are what consumers should key on.
			'event_type'        => esc_html($canonical_type),
			'event_status'      => esc_html($canonical_status),
			// Raw stored values preserved for debugging / migration verification.
			'event_type_raw'    => esc_html($raw_type),
			'event_status_raw'  => esc_html($raw_status),
			'context'           => $context,
			// Posts referenced by this event (e.g. generated via a schedule run),
			// resolved so consumers can render clickable titles + publish status
			// without re-querying the context blob themselves.
			'posts'             => self::resolve_context_posts($context),
		);
	}

	/**
	 * Resolve the post(s) referenced by a log entry's context into a display-ready list.
	 *
	 * `context.post_id` may be a single post ID or an array of post IDs
	 * (bulk/batch generation records one event covering several posts).
	 * Posts that no longer exist (deleted since the event was recorded) are
	 * silently skipped.
	 *
	 * @param array $context Decoded `details.context` block from the log row.
	 * @return array List of {id, title, status, status_label, edit_url, view_url, date_display}.
	 */
	private static function resolve_context_posts($context) {
		if (empty($context['post_id'])) {
			return array();
		}

		$ids = is_array($context['post_id']) ? $context['post_id'] : array($context['post_id']);
		$posts = array();

		foreach ($ids as $post_id) {
			$post_id = absint($post_id);
			if (!$post_id) {
				continue;
			}

			$post = get_post($post_id);
			if (!$post) {
				continue;
			}

			$title = get_the_title($post);
			$post_timestamp = get_post_time('U', true, $post);

			$posts[] = array(
				'id'           => $post_id,
				'title'        => esc_html($title !== '' ? $title : __('(no title)', 'ai-post-scheduler')),
				'status'       => esc_attr($post->post_status),
				'status_label' => esc_html(self::post_status_label($post->post_status)),
				'edit_url'     => esc_url((string) get_edit_post_link($post_id, 'raw')),
				'view_url'     => esc_url((string) get_permalink($post_id)),
				'date_display' => $post_timestamp ? esc_html(AIPS_DateTime::fromTimestamp((int) $post_timestamp)->toDisplay()) : '',
			);
		}

		return $posts;
	}

	/**
	 * Human-readable label for a post status (falls back to a titlecased slug
	 * for custom statuses without a registered status object).
	 *
	 * @param string $status Post status slug.
	 * @return string
	 */
	private static function post_status_label($status) {
		$status_object = get_post_status_object($status);
		if ($status_object && !empty($status_object->label)) {
			return $status_object->label;
		}
		return ucfirst(str_replace(array('-', '_'), ' ', $status));
	}

	/**
	 * Normalize a list of raw log rows.
	 *
	 * @param array $logs Raw DB rows.
	 * @return array List of normalized entries.
	 */
	public static function from_logs($logs) {
		$entries = array();
		foreach ((array) $logs as $log) {
			$entries[] = self::from_log($log);
		}
		return $entries;
	}
}
