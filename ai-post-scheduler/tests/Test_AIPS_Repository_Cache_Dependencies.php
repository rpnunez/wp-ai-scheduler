<?php
/**
 * Tests for AIPS_Repository_Cache_Dependencies.
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('AIPS_Repository_Cache_Dependencies')) {
	require_once dirname(__DIR__) . '/includes/class-aips-repository-cache-dependencies.php';
}

class Test_AIPS_Repository_Cache_Dependencies extends WP_UnitTestCase {

	public function test_tags_for_read_returns_expected_tags_for_authors_get_all() {
		$this->assertSame(
			array( 'authors' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read( 'authors.get_all' )
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_authors_get_by_id() {
		$this->assertSame(
			array( 'authors', 'author:44' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'authors.get_by_id',
				array(
					'author_id' => 44,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_author_domain_tags() {
		$this->assertSame(
			array(
				'authors',
				'author_generation_schedule',
				'dashboard_counts',
				'unified_schedule',
				'author:12',
			),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'author',
				array(
					'author_id' => 12,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_author_topic_domain_tags() {
		$this->assertSame(
			array(
				'author_topics',
				'dashboard_counts',
				'author_topics:author:12',
				'author_generation_summary:12',
				'author_post_queue:12',
				'author_topic:88',
			),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'author_topic',
				array(
					'author_id' => 12,
					'topic_id'  => 88,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_post_generation_domain_tags() {
		$this->assertSame(
			array(
				'author_topic_logs',
				'history',
				'dashboard_counts',
				'unified_schedule',
				'author:12',
				'author_topics:author:12',
				'author_topic:88',
				'post:99',
			),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'post_generation',
				array(
					'author_id' => 12,
					'topic_id'  => 88,
					'post_id'   => 99,
				)
			)
		);
	}

	public function test_tags_for_invalidation_falls_back_to_domain_tag_for_unknown_domain() {
		$this->assertSame(
			array( 'custom_domain' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation( 'custom_domain' )
		);
	}
}
