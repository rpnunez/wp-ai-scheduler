<?php
/**
 * Class Test_Logger_Security
 *
 * Tests the security features of the AIPS_Logger class.
 */
class Test_Logger_Security extends WP_UnitTestCase {

    /**
     * Test that redact_context correctly hides sensitive keys.
     */
    public function test_redact_context() {
        // Ensure the class is loaded
        if (!class_exists('AIPS_Logger')) {
            require_once dirname(dirname(__FILE__)) . '/ai-post-scheduler/includes/class-aips-logger.php';
        }

        $sensitive = array(
            'api_key' => 'secret123',
            'access_token' => 'token456',
            'nested' => array(
                'client_secret' => 'super_secret',
                'max_tokens' => 2000,
                'completion_tokens' => 150,
                'post_author' => 1
            ),
            'auth' => 'Bearer token'
        );

        $redacted = AIPS_Logger::redact_context($sensitive);

        // Verify sensitive keys are redacted
        $this->assertEquals('***REDACTED***', $redacted['api_key']);
        $this->assertEquals('***REDACTED***', $redacted['access_token']);
        $this->assertEquals('***REDACTED***', $redacted['nested']['client_secret']);
        $this->assertEquals('***REDACTED***', $redacted['auth']);

        // Verify safe keys containing 'token' or 'auth' are PRESERVED
        $this->assertEquals(2000, $redacted['nested']['max_tokens']);
        $this->assertEquals(150, $redacted['nested']['completion_tokens']);
        $this->assertEquals(1, $redacted['nested']['post_author']);
    }

    /**
     * Test that redact_context handles non-array input safely.
     */
    public function test_redact_context_handles_scalars() {
        if (!class_exists('AIPS_Logger')) {
            require_once dirname(dirname(__FILE__)) . '/ai-post-scheduler/includes/class-aips-logger.php';
        }

        $input = "simple string";
        $output = AIPS_Logger::redact_context($input);
        $this->assertEquals($input, $output);

        $input_null = null;
        $output_null = AIPS_Logger::redact_context($input_null);
        $this->assertEquals($input_null, $output_null);
    }
}
