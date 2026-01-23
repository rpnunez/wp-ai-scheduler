<?php

/**
 * Tests for AIPS_Admin_Assets class.
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Admin_Assets extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Ensure the class is loaded, as it might not be in the bootstrap fallback list
        if (!class_exists('AIPS_Admin_Assets')) {
            $file = dirname(dirname(__FILE__)) . '/includes/class-aips-admin-assets.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    public function test_class_exists() {
        $this->assertTrue(class_exists('AIPS_Admin_Assets'));
    }

    public function test_hook_registration() {
        // Ensure class exists before instantiating
        if (!class_exists('AIPS_Admin_Assets')) {
            $this->markTestSkipped('AIPS_Admin_Assets class not found.');
        }

        $assets = new AIPS_Admin_Assets();

        if (function_exists('has_action')) {
            $this->assertNotFalse(has_action('admin_enqueue_scripts', array($assets, 'enqueue_assets')));
        } else {
            // Fallback check for mocked hooks in bootstrap.php
            global $aips_test_hooks;
            $found = false;
            if (isset($aips_test_hooks['actions']['admin_enqueue_scripts'])) {
                foreach ($aips_test_hooks['actions']['admin_enqueue_scripts'] as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        if (is_array($callback['callback']) &&
                            $callback['callback'][0] === $assets &&
                            $callback['callback'][1] === 'enqueue_assets') {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
            $this->assertTrue($found, 'Action admin_enqueue_scripts not registered in mock environment');
        }
    }

    public function test_enqueue_assets_method_exists() {
        if (!class_exists('AIPS_Admin_Assets')) {
            $this->markTestSkipped('AIPS_Admin_Assets class not found.');
        }
        $this->assertTrue(method_exists('AIPS_Admin_Assets', 'enqueue_assets'));
    }
}
