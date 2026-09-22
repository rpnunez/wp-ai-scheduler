<?php
/**
 * Regression tests for the repository-trait follow-ups to #1944 / #2053.
 *
 * Covers:
 * - Relationships, Integration Mappings, and Content Auditor repositories
 *   adopting AIPS_Cacheable_Repository + AIPS_Repository_Tables.
 * - Cross-repository invalidation (tag versions are scoped per cache group).
 * - AIPS_Cache_Factory::flush_all() reaching named repository cache instances.
 * - AIPS_Post_Lifecycle_Cache_Invalidator lazy target resolution.
 * - History bulk clears invalidating cached stats.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Repository_Trait_Followups extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		AIPS_DB_Manager::install_tables();
	}

	public function tearDown(): void {
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * @dataProvider migrated_repository_provider
	 */
	public function test_repository_uses_shared_traits_and_group($class, $group) {
		$traits = class_uses($class);

		$this->assertContains('AIPS_Cacheable_Repository', $traits);
		$this->assertContains('AIPS_Repository_Tables', $traits);
		$this->assertSame($group, $this->invoke_protected(new $class(), 'repository_cache_group'));
	}

	public function migrated_repository_provider() {
		return array(
			array('AIPS_Relationships_Repository', 'aips_relationships'),
			array('AIPS_Integration_Mappings_Repository', 'aips_integration_mappings'),
			array('AIPS_Content_Auditor_Repository', 'aips_content_audits'),
		);
	}

	/**
	 * @dataProvider cached_operation_provider
	 */
	public function test_cached_operations_carry_broad_tag($class, $op, $tag) {
		$policies = $this->invoke_protected(new $class(), 'repository_cache_policies');

		$this->assertArrayHasKey($op, $policies, "$op should declare a policy.");
		$this->assertContains($tag, $policies[$op]['tags'], "$op must carry the broad '$tag' tag so writes invalidate it.");
		$this->assertNotSame('none', $policies[$op]['tier'] ?? 'none');
	}

	public function cached_operation_provider() {
		return array(
			array('AIPS_Relationships_Repository', 'relationships.get_related', 'relationships'),
			array('AIPS_Relationships_Repository', 'relationships.get_interconnections', 'relationships'),
			array('AIPS_Relationships_Repository', 'relationships.get_top_duplicate_pairs', 'relationships'),
			array('AIPS_Relationships_Repository', 'relationships.count', 'relationships'),
			array('AIPS_Integration_Mappings_Repository', 'integration_mappings.get_by_template', 'integration_mappings'),
			array('AIPS_Integration_Mappings_Repository', 'integration_mappings.get_by_id', 'integration_mappings'),
			array('AIPS_Content_Auditor_Repository', 'content_audits.get_history', 'content_audits'),
			array('AIPS_Content_Auditor_Repository', 'content_audits.count', 'content_audits'),
		);
	}

	public function test_relationship_reads_joining_wp_posts_carry_posts_tag() {
		$policies = $this->invoke_protected(new AIPS_Relationships_Repository(), 'repository_cache_policies');

		$this->assertContains(AIPS_Relationships_Repository::CACHE_TAG_POSTS, $policies['relationships.get_related']['tags']);
		$this->assertContains(AIPS_Relationships_Repository::CACHE_TAG_POSTS, $policies['relationships.get_top_duplicate_pairs']['tags']);
		$this->assertNotContains(AIPS_Relationships_Repository::CACHE_TAG_POSTS, $policies['relationships.count']['tags']);
	}

	public function test_content_audit_full_report_reads_stay_uncached() {
		$policies = $this->invoke_protected(new AIPS_Content_Auditor_Repository(), 'repository_cache_policies');

		$this->assertArrayNotHasKey('content_audits.get_by_id', $policies);
		$this->assertArrayNotHasKey('content_audits.get_latest', $policies);
	}

	/**
	 * The constructor previously declared `global $wpdb`, which rebound the
	 * parameter and discarded any injected instance.
	 */
	public function test_content_auditor_honors_injected_wpdb() {
		global $wpdb;
		$injected = clone $wpdb;

		$repo = new AIPS_Content_Auditor_Repository($injected);

		$prop = new ReflectionProperty($repo, 'wpdb');
		$prop->setAccessible(true);
		$this->assertSame($injected, $prop->getValue($repo));
	}

	public function test_content_audit_count_refreshes_after_save_and_delete() {
		$repo = new AIPS_Content_Auditor_Repository();
		$before = $repo->count();

		$id = $repo->save(array('niche' => 'Cache Niche', 'health_scorecard' => array()));
		$this->assertSame($before + 1, $repo->count());
		$this->assertCount(1, $repo->get_history(20, 0, 'Cache Niche'));

		$repo->delete($id);
		$this->assertSame($before, $repo->count());
		$this->assertCount(0, $repo->get_history(20, 0, 'Cache Niche'));
	}

	public function test_integration_mappings_sync_refreshes_cached_template_reads() {
		$repo = new AIPS_Integration_Mappings_Repository();

		$repo->sync_group_mappings(4242, 'acf', 'group_a', array(
			array('field_key' => 'field_one', 'is_active' => 1),
		));
		$this->assertCount(1, $repo->get_by_template(4242));

		$repo->sync_group_mappings(4242, 'acf', 'group_a', array(
			array('field_key' => 'field_one', 'is_active' => 1),
			array('field_key' => 'field_two', 'is_active' => 1),
		));
		$this->assertCount(2, $repo->get_by_template(4242));

		$repo->delete_by_template(4242);
		$this->assertCount(0, $repo->get_by_template(4242));
	}

	/**
	 * author_topics.get_status_counts LEFT JOINs aips_author_topic_logs but is
	 * cached in the aips_author_topics group. A post_generated log must evict it.
	 */
	public function test_post_generated_log_refreshes_author_topic_status_counts() {
		$topics = new AIPS_Author_Topics_Repository();
		$logs   = new AIPS_Author_Topic_Logs_Repository();

		$author_id = 9001;
		$topic_id  = $topics->create(array(
			'author_id'    => $author_id,
			'topic_title'  => 'Cross-group topic',
			'topic_prompt' => '',
			'status'       => 'approved',
		));
		$this->assertNotFalse($topic_id);

		$counts = $topics->get_status_counts($author_id);
		$this->assertSame(1, $counts['approved']);
		$this->assertSame(0, $counts['posts_generated']);

		$post_id = self::factory()->post->create();
		$logs->log_post_generation($topic_id, $post_id);

		$counts = $topics->get_status_counts($author_id);
		$this->assertSame(1, $counts['posts_generated'], 'Log write must invalidate the author-topics cache group.');
	}

	/**
	 * Topic-log counts INNER JOIN aips_author_topics; deleting the topic must
	 * evict the cached count held in the aips_author_topic_logs group.
	 */
	public function test_topic_delete_refreshes_log_counts() {
		$topics = new AIPS_Author_Topics_Repository();
		$logs   = new AIPS_Author_Topic_Logs_Repository();

		$author_id = 9002;
		$topic_id  = $topics->create(array(
			'author_id'    => $author_id,
			'topic_title'  => 'Soon deleted',
			'topic_prompt' => '',
			'status'       => 'approved',
		));
		$logs->log_post_generation($topic_id, self::factory()->post->create());

		$this->assertSame(1, (int) $logs->count_generated_posts_by_author($author_id));

		$topics->delete($topic_id);

		$this->assertSame(0, (int) $logs->count_generated_posts_by_author($author_id));
	}

	public function test_invalidate_repository_cache_tags_bumps_foreign_group() {
		$target = new AIPS_Relationships_Repository();
		$cache  = AIPS_Repository_Cache_Config::resolve_cache_instance('aips_relationships', array('tier' => 'medium'));
		$before = $cache->get_tag_versions(array('relationships'), 'aips_relationships');

		$source = new AIPS_Integration_Mappings_Repository();
		$method = new ReflectionMethod($source, 'invalidate_repository_cache_tags');
		$method->setAccessible(true);
		$method->invoke($source, 'AIPS_Relationships_Repository', array('relationships'), 'test');

		$after = $cache->get_tag_versions(array('relationships'), 'aips_relationships');
		$this->assertGreaterThan($before['relationships'], $after['relationships']);
		unset($target);
	}

	public function test_flush_all_clears_named_instances() {
		$named = AIPS_Cache_Factory::named('aips_followup_named', 'array');
		$named->set('k', 'v', 0, 'g');
		$this->assertSame('v', $named->get('k', 'g'));

		AIPS_Cache_Factory::flush_all();

		$this->assertNull($named->get('k', 'g'));
	}

	public function test_post_lifecycle_invalidator_resolves_default_targets_lazily() {
		$invalidator = new AIPS_Post_Lifecycle_Cache_Invalidator();

		$resolved = new ReflectionProperty($invalidator, 'resolved');
		$resolved->setAccessible(true);
		$this->assertNull($resolved->getValue($invalidator), 'No repository should be built before an event fires.');

		$post            = new WP_Post(new stdClass());
		$post->ID        = 3;
		$post->post_type = 'post';
		$invalidator->on_transition_post_status('publish', 'draft', $post);

		$classes = array_map('get_class', $resolved->getValue($invalidator));
		$this->assertContains('AIPS_Embeddings_Repository', $classes);
		$this->assertContains('AIPS_Relationships_Repository', $classes);
	}

	public function test_history_delete_by_status_refreshes_stats() {
		$repo = new AIPS_History_Repository();
		$repo->create(array('status' => 'failed', 'template_id' => 1, 'creation_method' => 'manual'));

		$before = $repo->get_stats();
		$this->assertGreaterThanOrEqual(1, $before['failed']);

		$repo->delete_by_status('failed');

		$after = $repo->get_stats();
		$this->assertSame(0, $after['failed']);
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
