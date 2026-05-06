<?php
/**
 * Tests for AIPS_Prompt_Template_Defaults
 *
 * Verifies the static registry that both repositories and the controller rely on.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Template_Defaults extends WP_UnitTestCase {

	// ----------------------------------------------------------------
	// Default group
	// ----------------------------------------------------------------

	/**
	 * get_default_group returns an array with the expected keys.
	 */
	public function test_get_default_group_has_required_keys() {
		$group = AIPS_Prompt_Template_Defaults::get_default_group();

		$this->assertArrayHasKey( 'name',        $group );
		$this->assertArrayHasKey( 'description', $group );
		$this->assertArrayHasKey( 'is_default',  $group );
		$this->assertSame( 1, $group['is_default'] );
		$this->assertNotEmpty( $group['name'] );
	}

	// ----------------------------------------------------------------
	// Component registry
	// ----------------------------------------------------------------

	/**
	 * get_components returns all seven expected component keys.
	 */
	public function test_get_components_contains_all_keys() {
		$components = AIPS_Prompt_Template_Defaults::get_components();

		$expected = array(
			'post_title',
			'post_excerpt',
			'post_content',
			'post_featured_image',
			'author_topic',
			'author_suggestions',
			'taxonomy',
		);

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $components, "Missing component: {$key}" );
		}
	}

	/**
	 * get_component returns null for an unknown key.
	 */
	public function test_get_component_returns_null_for_unknown_key() {
		$this->assertNull( AIPS_Prompt_Template_Defaults::get_component( 'non_existent' ) );
	}

	/**
	 * get_component_prompt returns empty string for an unknown key.
	 */
	public function test_get_component_prompt_unknown_key_returns_empty() {
		$this->assertSame( '', AIPS_Prompt_Template_Defaults::get_component_prompt( 'non_existent' ) );
	}

	/**
	 * post_content and post_featured_image have empty default prompts by design.
	 */
	public function test_post_content_and_image_default_prompts_are_empty() {
		$this->assertSame( '', AIPS_Prompt_Template_Defaults::get_component_prompt( 'post_content' ) );
		$this->assertSame( '', AIPS_Prompt_Template_Defaults::get_component_prompt( 'post_featured_image' ) );
	}

	/**
	 * Post title and excerpt have non-empty default prompts.
	 */
	public function test_post_title_and_excerpt_have_non_empty_defaults() {
		$this->assertNotEmpty( AIPS_Prompt_Template_Defaults::get_component_prompt( 'post_title' ) );
		$this->assertNotEmpty( AIPS_Prompt_Template_Defaults::get_component_prompt( 'post_excerpt' ) );
	}
}
