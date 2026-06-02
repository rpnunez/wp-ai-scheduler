<?php
$root = dirname(__DIR__);
$whitelistFile = $root . '/config/repository-boundary-whitelist.txt';
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

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes'));
$violations = array();

foreach ($iterator as $fileInfo) {
	if (!$fileInfo->isFile()) {
		continue;
	}
	$filename = $fileInfo->getFilename();
	if (!preg_match('/^class-aips-.*-(controller|service)\.php$/', $filename)) {
		continue;
	}
	$relative = ltrim(str_replace($root, '', $fileInfo->getPathname()), '/');
	if (isset($whitelist[$relative])) {
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

if (!empty($violations)) {
	echo "Repository boundary violations detected in controller/service classes:\n";
	foreach ($violations as $path) {
		echo " - {$path}\n";
	}
	echo "Move SQL into repositories or add a temporary whitelist entry with rationale.\n";
	exit(1);
}

echo "Repository boundary check passed: no direct wpdb usage in controllers/services.\n";
