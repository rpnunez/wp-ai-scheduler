<?php
/**
 * Tests for AIPS_Prompt_Template_Group_Repository
 *
 * Covers runtime prompt resolution and the static cache behaviour.
 * Component-definition and item-level tests live in
 * Test_AIPS_Prompt_Template_Item_Repository.
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
$this->resetSingleton();
$this->repo = new AIPS_Prompt_Template_Group_Repository();
}

public function tearDown(): void {
$this->resetSingleton();
parent::tearDown();
}

// ----------------------------------------------------------------
// get_prompt_for_component — fallback when no DB groups exist
// ----------------------------------------------------------------

/**
 * When no group exists in the DB the built-in default is returned.
 */
public function test_get_prompt_for_component_falls_back_to_built_in_default() {
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

/**
 * get_prompt_for_component returns empty string (the built-in default) for
 * post_content when no DB group exists (empty default preserves backwards compat).
 */
public function test_get_prompt_for_component_post_content_fallback_is_empty() {
$prompt = $this->repo->get_prompt_for_component( 'post_content' );

$this->assertSame( '', $prompt );
}

/**
 * get_prompt_for_component returns empty string for post_featured_image when no DB group.
 */
public function test_get_prompt_for_component_post_featured_image_fallback_is_empty() {
$prompt = $this->repo->get_prompt_for_component( 'post_featured_image' );

$this->assertSame( '', $prompt );
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
// Helpers
// ----------------------------------------------------------------

/**
 * Reset the singleton instances and prompt cache via reflection.
 *
 * @return void
 */
private function resetSingleton() {
foreach ( array( 'AIPS_Prompt_Template_Group_Repository', 'AIPS_Prompt_Template_Item_Repository' ) as $class ) {
try {
$ref = new ReflectionProperty( $class, 'instance' );
$ref->setAccessible( true );
$ref->setValue( null, null );
} catch ( ReflectionException $e ) {
// Class not loaded yet — nothing to reset.
}
}

try {
$ref_cache = new ReflectionProperty( 'AIPS_Prompt_Template_Group_Repository', 'prompt_cache' );
$ref_cache->setAccessible( true );
$ref_cache->setValue( null, null );
} catch ( ReflectionException $e ) {
// Ignore.
}
}
}
