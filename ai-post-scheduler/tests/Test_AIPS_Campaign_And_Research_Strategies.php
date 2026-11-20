<?php
/**
 * Tests for Campaign & Niche Research Generation Strategies (Issue #2400 Phase 3).
 *
 * Covers:
 * - AIPS_Campaign_Generation_Strategy + AIPS_Campaign_Pillar_Component + AIPS_Campaign_Cluster_Component
 * - AIPS_Research_Generation_Strategy + AIPS_Trending_Topics_Component + AIPS_Topic_Freshness_Component + AIPS_Topic_Comparison_Component
 * - Migrated AIPS_Research_Service methods and typed *_result() methods
 * - Registry & Container resolution for Phase 3 strategies/components
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Campaign_And_Research_Strategies
 */
class Test_AIPS_Campaign_And_Research_Strategies extends WP_UnitTestCase {

	/**
	 * Registry registers default Campaign and Research components.
	 */
	public function test_registry_registers_campaign_and_research_components() {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_campaign_components();
		$registry->register_default_research_components();

		$this->assertTrue($registry->has('campaign_pillar', 'campaigns'));
		$this->assertTrue($registry->has('campaign_cluster', 'campaigns'));
		$this->assertTrue($registry->has('trending_topics', 'research'));
		$this->assertTrue($registry->has('topic_freshness', 'research'));
		$this->assertTrue($registry->has('topic_comparison', 'research'));
	}

	/**
	 * AIPS_Campaign_Generation_Strategy assembles combined schema, handles AI unavailability, completed, and partial runs.
	 */
	public function test_campaign_generation_strategy_outcomes() {
		$context = new AIPS_Campaign_Generation_Context(
			array(
				'topic_niche'     => 'WordPress Performance',
				'target_audience' => 'Agency developers',
				'content_tone'    => 'technical',
				'publishing_goal' => 'Drive organic traffic',
				'frequency'       => 'weekly',
				'post_type'       => 'post',
				'output_style'    => 'how_to_guide',
			),
			array('daily', 'weekly'),
			array('post'),
			array('how_to_guide' => 'How-To Guide')
		);

		// 1. Combined schema merges pillar + cluster properties.
		$ai_unavail = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_unavail->method('is_available')->willReturn(false);

		$strategy_unavail = new AIPS_Campaign_Generation_Strategy(null, $ai_unavail);
		$schema           = $strategy_unavail->get_combined_json_schema();
		$this->assertArrayHasKey('campaign_name', $schema['properties']);
		$this->assertArrayHasKey('sample_article_ideas', $schema['properties']);
		$this->assertContains('campaign_name', $schema['required']);
		$this->assertContains('sample_article_ideas', $schema['required']);

		// AI unavailable -> failure with ai_unavailable error code.
		$pipeline_unavail = new AIPS_Generation_Pipeline($strategy_unavail, new AIPS_Generation_Component_Registry());
		$res_unavail      = $pipeline_unavail->execute($context);
		$this->assertTrue($res_unavail->is_failure());
		$this->assertSame('ai_unavailable', $strategy_unavail->get_last_error()->get_error_code());

		// 2. Completed run (pillar + cluster ideas present) & Partial run (cluster ideas omitted).
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('is_available')->willReturn(true);
		$ai_mock->method('generate_json')->willReturnOnConsecutiveCalls(
			array(
				'campaign_name'        => 'WP Speed Mastery',
				'content_goal'         => 'Drive organic traffic',
				'post_type'            => 'post',
				'prompt_template'      => 'Write about {{topic}}.',
				'title_prompt'         => 'Generate 1 title.',
				'campaign_mode'        => 'template',
				'review_policy'        => 'draft',
				'sample_article_ideas' => array('Object Caching 101', 'Database Query Profiling'),
			),
			array(
				'campaign_name'        => 'WP Speed Mastery',
				'content_goal'         => 'Drive organic traffic',
				'post_type'            => 'post',
				'prompt_template'      => 'Write about {{topic}}.',
				'title_prompt'         => 'Generate 1 title.',
				'campaign_mode'        => 'template',
				'review_policy'        => 'draft',
				'sample_article_ideas' => array(),
			)
		);

		$strategy = new AIPS_Campaign_Generation_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());

		$res_complete = $pipeline->execute($context);
		$this->assertTrue($res_complete->is_success());
		$this->assertSame(array('campaign_pillar' => true, 'campaign_cluster' => true), $res_complete->get_component_statuses());

		$res_partial = $pipeline->execute($context);
		$this->assertTrue($res_partial->is_partial());
		$this->assertSame(array('campaign_pillar' => true, 'campaign_cluster' => false), $res_partial->get_component_statuses());
		$this->assertNotEmpty($res_partial->get_errors());
	}

	/**
	 * AIPS_Research_Service delegates trending, freshness, comparison, and source guards through AIPS_Research_Generation_Strategy.
	 */
	public function test_research_strategy_and_service_migration() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('is_available')->willReturn(true);
		$ai_mock->method('generate_json')->willReturn(array(
			array('topic' => 'Low Score Topic', 'score' => 60, 'reason' => 'Decent', 'keywords' => array('a')),
			array('topic' => 'High Score Topic', 'score' => 95, 'reason' => 'Hot trend', 'keywords' => array('b', 'c')),
		));

		$service = new AIPS_Research_Service($ai_mock);

		// Trending topics sorted descending by score.
		$res = $service->research_trending_topics_result('AI Engineering', 5, array('llm'));
		$this->assertTrue($res->is_success());
		$topics = $res->get_data();
		$this->assertCount(2, $topics);
		$this->assertSame('High Score Topic', $topics[0]['topic']);
		$this->assertSame(95, $topics[0]['score']);

		// Missing niche guard.
		$missing_niche = $service->research_trending_topics('');
		$this->assertWPError($missing_niche);
		$this->assertSame('missing_niche', $missing_niche->get_error_code());

		// Source research empty term_ids guard.
		$no_terms = $service->research_from_sources(array(), 'AI Engineering');
		$this->assertWPError($no_terms);
		$this->assertSame('no_term_ids', $no_terms->get_error_code());

		// Freshness analysis via AIPS_Topic_Freshness_Component.
		$year      = AIPS_DateTime::now()->toDisplay('Y');
		$freshness = $service->analyze_topic_freshness(array(
			'topic'    => "Latest AI trends in {$year}",
			'keywords' => array('trending'),
		));
		$this->assertGreaterThanOrEqual(80, $freshness['score']);
		$this->assertTrue($freshness['is_fresh']);

		// Comparison via AIPS_Topic_Comparison_Component.
		$cmp = $service->compare_topics(
			array('score' => 90, 'keywords' => array('a')),
			array('score' => 70, 'keywords' => array('a', 'b'))
		);
		$this->assertSame(-1, $cmp);
	}

	/**
	 * AIPS_Container resolves Phase 3 strategies.
	 */
	public function test_container_resolves_phase3_strategies() {
		$container = AIPS_Container::get_instance();
		if (!$container->has(AIPS_Campaign_Generation_Strategy::class)) {
			$this->markTestSkipped('Container bindings not initialized in this test bootstrap.');
		}

		$this->assertInstanceOf(
			AIPS_Campaign_Generation_Strategy::class,
			$container->make(AIPS_Campaign_Generation_Strategy::class)
		);
		$this->assertInstanceOf(
			AIPS_Research_Generation_Strategy::class,
			$container->make(AIPS_Research_Generation_Strategy::class)
		);
	}
}
