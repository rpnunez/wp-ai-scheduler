<?php
/**
 * Ability Background Process
 *
 * Shared base for the background processes that run WordPress Abilities
 * (workflow runs and post-generation enrichment). They are AI-backed in the
 * sense that matters for the shared tick loop: a slice can call providers, so
 * the loop meters the calls it makes and honours the per-run AI budget.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

abstract class AIPS_Ability_Background_Process extends AIPS_Managed_Background_Process {

	/**
	 * @inheritDoc
	 */
	public function uses_ai(): bool {
		return true;
	}

	/**
	 * Abilities invoked so far in this request, whichever service instance made
	 * the call. The tick loop reads the delta across a slice.
	 *
	 * @return int
	 */
	protected function count_ai_calls(): int {
		return AIPS_Ability_Service::get_invocation_count();
	}

	/**
	 * Make sure the process is working through newly queued items.
	 *
	 * Starts a run when none is open; otherwise re-queues the tick of the open
	 * run if its cron event went missing. A paused run is left paused. Having
	 * nothing to do, or a run that is already open, counts as success.
	 *
	 * @return true|WP_Error
	 */
	public function kick() {
		if ($this->repository->get_open($this->get_key())) {
			$this->ensure_scheduled();

			return true;
		}

		$result = $this->start();

		if (is_wp_error($result) && !in_array($result->get_error_code(), array('aips_bg_already_open', 'aips_bg_nothing_to_do'), true)) {
			return $result;
		}

		return true;
	}
}
