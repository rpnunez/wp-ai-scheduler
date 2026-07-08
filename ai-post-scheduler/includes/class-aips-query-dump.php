<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Query_Dump
 *
 * Developer diagnostic: writes the request's full $wpdb->queries buffer to a
 * JSON-lines file in uploads/aips-logs/ at shutdown. Requires SAVEQUERIES.
 *
 * Enable by setting the 'aips_query_dump_enabled' option to '1' while
 * developer mode is active. Never active otherwise.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Query_Dump {

	public function __construct() {
		add_action( 'shutdown', array( $this, 'capture' ), 999 );
	}

	/**
	 * Write the current query buffer to a timestamped .jsonl file.
	 *
	 * @return string|null Absolute file path, or null when nothing was written or the
	 *                      write failed partway through (the partial file is removed).
	 */
	public function capture(): ?string {
		global $wpdb;

		if (empty( $wpdb->queries ) || !is_array( $wpdb->queries )) {
			return null;
		}

		$uploads = wp_upload_dir();
		if (!empty( $uploads['error'] )) {
			return null;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'aips-logs';
		if (!wp_mkdir_p( $dir )) {
			return null;
		}

		// Block direct listing/execution of the log directory.
		if (!file_exists( $dir . '/index.php' )) {
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
		}

		// Deny direct HTTP access to dump files (Apache only; nginx ignores .htaccess).
		if (!file_exists( $dir . '/.htaccess' )) {
			$htaccess = "<IfModule mod_authz_core.c>\n"
				. "\tRequire all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "\tDeny from all\n"
				. "</IfModule>\n";
			file_put_contents( $dir . '/.htaccess', $htaccess );
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'cli';
		$path    = sprintf( '%s/queries-%s-%s.jsonl', $dir, gmdate( 'Ymd-His' ), wp_generate_password( 16, false ) );

		$handle = fopen( $path, 'w' );
		if (false === $handle) {
			return null;
		}

		$failed = false;

		$meta_json = wp_json_encode( array( 'meta' => array(
			'request' => $request,
			'total'   => count( $wpdb->queries ),
			'time'    => gmdate( 'c' ),
		) ) );

		if ( false === $meta_json || false === fwrite( $handle, $meta_json . "\n" ) ) {
			$failed = true;
		}

		if (!$failed) {
			foreach ($wpdb->queries as $q) {
				$q_json = wp_json_encode( array(
					'sql'     => isset( $q[0] ) ? preg_replace( '/\s+/', ' ', trim( (string) $q[0] ) ) : '',
					'seconds' => isset( $q[1] ) ? (float) $q[1] : 0,
					'caller'  => isset( $q[2] ) ? (string) $q[2] : '',
				) );

				if ( false === $q_json || false === fwrite( $handle, $q_json . "\n" ) ) {
					$failed = true;
					break;
				}
			}
		}

		fclose( $handle );

		if ($failed) {
			unlink( $path );
			return null;
		}

		return $path;
	}
}
