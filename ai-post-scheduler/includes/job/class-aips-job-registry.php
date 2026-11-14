<?php
/**
 * AIPS Job Registry — declarative single source of truth for all background job definitions.
 *
 * Defines job types, hooks, ActionScheduler groups, retries, and handlers.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Registry {

	const GROUP_DEFAULT       = 'aips-default';
	const GROUP_AUTHOR_TOPICS = 'aips-author-topics';
	const GROUP_BULK_BATCH    = 'aips-bulk-batch';
	const GROUP_INDEXER       = 'aips-indexer';
	const GROUP_EMBEDDINGS    = 'aips-embeddings';

	const TYPE_AUTHOR_TOPIC_SLICE = 'author_topic_slice';
	const TYPE_BULK_BATCH_SLICE   = 'bulk_batch_slice';
	const TYPE_LINK_INDEX_SCAN    = 'link_index_scan';

	/**
	 * Default job tick hook name.
	 */
	const DEFAULT_TICK_HOOK = 'aips_job_tick';

	/**
	 * Return all registered job definitions.
	 *
	 * Filterable via 'aips_registered_jobs'.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_definitions(): array {
		$definitions = array(
			self::TYPE_AUTHOR_TOPIC_SLICE => array(
				'label'             => __( 'Author Topic Slice', 'ai-post-scheduler' ),
				'hook'              => self::DEFAULT_TICK_HOOK,
				'group'             => self::GROUP_AUTHOR_TOPICS,
				'max_retries'       => 3,
				'retry_backoff_sec' => 120,
				'batch_size'        => 10,
				'handler'           => 'AIPS_Author_Topics_Service',
			),
			self::TYPE_BULK_BATCH_SLICE   => array(
				'label'             => __( 'Bulk Batch Slice', 'ai-post-scheduler' ),
				'hook'              => self::DEFAULT_TICK_HOOK,
				'group'             => self::GROUP_BULK_BATCH,
				'max_retries'       => 5,
				'retry_backoff_sec' => 60,
				'batch_size'        => 25,
				'handler'           => 'AIPS_Bulk_Batch_Processor',
			),
			self::TYPE_LINK_INDEX_SCAN    => array(
				'label'             => __( 'Link Index Scan', 'ai-post-scheduler' ),
				'hook'              => self::DEFAULT_TICK_HOOK,
				'group'             => self::GROUP_INDEXER,
				'max_retries'       => 3,
				'retry_backoff_sec' => 60,
				'batch_size'        => 50,
				'handler'           => 'AIPS_Link_Index_Service',
			),
		);

		return (array) apply_filters( 'aips_registered_jobs', $definitions );
	}

	/**
	 * Retrieve a specific job type definition wrapped as an AIPS_Job_Type value object.
	 *
	 * @param string $job_type Job type slug.
	 * @return AIPS_Job_Type|null
	 */
	public function get( string $job_type ): ?AIPS_Job_Type {
		$definitions = $this->get_definitions();
		if ( ! isset( $definitions[ $job_type ] ) ) {
			return null;
		}

		return new AIPS_Job_Type( $job_type, $definitions[ $job_type ] );
	}

	/**
	 * Check whether a job type is registered.
	 *
	 * @param string $job_type Job type slug.
	 * @return bool
	 */
	public function has( string $job_type ): bool {
		$definitions = $this->get_definitions();
		return isset( $definitions[ $job_type ] );
	}

	/**
	 * Return all registered ActionScheduler groups across job types.
	 *
	 * @return string[]
	 */
	public function get_groups(): array {
		$groups = array(
			self::GROUP_DEFAULT,
			self::GROUP_AUTHOR_TOPICS,
			self::GROUP_BULK_BATCH,
			self::GROUP_INDEXER,
			self::GROUP_EMBEDDINGS,
		);

		foreach ( $this->get_definitions() as $def ) {
			if ( ! empty( $def['group'] ) && is_string( $def['group'] ) ) {
				$groups[] = $def['group'];
			}
		}

		return array_values( array_unique( $groups ) );
	}

	/**
	 * Return all action hooks registered across job types.
	 *
	 * @return string[]
	 */
	public function get_hooks(): array {
		$hooks = array( self::DEFAULT_TICK_HOOK );

		foreach ( $this->get_definitions() as $def ) {
			if ( ! empty( $def['hook'] ) && is_string( $def['hook'] ) ) {
				$hooks[] = $def['hook'];
			}
		}

		return array_values( array_unique( $hooks ) );
	}
}
