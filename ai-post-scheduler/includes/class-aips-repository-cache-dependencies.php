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
