<?php
/**
 * Enforce container-first instantiation policy in includes/.
 *
 * Flags direct instantiation of cross-cutting services:
 * - AIPS_Logger
 * - AIPS_History_Service
 * - AIPS_AI_Service
 * - AIPS_Resilience_Service
 *
 * Optional whitelist file:
 *   config/container-instantiation-whitelist.txt
 * One relative path per line (relative to plugin root).
 */

$root = dirname(__DIR__);
$includesDir = $root . '/includes';
$whitelistFile = $root . '/config/container-instantiation-whitelist.txt';

$patterns = array(
	'AIPS_Logger' => '/new\s+AIPS_Logger\s*\(/',
	'AIPS_History_Service' => '/new\s+AIPS_History_Service\s*\(/',
	'AIPS_AI_Service' => '/new\s+AIPS_AI_Service\s*\(/',
	'AIPS_Resilience_Service' => '/new\s+AIPS_Resilience_Service\s*\(/',
);

$whitelist = array();
if (file_exists($whitelistFile)) {
	$lines = file($whitelistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (is_array($lines)) {
		foreach ($lines as $line) {
			$line = trim($line);
			if ('' === $line || '#' === substr($line, 0, 1)) {
				continue;
			}
			$whitelist[str_replace('\\', '/', $line)] = true;
		}
	}
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($includesDir, FilesystemIterator::SKIP_DOTS)
);

$violations = array();

foreach ($iterator as $fileInfo) {
	if (!$fileInfo->isFile()) {
		continue;
	}

	$path = $fileInfo->getPathname();
	if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
		continue;
	}

	$relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/'));
	if (isset($whitelist[$relative])) {
		continue;
	}

	$content = file_get_contents($path);
	if ($content === false) {
		continue;
	}

	foreach ($patterns as $class => $regex) {
		if (!preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($matches[0] as $match) {
			$offset = $match[1];
			$line = substr_count(substr($content, 0, $offset), "\n") + 1;
			$violations[] = array(
				'file' => $relative,
				'line' => $line,
				'class' => $class,
			);
		}
	}
}

if (!empty($violations)) {
	echo "Container instantiation policy violations detected in includes/:\n";
	foreach ($violations as $violation) {
		echo sprintf(
			" - %s:%d (%s)\n",
			$violation['file'],
			$violation['line'],
			$violation['class']
		);
	}
	echo "Resolve via AIPS_Container::make()/makeIfExists() or add an approved whitelist entry with rationale.\n";
	exit(1);
}

echo "Container instantiation policy check passed: no direct core-service instantiation in includes/.\n";
