<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Central repository cache dependency map.
 *
 * Keep this intentionally explicit and searchable.
 */
class AIPS_Repository_Cache_Dependencies {

	/**
	 * Resolve read tags for a repository cache operation.
	 *
	 * @param string $operation_id Repository cache operation ID.
	 * @param array  $args Operation arguments.
	 * @return array<int, string>
	 */
	public static function tags_for_read( string $operation_id, array $args = array() ): array {
		$static_map = self::read_tag_static_map();
		if (isset( $static_map[ $operation_id ] )) {
			return $static_map[ $operation_id ];
		}

		$handler_map = self::read_tag_handler_map();
		if (isset( $handler_map[ $operation_id ] )) {
			$handler = $handler_map[ $operation_id ];
			if (method_exists( self::class, $handler )) {
				return self::$handler( $args );
			}
		}

		return array();
	}

	/**
	 * Resolve static read-tag mappings.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function read_tag_static_map(): array {
		return array(
			'authors.get_all'                             => array( 'authors' ),
			'author_topics.get_global_status_counts'      => array( 'author_topics', 'dashboard_counts' ),
			'author_topics.get_counts_grouped_by_author'  => array( 'author_topics', 'dashboard_counts' ),
			'author_topics.get_daily_topic_counts'        => array( 'author_topics', 'dashboard_counts' ),
			'author_topics.get_all_approved_for_queue'    => array( 'author_topics', 'dashboard_counts' ),
			'voices.get_all'                              => array( 'voices' ),
			'voices.search'                               => array( 'voices' ),
			'article_structures.get_all'                  => array( 'article_structures' ),
			'article_structures.count_by_status'          => array( 'article_structures', 'dashboard_counts' ),
			'article_structures.name_exists'              => array( 'article_structures' ),
			'prompt_sections.get_all'                     => array( 'prompt_sections' ),
			'prompt_sections.count_by_status'             => array( 'prompt_sections', 'dashboard_counts' ),
			'prompt_sections.key_exists'                  => array( 'prompt_sections' ),
			'templates.get_all'                           => array( 'templates' ),
			'templates.search'                            => array( 'templates' ),
			'templates.name_exists'                       => array( 'templates' ),
			'templates.count_by_status'                   => array( 'templates', 'dashboard_counts' ),
			'schedules.get_all'                           => array( 'schedules', 'templates', 'unified_schedule' ),
			'schedules.get_due'                           => array( 'schedules', 'templates', 'unified_schedule' ),
			'schedules.get_upcoming'                      => array( 'schedules', 'templates', 'unified_schedule' ),
			'schedules.get_campaign_owned_ids'            => array( 'schedules', 'unified_schedule' ),
			'schedules.get_active'                        => array( 'schedules', 'unified_schedule' ),
			'schedules.count_by_status'                   => array( 'schedules', 'dashboard_counts', 'unified_schedule' ),
			'schedules.get_post_count_for_schedules'      => array( 'schedules', 'templates', 'unified_schedule' ),
			'sources.get_all'                             => array( 'sources' ),
			'sources.get_active_urls'                     => array( 'sources' ),
			'sources.url_exists'                          => array( 'sources' ),
			'internal_links.get_paginated'                => array( 'internal_links', 'dashboard_counts' ),
			'internal_links.get_paginated_count'          => array( 'internal_links', 'dashboard_counts' ),
			'internal_links.get_status_counts'            => array( 'internal_links', 'dashboard_counts' ),
			'taxonomy.get_status_counts'                  => array( 'taxonomy', 'dashboard_counts' ),
			'taxonomy.search'                             => array( 'taxonomy' ),
			'post_slices.get_all'                         => array( 'post_slices' ),
			'post_slices.name_exists'                     => array( 'post_slices' ),
			'post_slices.get_counts'                      => array( 'post_slices', 'dashboard_counts' ),
			'history.get_stats'                           => array( 'history', 'dashboard_counts' ),
			'history.get_daily_success_failure_trend'     => array( 'history', 'dashboard_counts' ),
			'history.get_average_duration_by_flow'        => array( 'history', 'dashboard_counts' ),
			'history.get_retry_counts_by_service'         => array( 'history', 'dashboard_counts' ),
			'history.get_top_failure_reasons'             => array( 'history', 'dashboard_counts' ),
			'history.get_daily_generation_counts'         => array( 'history', 'dashboard_counts' ),
			'history.get_all_template_stats'              => array( 'history', 'templates', 'dashboard_counts' ),
			'telemetry.get_page'                          => array( 'telemetry', 'dashboard_counts' ),
			'telemetry.get_filtered_page'                 => array( 'telemetry', 'dashboard_counts' ),
			'telemetry.count'                             => array( 'telemetry', 'dashboard_counts' ),
			'telemetry.count_filtered'                    => array( 'telemetry', 'dashboard_counts' ),
			'telemetry.get_daily_rollup'                  => array( 'telemetry', 'dashboard_counts' ),
		);
	}

	/**
	 * Resolve read-tag handler mappings for argument-sensitive operations.
	 *
	 * @return array<string, string>
	 */
	private static function read_tag_handler_map(): array {
		return array(
			'authors.get_by_id'                             => 'read_tags_authors_get_by_id',
			'author_topics.get_by_author'                   => 'read_tags_author_topics_by_author',
			'author_topics.get_by_id'                       => 'read_tags_author_topics_by_id',
			'author_topics.get_approved_summary'            => 'read_tags_author_topics_approved_summary',
			'author_topics.get_rejected_summary'            => 'read_tags_author_topics_by_author',
			'author_topics.get_status_counts'               => 'read_tags_author_topics_by_author',
			'author_topics.get_approved_for_generation'     => 'read_tags_author_topics_approved_for_generation',
			'voices.get_by_id'                              => 'read_tags_voices_get_by_id',
			'article_structures.get_by_id'                  => 'read_tags_article_structures_get_by_id',
			'prompt_sections.get_by_id'                     => 'read_tags_prompt_sections_get_by_id',
			'prompt_sections.get_by_key'                    => 'read_tags_prompt_sections_get_by_key',
			'prompt_sections.get_by_keys'                   => 'read_tags_prompt_sections_get_by_keys',
			'templates.get_by_id'                           => 'read_tags_templates_get_by_id',
			'templates.count_by_campaign'                   => 'read_tags_templates_count_by_campaign',
			'schedules.get_by_id'                           => 'read_tags_schedules_get_by_id',
			'schedules.get_by_template'                     => 'read_tags_schedules_get_by_template',
			'schedules.get_active_by_template'              => 'read_tags_schedules_get_by_template',
			'schedules.count_by_campaign'                   => 'read_tags_schedules_count_by_campaign',
			'sources.get_by_id'                             => 'read_tags_sources_get_by_id',
			'sources.get_source_term_ids'                   => 'read_tags_sources_get_by_id',
			'sources.get_term_ids_for_sources'              => 'read_tags_sources_get_term_ids_for_sources',
			'sources.get_urls_by_group_term_ids'            => 'read_tags_sources_by_group_term_ids',
			'sources.get_by_group_term_ids'                 => 'read_tags_sources_by_group_term_ids',
			'sources_data.get_by_source_id'                 => 'read_tags_sources_data_get_by_source_id',
			'sources_data.get_latest_success_by_source_id'  => 'read_tags_sources_data_get_by_source_id',
			'sources_data.get_count_by_source_id'           => 'read_tags_sources_data_get_by_source_id',
			'sources_data.get_by_source_ids'                => 'read_tags_sources_data_by_source_ids',
			'sources_data.get_extracted_texts_by_source_ids' => 'read_tags_sources_data_by_source_ids',
			'sources_data.pick_next_for_prompt_bulk'        => 'read_tags_sources_data_by_source_ids',
			'sources_data.get_counts_by_source_ids'         => 'read_tags_sources_data_by_source_ids',
			'internal_links.get_by_id'                      => 'read_tags_internal_links_get_by_id',
			'internal_links.get_by_source_post'             => 'read_tags_internal_links_by_source_post',
			'internal_links.exists'                         => 'read_tags_internal_links_exists',
			'taxonomy.get_by_type'                          => 'read_tags_taxonomy_get_by_type',
			'taxonomy.get_by_status_and_type'               => 'read_tags_taxonomy_get_by_status_and_type',
			'taxonomy.get_by_id'                            => 'read_tags_taxonomy_get_by_id',
			'post_slices.get_by_id'                         => 'read_tags_post_slices_get_by_id',
			'history.count_completed_for_schedule'          => 'read_tags_history_count_completed_for_schedule',
			'history.get_template_stats'                    => 'read_tags_history_get_template_stats',
			'history.post_has_history_and_completed'        => 'read_tags_history_get_by_post_id',
			'history.get_by_post_id'                        => 'read_tags_history_get_by_post_id',
			'telemetry.get_row'                             => 'read_tags_telemetry_get_row',
			'telemetry.get_payload'                         => 'read_tags_telemetry_get_row',
			'bulk_batch_jobs.get_status_counts'             => 'read_tags_bulk_batch_jobs_get_status_counts',
		);
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_authors_get_by_id( array $args ): array {
		$tags = array( 'authors' );
		if (isset( $args['author_id'] ) && is_numeric( $args['author_id'] )) {
			$tags[] = 'author:' . (int) $args['author_id'];
		}

		return $tags;
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_author_topics_by_author( array $args ): array {
		return self::author_topic_read_tags( $args );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_author_topics_by_id( array $args ): array {
		$tags = array( 'author_topics' );
		if (isset( $args['author_id'] ) && is_numeric( $args['author_id'] )) {
			$tags[] = 'author_topics:author:' . (int) $args['author_id'];
		}
		if (isset( $args['topic_id'] ) && is_numeric( $args['topic_id'] )) {
			$tags[] = 'author_topic:' . (int) $args['topic_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_author_topics_approved_summary( array $args ): array {
		return self::unique_tags(
			array_merge(
				self::author_topic_read_tags( $args ),
				self::author_id_tag( 'author_generation_summary', $args )
			)
		);
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_author_topics_approved_for_generation( array $args ): array {
		return self::unique_tags(
			array_merge(
				self::author_topic_read_tags( $args ),
				self::author_id_tag( 'author_post_queue', $args )
			)
		);
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_voices_get_by_id( array $args ): array {
		$tags = array( 'voices' );
		if (isset( $args['voice_id'] ) && is_numeric( $args['voice_id'] )) {
			$tags[] = 'voice:' . (int) $args['voice_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_article_structures_get_by_id( array $args ): array {
		$tags = array( 'article_structures' );
		if (isset( $args['structure_id'] ) && is_numeric( $args['structure_id'] )) {
			$tags[] = 'article_structure:' . (int) $args['structure_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_prompt_sections_get_by_id( array $args ): array {
		$tags = array( 'prompt_sections' );
		if (isset( $args['section_id'] ) && is_numeric( $args['section_id'] )) {
			$tags[] = 'prompt_section:' . (int) $args['section_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_prompt_sections_get_by_key( array $args ): array {
		$tags = array( 'prompt_sections' );
		if (isset( $args['section_key'] ) && is_scalar( $args['section_key'] )) {
			$tags[] = 'prompt_section:key:' . sanitize_key( (string) $args['section_key'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_prompt_sections_get_by_keys( array $args ): array {
		return self::prompt_section_key_tags( $args );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_templates_get_by_id( array $args ): array {
		$tags = array( 'templates' );
		if (isset( $args['template_id'] ) && is_numeric( $args['template_id'] )) {
			$tags[] = 'template:' . (int) $args['template_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_templates_count_by_campaign( array $args ): array {
		return self::unique_tags(
			array_merge(
				array( 'templates' ),
				self::campaign_id_tag( 'campaign', $args )
			)
		);
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_schedules_get_by_id( array $args ): array {
		$tags = array( 'schedules', 'unified_schedule' );
		if (isset( $args['schedule_id'] ) && is_numeric( $args['schedule_id'] )) {
			$tags[] = 'schedule:' . (int) $args['schedule_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_schedules_get_by_template( array $args ): array {
		return self::unique_tags(
			array_merge(
				array( 'schedules', 'templates', 'unified_schedule' ),
				self::template_id_tag( 'template', $args )
			)
		);
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_schedules_count_by_campaign( array $args ): array {
		return self::unique_tags(
			array_merge(
				array( 'schedules', 'unified_schedule' ),
				self::campaign_id_tag( 'campaign', $args )
			)
		);
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_sources_get_by_id( array $args ): array {
		$tags = array( 'sources' );
		if (isset( $args['source_id'] ) && is_numeric( $args['source_id'] )) {
			$tags[] = 'source:' . (int) $args['source_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_sources_get_term_ids_for_sources( array $args ): array {
		return self::source_ids_tags( $args );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_sources_by_group_term_ids( array $args ): array {
		return self::source_group_term_tags( $args );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_sources_data_get_by_source_id( array $args ): array {
		$tags = array( 'sources_data', 'sources' );
		if (isset( $args['source_id'] ) && is_numeric( $args['source_id'] )) {
			$source_id = (int) $args['source_id'];
			$tags[]    = 'source_data:source:' . $source_id;
			$tags[]    = 'source:' . $source_id;
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_sources_data_by_source_ids( array $args ): array {
		return self::source_data_source_ids_tags( $args );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_internal_links_get_by_id( array $args ): array {
		$tags = array( 'internal_links' );
		if (isset( $args['internal_link_id'] ) && is_numeric( $args['internal_link_id'] )) {
			$tags[] = 'internal_link:' . (int) $args['internal_link_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_internal_links_by_source_post( array $args ): array {
		$tags = array( 'internal_links' );
		if (isset( $args['source_post_id'] ) && is_numeric( $args['source_post_id'] )) {
			$tags[] = 'internal_links:source:' . (int) $args['source_post_id'];
			$tags[] = 'post:' . (int) $args['source_post_id'];
		}
		if (!empty( $args['status'] ) && is_scalar( $args['status'] )) {
			$tags[] = 'internal_links:status:' . sanitize_key( (string) $args['status'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_internal_links_exists( array $args ): array {
		$tags = array( 'internal_links' );
		if (isset( $args['source_post_id'] ) && is_numeric( $args['source_post_id'] )) {
			$tags[] = 'internal_links:source:' . (int) $args['source_post_id'];
		}
		if (isset( $args['target_post_id'] ) && is_numeric( $args['target_post_id'] )) {
			$tags[] = 'internal_links:target:' . (int) $args['target_post_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_taxonomy_get_by_type( array $args ): array {
		$tags = array( 'taxonomy' );
		if (!empty( $args['taxonomy_type'] ) && is_scalar( $args['taxonomy_type'] )) {
			$tags[] = 'taxonomy_type:' . sanitize_key( (string) $args['taxonomy_type'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_taxonomy_get_by_status_and_type( array $args ): array {
		$tags = array( 'taxonomy' );
		if (!empty( $args['taxonomy_type'] ) && is_scalar( $args['taxonomy_type'] )) {
			$tags[] = 'taxonomy_type:' . sanitize_key( (string) $args['taxonomy_type'] );
		}
		if (!empty( $args['status'] ) && is_scalar( $args['status'] )) {
			$tags[] = 'taxonomy_status:' . sanitize_key( (string) $args['status'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_taxonomy_get_by_id( array $args ): array {
		$tags = array( 'taxonomy' );
		if (isset( $args['taxonomy_id'] ) && is_numeric( $args['taxonomy_id'] )) {
			$tags[] = 'taxonomy_item:' . (int) $args['taxonomy_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_post_slices_get_by_id( array $args ): array {
		$tags = array( 'post_slices' );
		if (isset( $args['slice_id'] ) && is_numeric( $args['slice_id'] )) {
			$tags[] = 'post_slice:' . (int) $args['slice_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_history_count_completed_for_schedule( array $args ): array {
		$tags = array( 'history', 'schedules', 'dashboard_counts' );
		if (isset( $args['schedule_id'] ) && is_numeric( $args['schedule_id'] )) {
			$tags[] = 'schedule:' . (int) $args['schedule_id'];
		}
		if (isset( $args['template_id'] ) && is_numeric( $args['template_id'] )) {
			$tags[] = 'template:' . (int) $args['template_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_history_get_template_stats( array $args ): array {
		$tags = array( 'history', 'templates', 'dashboard_counts' );
		if (isset( $args['template_id'] ) && is_numeric( $args['template_id'] )) {
			$tags[] = 'template:' . (int) $args['template_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_history_get_by_post_id( array $args ): array {
		$tags = array( 'history', 'dashboard_counts' );
		if (isset( $args['post_id'] ) && is_numeric( $args['post_id'] )) {
			$tags[] = 'post:' . (int) $args['post_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_telemetry_get_row( array $args ): array {
		$tags = array( 'telemetry', 'dashboard_counts' );
		if (isset( $args['telemetry_id'] ) && is_numeric( $args['telemetry_id'] )) {
			$tags[] = 'telemetry_item:' . (int) $args['telemetry_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function read_tags_bulk_batch_jobs_get_status_counts( array $args ): array {
		$tags = array( 'bulk_batch_jobs', 'dashboard_counts' );
		if (!empty( $args['statuses'] ) && is_array( $args['statuses'] )) {
			foreach ( $args['statuses'] as $status ) {
				if (is_scalar( $status )) {
					$tags[] = 'bulk_batch_job:status:' . sanitize_key( (string) $status );
				}
			}
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve invalidation tags for a cache domain.
	 *
	 * @param string $domain Domain name.
	 * @param array  $context Domain context.
	 * @return array<int, string>
	 */
	public static function tags_for_invalidation( string $domain, array $context = array() ): array {
		switch ($domain) {
			case 'author':
				return self::tags_for_author_invalidation( $context );

			case 'author_topic':
				return self::tags_for_author_topic_invalidation( $context );

			case 'author_topic_log':
				return array( 'author_topic_logs' );

			case 'post_generation':
				return self::tags_for_post_generation_invalidation( $context );

			case 'dashboard':
				return array( 'dashboard_counts' );

			case 'unified_schedule':
				return array( 'unified_schedule' );

				case 'voice':
					return self::tags_for_voice_invalidation( $context );

				case 'article_structure':
					return self::tags_for_article_structure_invalidation( $context );

				case 'prompt_section':
					return self::tags_for_prompt_section_invalidation( $context );

				case 'template':
					return self::tags_for_template_invalidation( $context );

				case 'schedule':
					return self::tags_for_schedule_invalidation( $context );

							case 'source':
								return self::tags_for_source_invalidation( $context );

							case 'source_data':
								return self::tags_for_source_data_invalidation( $context );

							case 'internal_link':
								return self::tags_for_internal_link_invalidation( $context );

							case 'taxonomy':
								return self::tags_for_taxonomy_invalidation( $context );

							case 'post_slice':
								return self::tags_for_post_slice_invalidation( $context );

							case 'history':
								return self::tags_for_history_invalidation( $context );

							case 'history_schedule_count':
								return self::tags_for_history_schedule_count_invalidation( $context );

							case 'telemetry':
								return self::tags_for_telemetry_invalidation( $context );

							case 'bulk_batch_job':
								return self::tags_for_bulk_batch_job_invalidation( $context );

			default:
				$domain = sanitize_key( $domain );
				return '' !== $domain ? array( $domain ) : array();
		}
	}

	/**
	 * Resolve author-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_author_invalidation( array $context ): array {
		$tags = array(
			'authors',
			'author_generation_schedule',
			'dashboard_counts',
			'unified_schedule',
		);

		if (isset( $context['author_id'] ) && is_numeric( $context['author_id'] )) {
			$tags[] = 'author:' . (int) $context['author_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve author-topic-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_author_topic_invalidation( array $context ): array {
		$tags = array(
			'author_topics',
			'dashboard_counts',
		);

		if (isset( $context['author_id'] ) && is_numeric( $context['author_id'] )) {
			$author_id = (int) $context['author_id'];
			$tags[]    = 'author_topics:author:' . $author_id;
			$tags[]    = 'author_generation_summary:' . $author_id;
			$tags[]    = 'author_post_queue:' . $author_id;
		}

		if (isset( $context['topic_id'] ) && is_numeric( $context['topic_id'] )) {
			$tags[] = 'author_topic:' . (int) $context['topic_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve post-generation-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_post_generation_invalidation( array $context ): array {
		$tags = array(
			'author_topic_logs',
			'history',
			'dashboard_counts',
			'unified_schedule',
		);

		if (isset( $context['author_id'] ) && is_numeric( $context['author_id'] )) {
			$author_id = (int) $context['author_id'];
			$tags[]    = 'author:' . $author_id;
			$tags[]    = 'author_topics:author:' . $author_id;
		}

		if (isset( $context['topic_id'] ) && is_numeric( $context['topic_id'] )) {
			$tags[] = 'author_topic:' . (int) $context['topic_id'];
		}

		if (isset( $context['post_id'] ) && is_numeric( $context['post_id'] )) {
			$tags[] = 'post:' . (int) $context['post_id'];
		}

		if (isset( $context['template_id'] ) && is_numeric( $context['template_id'] )) {
			$tags[] = 'template:' . (int) $context['template_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve template-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_template_invalidation( array $context ): array {
		$tags = array(
			'templates',
			'dashboard_counts',
			'unified_schedule',
		);

		if (isset( $context['template_id'] ) && is_numeric( $context['template_id'] )) {
			$tags[] = 'template:' . (int) $context['template_id'];
		}

		if (isset( $context['campaign_id'] ) && is_numeric( $context['campaign_id'] ) && (int) $context['campaign_id'] > 0) {
			$tags[] = 'campaign:' . (int) $context['campaign_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve schedule-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_schedule_invalidation( array $context ): array {
		$tags = array(
			'schedules',
			'dashboard_counts',
			'unified_schedule',
		);

		if (isset( $context['schedule_id'] ) && is_numeric( $context['schedule_id'] )) {
			$tags[] = 'schedule:' . (int) $context['schedule_id'];
		}

		if (isset( $context['template_id'] ) && is_numeric( $context['template_id'] )) {
			$tags[] = 'template:' . (int) $context['template_id'];
		}

		if (isset( $context['campaign_id'] ) && is_numeric( $context['campaign_id'] ) && (int) $context['campaign_id'] > 0) {
			$tags[] = 'campaign:' . (int) $context['campaign_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve source-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_source_invalidation( array $context ): array {
		$tags = array( 'sources' );

		if (isset( $context['source_id'] ) && is_numeric( $context['source_id'] )) {
			$tags[] = 'source:' . (int) $context['source_id'];
			$tags[] = 'source_data:source:' . (int) $context['source_id'];
		}

		if (!empty( $context['term_ids'] ) && is_array( $context['term_ids'] )) {
			foreach ( $context['term_ids'] as $term_id ) {
				if (is_numeric( $term_id )) {
					$tags[] = 'source_group_term:' . (int) $term_id;
				}
			}
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve source-data-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_source_data_invalidation( array $context ): array {
		$tags = array( 'sources_data', 'sources' );

		if (isset( $context['source_id'] ) && is_numeric( $context['source_id'] )) {
			$source_id = (int) $context['source_id'];
			$tags[]    = 'source_data:source:' . $source_id;
			$tags[]    = 'source:' . $source_id;
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve internal-link-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_internal_link_invalidation( array $context ): array {
		$tags = array( 'internal_links', 'dashboard_counts' );

		if (isset( $context['internal_link_id'] ) && is_numeric( $context['internal_link_id'] )) {
			$tags[] = 'internal_link:' . (int) $context['internal_link_id'];
		}

		if (isset( $context['source_post_id'] ) && is_numeric( $context['source_post_id'] )) {
			$post_id = (int) $context['source_post_id'];
			$tags[]  = 'internal_links:source:' . $post_id;
			$tags[]  = 'post:' . $post_id;
		}

		if (isset( $context['target_post_id'] ) && is_numeric( $context['target_post_id'] )) {
			$post_id = (int) $context['target_post_id'];
			$tags[]  = 'internal_links:target:' . $post_id;
			$tags[]  = 'post:' . $post_id;
		}

		if (!empty( $context['status'] ) && is_scalar( $context['status'] )) {
			$tags[] = 'internal_links:status:' . sanitize_key( (string) $context['status'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve taxonomy-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_taxonomy_invalidation( array $context ): array {
		$tags = array( 'taxonomy', 'dashboard_counts' );

		if (isset( $context['taxonomy_id'] ) && is_numeric( $context['taxonomy_id'] )) {
			$tags[] = 'taxonomy_item:' . (int) $context['taxonomy_id'];
		}

		if (!empty( $context['taxonomy_type'] ) && is_scalar( $context['taxonomy_type'] )) {
			$tags[] = 'taxonomy_type:' . sanitize_key( (string) $context['taxonomy_type'] );
		}

		if (!empty( $context['status'] ) && is_scalar( $context['status'] )) {
			$tags[] = 'taxonomy_status:' . sanitize_key( (string) $context['status'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve post-slice-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_post_slice_invalidation( array $context ): array {
		$tags = array( 'post_slices', 'dashboard_counts' );

		if (isset( $context['slice_id'] ) && is_numeric( $context['slice_id'] )) {
			$tags[] = 'post_slice:' . (int) $context['slice_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve history-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_history_invalidation( array $context ): array {
		$tags = array( 'history', 'dashboard_counts' );

		if (isset( $context['history_id'] ) && is_numeric( $context['history_id'] )) {
			$tags[] = 'history_item:' . (int) $context['history_id'];
		}

		if (isset( $context['post_id'] ) && is_numeric( $context['post_id'] )) {
			$tags[] = 'post:' . (int) $context['post_id'];
		}

		if (!empty( $context['status'] ) && is_scalar( $context['status'] )) {
			$tags[] = 'history_status:' . sanitize_key( (string) $context['status'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve schedule-completed-count invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_history_schedule_count_invalidation( array $context ): array {
		$tags = array( 'history', 'schedules', 'dashboard_counts' );

		if (isset( $context['schedule_id'] ) && is_numeric( $context['schedule_id'] )) {
			$tags[] = 'schedule:' . (int) $context['schedule_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve telemetry-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_telemetry_invalidation( array $context ): array {
		$tags = array( 'telemetry', 'dashboard_counts' );

		if (isset( $context['telemetry_id'] ) && is_numeric( $context['telemetry_id'] )) {
			$tags[] = 'telemetry_item:' . (int) $context['telemetry_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve bulk-batch-job-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_bulk_batch_job_invalidation( array $context ): array {
		$tags = array( 'bulk_batch_jobs', 'dashboard_counts' );

		if (!empty( $context['job_id'] ) && is_scalar( $context['job_id'] )) {
			$tags[] = 'bulk_batch_job:' . sanitize_text_field( (string) $context['job_id'] );
		}

		if (!empty( $context['status'] ) && is_scalar( $context['status'] )) {
			$tags[] = 'bulk_batch_job:status:' . sanitize_key( (string) $context['status'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve voice-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_voice_invalidation( array $context ): array {
		$tags = array( 'voices' );

		if (isset( $context['voice_id'] ) && is_numeric( $context['voice_id'] )) {
			$tags[] = 'voice:' . (int) $context['voice_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve article-structure-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_article_structure_invalidation( array $context ): array {
		$tags = array( 'article_structures' );

		if (isset( $context['structure_id'] ) && is_numeric( $context['structure_id'] )) {
			$tags[] = 'article_structure:' . (int) $context['structure_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve prompt-section-domain invalidation tags.
	 *
	 * @param array $context Domain context.
	 * @return array<int, string>
	 */
	private static function tags_for_prompt_section_invalidation( array $context ): array {
		$tags = array( 'prompt_sections' );

		if (isset( $context['section_id'] ) && is_numeric( $context['section_id'] )) {
			$tags[] = 'prompt_section:' . (int) $context['section_id'];
		}

		if (isset( $context['section_key'] ) && is_scalar( $context['section_key'] )) {
			$tags[] = 'prompt_section:key:' . sanitize_key( (string) $context['section_key'] );
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve bulk prompt-section read tags for a set of section keys.
	 *
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function prompt_section_key_tags( array $args ): array {
		$tags = array( 'prompt_sections' );

		if (empty( $args['section_keys'] ) || !is_array( $args['section_keys'] )) {
			return $tags;
		}

		foreach ( $args['section_keys'] as $section_key ) {
			if (is_scalar( $section_key )) {
				$tags[] = 'prompt_section:key:' . sanitize_key( (string) $section_key );
			}
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve source tags for a list of source IDs.
	 *
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function source_ids_tags( array $args ): array {
		$tags = array( 'sources' );

		if (empty( $args['source_ids'] ) || !is_array( $args['source_ids'] )) {
			return $tags;
		}

		foreach ( $args['source_ids'] as $source_id ) {
			if (is_numeric( $source_id )) {
				$tags[] = 'source:' . (int) $source_id;
			}
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve source-data tags for a list of source IDs.
	 *
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function source_data_source_ids_tags( array $args ): array {
		$tags = array( 'sources_data', 'sources' );

		if (empty( $args['source_ids'] ) || !is_array( $args['source_ids'] )) {
			return $tags;
		}

		foreach ( $args['source_ids'] as $source_id ) {
			if (is_numeric( $source_id )) {
				$source_id = (int) $source_id;
				$tags[]    = 'source_data:source:' . $source_id;
				$tags[]    = 'source:' . $source_id;
			}
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve source-group-term tags for a list of term IDs.
	 *
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function source_group_term_tags( array $args ): array {
		$tags = array( 'sources' );

		if (empty( $args['term_ids'] ) || !is_array( $args['term_ids'] )) {
			return $tags;
		}

		foreach ( $args['term_ids'] as $term_id ) {
			if (is_numeric( $term_id )) {
				$tags[] = 'source_group_term:' . (int) $term_id;
			}
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Resolve standard author-topic read tags.
	 *
	 * @param array $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function author_topic_read_tags( array $args ): array {
		$tags = array( 'author_topics' );

		if (isset( $args['author_id'] ) && is_numeric( $args['author_id'] )) {
			$tags[] = 'author_topics:author:' . (int) $args['author_id'];
		}

		return self::unique_tags( $tags );
	}

	/**
	 * Build an author-scoped tag when author_id is available.
	 *
	 * @param string $prefix Tag prefix.
	 * @param array  $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function author_id_tag( string $prefix, array $args ): array {
		if (isset( $args['author_id'] ) && is_numeric( $args['author_id'] )) {
			return array( $prefix . ':' . (int) $args['author_id'] );
		}

		return array();
	}

	/**
	 * Build a template-scoped tag when template_id is available.
	 *
	 * @param string $prefix Tag prefix.
	 * @param array  $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function template_id_tag( string $prefix, array $args ): array {
		if (isset( $args['template_id'] ) && is_numeric( $args['template_id'] )) {
			return array( $prefix . ':' . (int) $args['template_id'] );
		}

		return array();
	}

	/**
	 * Build a campaign-scoped tag when campaign_id is available.
	 *
	 * @param string $prefix Tag prefix.
	 * @param array  $args Operation arguments.
	 * @return array<int, string>
	 */
	private static function campaign_id_tag( string $prefix, array $args ): array {
		if (isset( $args['campaign_id'] ) && is_numeric( $args['campaign_id'] ) && (int) $args['campaign_id'] > 0) {
			return array( $prefix . ':' . (int) $args['campaign_id'] );
		}

		return array();
	}

	/**
	 * Sanitize and de-duplicate resolved tags.
	 *
	 * @param array $tags Raw tags.
	 * @return array<int, string>
	 */
	private static function unique_tags( array $tags ): array {
		$clean = array();

		foreach ( $tags as $tag ) {
			if (!is_scalar( $tag )) {
				continue;
			}

			$tag = trim( (string) $tag );
			if ('' !== $tag && !in_array( $tag, $clean, true )) {
				$clean[] = $tag;
			}
		}

		return $clean;
	}
}