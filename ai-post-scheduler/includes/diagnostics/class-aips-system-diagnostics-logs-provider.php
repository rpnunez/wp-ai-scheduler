<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * AIPS_System_Diagnostics_Logs_Provider
 */
class AIPS_System_Diagnostics_Logs_Provider implements AIPS_System_Diagnostic_Provider_Interface {

	/**
	 * @return array<string, mixed>
	 */
	public function get_diagnostics(): array {
		return array(
			'notifications' => $this->check_notifications(),
			'logs'          => $this->check_logs(),
		);
	}

	/**
	 * Check notification configuration and runtime diagnostics.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function check_notifications() {
		$repository = class_exists( 'AIPS_Notifications_Repository' ) ? new AIPS_Notifications_Repository() : null;
		$config     = AIPS_Config::get_instance();
		$recipient_list = $config->get_option( 'aips_review_notifications_email' ) ?: (string) get_option( 'admin_email' );
		$recipient_count = 0;
		if ( ! empty( $recipient_list ) ) {
			$parts           = preg_split( '/\s*,\s*/', $recipient_list );
			$parts           = is_array( $parts ) ? array_filter( $parts ) : array();
			$recipient_count = count( $parts );
		}

		$digest         = $config->get_notification_digest_config();
		$daily_marker   = $digest['daily_last_sent'];
		$weekly_marker  = $digest['weekly_last_sent'];
		$monthly_marker = $digest['monthly_last_sent'];

		$new_cron    = wp_next_scheduled( 'aips_notification_rollups' );
		$legacy_cron = wp_next_scheduled( 'aips_send_review_notifications' );

		$counts_24h   = array();
		$unread_count = 0;
		if ( $repository instanceof AIPS_Notifications_Repository ) {
			$counts_24h   = $repository->get_type_counts_for_window( DAY_IN_SECONDS );
			$unread_count = (int) $repository->count_unread();
		}

		$top_types = array();
		if ( ! empty( $counts_24h ) ) {
			arsort( $counts_24h );
			$counts_24h = array_slice( $counts_24h, 0, 8, true );
			foreach ( $counts_24h as $type => $count ) {
				$top_types[] = sprintf( '%s: %d', $type, (int) $count );
			}
		}

		return array(
			'recipients' => array(
				'label'   => __( 'Notification Recipients', 'ai-post-scheduler' ),
				'value'   => $recipient_count > 0 ? sprintf( _n( '%d recipient configured', '%d recipients configured', $recipient_count, 'ai-post-scheduler' ), $recipient_count ) : __( 'No recipients configured', 'ai-post-scheduler' ),
				'status'  => $recipient_count > 0 ? 'ok' : 'warning',
				'details' => $recipient_count > 0 ? array( $recipient_list ) : array(),
			),
			'rollup_markers' => array(
				'label'   => __( 'Rollup Send Markers', 'ai-post-scheduler' ),
				'value'   => __( 'Available', 'ai-post-scheduler' ),
				'status'  => 'info',
				'details' => array(
					sprintf( __( 'Daily marker: %s', 'ai-post-scheduler' ), $daily_marker ? $daily_marker : __( 'not set', 'ai-post-scheduler' ) ),
					sprintf( __( 'Weekly marker: %s', 'ai-post-scheduler' ), $weekly_marker ? $weekly_marker : __( 'not set', 'ai-post-scheduler' ) ),
					sprintf( __( 'Monthly marker: %s', 'ai-post-scheduler' ), $monthly_marker ? $monthly_marker : __( 'not set', 'ai-post-scheduler' ) ),
				),
			),
			'rollup_cron' => array(
				'label'  => __( 'Rollup Cron Hook', 'ai-post-scheduler' ),
				'value'  => $new_cron ? wp_date( 'Y-m-d H:i:s', $new_cron ) : __( 'Not Scheduled', 'ai-post-scheduler' ),
				'status' => $new_cron ? 'ok' : 'warning',
			),
			'legacy_rollup_cron' => array(
				'label'  => __( 'Legacy Rollup Hook (Compatibility)', 'ai-post-scheduler' ),
				'value'  => $legacy_cron ? wp_date( 'Y-m-d H:i:s', $legacy_cron ) : __( 'Not Scheduled', 'ai-post-scheduler' ),
				'status' => $legacy_cron ? 'info' : 'ok',
			),
			'unread_notifications' => array(
				'label'  => __( 'Unread DB Notifications', 'ai-post-scheduler' ),
				'value'  => (string) $unread_count,
				'status' => $unread_count > 0 ? 'info' : 'ok',
			),
			'recent_notification_types' => array(
				'label'   => __( 'Last 24h Notification Volume', 'ai-post-scheduler' ),
				'value'   => empty( $top_types ) ? __( 'No notifications in the last 24 hours', 'ai-post-scheduler' ) : sprintf( __( '%d type(s) active', 'ai-post-scheduler' ), count( $top_types ) ),
				'status'  => empty( $top_types ) ? 'info' : 'ok',
				'details' => $top_types,
			),
		);
	}

	/**
	 * Check the plugin log and, if enabled, the WP debug log.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function check_logs() {
		$logs_data = array();

		$logger    = new AIPS_Logger();
		$log_files = $logger->get_log_files();

		if ( ! empty( $log_files ) ) {
			usort( $log_files, function ( $a, $b ) {
				return strtotime( $b['modified'] ) - strtotime( $a['modified'] );
			} );
			$recent_log = $log_files[0];
			$upload_dir = wp_upload_dir();
			$log_path   = $upload_dir['basedir'] . '/aips-logs/' . $recent_log['name'];

			$errors = $this->scan_file_for_errors( $log_path );

			$logs_data['plugin_log'] = array(
				'label'   => sprintf( __( 'Plugin Log (%s)', 'ai-post-scheduler' ), $recent_log['name'] ),
				'value'   => empty( $errors ) ? __( 'No recent errors', 'ai-post-scheduler' ) : sprintf( __( '%d errors found', 'ai-post-scheduler' ), count( $errors ) ),
				'status'  => empty( $errors ) ? 'ok' : 'warning',
				'details' => $errors,
			);
		} else {
			$logs_data['plugin_log'] = array(
				'label'  => __( 'Plugin Log', 'ai-post-scheduler' ),
				'value'  => __( 'No log files found', 'ai-post-scheduler' ),
				'status' => 'info',
			);
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$debug_log_path = WP_CONTENT_DIR . '/debug.log';
			if ( is_string( WP_DEBUG_LOG ) ) {
				$debug_log_path = WP_DEBUG_LOG;
			}

			if ( file_exists( $debug_log_path ) ) {
				$errors                      = $this->scan_file_for_errors( $debug_log_path, 50, true );
				$logs_data['wp_debug_log'] = array(
					'label'   => __( 'WP Debug Log', 'ai-post-scheduler' ),
					'value'   => empty( $errors ) ? __( 'No recent errors from this plugin', 'ai-post-scheduler' ) : sprintf( __( '%d errors found', 'ai-post-scheduler' ), count( $errors ) ),
					'status'  => empty( $errors ) ? 'ok' : 'warning',
					'details' => $errors,
				);
			}
		}

		return $logs_data;
	}

	/**
	 * Scans a log file for recent error lines.
	 *
	 * @param string $file_path     The path to the file to scan.
	 * @param int    $lines         The number of lines to scan from the end.
	 * @param bool   $filter_plugin Whether to filter only plugin-related errors.
	 * @return string[] Array of error lines found (most recent first).
	 */
	private function scan_file_for_errors( $file_path, $lines = 100, $filter_plugin = false ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array();
		}

		$chunk_size = 1024 * 100;
		$file_size  = filesize( $file_path );

		if ( $file_size === false || $file_size === 0 ) {
			return array();
		}

		$handle = fopen( $file_path, 'rb' );
		if ( $handle === false ) {
			return array();
		}

		$offset = max( 0, $file_size - $chunk_size );
		fseek( $handle, $offset );
		$content = fread( $handle, $chunk_size );
		fclose( $handle );

		if ( $content === false ) {
			return array();
		}

		$file_lines = explode( "\n", $content );

		if ( $offset > 0 && ! empty( $file_lines ) ) {
			array_shift( $file_lines );
		}

		$file_lines = array_slice( $file_lines, - $lines );

		$errors = array();
		foreach ( $file_lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			if ( $filter_plugin && strpos( $line, 'ai-post-scheduler' ) === false ) {
				continue;
			}

			if ( stripos( $line, 'error' ) !== false || stripos( $line, 'warning' ) !== false || stripos( $line, 'fatal' ) !== false ) {
				$errors[] = $line;
			}
		}

		return array_reverse( $errors );
	}
}
