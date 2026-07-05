<?php
/**
 * @group cache-monitor
 */
class Test_AIPS_Cache_Monitor_Toggle extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
		remove_all_actions( 'wp_ajax_aips_cache_monitor_summary' );
	}

	public function test_default_is_disabled() {
		delete_option( 'aips_cache_monitor_enabled' );
		$defaults = AIPS_Config::get_instance()->get_default_options();
		$this->assertFalse( $defaults['aips_cache_monitor_enabled'],
			'Cache Monitor is a developer tool and must default off.' );
	}

	public function test_diagnostics_tab_hidden_when_disabled() {
		update_option( 'aips_cache_monitor_enabled', '0' );
		$this->assertFalse( AIPS_Diagnostics_Controller::is_tab_available( 'cache-monitor' ) );
		$this->assertArrayNotHasKey( 'cache-monitor', ( new AIPS_Diagnostics_Controller() )->get_tabs() );
	}

	public function test_diagnostics_tab_visible_when_enabled() {
		update_option( 'aips_cache_monitor_enabled', '1' );
		$this->assertTrue( AIPS_Diagnostics_Controller::is_tab_available( 'cache-monitor' ) );
		$this->assertArrayHasKey( 'cache-monitor', ( new AIPS_Diagnostics_Controller() )->get_tabs() );
	}

	public function test_controller_registers_no_ajax_hooks_when_disabled() {
		update_option( 'aips_cache_monitor_enabled', '0' );
		new AIPS_Cache_Monitor_Controller();
		$this->assertFalse( has_action( 'wp_ajax_aips_cache_monitor_summary' ),
			'Disabled monitor must not register AJAX hooks.' );
	}

	public function test_controller_registers_ajax_hooks_when_enabled() {
		update_option( 'aips_cache_monitor_enabled', '1' );
		new AIPS_Cache_Monitor_Controller();
		$this->assertNotFalse( has_action( 'wp_ajax_aips_cache_monitor_summary' ) );
	}

	public function test_cache_index_writes_nothing_when_monitor_disabled() {
		global $wpdb;
		update_option( 'aips_cache_monitor_enabled', '0' );
		update_option( 'aips_cache_monitor_index_enabled', '1' );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );
		$cache->set( 'monitor_off_probe', 'v', 60, 'default' );
		if ( method_exists( 'AIPS_Cache_Index', 'flush_pending' ) ) {
			AIPS_Cache_Index::flush_pending();
		}

		$this->assertSame( 0,
			(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index" ),
			'Master switch off must suppress all index writes.' );
	}
}
