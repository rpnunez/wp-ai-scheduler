<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Central repository cache dependency map.
 *
 * Single source of truth for every repository cache tag. Keep this
 * intentionally explicit and searchable:
 *
 * - READ_TAGS: operation ID => tag templates carried by that cached read.
 * - INVALIDATION_TAGS: domain => tag templates bumped in the writing
 *   repository's own cache group when that domain changes.
 * - DEPENDENTS: domain => [ repository class => tag templates ] bumped in OTHER
 *   repositories' cache groups. Tag versions are scoped per cache group, so a
 *   read that JOINs another repository's table (e.g. the dashboard over
 *   history) is only invalidated through an entry here.
 *
 * Templates use `{placeholder}` segments resolved from the read arguments or
 * invalidation context. A placeholder resolves only to a numeric value (cast
 * to int); a tag whose placeholder is missing or non-numeric is skipped, which
 * is always safe because every cached read also carries its broad tag.
 */
class AIPS_Repository_Cache_Dependencies {

	/**
	 * Read tag templates per repository operation.
	 *
	 * @var array<string, string[]>
	 */
	const READ_TAGS = array(
		// Authors.
		'authors.get_all'                                 => array( 'authors' ),
		'authors.get_by_id'                               => array( 'authors', 'author:{author_id}' ),

		// Author topics.
		'author_topics.get_by_author'                     => array( 'author_topics', 'author_topics:author:{author_id}' ),
		'author_topics.get_by_id'                         => array( 'author_topics', 'author_topics:author:{author_id}', 'author_topic:{topic_id}' ),
		'author_topics.get_approved_summary'              => array( 'author_topics', 'author_topics:author:{author_id}', 'author_generation_summary:{author_id}' ),
		'author_topics.get_rejected_summary'              => array( 'author_topics', 'author_topics:author:{author_id}' ),
		'author_topics.get_status_counts'                 => array( 'author_topics', 'author_topics:author:{author_id}' ),
		'author_topics.get_global_status_counts'          => array( 'author_topics', 'dashboard_counts' ),
		'author_topics.get_counts_grouped_by_author'      => array( 'author_topics', 'dashboard_counts' ),
		'author_topics.get_daily_topic_counts'            => array( 'author_topics', 'dashboard_counts' ),
		'author_topics.get_approved_for_generation'       => array( 'author_topics', 'author_topics:author:{author_id}', 'author_post_queue:{author_id}' ),
		'author_topics.get_all_approved_for_queue'        => array( 'author_topics', 'dashboard_counts' ),

		// Author topic logs (INNER JOIN aips_author_topics; see DEPENDENTS).
		'author_topic_logs.get_by_topic'                  => array( 'author_topic_logs', 'author_topic_logs:topic:{author_topic_id}' ),
		'author_topic_logs.get_by_id'                     => array( 'author_topic_logs' ),
		'author_topic_logs.count_generated_posts_by_author' => array( 'author_topic_logs', 'author_topic_logs:author:{author_id}' ),
		'author_topic_logs.get_post_generation_counts_grouped_by_author' => array( 'author_topic_logs' ),

		// Topic feedback (INNER JOIN aips_author_topics; see DEPENDENTS).
		'feedback.get_by_topic'                           => array( 'topic_feedback', 'topic_feedback:topic:{author_topic_id}' ),
		'feedback.get_by_author'                          => array( 'topic_feedback', 'topic_feedback:author:{author_id}' ),
		'feedback.get_by_id'                              => array( 'topic_feedback' ),
		'feedback.get_statistics'                         => array( 'topic_feedback', 'topic_feedback:author:{author_id}' ),
		'feedback.get_by_reason_category'                 => array( 'topic_feedback' ),
		'feedback.get_reason_category_statistics'         => array( 'topic_feedback' ),
		'feedback.get_statistics_bulk'                    => array( 'topic_feedback' ),
		'feedback.get_latest_by_topics'                   => array( 'topic_feedback' ),

		// Dashboard: each read carries the broad tag of every table it reads, all
		// bumped in the aips_dashboard group through DEPENDENTS, plus
		// `dashboard_counts` so the `dashboard` domain can evict every read.
		'dashboard.get_summary_stats'                     => array( 'history', 'dashboard_counts' ),
		'dashboard.get_schedules_run_count'               => array( 'history', 'dashboard_counts' ),
		'dashboard.get_ai_stats'                          => array( 'history', 'dashboard_counts' ),
		'dashboard.get_daily_generation_stats'            => array( 'history', 'dashboard_counts' ),
		'dashboard.get_daily_ai_stats'                    => array( 'history', 'dashboard_counts' ),
		'dashboard.get_topics_stats'                      => array( 'author_topics', 'dashboard_counts' ),
		'dashboard.get_daily_topic_totals'                => array( 'author_topics', 'dashboard_counts' ),
		'dashboard.get_recent_topics'                     => array( 'author_topics', 'authors', 'dashboard_counts' ),
		'dashboard.get_upcoming_runs_count'               => array( 'schedules', 'dashboard_counts' ),
		'dashboard.get_recent_posts'                      => array( 'history', 'templates', 'dashboard_counts' ),
		'dashboard.get_posts_by_topic'                    => array( 'history', 'author_topics', 'authors', 'dashboard_counts' ),
		'dashboard.get_executed_schedules'                => array( 'history', 'schedules', 'templates', 'authors', 'dashboard_counts' ),

		// Templates & prompt building blocks.
		'templates.get_all'                               => array( 'templates' ),
		'templates.get_by_id'                             => array( 'templates', 'template:{template_id}' ),
		'article_structures.get_all'                      => array( 'article_structures' ),
		'article_structures.get_by_id'                    => array( 'article_structures', 'article_structure:{structure_id}' ),
		'prompt_sections.get_all'                         => array( 'prompt_sections' ),
		'prompt_sections.get_by_key'                      => array( 'prompt_sections' ),
		'prompt_sections.get_by_id'                       => array( 'prompt_sections', 'prompt_section:{section_id}' ),
		'voices.get_all'                                  => array( 'voices' ),
		'voices.get_by_id'                                => array( 'voices', 'voice:{voice_id}' ),
		'post_slices.get_all'                             => array( 'post_slices' ),
		'post_slices.get_by_id'                           => array( 'post_slices', 'post_slice:{slice_id}' ),

		// Schedules & campaigns.
		'schedules.get_all'                               => array( 'schedules' ),
		'schedules.get_due_schedules'                     => array( 'schedules' ),
		'schedules.get_active'                            => array( 'schedules' ),
		'schedules.get_by_id'                             => array( 'schedules', 'schedule:{schedule_id}' ),
		'campaigns.get_campaign_by_id'                    => array( 'campaigns', 'campaign:{campaign_id}' ),

		// History (get_schedule_completed_count reads aips_schedule; see DEPENDENTS).
		'history.get_stats'                               => array( 'history' ),
		'history.get_schedule_completed_count'            => array( 'history', 'history_schedule:{schedule_id}' ),

		// Metrics: read aips_history, aips_schedule, and aips_author_topics.
		'metrics.get_generation_metrics'                  => array( 'metrics', 'history' ),
		'metrics.get_queue_depth_metrics'                 => array( 'metrics', 'schedules', 'author_topics' ),
		'metrics.get_queue_health_metrics'                => array( 'metrics', 'history', 'schedules', 'author_topics' ),

		// Sources.
		'sources.get_all'                                 => array( 'sources' ),
		'sources.get_by_id'                               => array( 'sources', 'source:{id}' ),
		'sources.get_active_urls'                         => array( 'sources' ),
		'sources.get_source_term_ids'                     => array( 'sources', 'source:{source_id}' ),
		'sources.get_term_ids_for_sources'                => array( 'sources' ),
		'sources.get_urls_by_group_term_ids'              => array( 'sources' ),
		'sources.get_by_group_term_ids'                   => array( 'sources' ),
		'sources_data.get_count_by_source_id'             => array( 'sources_data', 'sources_data:source:{source_id}' ),
		'sources_data.get_counts_by_source_ids'           => array( 'sources_data' ),

		// Content enrichment.
		'affiliate_links.get_by_id'                       => array( 'affiliate_links', 'affiliate_link:{id}' ),
		'affiliate_links.get_all'                         => array( 'affiliate_links' ),
		'affiliate_links.get_enabled_by_tags'             => array( 'affiliate_links' ),
		'affiliate_links.get_paginated'                   => array( 'affiliate_links' ),
		'affiliate_links.get_paginated_count'             => array( 'affiliate_links' ),
		'internal_links.get_by_source_post'               => array( 'internal_links', 'internal_links:source:{source_post_id}' ),
		'internal_links.get_status_counts'                => array( 'internal_links' ),
		'taxonomy.get_by_type'                            => array( 'taxonomy' ),
		'taxonomy.get_by_status_and_type'                 => array( 'taxonomy' ),
		'taxonomy.get_by_id'                              => array( 'taxonomy', 'taxonomy:{id}' ),
		'taxonomy.get_status_counts'                      => array( 'taxonomy' ),
		'taxonomy.search'                                 => array( 'taxonomy' ),
		'trending_topics.get_all'                         => array( 'trending_topics' ),
		'trending_topics.get_by_id'                       => array( 'trending_topics', 'trending_topic:{id}' ),
		'trending_topics.get_by_niche'                    => array( 'trending_topics' ),
		'trending_topics.get_top_topics'                  => array( 'trending_topics' ),
		'trending_topics.search'                          => array( 'trending_topics' ),
		'trending_topics.get_stats'                       => array( 'trending_topics' ),
		'trending_topics.get_niche_list'                  => array( 'trending_topics' ),

		// AI assistance, notifications.
		'ai_assistance.get_by_session_and_field'          => array( 'ai_assistance' ),
		'ai_assistance.get_by_field'                      => array( 'ai_assistance' ),
		'notifications.get_unread'                        => array( 'notifications' ),
		'notifications.count_unread'                      => array( 'notifications' ),

		// Semantic index. `*_posts` tags mark reads that JOIN wp_posts; they are
		// bumped by AIPS_Post_Lifecycle_Cache_Invalidator on native post changes.
		'embeddings.get_by_object'                        => array( 'embeddings' ),
		'embeddings.get_by_post_ids'                      => array( 'embeddings' ),
		'embeddings.count'                                => array( 'embeddings' ),
		'embeddings.count_indexed_for_types'              => array( 'embeddings', 'embeddings_posts' ),
		'embeddings.get_stats'                            => array( 'embeddings' ),
		'embeddings.get_all_indexed_post_ids'             => array( 'embeddings' ),
		'embeddings.get_stored_dimensions'                => array( 'embeddings' ),
		'relationships.get_related'                       => array( 'relationships', 'relationships_posts' ),
		'relationships.get_interconnections'              => array( 'relationships' ),
		'relationships.get_top_duplicate_pairs'           => array( 'relationships', 'relationships_posts' ),
		'relationships.count'                             => array( 'relationships' ),

		// Integrations, content audits.
		'integration_mappings.get_by_template'            => array( 'integration_mappings' ),
		'integration_mappings.get_by_id'                  => array( 'integration_mappings' ),
		'content_audits.get_history'                      => array( 'content_audits' ),
		'content_audits.count'                            => array( 'content_audits' ),
	);

	/**
	 * Invalidation tag templates per domain, bumped in the writer's own group.
	 *
	 * @var array<string, string[]>
	 */
	const INVALIDATION_TAGS = array(
		'author'                   => array( 'authors', 'author_generation_schedule', 'dashboard_counts', 'unified_schedule', 'author:{author_id}' ),
		'author_topic'             => array( 'author_topics', 'dashboard_counts', 'author_topics:author:{author_id}', 'author_generation_summary:{author_id}', 'author_post_queue:{author_id}', 'author_topic:{topic_id}' ),
		'author_topic_removed'     => array( 'author_topics', 'dashboard_counts', 'author_topics:author:{author_id}', 'author_generation_summary:{author_id}', 'author_post_queue:{author_id}', 'author_topic:{topic_id}' ),
		'author_topic_log'         => array( 'author_topic_logs', 'author_topic_logs:topic:{author_topic_id}' ),
		'author_topic_generation_log' => array( 'author_topic_logs', 'author_topic_logs:topic:{author_topic_id}' ),
		'topic_feedback'           => array( 'topic_feedback', 'topic_feedback:topic:{author_topic_id}' ),
		'post_generation'          => array( 'author_topic_logs', 'history', 'dashboard_counts', 'unified_schedule', 'author:{author_id}', 'author_topics:author:{author_id}', 'author_topic:{topic_id}', 'post:{post_id}' ),
		'dashboard'                => array( 'dashboard_counts' ),
		'unified_schedule'         => array( 'unified_schedule' ),
		'template'                 => array( 'templates', 'unified_schedule', 'template:{template_id}' ),
		'article_structure'        => array( 'article_structures', 'article_structure:{structure_id}' ),
		'prompt_section'           => array( 'prompt_sections', 'prompt_section:{section_id}' ),
		'voice'                    => array( 'voices', 'voice:{voice_id}' ),
		'post_slice'               => array( 'post_slices', 'post_slice:{slice_id}' ),
		'schedule'                 => array( 'schedules', 'unified_schedule', 'schedule:{schedule_id}' ),
		'campaign'                 => array( 'campaigns', 'campaign:{campaign_id}' ),
		'history'                  => array( 'history', 'history_schedule:{schedule_id}' ),
		'metrics'                  => array( 'metrics' ),
		'source'                   => array( 'sources', 'source:{source_id}' ),
		'sources_data'             => array( 'sources_data', 'sources_data:source:{source_id}' ),
		'affiliate_link'           => array( 'affiliate_links', 'affiliate_link:{id}' ),
		'internal_link'            => array( 'internal_links', 'internal_links:source:{source_post_id}' ),
		'taxonomy'                 => array( 'taxonomy', 'taxonomy:{id}' ),
		'trending_topic'           => array( 'trending_topics', 'trending_topic:{id}' ),
		'ai_assistance'            => array( 'ai_assistance' ),
		'notifications'            => array( 'notifications' ),
		'embeddings'               => array( 'embeddings' ),
		'embeddings_posts'         => array( 'embeddings_posts' ),
		'relationships'            => array( 'relationships' ),
		'relationships_posts'      => array( 'relationships_posts' ),
		'integration_mappings'     => array( 'integration_mappings' ),
		'content_audits'           => array( 'content_audits' ),
	);

	/**
	 * Cross-group dependents: domain => [ repository class => tag templates ].
	 *
	 * Each entry names a repository whose cached reads JOIN the table the
	 * domain's writer owns; the listed tags are bumped in that repository's
	 * cache group (see AIPS_Cacheable_Repository::invalidate_cache_domain()).
	 *
	 * @var array<string, array<string, string[]>>
	 */
	const DEPENDENTS = array(
		'history'                     => array(
			'AIPS_Dashboard_Repository' => array( 'history' ),
			'AIPS_Metrics_Repository'   => array( 'history' ),
		),
		'author'                      => array(
			'AIPS_Dashboard_Repository'     => array( 'authors' ),
			// author_topics.get_all_approved_for_queue INNER JOINs aips_authors.
			'AIPS_Author_Topics_Repository' => array( 'author_topics' ),
		),
		'author_topic'                => array(
			'AIPS_Dashboard_Repository' => array( 'author_topics' ),
			'AIPS_Metrics_Repository'   => array( 'author_topics' ),
		),
		// Deletions additionally orphan rows that feedback and topic-log reads
		// INNER JOIN away (author_id is resolved through the topic).
		'author_topic_removed'        => array(
			'AIPS_Dashboard_Repository'         => array( 'author_topics' ),
			'AIPS_Metrics_Repository'           => array( 'author_topics' ),
			'AIPS_Feedback_Repository'          => array( 'topic_feedback' ),
			'AIPS_Author_Topic_Logs_Repository' => array( 'author_topic_logs' ),
		),
		// author_topics.get_status_counts / get_approved_for_generation LEFT JOIN
		// logs on action = 'post_generated'.
		'author_topic_generation_log' => array(
			'AIPS_Author_Topics_Repository' => array( 'author_topics', 'author_topics:author:{author_id}' ),
		),
		'schedule'                    => array(
			'AIPS_Dashboard_Repository' => array( 'schedules' ),
			'AIPS_Metrics_Repository'   => array( 'schedules' ),
			// history.get_schedule_completed_count reads the schedule's created_at.
			'AIPS_History_Repository'   => array( 'history_schedule:{schedule_id}' ),
		),
		'template'                    => array(
			'AIPS_Dashboard_Repository' => array( 'templates' ),
		),
		'post_generation'             => array(
			'AIPS_Dashboard_Repository' => array( 'history', 'author_topics' ),
			'AIPS_Metrics_Repository'   => array( 'history', 'author_topics' ),
		),
	);

	/**
	 * Resolve read tags for a repository cache operation.
	 *
	 * @param string $operation_id Repository cache operation ID.
	 * @param array  $args Operation arguments.
	 * @return array<int, string>
	 */
	public static function tags_for_read( string $operation_id, array $args = array() ): array {
		if (!isset( self::READ_TAGS[ $operation_id ] )) {
			return array();
		}

		return self::resolve_templates( self::READ_TAGS[ $operation_id ], $args );
	}

	/**
	 * Resolve invalidation tags for a cache domain.
	 *
	 * Unknown domains fall back to a single tag named after the domain.
	 *
	 * @param string $domain Domain name.
	 * @param array  $context Domain context.
	 * @return array<int, string>
	 */
	public static function tags_for_invalidation( string $domain, array $context = array() ): array {
		if (isset( self::INVALIDATION_TAGS[ $domain ] )) {
			return self::resolve_templates( self::INVALIDATION_TAGS[ $domain ], $context );
		}

		$domain = sanitize_key( $domain );
		return '' !== $domain ? array( $domain ) : array();
	}

	/**
	 * Resolve cross-group dependents for a cache domain.
	 *
	 * @param string $domain Domain name.
	 * @param array  $context Domain context.
	 * @return array<string, array<int, string>> Repository class => resolved tags.
	 */
	public static function dependents_for_invalidation( string $domain, array $context = array() ): array {
		if (!isset( self::DEPENDENTS[ $domain ] )) {
			return array();
		}

		$resolved = array();
		foreach ( self::DEPENDENTS[ $domain ] as $repository_class => $templates ) {
			$tags = self::resolve_templates( $templates, $context );
			if (!empty( $tags )) {
				$resolved[ $repository_class ] = $tags;
			}
		}

		return $resolved;
	}

	/**
	 * Whether the map declares read tags for an operation.
	 *
	 * @param string $operation_id Repository cache operation ID.
	 * @return bool
	 */
	public static function has_read_tags( string $operation_id ): bool {
		return isset( self::READ_TAGS[ $operation_id ] );
	}

	/**
	 * Resolve `{placeholder}` tag templates against arguments.
	 *
	 * A placeholder resolves only to a numeric value (cast to int). Templates
	 * with a missing or non-numeric placeholder are skipped.
	 *
	 * @param string[] $templates Tag templates.
	 * @param array    $values Placeholder values.
	 * @return array<int, string>
	 */
	private static function resolve_templates( array $templates, array $values ): array {
		$tags = array();

		foreach ( $templates as $template ) {
			$template = (string) $template;
			if (!preg_match_all( '/\{([a-z0-9_]+)\}/', $template, $matches )) {
				$tags[] = $template;
				continue;
			}

			$tag = $template;
			foreach ( $matches[1] as $placeholder ) {
				if (!isset( $values[ $placeholder ] ) || !is_numeric( $values[ $placeholder ] )) {
					$tag = null;
					break;
				}

				$tag = str_replace( '{' . $placeholder . '}', (string) (int) $values[ $placeholder ], $tag );
			}

			if (null !== $tag) {
				$tags[] = $tag;
			}
		}

		return self::unique_tags( $tags );
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
