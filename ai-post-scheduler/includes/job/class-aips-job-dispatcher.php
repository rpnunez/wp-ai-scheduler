<?php
/**
 * Job Dispatcher Service
 *
 * Handles scheduling WordPress cron jobs with retry logic and error handling.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Job_Dispatcher
 *
 * Core service for dispatching WordPress cron jobs with built-in retry,
 * duplicate detection, and comprehensive logging.
 */
class AIPS_Job_Dispatcher {

	/**
	 * @var AIPS_Resilience_Service Resilience service for retry logic
	 */
	private $resilience_service;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service_Interface History service
	 */
	private $history_service;

	/**
	 * @var AIPS_Job_Transport_Interface Resolved scheduling transport.
	 */
	private $transport;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Resilience_Service|null        $resilience_service Optional resilience service.
	 * @param AIPS_Logger|null                    $logger             Optional logger.
	 * @param AIPS_History_Service_Interface|null $history_service    Optional history service.
	 * @param AIPS_Job_Transport_Interface|null   $transport          Optional scheduling transport.
	 *                                                                Defaults to the resolved transport
	 *                                                                (Action Scheduler when available,
	 *                                                                otherwise WP-Cron).
	 */
	public function __construct(
		?AIPS_Resilience_Service $resilience_service = null,
		?AIPS_Logger $logger = null,
		?AIPS_History_Service_Interface $history_service = null,
		?AIPS_Job_Transport_Interface $transport = null
	) {
		$container = AIPS_Container::get_instance();

		$this->resilience_service = $resilience_service ?: new AIPS_Resilience_Service();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class)
			? $container->make(AIPS_Logger_Interface::class)
			: new AIPS_Logger());
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class)
			? $container->make(AIPS_History_Service_Interface::class)
			: new AIPS_History_Service());
		$this->transport = $transport ?: $this->resolve_transport($container);
	}

	/**
	 * Resolve the scheduling transport via the container, falling back to a
	 * fresh resolver when no binding is registered.
	 *
	 * @param AIPS_Container $container Container instance.
	 * @return AIPS_Job_Transport_Interface
	 */
	private function resolve_transport(AIPS_Container $container): AIPS_Job_Transport_Interface {
		if ($container->has(AIPS_Job_Transport_Interface::class)) {
			return $container->make(AIPS_Job_Transport_Interface::class);
		}

		$resolver = $container->has(AIPS_Job_Transport_Resolver::class)
			? $container->make(AIPS_Job_Transport_Resolver::class)
			: new AIPS_Job_Transport_Resolver();

		return $resolver->resolve();
	}

	/**
	 * Get the active scheduling transport.
	 *
	 * @return AIPS_Job_Transport_Interface
	 */
	public function get_transport(): AIPS_Job_Transport_Interface {
		return $this->transport;
	}

	/**
	 * Dispatch a single job with retry logic.
	 *
	 * Attempts to schedule the job up to max_attempts times with exponential backoff.
	 *
	 * @param AIPS_Job_Definition $job         Job to dispatch.
	 * @param array               $retry_options {
	 *     Optional. Retry configuration.
	 *
	 *     @type int  $max_attempts   Maximum retry attempts (default: 3, clamped 1-5).
	 *     @type int  $initial_delay  Initial delay in seconds (default: 1).
	 *     @type bool $use_backoff    Use exponential backoff (default: true).
	 *     @type bool $log_to_history Log failures to history (default: true).
	 * }
	 * @return bool True if successfully scheduled, false otherwise.
	 */
	public function dispatch(AIPS_Job_Definition $job, array $retry_options = array()): bool {
		// Check if already scheduled to avoid duplicates
		$existing_timestamp = $this->get_scheduled_timestamp($job);
		if (false !== $existing_timestamp) {
			$this->logger->log(
				sprintf(
					'Job already scheduled: hook=%s, existing_fire_at=%d, requested_fire_at=%d',
					$job->get_hook(),
					$existing_timestamp,
					$job->get_fire_at()
				),
				'info',
				$job->get_metadata()
			);
			return true;
		}

		// Parse retry options
		$max_attempts = isset($retry_options['max_attempts'])
			? max(1, min(5, (int) $retry_options['max_attempts']))
			: 3;

		$initial_delay = isset($retry_options['initial_delay'])
			? max(1, (int) $retry_options['initial_delay'])
			: 1;

		$use_backoff = isset($retry_options['use_backoff'])
			? (bool) $retry_options['use_backoff']
			: true;

		$log_to_history = isset($retry_options['log_to_history'])
			? (bool) $retry_options['log_to_history']
			: true;

		// Track attempt number and last error for logging
		$attempt_num = 0;
		$last_error = null;

		$transport_name = $this->transport->get_name();

		$success = $this->resilience_service->retry_with_backoff(
			function() use ($job, $transport_name, &$attempt_num, &$last_error) {
				$attempt_num++;

				$result = $this->transport->schedule($job);

				if ($result === true) {
					if ($attempt_num > 1) {
						$this->logger->log(
							sprintf(
								'Job scheduled on attempt %d: hook=%s, transport=%s',
								$attempt_num,
								$job->get_hook(),
								$transport_name
							),
							'info',
							$job->get_metadata()
						);
					}
					return true;
				}

				// Log the failure
				if (is_wp_error($result)) {
					$error_msg = sprintf(
						'%s: %s',
						$result->get_error_code(),
						$result->get_error_message()
					);
				} else {
					$error_msg = sprintf(
						'Unknown error (%s transport returned non-true)',
						$transport_name
					);
				}
				$last_error = $error_msg;

				$this->logger->log(
					sprintf(
						'Attempt %d: Failed to schedule job: hook=%s, transport=%s, error=%s',
						$attempt_num,
						$job->get_hook(),
						$transport_name,
						$error_msg
					),
					'warning',
					array_merge($job->get_metadata(), array('attempt' => $attempt_num))
				);

				return false;
			},
			$max_attempts,
			$initial_delay,
			$use_backoff
		);

		// Log to history if requested and dispatch failed
		if (!$success && $log_to_history) {
			$this->log_dispatch_failure($job, $max_attempts, $last_error);
		}

		return $success;
	}

	/**
	 * Dispatch multiple jobs in batch.
	 *
	 * @param AIPS_Job_Definition[] $jobs          Jobs to dispatch.
	 * @param array                 $retry_options Retry options (same as dispatch()).
	 * @return AIPS_Dispatch_Summary Summary of dispatch results.
	 */
	public function dispatch_batch(array $jobs, array $retry_options = array()): AIPS_Dispatch_Summary {
		$scheduled_count = 0;
		$failed_count = 0;

		foreach ($jobs as $job) {
			if (!($job instanceof AIPS_Job_Definition)) {
				$failed_count++;
				continue;
			}

			if ($this->dispatch($job, $retry_options)) {
				$scheduled_count++;
			} else {
				$failed_count++;
			}
		}

		return new AIPS_Dispatch_Summary(
			$scheduled_count,
			$failed_count,
			count($jobs)
		);
	}

	/**
	 * Check if a job is already scheduled.
	 *
	 * @param AIPS_Job_Definition $job Job to check.
	 * @return bool True if already scheduled.
	 */
	public function is_scheduled(AIPS_Job_Definition $job): bool {
		return $this->get_scheduled_timestamp($job) !== false;
	}

	/**
	 * Get the timestamp of an already scheduled matching job.
	 *
	 * @param AIPS_Job_Definition $job Job to check.
	 * @return int|false Existing timestamp when found, false otherwise.
	 */
	private function get_scheduled_timestamp(AIPS_Job_Definition $job): int|false {
		return $this->transport->next_scheduled($job);
	}

	/**
	 * Log dispatch failure to history.
	 *
	 * @param AIPS_Job_Definition $job          Failed job.
	 * @param int                 $max_attempts  Maximum attempts made.
	 * @param string|null         $last_error    Last error message.
	 */
	private function log_dispatch_failure(
		AIPS_Job_Definition $job,
		int $max_attempts,
		?string $last_error
	): void {
		$metadata = $job->get_metadata();
		$history_type = isset($metadata['history_type']) ? $metadata['history_type'] : 'job_dispatch';

		$history_context = array();
		if (!empty($metadata['context_id'])) {
			$history_context[isset($metadata['context_id_name']) ? $metadata['context_id_name'] : 'id'] = $metadata['context_id'];
		}
		$history_context['creation_method'] = $history_type;

		$history = $this->history_service->create($history_type, $history_context);

		if ($history) {
			$failure_message = sprintf(
				'Failed to dispatch %s job after %d attempts: %s',
				$job->get_job_type(),
				$max_attempts,
				$last_error ?: 'Unknown error'
			);

			$history->record(
				'dispatch_failed',
				$failure_message,
				array(
					'event_type'   => 'job_dispatch_failed',
					'event_status' => 'failed',
				),
				null,
				array(
					'job_type'      => $job->get_job_type(),
					'hook'          => $job->get_hook(),
					'error'         => $last_error,
					'max_attempts'  => $max_attempts,
					'correlation_id' => $job->get_correlation_id(),
					'transport'     => $this->transport->get_name(),
				)
			);

			$this->history_service->update_history_record(
				$history->get_id(),
				array(
					'status' => 'failed',
					'error_message' => $failure_message,
					'completed_at' => AIPS_DateTime::now()->timestamp(),
				)
			);
		}
	}
}
