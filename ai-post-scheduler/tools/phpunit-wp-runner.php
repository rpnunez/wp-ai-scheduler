<?php
declare(strict_types=1);

$toolchainDir = __DIR__ . '/phpunit9';
$vendorBinary = $toolchainDir . '/vendor/bin/phpunit';
$pharBinary = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
	? $toolchainDir . '/vendor/bin/phpunit.bat'
	: $vendorBinary;

$candidate = file_exists($vendorBinary) ? $vendorBinary : $pharBinary;

if (!file_exists($candidate)) {
	fwrite(
		STDERR,
		"PHPUnit 9 toolchain not installed.\nRun `composer test:wp:setup` from ai-post-scheduler/ first.\n"
	);
	exit(1);
}

$args = $_SERVER['argv'];
array_shift($args);

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($candidate);
foreach ($args as $arg) {
	$command .= ' ' . escapeshellarg($arg);
}

passthru($command, $exitCode);
exit($exitCode);
