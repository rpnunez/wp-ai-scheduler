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
		switch ($operation_id) {
			case 'authors.get_all':
				return array( 'authors' );

			case 'authors.get_by_id':
				$tags = array( 'authors' );
				if (isset( $args['author_id'] ) && is_numeric( $args['author_id'] )) {
					$tags[] = 'author:' . (int) $args['author_id'];
				}
				return $tags;

			case 'author_topics.get_by_author':
				return self::author_topic_read_tags( $args );

			case 'author_topics.get_by_id':
				$tags = array( 'author_topics' );
				if (isset( $args['author_id'] ) && is_numeric( $args['author_id'] )) {
					$tags[] = 'author_topics:author:' . (int) $args['author_id'];
				}
				if (isset( $args['topic_id'] ) && is_numeric( $args['topic_id'] )) {
					$tags[] = 'author_topic:' . (int) $args['topic_id'];
				}
				return self::unique_tags( $tags );

			case 'author_topics.get_approved_summary':
				return self::unique_tags(
					array_merge(
						self::author_topic_read_tags( $args ),
						self::author_id_tag( 'author_generation_summary', $args )
					)
				);

			case 'author_topics.get_rejected_summary':
			case 'author_topics.get_status_counts':
				return self::author_topic_read_tags( $args );

			case 'author_topics.get_global_status_counts':
			case 'author_topics.get_counts_grouped_by_author':
			case 'author_topics.get_daily_topic_counts':
				return array( 'author_topics', 'dashboard_counts' );

			case 'author_topics.get_approved_for_generation':
				return self::unique_tags(
					array_merge(
						self::author_topic_read_tags( $args ),
						self::author_id_tag( 'author_post_queue', $args )
					)
				);

			case 'author_topics.get_all_approved_for_queue':
				return array( 'author_topics', 'dashboard_counts' );

				case 'voices.get_all':
				case 'voices.search':
					return array( 'voices' );

				case 'voices.get_by_id':
					$tags = array( 'voices' );
					if (isset( $args['voice_id'] ) && is_numeric( $args['voice_id'] )) {
						$tags[] = 'voice:' . (int) $args['voice_id'];
					}
					return self::unique_tags( $tags );

				case 'article_structures.get_all':
					return array( 'article_structures' );

				case 'article_structures.get_by_id':
					$tags = array( 'article_structures' );
					if (isset( $args['structure_id'] ) && is_numeric( $args['structure_id'] )) {
						$tags[] = 'article_structure:' . (int) $args['structure_id'];
					}
					return self::unique_tags( $tags );

				case 'article_structures.count_by_status':
					return array( 'article_structures', 'dashboard_counts' );

				case 'article_structures.name_exists':
					return array( 'article_structures' );

				case 'prompt_sections.get_all':
					return array( 'prompt_sections' );

				case 'prompt_sections.get_by_id':
					$tags = array( 'prompt_sections' );
					if (isset( $args['section_id'] ) && is_numeric( $args['section_id'] )) {
						$tags[] = 'prompt_section:' . (int) $args['section_id'];
					}
					return self::unique_tags( $tags );

				case 'prompt_sections.get_by_key':
					$tags = array( 'prompt_sections' );
					if (isset( $args['section_key'] ) && is_scalar( $args['section_key'] )) {
						$tags[] = 'prompt_section:key:' . sanitize_key( (string) $args['section_key'] );
					}
					return self::unique_tags( $tags );

				case 'prompt_sections.get_by_keys':
					return self::prompt_section_key_tags( $args );

				case 'prompt_sections.count_by_status':
					return array( 'prompt_sections', 'dashboard_counts' );

				case 'prompt_sections.key_exists':
					return array( 'prompt_sections' );

				case 'templates.get_all':
				case 'templates.search':
				case 'templates.name_exists':
					return array( 'templates' );

				case 'templates.get_by_id':
					$tags = array( 'templates' );
					if (isset( $args['template_id'] ) && is_numeric( $args['template_id'] )) {
						$tags[] = 'template:' . (int) $args['template_id'];
					}
					return self::unique_tags( $tags );

				case 'templates.count_by_campaign':
					return self::unique_tags(
						array_merge(
							array( 'templates' ),
							self::campaign_id_tag( 'campaign', $args )
						)
					);

				case 'templates.count_by_status':
					return array( 'templates', 'dashboard_counts' );

				case 'schedules.get_all':
				case 'schedules.get_due':
				case 'schedules.get_upcoming':
					return array( 'schedules', 'templates', 'unified_schedule' );

				case 'schedules.get_by_id':
					$tags = array( 'schedules', 'unified_schedule' );
					if (isset( $args['schedule_id'] ) && is_numeric( $args['schedule_id'] )) {
						$tags[] = 'schedule:' . (int) $args['schedule_id'];
					}
					return self::unique_tags( $tags );

				case 'schedules.get_by_template':
				case 'schedules.get_active_by_template':
					return self::unique_tags(
						array_merge(
							array( 'schedules', 'templates', 'unified_schedule' ),
							self::template_id_tag( 'template', $args )
						)
					);

				case 'schedules.count_by_campaign':
					return self::unique_tags(
						array_merge(
							array( 'schedules', 'unified_schedule' ),
							self::campaign_id_tag( 'campaign', $args )
						)
					);

				case 'schedules.get_campaign_owned_ids':
					return array( 'schedules', 'unified_schedule' );

				case 'schedules.get_active':
					return array( 'schedules', 'unified_schedule' );

				case 'schedules.count_by_status':
					return array( 'schedules', 'dashboard_counts', 'unified_schedule' );

				case 'schedules.get_post_count_for_schedules':
					return array( 'schedules', 'templates', 'unified_schedule' );

							case 'sources.get_all':
							case 'sources.get_active_urls':
							case 'sources.url_exists':
								return array( 'sources' );

							case 'sources.get_by_id':
							case 'sources.get_source_term_ids':
								$tags = array( 'sources' );
								if (isset( $args['source_id'] ) && is_numeric( $args['source_id'] )) {
									$tags[] = 'source:' . (int) $args['source_id'];
								}
								return self::unique_tags( $tags );

							case 'sources.get_term_ids_for_sources':
								return self::source_ids_tags( $args );

							case 'sources.get_urls_by_group_term_ids':
							case 'sources.get_by_group_term_ids':
								return self::source_group_term_tags( $args );

							case 'sources_data.get_by_source_id':
							case 'sources_data.get_latest_success_by_source_id':
							case 'sources_data.get_count_by_source_id':
								$tags = array( 'sources_data', 'sources' );
								if (isset( $args['source_id'] ) && is_numeric( $args['source_id'] )) {
									$source_id = (int) $args['source_id'];
									$tags[] = 'source_data:source:' . $source_id;
									$tags[] = 'source:' . $source_id;
								}
								return self::unique_tags( $tags );

							case 'sources_data.get_by_source_ids':
							case 'sources_data.get_extracted_texts_by_source_ids':
							case 'sources_data.pick_next_for_prompt_bulk':
							case 'sources_data.get_counts_by_source_ids':
								return self::source_data_source_ids_tags( $args );

							case 'internal_links.get_by_id':
								$tags = array( 'internal_links' );
								if (isset( $args['internal_link_id'] ) && is_numeric( $args['internal_link_id'] )) {
									$tags[] = 'internal_link:' . (int) $args['internal_link_id'];
								}
								return self::unique_tags( $tags );

							case 'internal_links.get_by_source_post':
								$tags = array( 'internal_links' );
								if (isset( $args['source_post_id'] ) && is_numeric( $args['source_post_id'] )) {
									$tags[] = 'internal_links:source:' . (int) $args['source_post_id'];
									$tags[] = 'post:' . (int) $args['source_post_id'];
								}
								if (!empty( $args['status'] ) && is_scalar( $args['status'] )) {
									$tags[] = 'internal_links:status:' . sanitize_key( (string) $args['status'] );
								}
								return self::unique_tags( $tags );

							case 'internal_links.get_paginated':
							case 'internal_links.get_paginated_count':
							case 'internal_links.get_status_counts':
								return array( 'internal_links', 'dashboard_counts' );

							case 'internal_links.exists':
								$tags = array( 'internal_links' );
								if (isset( $args['source_post_id'] ) && is_numeric( $args['source_post_id'] )) {
									$tags[] = 'internal_links:source:' . (int) $args['source_post_id'];
								}
								if (isset( $args['target_post_id'] ) && is_numeric( $args['target_post_id'] )) {
									$tags[] = 'internal_links:target:' . (int) $args['target_post_id'];
								}
								return self::unique_tags( $tags );

							case 'taxonomy.get_by_type':
								$tags = array( 'taxonomy' );
								if (!empty( $args['taxonomy_type'] ) && is_scalar( $args['taxonomy_type'] )) {
									$tags[] = 'taxonomy_type:' . sanitize_key( (string) $args['taxonomy_type'] );
								}
								return self::unique_tags( $tags );

							case 'taxonomy.get_by_status_and_type':
								$tags = array( 'taxonomy' );
								if (!empty( $args['taxonomy_type'] ) && is_scalar( $args['taxonomy_type'] )) {
									$tags[] = 'taxonomy_type:' . sanitize_key( (string) $args['taxonomy_type'] );
								}
								if (!empty( $args['status'] ) && is_scalar( $args['status'] )) {
									$tags[] = 'taxonomy_status:' . sanitize_key( (string) $args['status'] );
								}
								return self::unique_tags( $tags );

							case 'taxonomy.get_by_id':
								$tags = array( 'taxonomy' );
								if (isset( $args['taxonomy_id'] ) && is_numeric( $args['taxonomy_id'] )) {
									$tags[] = 'taxonomy_item:' . (int) $args['taxonomy_id'];
								}
								return self::unique_tags( $tags );

							case 'taxonomy.get_status_counts':
								return array( 'taxonomy', 'dashboard_counts' );

							case 'taxonomy.search':
								return array( 'taxonomy' );

			default:
				return array();
		}
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