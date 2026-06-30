<?php
class Test_AIPS_Admin_Assets extends WP_UnitTestCase {
    public function test_get_asset_routes() {
        $assets = new AIPS_Admin_Assets();
        $reflection = new ReflectionClass(AIPS_Admin_Assets::class);
        $method = $reflection->getMethod('get_asset_routes');
        $method->setAccessible(true);
        $routes = $method->invokeArgs($assets, array('toplevel_page_ai-post-scheduler', 'ai-post-scheduler'));
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        $has_dashboard = false;
        foreach ($routes as $route) {
            if (in_array('enqueue_dashboard_assets', $route['methods'], true)) {
                $has_dashboard = true;
                $this->assertTrue($route['conditions'][0] || $route['conditions'][1]);
            }
        }
        $this->assertTrue($has_dashboard, 'Dashboard route not found');
    }
}
