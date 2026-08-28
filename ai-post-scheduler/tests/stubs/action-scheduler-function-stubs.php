<?php
/**
 * In-memory Action Scheduler function stubs for unit tests.
 *
 * Action Scheduler is not loaded in the test suite, so these lightweight
 * stand-ins let AIPS_Action_Scheduler_Transport be exercised in isolation.
 * Require this file only inside process-isolated tests
 * (@runInSeparateProcess + @preserveGlobalState disabled) so the global
 * function definitions never leak into the rest of the suite and change
 * transport resolution for other tests.
 *
 * Scheduled actions are recorded in $GLOBALS['_aips_as_store'].
 *
 * @package AI_Post_Scheduler
 */

if (!isset($GLOBALS['_aips_as_store'])) {
	$GLOBALS['_aips_as_store'] = array();
}

// When set to true, as_schedule_single_action() simulates a storage failure
// (returns 0 and stores nothing).
if (!isset($GLOBALS['_aips_as_fail'])) {
	$GLOBALS['_aips_as_fail'] = false;
}

// When set to true, as_schedule_single_action() stores the action but returns a
// falsy id, simulating Action Scheduler versions/paths that succeed silently.
if (!isset($GLOBALS['_aips_as_silent_success'])) {
	$GLOBALS['_aips_as_silent_success'] = false;
}

if (!function_exists('as_schedule_single_action')) {
	/**
	 * @return int Pseudo action ID (0 on simulated failure or silent success).
	 */
	function as_schedule_single_action($timestamp, $hook, $args = array(), $group = '', $unique = false, $priority = 10) {
		if (!empty($GLOBALS['_aips_as_fail'])) {
			return 0;
		}

		$GLOBALS['_aips_as_store'][] = array(
			'timestamp' => (int) $timestamp,
			'hook'      => (string) $hook,
			'args'      => $args,
			'group'     => (string) $group,
		);

		if (!empty($GLOBALS['_aips_as_silent_success'])) {
			return 0;
		}

		return count($GLOBALS['_aips_as_store']);
	}
}

if (!function_exists('as_next_scheduled_action')) {
	/**
	 * @return int|false Timestamp of the next matching action, or false.
	 */
	function as_next_scheduled_action($hook, $args = null, $group = '') {
		foreach ($GLOBALS['_aips_as_store'] as $action) {
			if ($action['hook'] !== (string) $hook) {
				continue;
			}
			if (null !== $args && $action['args'] !== $args) {
				continue;
			}
			if ('' !== (string) $group && $action['group'] !== (string) $group) {
				continue;
			}
			return $action['timestamp'];
		}

		return false;
	}
}

if (!function_exists('as_unschedule_action')) {
	/**
	 * @return int|null Removed action index, or null when nothing matched.
	 */
	function as_unschedule_action($hook, $args = array(), $group = '') {
		foreach ($GLOBALS['_aips_as_store'] as $index => $action) {
			if ($action['hook'] !== (string) $hook) {
				continue;
			}
			if (array() !== $args && $action['args'] !== $args) {
				continue;
			}
			if ('' !== (string) $group && $action['group'] !== (string) $group) {
				continue;
			}
			unset($GLOBALS['_aips_as_store'][$index]);
			return $index;
		}

		return null;
	}
}
