<?php
/**
 * Tests for the combined post metadata prompt builder.
 *
 * @package AI_Post_Scheduler
 */
class Test_Prompt_Builder_Post_Metadata extends WP_UnitTestCase {

	public function test_metadata_preserves_voice_excerpt_instructions_and_ends_with_contract() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Template',
			'prompt_template' => 'Write about {{topic}}.',
			'title_prompt' => 'Accurate title for {{topic}}.',
		);
		$voice = (object) array(
			'id' => 2,
			'excerpt_instructions' => 'Use an expert tone for {{topic}}.',
		);
		$context = new AIPS_Template_Context($template, $voice, 'Security');
		$diversity = new class() {
			public function build_avoid_titles_block($subject) { return 'AVOID BLOCK'; }
			public function build_content_format_block($subject) { return ''; }
			public function build_post_slice_block($subject) { return ''; }
		};
		$builder = new AIPS_Prompt_Builder_Post_Metadata(new AIPS_Template_Processor(), $diversity);

		$prompt = $builder->build($context);

		$this->assertStringContainsString('Use an expert tone for Security.', $prompt);
		$this->assertGreaterThan(strpos($prompt, 'AVOID BLOCK'), strpos($prompt, 'RESPOND WITH EXACTLY THIS JSON SHAPE:'));
	}

	public function test_schema_is_strict_and_requires_requested_fields() {
		$builder = new AIPS_Prompt_Builder_Post_Metadata();
		$schema = $builder->get_schema(array('PrimaryKeyword'), true);

		$this->assertFalse($schema['additionalProperties']);
		$this->assertContains('image_prompt', $schema['required']);
		$this->assertContains('ai_variables', $schema['required']);
		$this->assertSame(array('PrimaryKeyword'), $schema['properties']['ai_variables']['required']);
		$this->assertFalse($schema['properties']['ai_variables']['additionalProperties']);
	}

	public function test_metadata_with_topic_context_includes_author_tone_and_style() {
		$author = (object) array(
			'id' => 5,
			'name' => 'Tech Author',
			'field_niche' => 'DevOps',
			'voice_tone' => 'authoritative and practical',
			'writing_style' => 'in-depth analysis with code samples',
		);
		$topic = (object) array(
			'id' => 10,
			'topic_title' => 'Kubernetes Security',
		);
		$context = new AIPS_Topic_Context($author, $topic);
		$builder = new AIPS_Prompt_Builder_Post_Metadata(new AIPS_Template_Processor());

		$prompt = $builder->build($context);

		$this->assertStringContainsString('Tone: authoritative and practical', $prompt);
		$this->assertStringContainsString('Writing Style: in-depth analysis with code samples', $prompt);
		$this->assertStringContainsString('EXCERPT INSTRUCTIONS:', $prompt);
	}
}
