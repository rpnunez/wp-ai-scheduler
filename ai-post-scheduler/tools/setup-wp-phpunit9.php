<?php
declare(strict_types=1);

$toolchainDir = __DIR__ . '/phpunit9';
$composerBinary = getenv('COMPOSER_BINARY');

if (!$composerBinary) {
	fwrite(STDERR, "COMPOSER_BINARY is not available. Run this via `composer test:wp:setup`.\n");
	exit(1);
}

$command = escapeshellarg(PHP_BINARY)
	. ' '
	. escapeshellarg($composerBinary)
	. ' install --working-dir='
	. escapeshellarg($toolchainDir);

passthru($command, $exitCode);
exit($exitCode);
