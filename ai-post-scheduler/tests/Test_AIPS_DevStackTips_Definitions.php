<?php
/**
 * Tests for AIPS_DevStackTips_Definitions.
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/scripts/class-aips-devstacktips-definitions.php';

class Test_AIPS_DevStackTips_Definitions extends WP_UnitTestCase {

	public function test_templates_are_keyed_by_stable_key_with_resolved_cross_refs() {
		$templates = AIPS_DevStackTips_Definitions::templates();

		$this->assertCount(11, $templates);
		$this->assertArrayHasKey('templates:security-guide', $templates);

		$sg = $templates['templates:security-guide'];
		$this->assertSame('Security Guide', $sg['name']);
		$this->assertSame('voices:senior-backend-mentor', $sg['voice_key']);
		$this->assertSame('structures:security-best-practices', $sg['structure_key']);
		$this->assertSame('source_groups:security-news', $sg['source_group_key']);
		$this->assertSame('security', $sg['slug_type']);
		$this->assertIsArray($sg['content_images']);
	}

	public function test_authors_are_keyed_by_stable_key_with_resolved_cross_refs() {
		$authors = AIPS_DevStackTips_Definitions::authors();

		$this->assertCount(11, $authors);
		$this->assertArrayHasKey('authors:security-expert', $authors);

		$se = $authors['authors:security-expert'];
		$this->assertTrue($se['v2_author']);
		$this->assertSame('categories:security', $se['category_key']);
		$this->assertSame('source_groups:security-news', $se['source_group_key']);

		// An author without a source group has a null source_group_key.
		$this->assertArrayHasKey('authors:devops-practitioner', $authors);
		$this->assertNull($authors['authors:devops-practitioner']['source_group_key']);
	}

	public function test_settings_contains_expected_keys() {
		$settings = AIPS_DevStackTips_Definitions::settings();
		$this->assertSame(array('ai_prompt', 'media_library'), $settings['aips_allowed_image_sources']);
		$this->assertSame(3, $settings['aips_max_post_images_limit']);
		$this->assertArrayHasKey('aips_ai_assistance_guidance', $settings);
	}

	public function test_slug_prompt_specializes_by_type_and_falls_back() {
		$this->assertStringContainsString('x-vs-y', AIPS_DevStackTips_Definitions::slug_prompt('comparison'));
		$this->assertSame(
			AIPS_DevStackTips_Definitions::slug_prompt('an-unknown-type'),
			AIPS_DevStackTips_Definitions::slug_prompt('default')
		);
	}

	public function test_image_prompt_helpers_apply_house_style() {
		$featured = AIPS_DevStackTips_Definitions::featured_image_prompt('a shield');
		$this->assertStringStartsWith(AIPS_DevStackTips_Definitions::HOUSE_STYLE, $featured);
		$this->assertStringContainsString('{{topic}}', $featured);

		$content = AIPS_DevStackTips_Definitions::content_image_prompt('a diagram');
		$this->assertStringStartsWith(AIPS_DevStackTips_Definitions::HOUSE_STYLE, $content);
		$this->assertStringContainsString('a diagram', $content);
	}
}
