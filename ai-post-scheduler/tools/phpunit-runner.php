<?php
declare(strict_types=1);

/**
 * Local PHPUnit entrypoint.
 *
 * Some checkouts of this repository can end up with a PHPUnit package that
 * still ships `src/TextUI/Command.php`, while Composer's generated launcher
 * assumes a different entrypoint. Loading the command class explicitly keeps
 * `composer test` deterministic across Windows and Docker without modifying
 * vendor-managed files.
 */

$vendor_dir = dirname(__DIR__) . '/vendor';
$autoload = $vendor_dir . '/autoload.php';

spl_autoload_register(
	static function ( string $class ) use ( $vendor_dir ): void {
		if ( strpos( $class, 'PHPUnit\\' ) === 0 ) {
			$relative = substr( $class, strlen( 'PHPUnit\\' ) );
			$file     = $vendor_dir . '/phpunit/phpunit/src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}

			return;
		}

		if ( strpos( $class, 'Doctrine\\Instantiator\\' ) === 0 ) {
			$relative = substr( $class, strlen( 'Doctrine\\Instantiator\\' ) );
			$file     = $vendor_dir . '/doctrine/instantiator/src/Doctrine/Instantiator/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	},
	true,
	true
);

if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "Composer autoload file not found. Run composer install first.\n" );
	exit( 1 );
}

require_once $autoload;

if ( ! class_exists( 'PHPUnit\\TextUI\\Command', false ) ) {
	$legacy_command = $vendor_dir . '/phpunit/phpunit/src/TextUI/Command.php';

	if ( file_exists( $legacy_command ) ) {
		require_once $legacy_command;
	}
}

if ( class_exists( 'PHPUnit\\TextUI\\Command', false ) ) {
	PHPUnit\TextUI\Command::main();
	return;
}

if ( class_exists( 'PHPUnit\\TextUI\\Application', false ) ) {
	$application = new PHPUnit\TextUI\Application();
	$application->run( $_SERVER['argv'] );
	return;
}

fwrite( STDERR, "Unable to locate a compatible PHPUnit TextUI entrypoint.\n" );
exit( 1 );
