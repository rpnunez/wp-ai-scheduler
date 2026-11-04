<?php
/**
 * Tests for AIPS_Campaign_Context Topic Silo integration.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Campaign_Context_Silo extends WP_UnitTestCase {

	public function test_silo_disabled_by_default() {
		$campaign = (object) array(
			'id'           => 1,
			'name'         => 'General Campaign',
			'content_goal' => 'Standard articles',
		);

		$inner_context = $this->createMock(AIPS_Generation_Context::class);
		$context = new AIPS_Campaign_Context($inner_context, $campaign);

		$this->assertFalse($context->is_silo_enabled());
		$this->assertNull($context->get_pillar_post_id());
		$this->assertNull($context->get_pillar_template_id());
		$this->assertSame(array(), $context->get_pillar_anchor_keywords());
	}

	public function test_silo_enabled_with_pillar_and_keywords() {
		$campaign = (object) array(
			'id'                     => 42,
			'name'                   => 'Solar Energy Cluster',
			'content_goal'           => 'Build authority in residential solar',
			'silo_enabled'           => 1,
			'pillar_post_id'         => 105,
			'pillar_template_id'     => 12,
			'pillar_anchor_keywords' => "solar panels cost, best residential solar\nsolar energy guide",
		);

		$inner_context = $this->createMock(AIPS_Generation_Context::class);
		$context = new AIPS_Campaign_Context($inner_context, $campaign);

		$this->assertTrue($context->is_silo_enabled());
		$this->assertSame(105, $context->get_pillar_post_id());
		$this->assertSame(12, $context->get_pillar_template_id());

		$keywords = $context->get_pillar_anchor_keywords();
		$this->assertCount(3, $keywords);
		$this->assertContains('solar panels cost', $keywords);
		$this->assertContains('best residential solar', $keywords);
		$this->assertContains('solar energy guide', $keywords);

		$array_data = $context->to_array();
		$this->assertTrue($array_data['silo_enabled']);
		$this->assertSame(105, $array_data['pillar_post_id']);
		$this->assertSame(12, $array_data['pillar_template_id']);
		$this->assertSame($keywords, $array_data['pillar_anchor_keywords']);
	}
}
