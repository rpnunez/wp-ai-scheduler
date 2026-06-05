<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Monitor_Controller {

	const NONCE_ACTION = 'aips_cache_monitor_action';
	const AJAX_NONCE_ACTION = 'aips_cache_monitor_ajax';

	private $service;

	public function __construct( AIPS_Cache_Monitor_Service $service = null ) {
		$this->service = $service ? $service : new AIPS_Cache_Monitor_Service();
		add_action( 'admin_post_aips_cache_monitor_action', array( $this, 'handle_admin_action' ) );
		foreach ($this->ajax_actions() as $action => $method) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	public function render_page() {
		if (!current_user_can( 'manage_options' )) {
			wp_die( esc_html__( 'Permission denied.', 'ai-post-scheduler' ) );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$notice = isset( $_GET['aips_cache_monitor_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['aips_cache_monitor_notice'] ) ) : '';
		$summary = $this->service->get_summary();
		$entries_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 25;
		$filters = $this->get_entry_filters( $_GET );
		$entries = $this->service->list_entries( $filters, $per_page, ( $entries_page - 1 ) * $per_page, $this->get_orderby(), $this->get_order() );
		$inspect = null;
		if (!empty( $_GET['inspect'] )) {
			$inspect = $this->service->inspect_entry( sanitize_text_field( wp_unslash( $_GET['inspect'] ) ), isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : '' );
		}
		$tags = $this->service->list_tags();
		$domains = $this->service->list_domains();
		$operations = $this->service->list_operations();
		$events = $this->service->list_events( array(), 50, 0 );
		$driver_status = $this->service->get_driver_status();
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		$ajax_nonce = wp_create_nonce( self::AJAX_NONCE_ACTION );
		include AIPS_PLUGIN_DIR . 'templates/admin/cache-monitor.php';
	}

	public function handle_admin_action() {
		if (!current_user_can( 'manage_options' )) {
			wp_die( esc_html__( 'Permission denied.', 'ai-post-scheduler' ) );
		}
		check_admin_referer( self::NONCE_ACTION, '_aips_cache_monitor_nonce' );
		$monitor_action = isset( $_POST['monitor_action'] ) ? sanitize_key( wp_unslash( $_POST['monitor_action'] ) ) : '';
		$message = __( 'Cache action completed.', 'ai-post-scheduler' );
		if ($monitor_action === 'delete_entry') {
			$count = $this->service->delete_entry( $this->posted_text( 'key_hash' ), $this->posted_text( 'group' ) );
			$message = sprintf( _n( '%d cache entry deleted.', '%d cache entries deleted.', $count, 'ai-post-scheduler' ), $count );
		} elseif ($monitor_action === 'delete_selected') {
			$items = $this->posted_items();
			$count = $this->service->delete_selected( $items );
			$message = sprintf( _n( '%d selected cache entry deleted.', '%d selected cache entries deleted.', $count, 'ai-post-scheduler' ), $count );
		} elseif ($monitor_action === 'flush_group') {
			$count = $this->service->flush_group( $this->posted_text( 'group' ) );
			$message = sprintf( _n( '%d cache entry removed from the group.', '%d cache entries removed from the group.', $count, 'ai-post-scheduler' ), $count );
		} elseif ($monitor_action === 'invalidate_tag') {
			$result = $this->service->invalidate_tag( $this->posted_text( 'tag' ) );
			$message = sprintf( __( 'Tag invalidated. New version: %1$d. Indexed entries affected: %2$d.', 'ai-post-scheduler' ), (int) $result['version'], (int) $result['affected_count'] );
		} elseif ($monitor_action === 'invalidate_domain') {
			$result = $this->service->invalidate_domain( $this->posted_text( 'domain' ) );
			$message = sprintf( __( 'Domain invalidated. Indexed entries affected: %d.', 'ai-post-scheduler' ), (int) $result['affected_count'] );
		} elseif ($monitor_action === 'flush_expired') {
			$result = $this->service->flush_expired();
			$message = sprintf( __( 'Expired cleanup complete. Index: %1$d. DB cache: %2$d.', 'ai-post-scheduler' ), (int) $result['index'], (int) $result['db_cache'] );
		} elseif ($monitor_action === 'flush_all') {
			if (empty( $_POST['confirm_flush_all'] )) {
				wp_die( esc_html__( 'Please confirm before flushing all plugin cache entries.', 'ai-post-scheduler' ) );
			}
			$count = $this->service->flush_all_plugin_cache();
			$message = sprintf( __( 'Plugin cache flushed. Indexed entries reset: %d.', 'ai-post-scheduler' ), $count );
		} elseif ($monitor_action === 'reset_index') {
			if (empty( $_POST['confirm_reset_index'] )) {
				wp_die( esc_html__( 'Please confirm before resetting the cache index.', 'ai-post-scheduler' ) );
			}
			$count = $this->service->reset_index();
			$message = sprintf( __( 'Cache index reset. Rows removed: %d.', 'ai-post-scheduler' ), $count );
		} elseif ($monitor_action === 'maintenance') {
			$result = $this->service->run_maintenance_action( $this->posted_text( 'maintenance_action' ) );
			$message = __( 'Maintenance action completed.', 'ai-post-scheduler' ) . ' ' . wp_json_encode( $result );
		}
		$this->redirect_with_notice( $message );
	}

	public function ajax_summary() {
		$this->verify_ajax();
		AIPS_Ajax_Response::success( array( 'summary' => $this->service->get_summary() ) );
	}

	public function ajax_entries() {
		$this->verify_ajax();
		$filters = $this->get_entry_filters( $_REQUEST );
		$page = isset( $_REQUEST['page_num'] ) ? max( 1, absint( $_REQUEST['page_num'] ) ) : 1;
		$per_page = isset( $_REQUEST['per_page'] ) ? max( 1, min( 100, absint( $_REQUEST['per_page'] ) ) ) : 25;
		AIPS_Ajax_Response::success( $this->service->list_entries( $filters, $per_page, ( $page - 1 ) * $per_page, $this->get_orderby(), $this->get_order() ) );
	}

	public function ajax_inspect() {
		$this->verify_ajax();
		$entry = $this->service->inspect_entry( $this->request_text( 'key_hash' ), $this->request_text( 'group' ) );
		if (!$entry) {
			AIPS_Ajax_Response::not_found( __( 'Cache entry', 'ai-post-scheduler' ) );
		}
		AIPS_Ajax_Response::success( array( 'entry' => $entry ) );
	}

	public function ajax_delete_entry() {
		$this->verify_ajax();
		$count = $this->service->delete_entry( $this->request_text( 'key_hash' ), $this->request_text( 'group' ) );
		AIPS_Ajax_Response::success( array( 'affected_count' => $count ), __( 'Cache entry deleted.', 'ai-post-scheduler' ) );
	}

	public function ajax_flush_group() {
		$this->verify_ajax();
		$count = $this->service->flush_group( $this->request_text( 'group' ) );
		AIPS_Ajax_Response::success( array( 'affected_count' => $count ), __( 'Cache group flushed.', 'ai-post-scheduler' ) );
	}

	public function ajax_invalidate_tag() {
		$this->verify_ajax();
		AIPS_Ajax_Response::success( $this->service->invalidate_tag( $this->request_text( 'tag' ) ), __( 'Cache tag invalidated.', 'ai-post-scheduler' ) );
	}

	public function ajax_invalidate_domain() {
		$this->verify_ajax();
		AIPS_Ajax_Response::success( $this->service->invalidate_domain( $this->request_text( 'domain' ) ), __( 'Cache domain invalidated.', 'ai-post-scheduler' ) );
	}

	public function ajax_operations() {
		$this->verify_ajax();
		AIPS_Ajax_Response::success( array( 'operations' => $this->service->list_operations() ) );
	}

	public function ajax_events() {
		$this->verify_ajax();
		AIPS_Ajax_Response::success( array( 'events' => $this->service->list_events() ) );
	}

	public function ajax_maintenance() {
		$this->verify_ajax();
		AIPS_Ajax_Response::success( $this->service->run_maintenance_action( $this->request_text( 'maintenance_action' ) ), __( 'Maintenance action completed.', 'ai-post-scheduler' ) );
	}

	private function ajax_actions() {
		return array(
			'aips_cache_monitor_summary' => 'ajax_summary',
			'aips_cache_monitor_entries' => 'ajax_entries',
			'aips_cache_monitor_inspect' => 'ajax_inspect',
			'aips_cache_monitor_delete_entry' => 'ajax_delete_entry',
			'aips_cache_monitor_flush_group' => 'ajax_flush_group',
			'aips_cache_monitor_invalidate_tag' => 'ajax_invalidate_tag',
			'aips_cache_monitor_invalidate_domain' => 'ajax_invalidate_domain',
			'aips_cache_monitor_operations' => 'ajax_operations',
			'aips_cache_monitor_events' => 'ajax_events',
			'aips_cache_monitor_maintenance' => 'ajax_maintenance',
		);
	}

	private function verify_ajax() {
		if (!current_user_can( 'manage_options' )) {
			AIPS_Ajax_Response::permission_denied();
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if (!wp_verify_nonce( $nonce, self::AJAX_NONCE_ACTION )) {
			AIPS_Ajax_Response::invalid_request( __( 'Invalid cache monitor nonce.', 'ai-post-scheduler' ) );
		}
	}

	private function get_entry_filters( $source ) {
		$map = array( 'group', 'tier', 'driver', 'operation_id', 'repository', 'tag', 'ttl_state', 'search', 'domain', 'min_size', 'max_size' );
		$filters = array();
		foreach ($map as $key) {
			if (isset( $source[ $key ] ) && $source[ $key ] !== '') {
				$filters[ $key ] = sanitize_text_field( wp_unslash( $source[ $key ] ) );
			}
		}
		return $filters;
	}

	private function get_orderby() {
		return isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'updated_at';
	}

	private function get_order() {
		return isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
	}

	private function posted_text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private function request_text( $key ) {
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	private function posted_items() {
		$items = array();
		if (empty( $_POST['selected_entries'] ) || !is_array( $_POST['selected_entries'] )) {
			return $items;
		}
		foreach (wp_unslash( $_POST['selected_entries'] ) as $encoded) {
			$parts = explode( '|', sanitize_text_field( $encoded ), 2 );
			$items[] = array( 'key_hash' => $parts[0], 'group' => isset( $parts[1] ) ? $parts[1] : '' );
		}
		return $items;
	}

	private function redirect_with_notice( $message ) {
		$url = add_query_arg( array( 'page' => 'aips-cache-monitor', 'aips_cache_monitor_notice' => rawurlencode( $message ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
