<?php
/**
 * Job Groups
 *
 * Central definition of Action Scheduler queue-group names. Grouping is an
 * Action Scheduler concept with no direct WP-Cron equivalent; centralizing the
 * names here keeps them out of feature controllers and cron handlers and lets
 * a job type map to a stable group regardless of the selected transport.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Job_Groups
 *
 * Canonical Action Scheduler group names.
 */
final class AIPS_Job_Groups {

	/**
	 * Default group for plugin-owned background jobs.
	 */
	const DEFAULT_GROUP = 'ai-post-scheduler';

	/**
	 * Author-embeddings processing group.
	 */
	const EMBEDDINGS = 'aips-embeddings';

	/**
	 * Internal-links indexing group.
	 */
	const INTERNAL_LINKS = 'aips-internal-links';

	/**
	 * Normalize a group value, falling back to the default group when empty.
	 *
	 * @param string $group Requested group name.
	 * @return string Non-empty group name.
	 */
	public static function normalize(string $group): string {
		$group = trim($group);
		return '' !== $group ? $group : self::DEFAULT_GROUP;
	}
}
