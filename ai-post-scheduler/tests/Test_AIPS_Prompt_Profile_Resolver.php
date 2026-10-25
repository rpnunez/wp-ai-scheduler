<?php
/**
 * Tests for AIPS_Prompt_Profile_Resolver.
 *
 * @package AI_Post_Scheduler
 */

class Mock_Prompt_Profiles_Repository_For_Resolver {
	public $profiles = array();
	public $default_profile = null;

	public function get_by_id($id) {
		$id = (int) $id;
		return isset($this->profiles[$id]) ? $this->profiles[$id] : null;
	}

	public function get_default() {
		return $this->default_profile;
	}
}

class Test_AIPS_Prompt_Profile_Resolver extends WP_UnitTestCase {

	/** @var Mock_Prompt_Profiles_Repository_For_Resolver */
	private $repository_mock;

	/** @var AIPS_Prompt_Profile_Resolver */
	private $resolver;

	public function setUp(): void {
		parent::setUp();
		$this->repository_mock = new Mock_Prompt_Profiles_Repository_For_Resolver();
		$this->resolver = new AIPS_Prompt_Profile_Resolver($this->repository_mock);
	}

	public function test_cascade_resolves_tier1_template_profile() {
		$template_profile = (object) array(
			'id'        => 10,
			'name'      => 'Template Specific Profile',
			'is_active' => 1,
		);
		$author_profile = (object) array(
			'id'        => 20,
			'name'      => 'Author Profile',
			'is_active' => 1,
		);
		$default_profile = (object) array(
			'id'        => 30,
			'name'      => 'Default Profile',
			'is_active' => 1,
		);

		$this->repository_mock->profiles[10] = $template_profile;
		$this->repository_mock->profiles[20] = $author_profile;
		$this->repository_mock->default_profile = $default_profile;

		$template = (object) array('prompt_profile_id' => 10);
		$author = (object) array('prompt_profile_id' => 20);

		$resolved = $this->resolver->resolve_profile($template, $author);
		$this->assertNotNull($resolved);
		$this->assertEquals(10, $resolved->id);
	}

	public function test_cascade_resolves_tier2_author_when_tier1_absent() {
		$author_profile = (object) array(
			'id'        => 20,
			'name'      => 'Author Profile',
			'is_active' => 1,
		);
		$default_profile = (object) array(
			'id'        => 30,
			'name'      => 'Default Profile',
			'is_active' => 1,
		);

		$this->repository_mock->profiles[20] = $author_profile;
		$this->repository_mock->default_profile = $default_profile;

		$template = (object) array('prompt_profile_id' => null);
		$author = (object) array('prompt_profile_id' => 20);

		$resolved = $this->resolver->resolve_profile($template, $author);
		$this->assertNotNull($resolved);
		$this->assertEquals(20, $resolved->id);
	}

	public function test_cascade_resolves_tier3_default_when_subjects_have_no_profile() {
		$default_profile = (object) array(
			'id'        => 30,
			'name'      => 'Default Profile',
			'is_active' => 1,
		);

		$this->repository_mock->default_profile = $default_profile;

		$template = (object) array('prompt_profile_id' => 0);
		$author = (object) array('prompt_profile_id' => 0);

		$resolved = $this->resolver->resolve_profile($template, $author);
		$this->assertNotNull($resolved);
		$this->assertEquals(30, $resolved->id);
	}

	public function test_stage_prompt_uses_custom_or_falls_back_to_core() {
		$profile = (object) array(
			'id'           => 1,
			'is_active'    => 1,
			'title_prompt' => 'Custom title prompt: {{topic}}',
			'content_prompt' => null, // empty, should fall back
		);

		$this->repository_mock->profiles[1] = $profile;

		$custom_title = $this->resolver->get_stage_prompt('title_prompt', 1);
		$this->assertEquals('Custom title prompt: {{topic}}', $custom_title);

		$fallback_content = $this->resolver->get_stage_prompt('content_prompt', 1);
		$this->assertNotEmpty($fallback_content);
	}

	public function test_interpolate_and_mandatory_tag_safety() {
		$template = "Title: {{title}}\nKeywords: {{keywords}}\nSummary: {{instructions}}";

		$placeholders = array(
			'title'        => 'My Post',
			'{{keywords}}' => 'PHP, WordPress', // testing with brackets
			'instructions' => 'Write clearly',
		);

		$mandatory = array(
			'instructions' => 'Write clearly', // already present in template
			'disclosure'   => 'AI generated post', // absent from template
		);

		$interpolated = $this->resolver->interpolate($template, $placeholders, $mandatory);

		// Assert placeholders replaced
		$this->assertStringContainsString('Title: My Post', $interpolated);
		$this->assertStringContainsString('Keywords: PHP, WordPress', $interpolated);
		$this->assertStringContainsString('Summary: Write clearly', $interpolated);

		// Assert missing mandatory tag was appended
		$this->assertStringContainsString('AI generated post', $interpolated);

		// Assert instructions was NOT appended twice
		$this->assertEquals(1, substr_count($interpolated, 'Write clearly'));
	}
}
