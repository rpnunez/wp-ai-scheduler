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
 */

$root = dirname(__DIR__);
$whitelistFile = $root . '/config/scheduling-boundary-whitelist.txt';

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

// Backend scheduling functions that must not be called outside the whitelist.
// Matched only as function calls (name immediately followed by "("), so
// doc comments and string mentions do not trigger false positives.
$functions = array(
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

// Negative lookbehind rejects method calls so only global-function calls match:
//   $obj->wp_next_scheduled(  (">")   Foo::as_unschedule_action(  (":")
//   \wp_next_scheduled(       ("\")   $wp_next_scheduled(          ("$")
// plus any identifier char ("\w") so longer names ending in one of these are
// not mistaken for the bare function.
$pattern = '/(?<![\w\$>:\\\\])(' . implode('|', array_map('preg_quote', $functions)) . ')\s*\(/';

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

	if (preg_match_all($pattern, $content, $matches)) {
		$violations[$relative] = array_values(array_unique($matches[1]));
	}
}

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
