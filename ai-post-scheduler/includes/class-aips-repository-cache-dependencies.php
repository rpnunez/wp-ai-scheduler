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

				case 'template':
					return self::tags_for_template_invalidation( $context );

				case 'schedule':
					return self::tags_for_schedule_invalidation( $context );

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