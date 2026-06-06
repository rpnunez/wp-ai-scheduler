<?php
/**
 * @covers AIPS_Settings_UI
 */
class Test_AIPS_Settings_UI_Cache_Driver extends WP_UnitTestCase {

/** @var AIPS_Settings_UI */
private $settings_ui;

public function setUp(): void {
parent::setUp();
$this->settings_ui = new AIPS_Settings_UI();
}

public function test_sanitize_cache_driver_keeps_supported_drivers() {
$this->assertSame( 'array', $this->settings_ui->sanitize_cache_driver( 'array' ) );
$this->assertSame( 'db', $this->settings_ui->sanitize_cache_driver( 'db' ) );
$this->assertSame( 'wp_object_cache', $this->settings_ui->sanitize_cache_driver( 'wp_object_cache' ) );
}

public function test_sanitize_cache_driver_maps_legacy_drivers_to_wp_object_cache() {
$this->assertSame( 'wp_object_cache', $this->settings_ui->sanitize_cache_driver( 'session' ) );
$this->assertSame( 'wp_object_cache', $this->settings_ui->sanitize_cache_driver( 'redis' ) );
}

public function test_sanitize_cache_driver_falls_back_to_array_for_invalid_value() {
$this->assertSame( 'array', $this->settings_ui->sanitize_cache_driver( 'invalid_driver' ) );
}
}
