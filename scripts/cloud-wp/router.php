<?php
/**
 * Router for PHP's built-in server (`php -S ... router.php`) serving the
 * cloud-session WordPress site. Existing files and directories (static assets,
 * wp-admin/*.php, wp-login.php) are handled by the server itself; everything
 * else goes through WordPress so pretty permalinks work.
 */
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

if ( '/' !== $path && file_exists( $_SERVER['DOCUMENT_ROOT'] . urldecode( $path ) ) ) {
	return false;
}

require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
