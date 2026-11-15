<?php
/**
 * Tests for AIPS_Prompt_Registry
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Registry extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Registry
	 */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry = new AIPS_Prompt_Registry();
	}

	/**
	 * Every documented prompt type from the registry constants must be
	 * catalogued and resolvable.
	 */
	public function test_all_catalog_constants_are_registered_and_resolvable() {
		$constants = array(
			AIPS_Prompt_Registry::TYPE_POST_CONTENT,
			AIPS_Prompt_Registry::TYPE_SECTION_CONTENT,
			AIPS_Prompt_Registry::TYPE_POST_TITLE,
			AIPS_Prompt_Registry::TYPE_POST_EXCERPT,
			AIPS_Prompt_Registry::TYPE_POST_SLUG,
			AIPS_Prompt_Registry::TYPE_TAXONOMIES,
			AIPS_Prompt_Registry::TYPE_FEATURED_IMAGE,
			AIPS_Prompt_Registry::TYPE_INLINE_IMAGE,
			AIPS_Prompt_Registry::TYPE_TOPIC_RESEARCH,
			AIPS_Prompt_Registry::TYPE_AUTHOR_TOPICS,
			AIPS_Prompt_Registry::TYPE_AUTHOR_FEEDBACK,
			AIPS_Prompt_Registry::TYPE_AI_EDIT,
		);

		$registered_types = $this->registry->get_registered_types();

		foreach ($constants as $type) {
			$this->assertContains($type, $registered_types, "Expected '$type' to be registered.");
			$this->assertTrue($this->registry->has_type($type));

			$template = $this->registry->get_template($type);
			$this->assertNotWPError($template);
			$this->assertArrayHasKey('system_instructions', $template);
			$this->assertArrayHasKey('required_params', $template);
			$this->assertArrayHasKey('optional_params', $template);
			$this->assertNotEmpty($template['system_instructions']);
		}

		$this->assertCount(count($constants), $registered_types);
	}

	public function test_get_template_for_unknown_type_returns_wp_error() {
		$result = $this->registry->get_template('not_a_real_type');

		$this->assertWPError($result);
		$this->assertSame('aips_prompt_registry_unknown_type', $result->get_error_code());
	}

	public function test_has_type_returns_false_for_unknown_type() {
		$this->assertFalse($this->registry->has_type('not_a_real_type'));
	}

	public function test_validate_params_passes_with_all_required_params() {
		$result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_POST_TITLE,
			array('content' => 'Some generated article content.')
		);

		$this->assertTrue($result);
	}

	public function test_validate_params_passes_with_required_and_optional_params() {
		$result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_POST_EXCERPT,
			array(
				'title'   => 'A Great Post',
				'content' => 'Body content here.',
				'topic'   => 'WordPress',
			)
		);

		$this->assertTrue($result);
	}

	public function test_validate_params_fails_when_required_param_missing() {
		$result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_POST_EXCERPT,
			array('title' => 'A Great Post')
		);

		$this->assertWPError($result);
		$this->assertSame('aips_prompt_registry_missing_params', $result->get_error_code());
		$this->assertStringContainsString('content', $result->get_error_message());
	}

	public function test_validate_params_fails_for_unknown_type() {
		$result = $this->registry->validate_params('not_a_real_type', array());

		$this->assertWPError($result);
		$this->assertSame('aips_prompt_registry_unknown_type', $result->get_error_code());
	}

	public function test_validate_params_fails_on_type_mismatch() {
		$result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_TOPIC_RESEARCH,
			array('niche' => 'WordPress', 'count' => '5')
		);

		$this->assertWPError($result);
		$this->assertSame('aips_prompt_registry_invalid_param_type', $result->get_error_code());
	}

	public function test_validate_params_ignores_unknown_extra_params() {
		$result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_POST_TITLE,
			array('content' => 'Body content.', 'unrelated_key' => 'ignored')
		);

		$this->assertTrue($result);
	}

	public function test_validate_params_accepts_object_type_as_array_or_object() {
		$object_result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_AUTHOR_TOPICS,
			array('author' => (object) array('name' => 'Jane'), 'count' => 3)
		);
		$array_result = $this->registry->validate_params(
			AIPS_Prompt_Registry::TYPE_AUTHOR_TOPICS,
			array('author' => array('name' => 'Jane'), 'count' => 3)
		);

		$this->assertTrue($object_result);
		$this->assertTrue($array_result);
	}

	/**
	 * The registry is registered as a container singleton and must resolve
	 * to the same instance consistent with other core bindings.
	 */
	public function test_registered_as_container_singleton() {
		$container = AIPS_Container::get_instance();

		$first  = $container->make(AIPS_Prompt_Registry::class);
		$second = $container->make(AIPS_Prompt_Registry::class);

		$this->assertInstanceOf(AIPS_Prompt_Registry::class, $first);
		$this->assertSame($first, $second);
	}
}
