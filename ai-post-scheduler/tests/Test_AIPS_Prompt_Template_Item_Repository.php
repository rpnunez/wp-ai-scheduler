<?php
/**
 * Tests for AIPS_Prompt_Template_Item_Repository
 *
 * Covers component definition lookup, built-in default fallback, and the
 * stub-generation behaviour of get_items_for_group().
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Template_Item_Repository extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Template_Item_Repository
	 */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		if ( function_exists( 'dbDelta' ) ) {
			AIPS_DB_Manager::install_tables();
		}
		$this->resetSingleton();
		$this->repo = new AIPS_Prompt_Template_Item_Repository();
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

		$expected_keys = array(
			'post_title',
			'post_excerpt',
			'post_content',
			'post_featured_image',
			'author_topic',
			'author_suggestions',
			'taxonomy',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $defs, "Missing component key: {$key}" );
		}
	}

	/**
	 * Each component definition has the required structural fields.
	 */
	public function test_component_definitions_have_required_fields() {
		$defs = $this->repo->get_component_definitions();

		foreach ( $defs as $key => $def ) {
			$this->assertArrayHasKey( 'key',            $def, "Missing 'key' field for {$key}" );
			$this->assertArrayHasKey( 'label',          $def, "Missing 'label' field for {$key}" );
			$this->assertArrayHasKey( 'description',    $def, "Missing 'description' field for {$key}" );
			$this->assertArrayHasKey( 'default_prompt', $def, "Missing 'default_prompt' field for {$key}" );
			// default_prompt may legitimately be empty (post_content, post_featured_image).
			$this->assertIsString( $def['default_prompt'], "default_prompt must be a string for {$key}" );
		}
	}

	// ----------------------------------------------------------------
	// Built-in default prompts
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
	 * get_default_prompt returns empty string for post_content (optional prefix).
	 */
	public function test_get_default_prompt_post_content_is_empty() {
		$prompt = $this->repo->get_default_prompt( 'post_content' );

		$this->assertSame( '', $prompt );
	}

	/**
	 * get_default_prompt returns empty string for post_featured_image (optional prefix).
	 */
	public function test_get_default_prompt_post_featured_image_is_empty() {
		$prompt = $this->repo->get_default_prompt( 'post_featured_image' );

		$this->assertSame( '', $prompt );
	}

	/**
	 * get_default_prompt returns empty string for an unknown key.
	 */
	public function test_get_default_prompt_unknown_key_returns_empty_string() {
		$prompt = $this->repo->get_default_prompt( 'totally_unknown_key' );

		$this->assertSame( '', $prompt );
	}

	// ----------------------------------------------------------------
	// get_items_for_group — stub behaviour
	// ----------------------------------------------------------------

	/**
	 * get_items_for_group returns a stub for every known component (including the
	 * two new ones) even when the DB returns no rows.
	 */
	public function test_get_items_for_group_returns_all_components_as_stubs() {
		$items = $this->repo->get_items_for_group( 999 );

		$this->assertIsArray( $items );

		$expected_keys = array(
			'post_title',
			'post_excerpt',
			'post_content',
			'post_featured_image',
			'author_topic',
			'author_suggestions',
			'taxonomy',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $items, "Stub missing for component: {$key}" );
			$this->assertEquals( $key, $items[ $key ]->component_key );
		}
	}

	/**
	 * Stubs for post_content and post_featured_image have empty prompt_text
	 * (matching the empty built-in default — preserves backward compatibility).
	 */
	public function test_post_content_and_image_stubs_have_empty_prompt_text() {
		$items = $this->repo->get_items_for_group( 999 );

		$this->assertSame( '', $items['post_content']->prompt_text );
		$this->assertSame( '', $items['post_featured_image']->prompt_text );
	}

	// ----------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------

	/**
	 * Reset the singleton instance via reflection.
	 *
	 * @return void
	 */
	private function resetSingleton() {
		try {
			$ref = new ReflectionProperty( 'AIPS_Prompt_Template_Item_Repository', 'instance' );
			$ref->setAccessible( true );
			$ref->setValue( null, null );
		} catch ( ReflectionException $e ) {
			// Class not loaded yet — nothing to reset.
		}
	}
}
