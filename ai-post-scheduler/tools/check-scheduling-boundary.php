<?php
/**
 * Scheduling boundary lint.
 *
 * Fails when a file under includes/ calls a low-level scheduling backend API
 * (WP-Cron or Action Scheduler) directly without being on the whitelist of
 * documented infrastructure/bootstrap/diagnostic locations.
 *
 * Feature controllers, cron handlers, and services must schedule background
 * jobs through AIPS_Job_Scheduler so that transport selection, duplicate
 * detection, correlation, logging, and history apply uniformly.
 *
 * The detection helpers below are pure and side-effect free so they can be
 * exercised by a guard test (tests/Test_AIPS_Scheduling_Boundary_Check.php).
 * The directory scan only runs when this file is executed directly, so
 * requiring it from a test defines the helpers without scanning or exiting.
 */

/**
 * Backend scheduling functions that must not be called outside the whitelist.
 *
 * @return string[]
 */
function aips_scheduling_boundary_functions(): array {
	return array(
		'wp_schedule_single_event',
		'wp_schedule_event',
		'wp_reschedule_event',
		'wp_next_scheduled',
		'wp_unschedule_event',
		'wp_clear_scheduled_hook',
		'wp_unschedule_hook',
		'as_schedule_single_action',
		'as_schedule_recurring_action',
		'as_enqueue_async_action',
		'as_next_scheduled_action',
		'as_unschedule_action',
		'as_unschedule_all_actions',
		'as_has_scheduled_action',
	);
}

/**
 * Scan a chunk of PHP source for direct backend scheduling calls.
 *
 * Detects two forms:
 *  - Direct calls, including the explicit-global "\wp_schedule_single_event("
 *    form, while rejecting method/static calls, variables, and longer
 *    identifiers that merely end in one of these names.
 *  - Dynamic calls by name, where the function name appears as a complete
 *    quoted string literal — e.g. call_user_func('as_schedule_single_action',
 *    ...) or array($this, 'wp_...') — which reach the same global API while
 *    slipping past the direct-call pattern.
 *
 * @param string        $content   PHP source to scan.
 * @param string[]|null $functions Function names to look for; defaults to the
 *                                 full backend list.
 * @return string[] Unique names of backend functions found, in first-seen order.
 */
function aips_scheduling_boundary_scan(string $content, ?array $functions = null): array {
	$functions = $functions ?: aips_scheduling_boundary_functions();
	$names     = implode('|', array_map('preg_quote', $functions));

	// Direct-call pattern. The lookbehind rejects method/static calls, variables,
	// and longer identifiers that merely end in one of these names:
	//   $obj->wp_next_scheduled(  (">")   Foo::as_unschedule_action(  (":")
	//   $wp_next_scheduled(       ("$", a variable, not a call)   my_wp_cron(  ("\w")
	// An optional leading backslash then catches the explicit-global form
	// "\wp_schedule_single_event(", which calls the same global function. Because
	// a backslash is also in the rejection set, a genuinely namespaced
	// "Ns\wp_...(" (backslash preceded by an identifier) is still left alone.
	$pattern = '/(?<![\w\$>:\\\\])\\\\?(' . $names . ')\s*\(/';

	// Dynamic-call pattern: the function name as a complete quoted string literal.
	$stringPattern = '/([\'"])(' . $names . ')\1/';

	$found = array();
	if (preg_match_all($pattern, $content, $matches)) {
		$found = array_merge($found, $matches[1]);
	}
	if (preg_match_all($stringPattern, $content, $stringMatches)) {
		$found = array_merge($found, $stringMatches[2]);
	}

	return array_values(array_unique($found));
}

/**
 * Load the whitelist of paths allowed to call backend scheduling APIs directly.
 *
 * @param string $whitelistFile Absolute path to the whitelist file.
 * @return array<string,bool> Map of normalised relative path => true.
 */
function aips_scheduling_boundary_whitelist(string $whitelistFile): array {
	$whitelist = array();
	if (file_exists($whitelistFile)) {
		$lines = file($whitelistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$line = trim($line);
			if ('' === $line || '#' === $line[0]) {
				continue;
			}
			$whitelist[$line] = true;
		}
	}

	return $whitelist;
}

/**
 * Scan the includes/ tree and return violations keyed by relative path.
 *
 * @param string             $root      Plugin root (dir containing includes/).
 * @param array<string,bool> $whitelist Allowed relative paths.
 * @return array<string,string[]> Map of relative path => backend names found.
 */
function aips_scheduling_boundary_scan_tree(string $root, array $whitelist): array {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root . '/includes', FilesystemIterator::SKIP_DOTS)
	);

	$violations = array();

	foreach ($iterator as $fileInfo) {
		if (!$fileInfo->isFile() || 'php' !== strtolower($fileInfo->getExtension())) {
			continue;
		}

		$relative = ltrim(str_replace($root, '', $fileInfo->getPathname()), '/\\');
		$relative = str_replace('\\', '/', $relative);

		if (isset($whitelist[$relative])) {
			continue;
		}

		$content = file_get_contents($fileInfo->getPathname());
		if (false === $content) {
			continue;
		}

		$found = aips_scheduling_boundary_scan($content);
		if (!empty($found)) {
			$violations[$relative] = $found;
		}
	}

	return $violations;
}

// Only run the scan when executed directly (php tools/check-scheduling-boundary.php).
// A test that requires this file for its helpers skips the scan and exit().
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
	$root       = dirname(__DIR__);
	$whitelist  = aips_scheduling_boundary_whitelist($root . '/config/scheduling-boundary-whitelist.txt');
	$violations = aips_scheduling_boundary_scan_tree($root, $whitelist);

	if (!empty($violations)) {
		echo "Scheduling boundary violations detected:\n";
		foreach ($violations as $path => $calls) {
			echo " - {$path}: " . implode(', ', $calls) . "()\n";
		}
		echo "\nSchedule background jobs through AIPS_Job_Scheduler instead of calling\n";
		echo "WP-Cron / Action Scheduler APIs directly. If this is legitimate\n";
		echo "infrastructure, bootstrap, or diagnostic code, add the file to\n";
		echo "config/scheduling-boundary-whitelist.txt with rationale.\n";
		exit(1);
	}

	echo "Scheduling boundary check passed: no direct backend scheduling calls outside the whitelist.\n";
}
