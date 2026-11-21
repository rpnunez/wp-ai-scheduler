<?php
/**
 * AIPS Job Context — runtime execution context passed to background job handlers.
 *
 * Provides time/memory execution budgeting guards, cursor state, and logging.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Context {

	/** @var AIPS_Job_Record */
	private $job_record;

	/** @var AIPS_Logger */
	private $logger;

	/** @var float */
	private $start_time;

	/** @var int */
	private $time_budget_seconds;

	/** @var int */
	private $memory_limit_bytes;

	/**
	 * @param AIPS_Job_Record $job_record
	 * @param AIPS_Logger|null $logger
	 * @param int|null $time_budget_seconds Defaults to 20 seconds or filter.
	 */
	public function __construct( AIPS_Job_Record $job_record, ?AIPS_Logger $logger = null, ?int $time_budget_seconds = null ) {
		$this->job_record          = $job_record;
		$this->logger              = $logger ?: new AIPS_Logger();
		$this->start_time          = microtime( true );
		$this->time_budget_seconds = ( null !== $time_budget_seconds && $time_budget_seconds > 0 )
			? $time_budget_seconds
			: (int) apply_filters( 'aips_job_time_budget_seconds', 20, $job_record->get_job_type() );
		$this->memory_limit_bytes  = $this->calculate_memory_limit();
	}

	public function get_job(): AIPS_Job_Record {
		return $this->job_record;
	}

	public function get_job_id(): string {
		return $this->job_record->get_job_id();
	}

	public function get_job_type(): string {
		return $this->job_record->get_job_type();
	}

	public function get_payload(): array {
		return $this->job_record->get_payload();
	}

	public function get_cursor_id(): int {
		return $this->job_record->get_cursor_id();
	}

	public function get_logger(): AIPS_Logger {
		return $this->logger;
	}

	/**
	 * Log a message with job context.
	 *
	 * @param string $message
	 * @param string $level
	 */
	public function log( string $message, string $level = 'info' ): void {
		$prefix = sprintf( '[Job %s:%s] ', $this->job_record->get_job_type(), substr( $this->job_record->get_job_id(), 0, 8 ) );
		$this->logger->log( $prefix . $message, $level );
	}

	/**
	 * Seconds elapsed since context initialization.
	 *
	 * @return float
	 */
	public function get_elapsed_seconds(): float {
		return microtime( true ) - $this->start_time;
	}

	/**
	 * Check whether the runner should yield (due to time or memory limit).
	 *
	 * @param int $reserve_seconds Minimum seconds required to continue another item. Default 3s.
	 * @return bool True if execution budget is exhausted and slice should yield.
	 */
	public function should_yield( int $reserve_seconds = 3 ): bool {
		$elapsed = $this->get_elapsed_seconds();
		if ( ( $elapsed + $reserve_seconds ) >= $this->time_budget_seconds ) {
			return true;
		}

		if ( $this->memory_limit_bytes > 0 ) {
			$current_memory = memory_get_usage( true );
			if ( $current_memory >= ( $this->memory_limit_bytes * 0.85 ) ) {
				return true;
			}
		}

		return false;
	}

	private function calculate_memory_limit(): int {
		$memory_limit = ini_get( 'memory_limit' );
		if ( ! $memory_limit || '-1' === $memory_limit ) {
			return 0;
		}

		$value = (int) $memory_limit;
		$unit  = strtolower( substr( trim( $memory_limit ), -1 ) );

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}
}
