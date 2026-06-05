<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Optional introspection contract for cache drivers used by Cache Monitor.
 */
interface AIPS_Cache_Monitorable_Driver {

	public function get_monitor_capabilities();

	public function list_entries( array $filters = array(), $limit = 100, $offset = 0 );

	public function count_entries( array $filters = array() );

	public function get_entry_metadata( $key, $group = 'default' );

	public function delete_entry( $key, $group = 'default' );

	public function delete_group( $group );

	public function estimate_size( array $filters = array() );
}
