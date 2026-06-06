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

	public function test_tags_for_read_returns_expected_tags_for_author_topics_get_by_author() {
		$this->assertSame(
			array( 'author_topics', 'author_topics:author:44' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'author_topics.get_by_author',
				array(
					'author_id' => 44,
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_author_topics_get_by_id() {
		$this->assertSame(
			array( 'author_topics', 'author_topics:author:44', 'author_topic:88' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'author_topics.get_by_id',
				array(
					'author_id' => 44,
					'topic_id'  => 88,
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_templates_count_by_status() {
		$this->assertSame(
			array( 'templates', 'dashboard_counts' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read( 'templates.count_by_status' )
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_schedules_get_by_template() {
		$this->assertSame(
			array( 'schedules', 'templates', 'unified_schedule', 'template:7' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'schedules.get_by_template',
				array(
					'template_id' => 7,
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_schedules_count_by_status() {
		$this->assertSame(
			array( 'schedules', 'dashboard_counts', 'unified_schedule' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read( 'schedules.count_by_status' )
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

	public function test_tags_for_invalidation_returns_template_domain_tags() {
		$this->assertSame(
			array(
				'templates',
				'dashboard_counts',
				'unified_schedule',
				'template:7',
				'campaign:3',
			),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'template',
				array(
					'template_id' => 7,
					'campaign_id' => 3,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_schedule_domain_tags() {
		$this->assertSame(
			array(
				'schedules',
				'dashboard_counts',
				'unified_schedule',
				'schedule:22',
				'template:7',
				'campaign:3',
			),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'schedule',
				array(
					'schedule_id' => 22,
					'template_id' => 7,
					'campaign_id' => 3,
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