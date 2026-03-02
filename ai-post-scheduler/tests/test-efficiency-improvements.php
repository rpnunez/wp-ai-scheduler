<?php
/**
 * Tests for the three efficiency improvements:
 *
 * 1. AIPS_Config::get_option() in-memory caching
 * 2. AIPS_History_Repository::get_all_template_stats() transient caching
 * 3. AIPS_Logger singleton pattern
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Efficiency_Improvements extends WP_UnitTestCase {

/**
 * Reset the AIPS_Config singleton before each test so that the
 * in-memory cache is always empty at test start.
 */
public function setUp(): void {
parent::setUp();
$this->reset_config_singleton();
}

private function reset_config_singleton() {
$ref       = new ReflectionClass('AIPS_Config');
$inst_prop = $ref->getProperty('instance');
$inst_prop->setAccessible(true);
$inst_prop->setValue(null, null);
}

// =========================================================================
// Improvement 1: AIPS_Config in-memory option cache
// =========================================================================

public function test_config_get_option_returns_existing_value() {
update_option('aips_max_tokens', 1234);
$config = AIPS_Config::get_instance();
$this->assertEquals(1234, $config->get_option('aips_max_tokens'));
}

public function test_config_get_option_caches_in_memory() {
update_option('aips_test_cache_key', 'original');
$config = AIPS_Config::get_instance();

// First call – primes the cache.
$first = $config->get_option('aips_test_cache_key');
$this->assertEquals('original', $first);

// Directly change the DB row without going through set_option(),
// simulating an external change.
update_option('aips_test_cache_key', 'changed');

// Second call – should still return the cached value.
$cached = $config->get_option('aips_test_cache_key');
$this->assertEquals('original', $cached, 'In-memory cache should serve the first-seen value within the same request.');
}

public function test_config_get_option_non_existent_always_applies_default() {
$config = AIPS_Config::get_instance();
delete_option('aips_nonexistent_xyz');

// First call with no default.
$this->assertNull($config->get_option('aips_nonexistent_xyz'));

// Second call with an explicit default — must NOT be masked by a cached null.
$this->assertEquals('fallback', $config->get_option('aips_nonexistent_xyz', 'fallback'));
}

public function test_config_set_option_updates_cache_unconditionally() {
update_option('aips_test_set_key', 'before');
$config = AIPS_Config::get_instance();

// Prime the cache.
$config->get_option('aips_test_set_key');

// Update via set_option() – cache must be refreshed.
$config->set_option('aips_test_set_key', 'after');
$this->assertEquals('after', $config->get_option('aips_test_set_key'), 'set_option() must update the in-memory cache.');
}

public function test_config_set_option_updates_cache_even_when_value_unchanged() {
update_option('aips_test_noop_key', 'same');
$config = AIPS_Config::get_instance();

// set_option() with same value — update_option() returns false (no-op).
// The cache must still be populated so subsequent reads don't hit the DB.
$config->set_option('aips_test_noop_key', 'same');
$this->assertEquals('same', $config->get_option('aips_test_noop_key'));
}

// =========================================================================
// Improvement 2: AIPS_History_Repository::get_all_template_stats() caching
// =========================================================================

public function test_get_all_template_stats_is_cached() {
$repo = new AIPS_History_Repository();

// Ensure the transient is cleared.
delete_transient('aips_all_template_stats');

// First call – populates the transient.
$repo->get_all_template_stats();

// The transient should now exist.
$this->assertNotFalse(
get_transient('aips_all_template_stats'),
'get_all_template_stats() should populate the aips_all_template_stats transient.'
);
}

public function test_get_all_template_stats_cache_invalidated_on_create() {
$repo = new AIPS_History_Repository();

// Prime transient.
set_transient('aips_all_template_stats', array('fake' => 99), HOUR_IN_SECONDS);

// Create a new history entry – should clear the cache.
$repo->create(array(
'template_id' => 1,
'status'      => 'completed',
));

$this->assertFalse(
get_transient('aips_all_template_stats'),
'create() should invalidate the aips_all_template_stats transient.'
);
}

public function test_get_all_template_stats_cache_invalidated_on_delete() {
$repo = new AIPS_History_Repository();

set_transient('aips_all_template_stats', array('fake' => 1), HOUR_IN_SECONDS);

$repo->delete_by_status('completed');

$this->assertFalse(
get_transient('aips_all_template_stats'),
'delete_by_status() should invalidate the aips_all_template_stats transient.'
);
}

public function test_get_all_template_stats_cache_invalidated_on_update() {
global $wpdb;
$repo = new AIPS_History_Repository();

// Insert a real history row to update.
$inserted = $wpdb->insert(
$wpdb->prefix . 'aips_history',
array(
'template_id'     => 1,
'status'          => 'processing',
'generated_title' => '',
),
array('%d', '%s', '%s')
);
$id = $inserted ? $wpdb->insert_id : null;

if (!$id) {
$this->markTestSkipped('Could not insert a history row for update test.');
}

set_transient('aips_all_template_stats', array('fake' => 7), HOUR_IN_SECONDS);

$repo->update($id, array('status' => 'completed'));

$this->assertFalse(
get_transient('aips_all_template_stats'),
'update() should invalidate the aips_all_template_stats transient.'
);
}

// =========================================================================
// Improvement 3: AIPS_Logger singleton
// =========================================================================

public function test_logger_get_instance_returns_same_object() {
$a = AIPS_Logger::get_instance();
$b = AIPS_Logger::get_instance();
$this->assertSame($a, $b, 'AIPS_Logger::get_instance() must always return the same instance.');
}

public function test_logger_can_still_be_instantiated_directly() {
// Direct instantiation should still work (backward-compat for tests and
// callers that inject a custom logger).
$logger = new AIPS_Logger();
$this->assertInstanceOf('AIPS_Logger', $logger);
}

public function test_logger_singleton_and_direct_instance_are_different_objects() {
$singleton = AIPS_Logger::get_instance();
$direct    = new AIPS_Logger();
$this->assertNotSame($singleton, $direct);
$this->assertInstanceOf('AIPS_Logger', $direct);
}
}
