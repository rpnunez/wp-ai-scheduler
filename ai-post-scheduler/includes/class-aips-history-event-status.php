<?php
/**
 * History Event Status catalog.
 *
 * Canonical vocabulary for the lifecycle status carried by a history event.
 * Producers should emit only the canonical values defined here; historical
 * synonyms are mapped back to their canonical form so consumers see one
 * stable set of statuses regardless of when a row was written.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Event_Status
 *
 * Defines the canonical statuses an event may carry and resolves legacy
 * synonyms to their canonical value.
 */
final class AIPS_History_Event_Status {

	/**
	 * Work has been queued but has not started.
	 */
	const PENDING = 'pending';

	/**
	 * Work is actively in progress.
	 */
	const RUNNING = 'running';

	/**
	 * Work finished and every intended outcome succeeded.
	 */
	const SUCCESS = 'success';

	/**
	 * Work finished but only some intended outcomes succeeded.
	 */
	const PARTIAL = 'partial';

	/**
	 * Work finished with an error; nothing succeeded.
	 */
	const FAILED = 'failed';

	/**
	 * Work was cancelled before completion.
	 */
	const CANCELLED = 'cancelled';

	/**
	 * Work was intentionally not performed for this subject.
	 */
	const SKIPPED = 'skipped';

	/**
	 * Legacy status synonyms mapped to their canonical value.
	 *
	 * Kept so that historical rows written before the contract existed still
	 * resolve to a canonical status when read back.
	 *
	 * @var array<string, string>
	 */
	private static $aliases = array(
		'complete'   => self::SUCCESS,
		'completed'  => self::SUCCESS,
		'succeeded'  => self::SUCCESS,
		'ok'         => self::SUCCESS,
		'draft'      => self::SUCCESS,
		'processing' => self::RUNNING,
		'started'    => self::RUNNING,
		'in_progress' => self::RUNNING,
		'error'      => self::FAILED,
		'failure'    => self::FAILED,
		'canceled'   => self::CANCELLED,
		'skip'       => self::SKIPPED,
	);

	/**
	 * Return the canonical list of statuses.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::PENDING,
			self::RUNNING,
			self::SUCCESS,
			self::PARTIAL,
			self::FAILED,
			self::CANCELLED,
			self::SKIPPED,
		);
	}

	/**
	 * Determine whether a value is already a canonical status.
	 *
	 * @param string $status Status value.
	 * @return bool
	 */
	public static function is_canonical($status) {
		return in_array((string) $status, self::all(), true);
	}

	/**
	 * Resolve any known status (canonical or legacy synonym) to its canonical value.
	 *
	 * Unknown values are returned lowercased and trimmed but otherwise unchanged
	 * so that no data is silently discarded.
	 *
	 * @param string $status Raw status value.
	 * @return string Canonical status value.
	 */
	public static function canonicalize($status) {
		$normalized = strtolower(trim((string) $status));

		if ($normalized === '') {
			return '';
		}

		if (self::is_canonical($normalized)) {
			return $normalized;
		}

		if (isset(self::$aliases[$normalized])) {
			return self::$aliases[$normalized];
		}

		return $normalized;
	}

	/**
	 * Return every raw synonym (canonical + legacy aliases) that resolves to the
	 * same canonical status as the input.
	 *
	 * Used by read paths so a filter on a canonical status ("success") also
	 * matches rows persisted with a legacy synonym ("complete", "draft").
	 *
	 * @param string $status Canonical or legacy status value.
	 * @return string[] Unique list of stored values to match.
	 */
	public static function synonyms_for($status) {
		$canonical = self::canonicalize($status);

		if ($canonical === '') {
			return array();
		}

		$synonyms = array($canonical);
		foreach (self::$aliases as $alias => $target) {
			if ($target === $canonical) {
				$synonyms[] = $alias;
			}
		}

		return array_values(array_unique($synonyms));
	}

	/**
	 * Determine whether a status represents a terminal (lifecycle-ending) outcome.
	 *
	 * @param string $status Status value (canonical or legacy).
	 * @return bool
	 */
	public static function is_terminal($status) {
		$canonical = self::canonicalize($status);

		return in_array(
			$canonical,
			array(
				self::SUCCESS,
				self::PARTIAL,
				self::FAILED,
				self::CANCELLED,
				self::SKIPPED,
			),
			true
		);
	}
}
