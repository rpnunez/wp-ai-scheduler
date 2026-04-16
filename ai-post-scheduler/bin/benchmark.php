#!/usr/bin/env php
<?php
/**
 * Performance Benchmark Script
 *
 * Boots WordPress and measures performance metrics:
 * - Database queries count ($wpdb->num_queries)
 * - Peak memory usage (memory_get_peak_usage)
 * - Wall time for page loads
 *
 * This script is used in CI to detect performance regressions.
 *
 * @package AI_Post_Scheduler
 */

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
	die("This script must be run from the command line.\n");
}

// Parse command line arguments
$options = getopt('', array(
	'wp-core-dir:',
	'baseline-file::',
	'output-file::',
	'fail-on-regression',
));

$wp_core_dir = $options['wp-core-dir'] ?? getenv('WP_CORE_DIR') ?: '/tmp/wordpress';
$baseline_file = $options['baseline-file'] ?? '';
$output_file = $options['output-file'] ?? '';
$fail_on_regression = isset($options['fail-on-regression']);

// Output configuration
echo "========================================\n";
echo "Performance Benchmark\n";
echo "========================================\n";
echo "WordPress Core: $wp_core_dir\n";
if ($baseline_file) {
	echo "Baseline File: $baseline_file\n";
}
if ($output_file) {
	echo "Output File: $output_file\n";
}
echo "========================================\n\n";

// Define ABSPATH for WordPress
if (!defined('ABSPATH')) {
	define('ABSPATH', rtrim($wp_core_dir, '/') . '/');
}

// Load WordPress
if (!file_exists(ABSPATH . 'wp-load.php')) {
	die("Error: WordPress not found at " . ABSPATH . "\n");
}

// Bootstrap WordPress
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once ABSPATH . 'wp-load.php';

// Ensure wpdb is available
global $wpdb;
if (!$wpdb) {
	die("Error: WordPress database not initialized.\n");
}

// Initialize benchmarking results
$benchmarks = array();

/**
 * Run a benchmark test
 *
 * @param string $name Test name
 * @param callable $callback Test callback
 * @return array Metrics array
 */
function run_benchmark($name, $callback) {
	global $wpdb;

	// Reset query counter
	$wpdb->num_queries = 0;

	// Record start metrics
	$start_time = microtime(true);
	$start_memory = memory_get_usage(true);
	$start_queries = $wpdb->num_queries;

	// Run the callback
	$callback();

	// Record end metrics
	$end_time = microtime(true);
	$end_memory = memory_get_usage(true);
	$end_queries = $wpdb->num_queries;

	// Calculate metrics
	$metrics = array(
		'name' => $name,
		'queries' => $end_queries - $start_queries,
		'memory_delta' => $end_memory - $start_memory,
		'memory_peak' => memory_get_peak_usage(true),
		'wall_time' => ($end_time - $start_time) * 1000, // Convert to milliseconds
	);

	return $metrics;
}

/**
 * Format bytes to human-readable string
 *
 * @param int $bytes Bytes
 * @return string Formatted string
 */
function format_bytes($bytes) {
	$units = array('B', 'KB', 'MB', 'GB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Print benchmark results
 *
 * @param array $metrics Metrics array
 */
function print_metrics($metrics) {
	echo sprintf(
		"%-40s | Queries: %4d | Memory: %10s | Peak: %10s | Time: %8.2fms\n",
		$metrics['name'],
		$metrics['queries'],
		format_bytes($metrics['memory_delta']),
		format_bytes($metrics['memory_peak']),
		$metrics['wall_time']
	);
}

// Run benchmarks
echo "Running benchmarks...\n\n";
echo str_repeat('-', 120) . "\n";

// Benchmark 1: Admin Dashboard Page Load
$benchmarks['admin_dashboard'] = run_benchmark('Admin Dashboard Load', function() {
	// Simulate admin dashboard load
	if (!defined('WP_ADMIN')) {
		define('WP_ADMIN', true);
	}

	// Load admin functions
	if (file_exists(ABSPATH . 'wp-admin/includes/admin.php')) {
		// Set current screen
		set_current_screen('dashboard');

		// Get admin dashboard data (simulates typical admin load)
		$user = wp_get_current_user();
		$plugins = get_plugins();
		$active_plugins = get_option('active_plugins', array());
	}
});
print_metrics($benchmarks['admin_dashboard']);

// Benchmark 2: Frontend Page Load
$benchmarks['frontend_page'] = run_benchmark('Frontend Page Load', function() {
	global $wp, $wp_query, $wp_the_query;

	// Simulate a frontend page load
	$wp_query = new WP_Query(array(
		'post_type' => 'post',
		'posts_per_page' => 10,
		'orderby' => 'date',
		'order' => 'DESC',
	));

	// Get posts (triggers query)
	if ($wp_query->have_posts()) {
		while ($wp_query->have_posts()) {
			$wp_query->the_post();
			// Simulate typical frontend template functions
			get_the_title();
			get_the_permalink();
			get_the_excerpt();
		}
		wp_reset_postdata();
	}
});
print_metrics($benchmarks['frontend_page']);

// Benchmark 3: Plugin Admin Page (AI Post Scheduler Settings)
$benchmarks['plugin_admin'] = run_benchmark('Plugin Admin Page Load', function() {
	// Load plugin admin functions if available
	if (class_exists('AIPS_Settings')) {
		// Simulate loading the plugin settings page
		$settings = new AIPS_Settings();

		// Get plugin options (typical admin page load)
		$options = array(
			get_option('aips_ai_engine_enabled'),
			get_option('aips_default_post_status'),
			get_option('aips_enable_logging'),
		);
	}
});
print_metrics($benchmarks['plugin_admin']);

// Benchmark 4: AJAX Endpoint (Template List)
$benchmarks['ajax_endpoint'] = run_benchmark('AJAX Endpoint (Template List)', function() {
	// Simulate AJAX endpoint call
	if (class_exists('AIPS_Template_Repository')) {
		$repo = new AIPS_Template_Repository();
		$templates = $repo->get_all();
	}
});
print_metrics($benchmarks['ajax_endpoint']);

// Benchmark 5: Database-heavy operation (Schedule Check)
$benchmarks['schedule_check'] = run_benchmark('Schedule Check (Heavy Query)', function() {
	global $wpdb;

	// Simulate a schedule check operation
	if (class_exists('AIPS_Schedule_Repository')) {
		$repo = new AIPS_Schedule_Repository();
		$due_schedules = $repo->get_due_schedules();
	} else {
		// Fallback to direct query
		$table = $wpdb->prefix . 'aips_schedule';
		$wpdb->get_results("SELECT * FROM {$table} WHERE is_active = 1 LIMIT 10");
	}
});
print_metrics($benchmarks['schedule_check']);

echo str_repeat('-', 120) . "\n\n";

// Calculate totals
$totals = array(
	'queries' => array_sum(array_column($benchmarks, 'queries')),
	'memory_peak' => max(array_column($benchmarks, 'memory_peak')),
	'wall_time' => array_sum(array_column($benchmarks, 'wall_time')),
);

echo "TOTALS:\n";
echo sprintf(
	"Total Queries: %d | Peak Memory: %s | Total Time: %.2fms\n\n",
	$totals['queries'],
	format_bytes($totals['memory_peak']),
	$totals['wall_time']
);

// Save results to file if requested
if ($output_file) {
	$output_data = array(
		'timestamp' => date('Y-m-d H:i:s'),
		'php_version' => PHP_VERSION,
		'benchmarks' => $benchmarks,
		'totals' => $totals,
	);

	file_put_contents($output_file, json_encode($output_data, JSON_PRETTY_PRINT));
	echo "Results saved to: $output_file\n\n";
}

// Compare against baseline if provided
if ($baseline_file && file_exists($baseline_file)) {
	echo "========================================\n";
	echo "Baseline Comparison\n";
	echo "========================================\n\n";

	$baseline = json_decode(file_get_contents($baseline_file), true);

	if (!$baseline || !isset($baseline['totals'])) {
		echo "Error: Invalid baseline file format.\n";
		exit(1);
	}

	// Define thresholds (% increase allowed)
	$thresholds = array(
		'queries' => 20,      // Allow 20% increase in queries
		'memory_peak' => 25,  // Allow 25% increase in memory
		'wall_time' => 30,    // Allow 30% increase in wall time
	);

	$failed = false;

	foreach (array('queries', 'memory_peak', 'wall_time') as $metric) {
		$baseline_value = $baseline['totals'][$metric];
		$current_value = $totals[$metric];

		if ($baseline_value == 0) {
			echo "$metric: No baseline value (baseline: 0)\n";
			continue;
		}

		$increase_pct = (($current_value - $baseline_value) / $baseline_value) * 100;
		$threshold = $thresholds[$metric];

		$status = 'PASS';
		if ($increase_pct > $threshold) {
			$status = 'FAIL';
			$failed = true;
		}

		$metric_label = ucfirst(str_replace('_', ' ', $metric));
		$baseline_formatted = ($metric === 'memory_peak') ? format_bytes($baseline_value) : $baseline_value;
		$current_formatted = ($metric === 'memory_peak') ? format_bytes($current_value) : $current_value;

		echo sprintf(
			"%-15s | Baseline: %12s | Current: %12s | Change: %+7.2f%% | Threshold: %+3d%% | %s\n",
			$metric_label,
			$baseline_formatted,
			$current_formatted,
			$increase_pct,
			$threshold,
			$status
		);
	}

	echo "\n";

	if ($failed && $fail_on_regression) {
		echo "FAILED: Performance regression detected!\n";
		exit(1);
	} elseif ($failed) {
		echo "WARNING: Performance regression detected (not failing build).\n";
		exit(0);
	} else {
		echo "SUCCESS: All performance metrics within acceptable thresholds.\n";
		exit(0);
	}
} else {
	echo "No baseline file provided or found. Results saved for future comparison.\n";
	exit(0);
}
