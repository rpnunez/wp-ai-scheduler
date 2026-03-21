<?php
/**
 * Compatibility tests for migrated Config class.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Config_Compat extends WP_UnitTestCase {

    public function test_namespaced_class_exists() {
        $this->assertTrue(class_exists('AIPS\\Support\\Config'));
    }

    public function test_legacy_class_alias_maps_to_namespaced_class() {
        $config = AIPS_Config::get_instance();
        $this->assertInstanceOf('AIPS\\Support\\Config', $config);
    }
}

