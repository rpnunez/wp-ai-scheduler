<?php
/**
 * Link Index Scan Process
 *
 * Exposes the Link Report's background scan (AIPS_Link_Index_Service) so it
 * shows up, and can be paused or stopped, alongside the other background
 * processes. The scan keeps its own job store and tick chain; this adapter
 * maps its state onto the shared snapshot. It makes no AI calls.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Link_Index_Scan_Process extends AIPS_Background_Process_Base {

	const KEY = 'link_index_scan';

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $service;

	/**
	 * @param AIPS_Link_Index_Service|null $service Link index service.
	 */
	public function __construct(?AIPS_Link_Index_Service $service = null) {
		$this->service = $service ?: AIPS_Container::get_instance()->make(AIPS_Link_Index_Service::class);
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Link Index Scan', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Reads the links in published posts to build the Link Report. Parses post HTML only; no AI calls.', 'ai-post-scheduler');
	}

	/**
	 * @inheritDoc
	 */
	public function get_snapshot(): array {
		$scan = $this->service->get_backfill_status();

		if (!$scan) {
			return $this->make_snapshot(array('status' => self::STATUS_IDLE));
		}

		$map = array(
			AIPS_Bulk_Batch_Job_Store::STATUS_PENDING    => 'running',
			AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING => 'running',
			AIPS_Link_Index_Service::STATUS_PAUSED       => 'paused',
			AIPS_Bulk_Batch_Job_Store::STATUS_COMPLETED  => 'completed',
			AIPS_Bulk_Batch_Job_Store::STATUS_FAILED     => 'failed',
			AIPS_Link_Index_Service::STATUS_CANCELLED    => 'cancelled',
		);
		$status = isset($map[$scan['status']]) ? $map[$scan['status']] : self::STATUS_IDLE;

		// A finished scan is history, not an open run: report idle plus the last result.
		if (in_array($status, array('completed', 'failed', 'cancelled'), true)) {
			return $this->make_snapshot(array(
				'status'      => self::STATUS_IDLE,
				'last_status' => $status,
				'processed'   => $scan['processed'],
				'total'       => $scan['total'],
			));
		}

		return $this->make_snapshot(array(
			'status'    => $status,
			'processed' => $scan['processed'],
			'total'     => $scan['total'],
		));
	}

	/**
	 * @inheritDoc
	 */
	public function start(array $options = array()) {
		$result = $this->service->start_backfill(AIPS_Link_Index_Service::MODE_MISSING);

		if (is_wp_error($result)) {
			return $result;
		}

		return $this->get_snapshot();
	}

	public function pause(): bool {
		return $this->service->pause_backfill();
	}

	public function resume(): bool {
		return $this->service->resume_backfill();
	}

	public function cancel(): bool {
		return $this->service->cancel_backfill();
	}

	/**
	 * Re-queue a running scan whose tick went missing (called on status polls).
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		$this->service->ensure_scan_scheduled();
	}
}
