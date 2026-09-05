<?php
/**
 * Structural regression tests for the Phase 1 (#1944) repository cache migration.
 *
 * Verifies that the Authors & Author-Topics feature repositories adopted both the
 * AIPS_Cacheable_Repository and AIPS_Repository_Tables traits, declare the expected
 * cache group, and register cache policies (each carrying the broad feature tag that
 * guarantees invalidation on writes). These assertions need only $wpdb->prefix, so
 * they run without database tables.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase1_Repository_Cache_Wiring extends WP_UnitTestCase {

	/**
	 * @dataProvider migrated_repository_provider
	 */
	public function test_repository_uses_shared_traits($class) {
		$traits = class_uses($class);

		$this->assertContains('AIPS_Cacheable_Repository', $traits, "$class should use AIPS_Cacheable_Repository.");
		$this->assertContains('AIPS_Repository_Tables', $traits, "$class should use AIPS_Repository_Tables.");
	}

	/**
	 * @dataProvider migrated_repository_provider
	 */
	public function test_repository_declares_group_and_broad_tagged_policies($class, $expected_group, $broad_tag, array $expected_ops) {
		$repo = new $class();

		$this->assertSame($expected_group, $this->invoke_protected($repo, 'repository_cache_group'));

		$policies = $this->invoke_protected($repo, 'repository_cache_policies');
		$this->assertIsArray($policies);

		foreach ($expected_ops as $op) {
			$this->assertArrayHasKey($op, $policies, "$class should declare a policy for $op.");
			$this->assertArrayHasKey('tags', $policies[$op], "$op policy should declare tags.");
			$this->assertContains(
				$broad_tag,
				$policies[$op]['tags'],
				"$op policy must carry the broad '$broad_tag' tag so writes invalidate it."
			);
		}
	}

	public function migrated_repository_provider() {
		return array(
			'feedback' => array(
				'AIPS_Feedback_Repository',
				'aips_topic_feedback',
				'topic_feedback',
				array(
					'feedback.get_by_topic',
					'feedback.get_by_author',
					'feedback.get_by_id',
					'feedback.get_statistics',
					'feedback.get_reason_category_statistics',
				),
			),
			'author_topic_logs' => array(
				'AIPS_Author_Topic_Logs_Repository',
				'aips_author_topic_logs',
				'author_topic_logs',
				array(
					'author_topic_logs.get_by_topic',
					'author_topic_logs.get_by_id',
					'author_topic_logs.count_generated_posts_by_author',
					'author_topic_logs.get_post_generation_counts_grouped_by_author',
				),
			),
			'trending_topics' => array(
				'AIPS_Trending_Topics_Repository',
				'aips_trending_topics',
				'trending_topics',
				array(
					'trending_topics.get_all',
					'trending_topics.get_by_id',
					'trending_topics.get_by_niche',
					'trending_topics.get_top_topics',
					'trending_topics.get_stats',
					'trending_topics.get_niche_list',
				),
			),
		);
	}

	/**
	 * Invoke a protected/private method for assertion purposes.
	 *
	 * @param object $object Target instance.
	 * @param string $method Method name.
	 * @return mixed
	 */
	private function invoke_protected($object, $method) {
		$ref = new ReflectionMethod($object, $method);
		$ref->setAccessible(true);
		return $ref->invoke($object);
	}
}
