<?php

function aips_load_boundary_allowlist($file) {
	$allowlist = array();
	if (!file_exists($file)) {
		return $allowlist;
	}

	$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (false === $lines) {
		return $allowlist;
	}

	foreach ($lines as $line) {
		$line = trim($line);
		if ('' === $line || '#' === $line[0]) {
			continue;
		}

		$allowlist[$line] = true;
	}

	return $allowlist;
}

function aips_relative_boundary_path($root, $path) {
	$normalized_root = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $root);
	$normalized_path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
	$relative        = str_replace($normalized_root . DIRECTORY_SEPARATOR, '', $normalized_path);
	return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
}

function aips_scan_controller_service_wpdb_usage($root, array $allowlist) {
	$iterator   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes'));
	$violations = array();

	foreach ($iterator as $fileInfo) {
		if (!$fileInfo->isFile()) {
			continue;
		}

		$filename = $fileInfo->getFilename();
		if (!preg_match('/^class-aips-.*-(controller|service)\.php$/', $filename)) {
			continue;
		}

		$relative = aips_relative_boundary_path($root, $fileInfo->getPathname());
		if (isset($allowlist[$relative])) {
			continue;
		}

		$content = file_get_contents($fileInfo->getPathname());
		if (false === $content) {
			continue;
		}

		if (preg_match('/\$wpdb\s*->|global\s+\$wpdb/', $content)) {
			$violations[] = $relative;
		}
	}

	sort($violations);
	return $violations;
}

function aips_scan_repository_legacy_cache_usage($root, array $baseline) {
	$iterator      = new DirectoryIterator($root . '/includes');
	$violations    = array();
	$stale_entries = array();
	$matched       = array();

	foreach ($iterator as $fileInfo) {
		if (!$fileInfo->isFile()) {
			continue;
		}

		$filename = $fileInfo->getFilename();
		if (!preg_match('/^class-aips-.*-repository\.php$/', $filename)) {
			continue;
		}

		$relative = aips_relative_boundary_path($root, $fileInfo->getPathname());
		$content  = file_get_contents($fileInfo->getPathname());
		if (false === $content) {
			continue;
		}

		$uses_legacy_cache = (bool) preg_match('/AIPS_Cache_Invalidation_Bus::|AIPS_Cache_Policy::/', $content);
		if (!$uses_legacy_cache) {
			continue;
		}

		$matched[$relative] = true;
		if (!isset($baseline[$relative])) {
			$violations[] = $relative;
		}
	}

	foreach (array_keys($baseline) as $relative) {
		if (!isset($matched[$relative])) {
			$stale_entries[] = $relative;
		}
	}

	sort($violations);
	sort($stale_entries);

	return array(
		'violations'    => $violations,
		'stale_entries' => $stale_entries,
	);
}

function aips_run_repository_boundary_check($root) {
	$wpdb_allowlist = aips_load_boundary_allowlist($root . '/config/repository-boundary-whitelist.txt');
	$legacy_baseline = aips_load_boundary_allowlist($root . '/config/repository-cache-legacy-baseline.txt');

	return array(
		'wpdb_violations'     => aips_scan_controller_service_wpdb_usage($root, $wpdb_allowlist),
		'legacy_cache_result' => aips_scan_repository_legacy_cache_usage($root, $legacy_baseline),
	);
}

function aips_print_repository_boundary_report(array $result) {
	$has_errors = false;

	if (!empty($result['wpdb_violations'])) {
		$has_errors = true;
		echo "Repository boundary violations detected in controller/service classes:\n";
		foreach ($result['wpdb_violations'] as $path) {
			echo " - {$path}\n";
		}
		echo "Move SQL into repositories or add a temporary whitelist entry with rationale.\n";
	}

	if (!empty($result['legacy_cache_result']['violations'])) {
		$has_errors = true;
		echo "Legacy repository cache usage detected outside the approved migration baseline:\n";
		foreach ($result['legacy_cache_result']['violations'] as $path) {
			echo " - {$path}\n";
		}
		echo "Use AIPS_Cacheable_Repository plus explicit repository cache policies instead of AIPS_Cache_Policy/AIPS_Cache_Invalidation_Bus in new repository work.\n";
	}

	if (!empty($result['legacy_cache_result']['stale_entries'])) {
		$has_errors = true;
		echo "Repository cache legacy baseline contains stale entries:\n";
		foreach ($result['legacy_cache_result']['stale_entries'] as $path) {
			echo " - {$path}\n";
		}
		echo "Remove stale entries from config/repository-cache-legacy-baseline.txt.\n";
	}

	if ($has_errors) {
		return 1;
	}

	echo "Repository boundary check passed: controller/service SQL boundaries and repository cache migration guardrails are intact.\n";
	return 0;
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	$root = dirname(__DIR__);
	exit(aips_print_repository_boundary_report(aips_run_repository_boundary_check($root)));
}
