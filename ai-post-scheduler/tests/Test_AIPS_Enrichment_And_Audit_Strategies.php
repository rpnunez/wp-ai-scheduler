<?php
/**
 * Tests for Slice B Tier 2 Migrations: Consolidation, Competitor Gap,
 * Integration Fields, Affiliate Links, and Content Audit Strategies (Issue #2400).
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Enrichment_And_Audit_Strategies
 */
class Test_AIPS_Enrichment_And_Audit_Strategies extends WP_UnitTestCase {

	/**
	 * Registry registers all Slice B components cleanly.
	 */
	public function test_registry_registers_slice_b_components() {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components();
		$registry->register_default_research_components();
		$registry->register_default_enrichment_components();

		$this->assertTrue($registry->has('post_integration_fields', 'posts'));
		$this->assertTrue($registry->has('post_affiliate_links', 'posts'));
		$this->assertTrue($registry->has('competitor_gap', 'research'));
		$this->assertTrue($registry->has('post_consolidation', 'consolidation'));
		$this->assertTrue($registry->has('content_audit_pass', 'content_audit'));
	}

	/**
	 * AIPS_Consolidation_Generation_Strategy merges two posts and sanitizes fenced HTML.
	 */
	public function test_consolidation_strategy_pipeline_execution() {
		$keep_id   = self::factory()->post->create(array('post_title' => 'Keep Post', 'post_content' => '<p>Keep body.</p>', 'post_status' => 'publish'));
		$retire_id = self::factory()->post->create(array('post_title' => 'Retire Post', 'post_content' => '<p>Retire body.</p>', 'post_status' => 'publish'));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn("```html\n<p>Unified canonical article content.</p>\n```");

		$context  = new AIPS_Consolidation_Generation_Context(get_post($keep_id), get_post($retire_id));
		$strategy = new AIPS_Consolidation_Generation_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$result   = $pipeline->execute($context);

		$this->assertTrue($result->is_success());
		$this->assertSame('<p>Unified canonical article content.</p>', $result->get_data());
		$this->assertSame(array('post_consolidation' => true), $result->get_component_statuses());
	}

	/**
	 * AIPS_Competitor_Gap_Component executes via AIPS_Research_Generation_Strategy (MODE_GAP_SUGGESTIONS).
	 */
	public function test_competitor_gap_component_via_research_strategy() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn(wp_json_encode(array(
				array(
					'title'     => 'Bridge Topic One',
					'rationale' => 'Strengthens cluster depth.',
					'brief'     => 'Cover advanced workflows.',
				),
			)));

		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_GAP_SUGGESTIONS,
			array(
				'niche' => 'WordPress',
				'topic' => array(
					'post_title'   => 'WP Performance',
					'post_excerpt' => 'Speed tips.',
					'cluster_name' => 'Performance',
				),
			)
		);

		$strategy = new AIPS_Research_Generation_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$result   = $pipeline->execute($context);

		$this->assertTrue($result->is_success());
		$data = $result->get_data();
		$this->assertCount(1, $data);
		$this->assertSame('Bridge Topic One', $data[0]['title']);
	}

	/**
	 * AIPS_Affiliate_Links_Post_Component injects rel="nofollow sponsored" anchor cleanly.
	 */
	public function test_affiliate_links_post_component_injection() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_json')
			->willReturn(array(
				'match'       => 'Try managed WordPress hosting for faster sites.',
				'replacement' => 'Try [[managed WordPress hosting]] for faster sites.',
			));

		$component = new AIPS_Affiliate_Links_Post_Component(null, $ai_mock);
		$updated   = $component->inject_affiliate_link(
			'Intro. Try managed WordPress hosting for faster sites. Outro.',
			'https://partner.example.com/host',
			'hosting'
		);

		$this->assertStringContainsString('href="https://partner.example.com/host"', $updated);
		$this->assertStringContainsString('rel="nofollow sponsored"', $updated);
		$this->assertStringContainsString('>managed WordPress hosting</a>', $updated);
	}

	/**
	 * AIPS_Content_Audit_Strategy executes JSON pass with text fallback cleanly.
	 */
	public function test_content_audit_strategy_json_and_text_fallback() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_json')
			->willReturn(new WP_Error('json_failed', 'Schema unsupported'));
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn("```json\n[{\"topic\":\"Missing Guide\"}]\n```");

		$context  = new AIPS_Content_Audit_Generation_Context('topic_gaps', 'Audit prompt', array('temperature' => 0.6), 0.6, false);
		$strategy = new AIPS_Content_Audit_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$result   = $pipeline->execute($context);

		$this->assertTrue($result->is_success());
		$this->assertSame(array(array('topic' => 'Missing Guide')), $result->get_data());
	}
}
