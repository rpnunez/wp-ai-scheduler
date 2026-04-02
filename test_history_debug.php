<?php
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'ai-post-scheduler/tests/bootstrap.php';

class Debug_Test extends WP_UnitTestCase {
    public function test_debug() {
        $history_instance = new AIPS_History();
        $history_handler = $history_instance;
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
        } catch (Throwable $e) {
            echo "FILE: " . $e->getFile() . "\n";
            echo "LINE: " . $e->getLine() . "\n";
            echo "MESSAGE: " . $e->getMessage() . "\n";
        }
    }
}

$test = new Debug_Test();
$test->setUp();
$test->test_debug();
