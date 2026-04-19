<?php
/**
 * Tests for AIPS_Prompt_Template_Group_Repository
 *
 * Covers component definition lookup, built-in default fallback, and the
 * static prompt cache behaviour.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Template_Group_Repository extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Template_Group_Repository
	 */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		// Reset the static singleton and prompt cache between tests.
		$this->resetSingleton();
		$this->repo = new AIPS_Prompt_Template_Group_Repository();
	}

	public function tearDown(): void {
		$this->resetSingleton();
		parent::tearDown();
	}

	// ----------------------------------------------------------------
	// Component definitions
	// ----------------------------------------------------------------

	/**
	 * All expected component keys are returned by get_component_definitions().
	 */
	public function test_get_component_definitions_returns_all_known_keys() {
		$defs = $this->repo->get_component_definitions();

		$expected_keys = array( 'post_title', 'post_excerpt', 'author_topic', 'author_suggestions', 'taxonomy' );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $defs, "Missing component key: {$key}" );
		}
	}

	/**
	 * Each component definition has the required fields.
	 */
	public function test_component_definitions_have_required_fields() {
		$defs = $this->repo->get_component_definitions();

		foreach ( $defs as $key => $def ) {
			$this->assertArrayHasKey( 'key',            $def, "Missing 'key' field for {$key}" );
			$this->assertArrayHasKey( 'label',          $def, "Missing 'label' field for {$key}" );
			$this->assertArrayHasKey( 'description',    $def, "Missing 'description' field for {$key}" );
			$this->assertArrayHasKey( 'default_prompt', $def, "Missing 'default_prompt' field for {$key}" );
			$this->assertNotEmpty( $def['default_prompt'], "Empty default_prompt for {$key}" );
		}
	}

	// ----------------------------------------------------------------
	// Built-in default fallback
	// ----------------------------------------------------------------

	/**
	 * get_default_prompt returns the built-in text for post_title.
	 */
	public function test_get_default_prompt_post_title() {
		$prompt = $this->repo->get_default_prompt( 'post_title' );

		$this->assertStringContainsString( 'Generate a title for a blog post', $prompt );
		$this->assertStringContainsString( 'ONLY the most relevant title', $prompt );
	}

	/**
	 * get_default_prompt returns the built-in text for post_excerpt.
	 */
	public function test_get_default_prompt_post_excerpt() {
		$prompt = $this->repo->get_default_prompt( 'post_excerpt' );

		$this->assertStringContainsString( 'Write an excerpt for an article', $prompt );
		$this->assertStringContainsString( '40 and 60 words', $prompt );
	}

	/**
	 * get_default_prompt returns empty string for an unknown key.
	 */
	public function test_get_default_prompt_unknown_key_returns_empty_string() {
		$prompt = $this->repo->get_default_prompt( 'totally_unknown_key' );

		$this->assertSame( '', $prompt );
	}

	// ----------------------------------------------------------------
	// get_prompt_for_component — fallback when no DB groups exist
	// ----------------------------------------------------------------

	/**
	 * When no group exists in the DB the built-in default is returned.
	 *
	 * The mock $wpdb returns null for get_row and [] for get_results so no
	 * group is ever found, forcing the fallback path.
	 */
	public function test_get_prompt_for_component_falls_back_to_built_in_default() {
		// The mock wpdb returns null for get_row — no default group found.
		$prompt = $this->repo->get_prompt_for_component( 'post_title' );

		$this->assertStringContainsString( 'Generate a title for a blog post', $prompt );
	}

	/**
	 * get_prompt_for_component returns built-in default for post_excerpt when no DB group.
	 */
	public function test_get_prompt_for_component_post_excerpt_fallback() {
		$prompt = $this->repo->get_prompt_for_component( 'post_excerpt' );

		$this->assertStringContainsString( 'Write an excerpt for an article', $prompt );
	}

	/**
	 * get_prompt_for_component returns built-in default for author_topic when no DB group.
	 */
	public function test_get_prompt_for_component_author_topic_fallback() {
		$prompt = $this->repo->get_prompt_for_component( 'author_topic' );

		$this->assertStringContainsString( 'Requirements', $prompt );
	}

	/**
	 * get_prompt_for_component returns built-in default for taxonomy when no DB group.
	 */
	public function test_get_prompt_for_component_taxonomy_fallback() {
		$prompt = $this->repo->get_prompt_for_component( 'taxonomy' );

		$this->assertStringContainsString( '{type_label}', $prompt );
	}

	// ----------------------------------------------------------------
	// Static prompt cache
	// ----------------------------------------------------------------

	/**
	 * Calling get_prompt_for_component twice returns the same result (from cache).
	 */
	public function test_prompt_cache_returns_consistent_result() {
		$first  = $this->repo->get_prompt_for_component( 'post_title' );
		$second = $this->repo->get_prompt_for_component( 'post_title' );

		$this->assertSame( $first, $second );
	}

	/**
	 * flush_prompt_cache clears the static cache so the next call re-queries.
	 */
	public function test_flush_prompt_cache_clears_static_cache() {
		// Populate the cache.
		$this->repo->get_prompt_for_component( 'post_title' );

		// Flush it.
		$this->repo->flush_prompt_cache();

		// After flush, the result is still the built-in default (since no DB group).
		$prompt = $this->repo->get_prompt_for_component( 'post_title' );
		$this->assertStringContainsString( 'Generate a title for a blog post', $prompt );
	}

	// ----------------------------------------------------------------
	// get_items_for_group — stub behaviour
	// ----------------------------------------------------------------

	/**
	 * get_items_for_group returns a stub for every known component even when the
	 * DB returns no rows (group_id does not matter since wpdb mock returns []).
	 */
	public function test_get_items_for_group_returns_all_components_as_stubs() {
		$items = $this->repo->get_items_for_group( 999 );

		$this->assertIsArray( $items );

		$expected_keys = array( 'post_title', 'post_excerpt', 'author_topic', 'author_suggestions', 'taxonomy' );
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $items, "Stub missing for component: {$key}" );
			$this->assertEquals( $key, $items[ $key ]->component_key );
		}
	}

	// ----------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------

	/**
	 * Reset the singleton instance and prompt cache via reflection.
	 *
	 * @return void
	 */
	private function resetSingleton() {
		try {
			$ref_instance = new ReflectionProperty( 'AIPS_Prompt_Template_Group_Repository', 'instance' );
			$ref_instance->setAccessible( true );
			$ref_instance->setValue( null, null );

			$ref_cache = new ReflectionProperty( 'AIPS_Prompt_Template_Group_Repository', 'prompt_cache' );
			$ref_cache->setAccessible( true );
			$ref_cache->setValue( null, null );
		} catch ( ReflectionException $e ) {
			// Class not loaded yet — nothing to reset.
		}
	}
}
