<?php
/**
 * Offline synchronous bulk-service benchmark with deterministic fixture dependencies.
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$scenario = $argv[2] ?? '';
$root = realpath($argv[1] ?? '');
$service = $root . '/ai-post-scheduler/includes/class-aips-bulk-generator-service.php';
if ($root === false || !is_file($service) || !in_array($scenario, array('success', 'retry', 'incomplete'), true)) {
	fwrite(STDERR, "Usage: php bulk.php <checkout> <success|retry|incomplete>\n");
	exit(2);
}

define('ABSPATH', $root);
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function __($value, $domain) { return $value; }
function get_current_user_id() { return 0; }
function is_wp_error($value) { return $value instanceof WP_Error; }

class WP_Error {
	public function get_error_message() { return 'Fixture failure'; }
	public function get_error_code() { return 'fixture_failure'; }
}

class AIPS_Container {
	public static function get_instance() { return new self(); }
}
interface AIPS_History_Service_Interface {}
class AIPS_History_Record_Kind {
	const JOB = 'job';
}
class AIPS_Generation_Trigger {
	public static function record(...$args) {}
}
class AIPS_Benchmark_History implements AIPS_History_Service_Interface {
	public function create(...$args) { return $this; }
	public function record(...$args) {}
	public function record_user_action(...$args) {}
	public function complete_success(...$args) {}
	public function complete_failure(...$args) {}
}

require $service;

$callbacks = 0;
$attempts = 0;
$generator = new AIPS_Bulk_Generator_Service(new AIPS_Benchmark_History());
$started = hrtime(true);
$result = $generator->run(range(1, 5), static function ($item) use ($scenario, &$callbacks, &$attempts) {
	$callbacks++;
	$attempts++;
	if ($item === 1 && $scenario === 'retry') {
		$attempts++;
	}
	return $item === 1 && $scenario === 'incomplete' ? new WP_Error() : $item;
}, array('limit_default' => 5));
$elapsed_ms = (hrtime(true) - $started) / 1e6;

echo json_encode(array(
	'scenario' => $scenario,
	'php_version' => PHP_VERSION,
	'completed' => $result->success_count,
	'failed' => $result->failed_count,
	'callbacks' => $callbacks,
	'simulated_attempts' => $attempts,
	'elapsed_ms' => $elapsed_ms,
), JSON_THROW_ON_ERROR) . PHP_EOL;
