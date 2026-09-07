<?php
/**
 * Structural regression tests for the AIPS_Embeddings_Repository cache migration (#2053).
 *
 * Verifies that the polymorphic embeddings repository adopted both the
 * AIPS_Cacheable_Repository and AIPS_Repository_Tables traits, declares the
 * expected cache group, registers broad-tagged policies for every cached read,
 * carries the wp_posts-dependent tag on joined reads, deliberately leaves the
 * similarity / queue-cursor reads uncached, and that the invalidator hooks the
 * WordPress post lifecycle. These assertions need only $wpdb->prefix, so they
 * run without database tables.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Embeddings_Repository_Cache_Wiring extends WP_UnitTestCase {

	public function test_repository_uses_shared_traits() {
		$traits = class_uses(AIPS_Embeddings_Repository::class);

		$this->assertContains('AIPS_Cacheable_Repository', $traits);
		$this->assertContains('AIPS_Repository_Tables', $traits);
	}

	public function test_repository_declares_group() {
		$repo = new AIPS_Embeddings_Repository();

		$this->assertSame('aips_embeddings', $this->invoke_protected($repo, 'repository_cache_group'));
	}

	/**
	 * @dataProvider cached_operation_provider
	 */
	public function test_cached_operations_carry_broad_tag($op) {
		$policies = $this->policies();

		$this->assertArrayHasKey($op, $policies, "$op should declare a policy.");
		$this->assertArrayHasKey('tags', $policies[$op], "$op policy should declare tags.");
		$this->assertContains(
			AIPS_Embeddings_Repository::CACHE_TAG,
			$policies[$op]['tags'],
			"$op policy must carry the broad 'embeddings' tag so writes invalidate it."
		);
		$this->assertNotSame(
			'none',
			$policies[$op]['tier'] ?? 'none',
			"$op policy must use a real cache tier."
		);
	}

	public function cached_operation_provider() {
		return array(
			array('embeddings.get_by_object'),
			array('embeddings.get_by_post_ids'),
			array('embeddings.count'),
			array('embeddings.count_indexed_for_types'),
			array('embeddings.get_stats'),
			array('embeddings.get_all_indexed_post_ids'),
			array('embeddings.get_stored_dimensions'),
		);
	}

	/**
	 * Reads that JOIN wp_posts must additionally carry the post-dependent tag so
	 * AIPS_Embeddings_Cache_Invalidator can evict them without evicting the
	 * table-only reads.
	 */
	public function test_wp_posts_joined_reads_carry_posts_tag() {
		$policies = $this->policies();

		$this->assertContains(
			AIPS_Embeddings_Repository::CACHE_TAG_POSTS,
			$policies['embeddings.count_indexed_for_types']['tags']
		);
	}

	/**
	 * Table-only reads must NOT carry the post-dependent tag; otherwise every
	 * post save on the site would evict them for no reason.
	 */
	public function test_table_only_reads_do_not_carry_posts_tag() {
		$policies = $this->policies();

		foreach (array('embeddings.get_by_object', 'embeddings.get_by_post_ids', 'embeddings.count', 'embeddings.get_stats') as $op) {
			$this->assertNotContains(
				AIPS_Embeddings_Repository::CACHE_TAG_POSTS,
				$policies[$op]['tags'],
				"$op does not join wp_posts and must not carry the posts tag."
			);
		}
	}

	/**
	 * Full-vector similarity reads and the backfill queue cursor are documented
	 * as deliberately uncached. Guard against someone adding a policy without
	 * revisiting that rationale.
	 */
	public function test_deliberately_uncached_operations_have_no_policy() {
		$policies = $this->policies();

		foreach (array('embeddings.get_all_for_similarity', 'embeddings.get_all_for_similarity_by_type', 'embeddings.get_unindexed_post_ids') as $op) {
			$this->assertArrayNotHasKey($op, $policies, "$op is documented as uncached; see #2053 before adding a policy.");
		}
	}

	public function test_invalidator_registers_post_lifecycle_hooks() {
		$repo        = new AIPS_Embeddings_Repository();
		$invalidator = new AIPS_Embeddings_Cache_Invalidator($repo);

		$invalidator->register();

		try {
			$this->assertNotFalse(has_action('transition_post_status', array($invalidator, 'on_transition_post_status')));
			$this->assertNotFalse(has_action('deleted_post', array($invalidator, 'on_deleted_post')));
		} finally {
			remove_action('transition_post_status', array($invalidator, 'on_transition_post_status'), 10);
			remove_action('deleted_post', array($invalidator, 'on_deleted_post'), 10);
		}
	}

	public function test_invalidator_ignores_revisions_and_autosaves() {
		$repo = $this->getMockBuilder(AIPS_Embeddings_Repository::class)
			->onlyMethods(array('invalidate_post_dependent_reads'))
			->getMock();
		$repo->expects($this->never())->method('invalidate_post_dependent_reads');

		$invalidator = new AIPS_Embeddings_Cache_Invalidator($repo);

		$revision            = new WP_Post(new stdClass());
		$revision->ID        = 1;
		$revision->post_type = 'revision';

		$invalidator->on_transition_post_status('inherit', 'inherit', $revision);
		$invalidator->on_deleted_post(1, $revision);
		$invalidator->on_transition_post_status('publish', 'draft', 'not-a-post');
	}

	public function test_invalidator_bumps_on_real_post_changes() {
		$repo = $this->getMockBuilder(AIPS_Embeddings_Repository::class)
			->onlyMethods(array('invalidate_post_dependent_reads'))
			->getMock();
		$repo->expects($this->exactly(3))->method('invalidate_post_dependent_reads');

		$invalidator = new AIPS_Embeddings_Cache_Invalidator($repo);

		$post            = new WP_Post(new stdClass());
		$post->ID        = 2;
		$post->post_type = 'post';

		$invalidator->on_transition_post_status('trash', 'publish', $post);
		$invalidator->on_transition_post_status('publish', 'publish', $post); // same status: post_type may have changed
		$invalidator->on_deleted_post(2, null); // WP < 5.5 signature: no post object, still bump
	}

	private function policies() {
		return $this->invoke_protected(new AIPS_Embeddings_Repository(), 'repository_cache_policies');
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
