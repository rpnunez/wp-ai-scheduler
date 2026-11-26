<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Call_Counter
 *
 * Lifetime tally of AI calls sent to each provider, broken down by call type
 * (text, json, image, embedding) and failures. History logs record that an AI
 * request happened but not which provider served it, so this counter is the
 * only per-provider source. It starts counting when first incremented; the
 * `since` timestamp is stored so the UI can say so.
 *
 * Stored in one non-autoloaded option. The read-modify-write is not atomic, so
 * truly concurrent calls can occasionally drop an increment — acceptable for a
 * diagnostics figure.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */
class AIPS_AI_Call_Counter {

	/**
	 * Option name holding the counter payload.
	 */
	const OPTION = 'aips_ai_call_counters';

	/**
	 * Record one AI call.
	 *
	 * @param string $provider_id Provider id (e.g. "wp_ai_client", "meow").
	 * @param string $type        Call type (text, json, image, embedding).
	 * @param bool   $success     Whether the call succeeded.
	 * @return void
	 */
	public static function increment($provider_id, $type, $success = true) {
		$provider_id = sanitize_key((string) $provider_id);
		$type        = sanitize_key((string) $type);

		if ($provider_id === '' || $type === '') {
			return;
		}

		$data   = self::get_counts();
		$exists = get_option(self::OPTION, null) !== null;

		if (empty($data['since'])) {
			$data['since'] = time();
		}

		if (!isset($data['providers'][$provider_id]) || !is_array($data['providers'][$provider_id])) {
			$data['providers'][$provider_id] = array(
				'total'   => 0,
				'failed'  => 0,
				'by_type' => array(),
			);
		}

		$entry = &$data['providers'][$provider_id];
		$entry['total'] = (int) $entry['total'] + 1;
		$entry['by_type'][$type] = (isset($entry['by_type'][$type]) ? (int) $entry['by_type'][$type] : 0) + 1;

		if (!$success) {
			$entry['failed'] = (int) $entry['failed'] + 1;
		}
		unset($entry);

		if ($exists) {
			update_option(self::OPTION, $data, false);
		} else {
			add_option(self::OPTION, $data, '', false);
		}
	}

	/**
	 * Get the stored counts.
	 *
	 * @return array{since:int, providers:array<string, array{total:int, failed:int, by_type:array<string,int>}>}
	 */
	public static function get_counts() {
		$data = get_option(self::OPTION, array());

		if (!is_array($data)) {
			$data = array();
		}

		return array(
			'since'     => isset($data['since']) ? (int) $data['since'] : 0,
			'providers' => isset($data['providers']) && is_array($data['providers']) ? $data['providers'] : array(),
		);
	}
}
