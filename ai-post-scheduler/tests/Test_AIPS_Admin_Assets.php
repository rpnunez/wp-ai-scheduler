<?php

/**
 * Tests for AIPS_Admin_Assets routing refactor.
 */
class Test_AIPS_Admin_Assets extends WP_UnitTestCase {

	public function test_get_asset_routes_returns_array() {
		$assets = new AIPS_Admin_Assets();

		$reflection = new ReflectionClass($assets);
		$method = $reflection->getMethod('get_asset_routes');
		$method->setAccessible(true);

		$routes = $method->invoke($assets, 'toplevel_page_ai-post-scheduler', 'ai-post-scheduler');

		$this->assertIsArray($routes);
		$this->assertNotEmpty($routes);

		// Verify that at least one condition evaluates to true for the dashboard
		$dashboard_route = null;
		foreach ($routes as $route) {
			if (isset($route['method']) && $route['method'] === 'enqueue_dashboard_assets') {
				$dashboard_route = $route;
				break;
			}
		}

		$this->assertNotNull($dashboard_route);
		$this->assertTrue($dashboard_route['condition']);
	}
}
