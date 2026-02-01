<?php
/**
 * Tests for Variable Resolution Service
 */

// Load service if needed
if (!class_exists('AIPS_Variable_Resolution_Service')) {
    require_once dirname(dirname(__FILE__)) . '/includes/class-aips-variable-resolution-service.php';
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

class Test_Variable_Resolution_Service extends WP_UnitTestCase {

    public function test_smart_truncate_content() {
        // Mock dependencies
        $processor = $this->getMockBuilder('AIPS_Template_Processor')->getMock();
        $ai = $this->getMockBuilder('AIPS_AI_Service')->getMock();
        $logger = $this->getMockBuilder('AIPS_Logger')->getMock();

        $service = new AIPS_Variable_Resolution_Service($processor, $ai, $logger);

        $content = str_repeat('a', 3000);
        $truncated = $service->smart_truncate_content($content, 100);

        // 100 chars max.
        // Separator "\n\n[...]\n\n" is 9 chars.
        // available = 91.
        // start = 91 * 0.6 = 54.6 -> 54
        // end = 91 - 54 = 37.
        // Total = 54 + 9 + 37 = 100.

        $this->assertEquals(100, strlen($truncated));
        $this->assertStringContainsString('[...]', $truncated);
    }

    public function test_smart_truncate_content_short() {
        // Mock dependencies
        $processor = $this->getMockBuilder('AIPS_Template_Processor')->getMock();
        $ai = $this->getMockBuilder('AIPS_AI_Service')->getMock();
        $logger = $this->getMockBuilder('AIPS_Logger')->getMock();

        $service = new AIPS_Variable_Resolution_Service($processor, $ai, $logger);

        $content = str_repeat('a', 50);
        $truncated = $service->smart_truncate_content($content, 100);

        $this->assertEquals(50, strlen($truncated));
        $this->assertStringNotContainsString('[...]', $truncated);
    }
}
