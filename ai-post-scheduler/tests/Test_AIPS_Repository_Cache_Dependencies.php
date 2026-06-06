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

	public function test_tags_for_read_returns_expected_tags_for_voices_get_by_id() {
		$this->assertSame(
			array( 'voices', 'voice:44' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'voices.get_by_id',
				array(
					'voice_id' => 44,
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_article_structures_get_by_id() {
		$this->assertSame(
			array( 'article_structures', 'article_structure:44' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'article_structures.get_by_id',
				array(
					'structure_id' => 44,
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_prompt_sections_get_by_keys() {
		$this->assertSame(
			array( 'prompt_sections', 'prompt_section:key:intro', 'prompt_section:key:body' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'prompt_sections.get_by_keys',
				array(
					'section_keys' => array( 'intro', 'body' ),
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_sources_get_by_id() {
		$this->assertSame(
			array( 'sources', 'source:44' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'sources.get_by_id',
				array(
					'source_id' => 44,
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_sources_data_get_counts_by_source_ids() {
		$this->assertSame(
			array( 'sources_data', 'sources', 'source_data:source:3', 'source:3', 'source_data:source:7', 'source:7' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'sources_data.get_counts_by_source_ids',
				array(
					'source_ids' => array( 3, 7 ),
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_internal_links_get_by_source_post() {
		$this->assertSame(
			array( 'internal_links', 'internal_links:source:99', 'post:99', 'internal_links:status:pending' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'internal_links.get_by_source_post',
				array(
					'source_post_id' => 99,
					'status'         => 'pending',
				)
			)
		);
	}

	public function test_tags_for_read_returns_expected_tags_for_taxonomy_get_by_status_and_type() {
		$this->assertSame(
			array( 'taxonomy', 'taxonomy_type:category', 'taxonomy_status:approved' ),
			AIPS_Repository_Cache_Dependencies::tags_for_read(
				'taxonomy.get_by_status_and_type',
				array(
					'status'        => 'approved',
					'taxonomy_type' => 'category',
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

	public function test_tags_for_invalidation_returns_voice_domain_tags() {
		$this->assertSame(
			array( 'voices', 'voice:12' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'voice',
				array(
					'voice_id' => 12,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_article_structure_domain_tags() {
		$this->assertSame(
			array( 'article_structures', 'article_structure:12' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'article_structure',
				array(
					'structure_id' => 12,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_prompt_section_domain_tags() {
		$this->assertSame(
			array( 'prompt_sections', 'prompt_section:88', 'prompt_section:key:intro' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'prompt_section',
				array(
					'section_id'  => 88,
					'section_key' => 'intro',
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_source_domain_tags() {
		$this->assertSame(
			array( 'sources', 'source:12', 'source_data:source:12', 'source_group_term:3', 'source_group_term:7' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'source',
				array(
					'source_id' => 12,
					'term_ids'  => array( 3, 7 ),
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_source_data_domain_tags() {
		$this->assertSame(
			array( 'sources_data', 'sources', 'source_data:source:12', 'source:12' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'source_data',
				array(
					'source_id' => 12,
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_internal_link_domain_tags() {
		$this->assertSame(
			array(
				'internal_links',
				'dashboard_counts',
				'internal_link:22',
				'internal_links:source:99',
				'post:99',
				'internal_links:target:105',
				'post:105',
				'internal_links:status:accepted',
			),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'internal_link',
				array(
					'internal_link_id' => 22,
					'source_post_id'   => 99,
					'target_post_id'   => 105,
					'status'           => 'accepted',
				)
			)
		);
	}

	public function test_tags_for_invalidation_returns_taxonomy_domain_tags() {
		$this->assertSame(
			array( 'taxonomy', 'dashboard_counts', 'taxonomy_item:31', 'taxonomy_type:post_tag', 'taxonomy_status:pending' ),
			AIPS_Repository_Cache_Dependencies::tags_for_invalidation(
				'taxonomy',
				array(
					'taxonomy_id'   => 31,
					'taxonomy_type' => 'post_tag',
					'status'        => 'pending',
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