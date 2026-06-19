<?php
class Test_AIPS_Admin_Assets extends WP_UnitTestCase {
    public function test_get_asset_routes() {
        $assets = new AIPS_Admin_Assets();
        $reflection = new ReflectionClass($assets);
        $method = $reflection->getMethod('get_asset_routes');
        $method->setAccessible(true);
        $routes = $method->invoke($assets);

        $this->assertIsArray($routes);
        // Note: the constant in class is PAGE_DASHBOARD = 'ai-post-scheduler'
        $this->assertArrayHasKey('ai-post-scheduler', $routes);
        $this->assertEquals('enqueue_dashboard_assets', $routes['ai-post-scheduler']);
        $this->assertArrayHasKey('aips-authors', $routes);
        $this->assertEquals('enqueue_authors_assets', $routes['aips-authors']);
    }

    public function test_enqueue_admin_assets_skips_non_plugin_pages() {
        $assets = new AIPS_Admin_Assets();
        $_GET['page'] = 'some-other-plugin';

        // This shouldn't throw any errors and should return early
        $assets->enqueue_admin_assets('toplevel_page_some-other-plugin');

        $this->assertTrue(true); // Reached without fatal errors
    }
}
