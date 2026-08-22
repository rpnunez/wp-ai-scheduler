<?php
/**
 * Test AIPS_Generation_Pipeline and middleware stages.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Pipeline extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AIPS_Mock_AI_Provider::reset();
		AIPS_Config::get_instance()->set_option('aips_ai_provider', 'mock');
		AIPS_AI_Provider_Factory::reset_cache();
	}

	public function test_pipeline_execution_builds_payload() {
		$context = new AIPS_Topic_Context(array(
			'topic'          => 'Pipeline Automation Architecture',
			'content_prompt' => 'Write a guide to modular pipeline execution.',
			'post_status'    => 'draft',
		));

		$pipeline = new AIPS_Generation_Pipeline();
		$payload = $pipeline->execute($context);

		$this->assertInstanceOf(AIPS_Generation_Pipeline_Payload::class, $payload);
		$this->assertSame('Pipeline Automation Architecture', $payload->title);
		$this->assertNotEmpty($payload->raw_content);
		$this->assertNotEmpty($payload->formatted_content);
		$this->assertNotEmpty($payload->excerpt);
		$this->assertArrayHasKey('drafting', $payload->stage_timings);
		$this->assertGreaterThan(0, $payload->post_id);

		$post = get_post($payload->post_id);
		$this->assertNotNull($post);
		$this->assertSame('draft', $post->post_status);
	}

	public function test_pipeline_stage_reordering_and_filtering() {
		$context = new AIPS_Topic_Context(array('topic' => 'Filter Test'));
		$pipeline = new AIPS_Generation_Pipeline();

		add_filter('aips_generation_pipeline_stages', function($stages) {
			// Remove SEO stage for this test
			return array_values(array_filter($stages, function($s) {
				return $s->get_id() !== 'seo';
			}));
		});

		$ordered = $pipeline->get_ordered_stages($context);
		$stage_ids = array_map(function($s) { return $s->get_id(); }, $ordered);

		$this->assertNotContains('seo', $stage_ids);
		$this->assertContains('drafting', $stage_ids);
	}
}
