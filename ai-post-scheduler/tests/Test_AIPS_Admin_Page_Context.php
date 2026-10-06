<?php
/**
 * Tests for AIPS_Admin_Page_Context
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Page_Context extends WP_UnitTestCase {

	/**
	 * Test canonical hubs list contains all 8 hubs.
	 */
	public function test_get_canonical_hubs() {
		$hubs = AIPS_Admin_Page_Context::get_canonical_hubs();
		$this->assertIsArray($hubs);
		$this->assertCount(8, $hubs);

		$expected_keys = array(
			AIPS_Admin_Page_Context::HUB_DASHBOARD,
			AIPS_Admin_Page_Context::HUB_AUTOMATIONS,
			AIPS_Admin_Page_Context::HUB_STUDIO,
			AIPS_Admin_Page_Context::HUB_RESEARCH,
			AIPS_Admin_Page_Context::HUB_CONTENT,
			AIPS_Admin_Page_Context::HUB_HISTORY,
			AIPS_Admin_Page_Context::HUB_SETTINGS,
			AIPS_Admin_Page_Context::HUB_DIAGNOSTICS,
		);

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $hubs);
			$this->assertNotEmpty($hubs[$key]['label']);
			$this->assertNotEmpty($hubs[$key]['slug']);
		}
	}

	/**
	 * Test resolving root dashboard context.
	 */
	public function test_resolve_root_dashboard_context() {
		$ctx = AIPS_Admin_Page_Context::resolve('ai-post-scheduler');

		$this->assertEquals(AIPS_Admin_Page_Context::HUB_DASHBOARD, $ctx->hub_key);
		$this->assertEquals('Dashboard', $ctx->hub_label);
		$this->assertNotEmpty($ctx->hub_url);
		$this->assertCount(1, $ctx->breadcrumbs);
		$this->assertEquals('Dashboard', $ctx->breadcrumbs[0]['label']);
	}

	/**
	 * Test resolving Automations tab context with breadcrumbs.
	 */
	public function test_resolve_automations_tabs() {
		$ctx = AIPS_Admin_Page_Context::resolve('aips-automations', 'schedules');

		$this->assertEquals(AIPS_Admin_Page_Context::HUB_AUTOMATIONS, $ctx->hub_key);
		$this->assertEquals('schedules', $ctx->section_key);
		$this->assertEquals('Schedules', $ctx->section_label);
		$this->assertCount(2, $ctx->breadcrumbs);
		$this->assertEquals('Automations', $ctx->breadcrumbs[0]['label']);
		$this->assertNotEmpty($ctx->breadcrumbs[0]['url']);
		$this->assertEquals('Schedules', $ctx->breadcrumbs[1]['label']);
		$this->assertEmpty($ctx->breadcrumbs[1]['url']); // Current page unlinked
	}

	/**
	 * Test resolving nested leaf view (e.g. Campaign Wizard).
	 */
	public function test_resolve_leaf_view_wizard() {
		$ctx = AIPS_Admin_Page_Context::resolve('aips-campaign-wizard');

		$this->assertEquals(AIPS_Admin_Page_Context::HUB_AUTOMATIONS, $ctx->hub_key);
		$this->assertEquals('campaigns', $ctx->section_key);
		$this->assertEquals('wizard', $ctx->view_key);
		$this->assertEquals('Campaign Wizard', $ctx->view_label);
		$this->assertCount(3, $ctx->breadcrumbs);
		$this->assertEquals('Automations', $ctx->breadcrumbs[0]['label']);
		$this->assertEquals('Campaigns', $ctx->breadcrumbs[1]['label']);
		$this->assertEquals('Campaign Wizard', $ctx->breadcrumbs[2]['label']);
		$this->assertEmpty($ctx->breadcrumbs[2]['url']);
	}

	/**
	 * Test resolving Studio section context.
	 */
	public function test_resolve_studio_section() {
		$ctx = AIPS_Admin_Page_Context::resolve('aips-studio', 'templates');

		$this->assertEquals(AIPS_Admin_Page_Context::HUB_STUDIO, $ctx->hub_key);
		$this->assertEquals('templates', $ctx->section_key);
		$this->assertEquals('Templates', $ctx->section_label);
		$this->assertEquals('Templates', $ctx->context_title);
		$this->assertCount(2, $ctx->breadcrumbs);
	}

	/**
	 * Test is_child_page mapping.
	 */
	public function test_is_child_page() {
		$this->assertTrue(AIPS_Admin_Page_Context::is_child_page('aips-schedule', AIPS_Admin_Page_Context::HUB_AUTOMATIONS));
		$this->assertTrue(AIPS_Admin_Page_Context::is_child_page('aips-campaign-wizard', AIPS_Admin_Page_Context::HUB_AUTOMATIONS));
		$this->assertTrue(AIPS_Admin_Page_Context::is_child_page('aips-templates', AIPS_Admin_Page_Context::HUB_STUDIO));
		$this->assertTrue(AIPS_Admin_Page_Context::is_child_page('aips-voices', AIPS_Admin_Page_Context::HUB_STUDIO));
		$this->assertTrue(AIPS_Admin_Page_Context::is_child_page('aips-telemetry', AIPS_Admin_Page_Context::HUB_DIAGNOSTICS));
		$this->assertFalse(AIPS_Admin_Page_Context::is_child_page('aips-templates', AIPS_Admin_Page_Context::HUB_AUTOMATIONS));
		$this->assertFalse(AIPS_Admin_Page_Context::is_child_page('unknown-page', AIPS_Admin_Page_Context::HUB_DIAGNOSTICS));
	}

	/**
	 * Test to_header_args export.
	 */
	public function test_to_header_args() {
		$ctx = AIPS_Admin_Page_Context::resolve('aips-automations', 'authors', null, array(
			'summary_items' => array(
				array('label' => 'Total Authors', 'value' => 5, 'type' => 'neutral'),
			),
		));

		$args = $ctx->to_header_args();

		$this->assertIsArray($args);
		$this->assertEquals('Automations', $args['title']);
		$this->assertEquals('Authors', $args['context_title']);
		$this->assertCount(2, $args['breadcrumbs']);
		$this->assertCount(1, $args['summary_items']);
		$this->assertEquals(5, $args['summary_items'][0]['value']);
	}

	/**
	 * Test Automations controller page context resolution across tabs.
	 */
	public function test_automations_controller_get_page_context() {
		$controller = new AIPS_Automations_Controller();

		$tabs = array('schedules', 'campaigns', 'authors', 'sources', 'monetization', 'internal-links', 'taxonomy');

		foreach ($tabs as $tab) {
			$ctx = $controller->get_page_context($tab);
			$this->assertInstanceOf(AIPS_Admin_Page_Context::class, $ctx);
			$this->assertEquals(AIPS_Admin_Page_Context::HUB_AUTOMATIONS, $ctx->hub_key);
			$this->assertEquals($tab, $ctx->section_key);
			$this->assertIsArray($ctx->summary_items);
			$this->assertIsArray($ctx->actions);
		}
	}

	/**
	 * Test Studio controller page context resolution across tabs.
	 */
	public function test_studio_controller_get_page_context() {
		$controller = new AIPS_Studio_Controller();

		$sections = array('', 'launchpad', 'templates', 'voices', 'structures', 'post-slices');

		foreach ($sections as $sec) {
			$ctx = $controller->get_page_context($sec);
			$this->assertInstanceOf(AIPS_Admin_Page_Context::class, $ctx);
			$this->assertEquals(AIPS_Admin_Page_Context::HUB_STUDIO, $ctx->hub_key);
			$this->assertIsArray($ctx->summary_items);
			$this->assertIsArray($ctx->actions);
		}
	}
}

