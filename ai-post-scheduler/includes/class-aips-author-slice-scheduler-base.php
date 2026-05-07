<?php
/**
 * Author Slice Scheduler Base
 *
 * Abstract base class providing shared scheduling logic for author-based
 * slice dispatching with retry mechanisms.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Abstract Class AIPS_Author_Slice_Scheduler_Base
 *
 * Provides common scheduling infrastructure for dispatching author slices
 * with exponential backoff retry and delayed batch retry mechanisms.
 */
abstract class AIPS_Author_Slice_Scheduler_Base {

	/**
	 * @var AIPS_Authors_Repository Repository for authors
	 */
	protected $authors_repository;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	protected $logger;

	/**
	 * @var AIPS_History_Service Service for history logging
	 */
	protected $history_service;

	/**
	 * @var AIPS_Resilience_Service Resilience service for retry logic
	 */
	protected $resilience_service;

	/**
	 * Get the cron hook name for this scheduler's slice processing.
	 *
	 * @return string The WordPress cron hook name.
	 */
	abstract protected function get_slice_hook(): string;

	/**
	 * Get the filter name for stagger seconds configuration.
	 *
	 * @return string The WordPress filter name.
	 */
	abstract protected function get_stagger_filter(): string;

	/**
	 * Get the default stagger seconds value.
	 *
	 * @return int Default number of seconds between author slices.
	 */
	abstract protected function get_default_stagger_seconds(): int;

	/**
	 * Get the history service type for this scheduler.
	 *
	 * @return string Type string for history service (e.g., 'author_topic_generation').
	 */
	abstract protected function get_history_type(): string;

	/**
	 * Get the human-readable log type for this scheduler.
	 *
	 * @return string Log type (e.g., 'author-topics', 'author-post').
	 */
	abstract protected function get_log_type(): string;

	/**
	 * Dispatch one cron event per due author with staggered timing.
	 *
	 * Each event fires shortly after the current time (staggered to avoid
	 * hammering the AI service simultaneously) and processes a single author.
	 *
	 * @param object[] $due_authors Array of author objects from the repository.
	 */
	protected function dispatch_author_slices( array $due_authors ): void {
		// Use AIPS_DateTime instead of time() for consistent timezone-safe timestamp handling
		$now            = AIPS_DateTime::now()->timestamp();
		$correlation_id = (string) AIPS_Correlation_ID::get();

		// Get stagger configuration from filter
		$stagger_seconds = (int) apply_filters( $this->get_stagger_filter(), $this->get_default_stagger_seconds() );
		$stagger_seconds = max( 0, $stagger_seconds );

		$failed_authors = array();
		$successful_count = 0;

		$i = 0;
		foreach ( $due_authors as $author ) {
			$fire_at = $now + ($i * $stagger_seconds);
			$args = array( (int) $author->id, $correlation_id );

			// Avoid scheduling duplicate events
			if ( wp_next_scheduled( $this->get_slice_hook(), $args ) ) {
				$successful_count++;
				$i++;
				continue;
			}

			// Try to schedule the event with retry logic
			$scheduled = $this->schedule_slice_with_retry( $this->get_slice_hook(), $fire_at, $args, $author->id );

			if ( $scheduled ) {
				$successful_count++;
			} else {
				$failed_authors[] = $author;
			}

			$i++;
		}

		$this->logger->log(
			sprintf(
				'Dispatched %d/%d %s slice events (stagger: %ds each).',
				$successful_count,
				count($due_authors),
				$this->get_log_type(),
				$stagger_seconds
			),
			'info'
		);

		// Schedule a delayed retry for any failed authors
		if ( ! empty( $failed_authors ) ) {
			$this->schedule_failed_authors_retry( $failed_authors, $correlation_id );
		}
	}

	/**
	 * Schedule a slice event with retry logic using AIPS_Resilience_Service.
	 *
	 * Attempts to schedule the event up to 3 times with exponential backoff
	 * (1s, 2s delays between attempts) to handle transient WordPress cron issues.
	 *
	 * @param string $hook      The cron hook name.
	 * @param int    $fire_at   Unix timestamp when the event should fire.
	 * @param array  $args      Arguments to pass to the hook.
	 * @param int    $author_id Author ID for logging.
	 * @return bool True if successfully scheduled, false otherwise.
	 */
	protected function schedule_slice_with_retry( string $hook, int $fire_at, array $args, int $author_id ): bool {
		$max_attempts = (int) apply_filters( 'aips_slice_schedule_max_attempts', 3 );
		$attempt_num = 0;
		$last_error = null;

		$success = $this->resilience_service->retry_with_backoff(
			function() use ( $hook, $fire_at, $args, $author_id, &$attempt_num, &$last_error ) {
				$attempt_num++;
				$result = wp_schedule_single_event( $fire_at, $hook, $args );

				if ( $result === true ) {
					if ( $attempt_num > 1 ) {
						$this->logger->log(
							sprintf( 'Successfully scheduled slice for author ID %d on attempt %d', $author_id, $attempt_num ),
							'info'
						);
					}
					return true;
				}

				// Log the failure
				$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error (returned false)';
				$last_error = $error_msg;

				$this->logger->log(
					sprintf(
						'Attempt %d: Failed to schedule %s slice for author ID %d: %s',
						$attempt_num,
						$this->get_log_type(),
						$author_id,
						$error_msg
					),
					'warning'
				);

				return false;
			},
			$max_attempts,
			1, // Initial 1-second delay
			true // Use exponential backoff
		);

		// If all attempts failed, log to history
		if ( ! $success ) {
			$history = $this->history_service->create( $this->get_history_type(), array(
				'author_id' => $author_id,
			) );

			$history->record(
				'dispatch_failed',
				sprintf( 'Failed to dispatch %s slice after %d attempts: %s', $this->get_log_type(), $max_attempts, $last_error ),
				array(
					'event_type'   => 'dispatch_slice_failed',
					'event_status' => 'failed',
				),
				null,
				array(
					'author_id'     => $author_id,
					'error'         => $last_error,
					'max_attempts'  => $max_attempts,
				)
			);
		}

		return $success;
	}

	/**
	 * Schedule a delayed retry event for failed authors.
	 *
	 * When one or more author slices fail to schedule despite retries, this method
	 * schedules a single delayed event that will attempt to re-dispatch those authors
	 * after a configurable delay (default 5 minutes).
	 *
	 * @param object[] $failed_authors Array of author objects that failed to schedule.
	 * @param string   $correlation_id Correlation ID for tracing.
	 */
	protected function schedule_failed_authors_retry( array $failed_authors, string $correlation_id ): void {
		$retry_delay = (int) apply_filters( 'aips_author_slice_retry_delay_seconds', 300 ); // 5 minutes
		$retry_delay = max( 60, $retry_delay ); // At least 1 minute

		$retry_hook = $this->get_retry_hook();
		$retry_at   = AIPS_DateTime::now()->timestamp() + $retry_delay;

		$author_ids = array_map( function( $author ) {
			return (int) $author->id;
		}, $failed_authors );

		$retry_args = array(
			wp_json_encode( $author_ids ),
			$correlation_id,
		);

		// Avoid duplicate retry events
		if ( wp_next_scheduled( $retry_hook, $retry_args ) ) {
			$this->logger->log(
				sprintf(
					'Retry event for %d failed %s slices already scheduled',
					count( $failed_authors ),
					$this->get_log_type()
				),
				'info'
			);
			return;
		}

		$result = wp_schedule_single_event( $retry_at, $retry_hook, $retry_args );

		if ( $result === true ) {
			$this->logger->log(
				sprintf(
					'Scheduled delayed retry for %d failed %s slices in %d seconds',
					count( $failed_authors ),
					$this->get_log_type(),
					$retry_delay
				),
				'info'
			);
		} else {
			$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'returned false';
			$this->logger->log(
				sprintf(
					'CRITICAL: Failed to schedule retry event for %d failed %s slices: %s',
					count( $failed_authors ),
					$this->get_log_type(),
					$error_msg
				),
				'error'
			);

			// Log critical failure to history
			$history = $this->history_service->create( $this->get_history_type(), array() );
			$history->record(
				'retry_schedule_failed',
				sprintf( 'Failed to schedule retry for %d failed author slices: %s', count( $failed_authors ), $error_msg ),
				array(
					'event_type'   => 'retry_schedule_failed',
					'event_status' => 'failed',
				),
				null,
				array(
					'failed_author_ids' => $author_ids,
					'error'             => $error_msg,
				)
			);
		}
	}

	/**
	 * Retry failed author slices (common implementation).
	 *
	 * This is the callback for retry cron hooks. It re-attempts to dispatch
	 * slice events for authors that failed to schedule earlier.
	 *
	 * @param string $author_ids_json JSON-encoded array of author IDs.
	 * @param string $correlation_id  Correlation ID for tracing.
	 */
	protected function retry_failed_slices( string $author_ids_json, string $correlation_id = '' ): void {
		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::set( $correlation_id );
		} else {
			AIPS_Correlation_ID::generate();
		}

		try {
			$author_ids = json_decode( $author_ids_json, true );
			if ( ! is_array( $author_ids ) || empty( $author_ids ) ) {
				$this->logger->log( 'Invalid author IDs provided for retry', 'error' );
				return;
			}

			$this->logger->log(
				sprintf( 'Retrying %s generation for %d failed authors', $this->get_log_type(), count( $author_ids ) ),
				'info'
			);

			// Fetch the author objects
			$authors = array();
			foreach ( $author_ids as $author_id ) {
				$author = $this->authors_repository->get_by_id( $author_id );
				if ( $author ) {
					$authors[] = $author;
				} else {
					$this->logger->log(
						sprintf( 'Retry: author ID %d not found', $author_id ),
						'warning'
					);
				}
			}

			if ( empty( $authors ) ) {
				$this->logger->log( 'No valid authors found for retry', 'warning' );
				return;
			}

			// Re-dispatch these authors
			$this->dispatch_author_slices( $authors );

		} finally {
			AIPS_Correlation_ID::reset();
		}
	}

	/**
	 * Get the retry cron hook name for this scheduler.
	 *
	 * @return string The WordPress cron hook name for retries.
	 */
	abstract protected function get_retry_hook(): string;
}
