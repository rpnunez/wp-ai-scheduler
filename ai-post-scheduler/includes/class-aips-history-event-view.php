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

		return array(
			'id'               => isset($log->id) ? absint($log->id) : 0,
			'timestamp'        => isset($log->timestamp) ? esc_html($log->timestamp) : '',
			'log_type'         => isset($details['log_subtype']) ? esc_html($details['log_subtype']) : '',
			'history_type_id'  => isset($log->history_type_id) ? absint($log->history_type_id) : 0,
			'message'          => isset($details['message']) ? esc_html($details['message']) : '',
			// Canonical values are what consumers should key on.
			'event_type'       => esc_html($canonical_type),
			'event_status'     => esc_html($canonical_status),
			// Raw stored values preserved for debugging / migration verification.
			'event_type_raw'   => esc_html($raw_type),
			'event_status_raw' => esc_html($raw_status),
			'context'          => $context,
		);
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
