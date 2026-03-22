<?php
/**
 * Tests for story package helpers and prompt builder integration.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Story_Package extends WP_UnitTestCase {

	public function test_normalize_outputs_keeps_supported_keys_and_forces_full_article() {
		$result = AIPS_Story_Package::normalize_outputs(array('social_posts', 'meta_description', 'unknown'));

		$this->assertSame(
			array('full_article', 'social_posts', 'meta_description'),
			$result
		);
	}

	public function test_build_package_payload_adds_labels_and_components() {
		$payload = AIPS_Story_Package::build_package_payload(
			array('full_article', 'newsletter_summary'),
			array(
				'newsletter_summary' => array(
					'content' => 'A short email-ready summary.',
				),
			)
		);

		$this->assertArrayHasKey('artifacts', $payload);
		$this->assertSame('newsletter_summary', $payload['artifacts']['newsletter_summary']['key']);
		$this->assertSame('story_package_newsletter_summary', $payload['artifacts']['newsletter_summary']['component']);
		$this->assertSame('A short email-ready summary.', $payload['artifacts']['newsletter_summary']['content']);
	}

	public function test_prompt_builder_includes_story_package_prompts_when_enabled() {
		$builder = new AIPS_Prompt_Builder(new AIPS_Template_Processor(), new AIPS_Article_Structure_Manager());
		$template = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Create a title about {{topic}}',
			'image_prompt' => 'Illustrate {{topic}}',
			'voice_id' => 0,
			'article_structure_id' => 0,
			'include_sources' => 0,
			'story_package_enabled' => 1,
			'story_package_outputs' => wp_json_encode(array('full_article', 'newsletter_summary', 'meta_description')),
		);

		$result = $builder->build_prompts($template, 'AI policy');

		$this->assertArrayHasKey('story_package', $result['prompts']);
		$this->assertArrayHasKey('newsletter_summary', $result['prompts']['story_package']);
		$this->assertArrayHasKey('meta_description', $result['prompts']['story_package']);
		$this->assertStringContainsString('newsletter-ready summary', $result['prompts']['story_package']['newsletter_summary']);
		$this->assertSame(
			array('full_article', 'newsletter_summary', 'meta_description'),
			$result['metadata']['story_package_outputs']
		);
	}
}
